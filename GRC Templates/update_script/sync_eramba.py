#!/usr/bin/env python3
"""
eramba GRC Templates Sync Script
=================================
Syncs policies and controls from the GitHub repo into an eramba instance.
Also updates compliance analysis links (requirement → policy/control mappings).

Usage:
    python sync_eramba.py [--dry-run] [--only policies|controls|compliance]

Environment variables (required):
    ERAMBA_URL      Base URL of the eramba instance, e.g. https://templates-prod.cloud.eramba.org
    ERAMBA_USER     eramba username with REST API access
    ERAMBA_PASSWORD eramba password

Environment variables (optional):
    GITHUB_TOKEN    GitHub personal access token (needed for private repos or to avoid rate limits)
    DRY_RUN         Set to "1" to print what would happen without making changes

GitHub repo:
    eramba/templates  branch: master
    Policies in:  GRC Templates/LLM - GRC Templates/Policies/
    Controls in:  GRC Templates/LLM - GRC Templates/Controls/internal_controls.csv
    Mapping in:   GRC Templates/LLM - GRC Templates/Controls/mapping_controls_to_requirements.csv
"""

import os
import sys
import json
import markdown as md_converter
import csv
import time
import base64
import argparse
import urllib.request
import urllib.parse
import urllib.error
import ssl
from datetime import date
from io import StringIO

# SSL context — disable verification if the server uses a self-signed or
# internally-issued certificate that Python cannot verify locally.
# For production use with a valid public certificate, remove this and use
# the default ssl context instead.
SSL_CONTEXT = ssl.create_default_context()
SSL_CONTEXT.check_hostname = False
SSL_CONTEXT.verify_mode = ssl.CERT_NONE

# ---------------------------------------------------------------------------
# Configuration
# ---------------------------------------------------------------------------

ERAMBA_URL      = os.environ.get('ERAMBA_URL', '').rstrip('/')
ERAMBA_USER     = os.environ.get('ERAMBA_USER', '')
ERAMBA_PASSWORD = os.environ.get('ERAMBA_PASSWORD', '')
GITHUB_TOKEN    = os.environ.get('GITHUB_TOKEN', '')
DRY_RUN         = os.environ.get('DRY_RUN', '0') == '1'

GITHUB_REPO     = 'eramba/templates'
GITHUB_BRANCH   = 'master'
GITHUB_BASE     = 'GRC Templates/LLM - GRC Templates'

# How we set new policies in eramba
# How we set new policies in eramba
POLICY_DEFAULTS = {
    'security_policy_document_type_id': 1,   # 1 = Procedure
    'use_attachments': 0,                     # 0 = Use Content (HTML in description)
    'status': 1,                              # 1 = Published
    'permission': 'public',
    'version': os.environ.get('PR_NUMBER', '1.0'),
    'asset_label_id': None,
    'url': '',
    'tags': [],
    'projects': [1],
    'owners': ['User-1'],
    'collaborators': ['User-1'],
    'related_documents': [1],
}

# How we set new controls in eramba
CONTROL_DEFAULTS = {
    'security_service_type_id': 4,            # 4 = Production
}

# Delay between API calls to avoid hammering the server
API_DELAY = 0.5


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def log(msg, level='INFO'):
    prefix = {'INFO': '  ', 'OK': '✓ ', 'SKIP': '– ', 'WARN': '! ', 'ERR': '✗ ', 'DRY': '~ '}
    print(f"{prefix.get(level, '  ')}{msg}")


def eramba_auth_header():
    creds = base64.b64encode(f"{ERAMBA_USER}:{ERAMBA_PASSWORD}".encode()).decode()
    return {'Authorization': f'Basic {creds}'}


def eramba_request(method, path, data=None):
    """Make an authenticated request to eramba. Returns (response_dict, error_string)."""
    url = f"{ERAMBA_URL}{path}"
    headers = {
        **eramba_auth_header(),
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    }
    body = json.dumps(data).encode() if data is not None else None
    req = urllib.request.Request(url, data=body, headers=headers, method=method)
    for attempt in range(3):
        try:
            with urllib.request.urlopen(req, timeout=30, context=SSL_CONTEXT) as r:
                raw = r.read().decode()
                return json.loads(raw) if raw.strip() else {}, None
        except urllib.error.HTTPError as e:
            body = e.read().decode()
            if e.code == 502 and attempt < 2:
                time.sleep(2 ** attempt)  # backoff: 1s, 2s
                continue
            return None, f"HTTP {e.code}: {body[:300]}"
        except Exception as e:
            if attempt < 2:
                time.sleep(1)
                continue
            return None, str(e)
    return None, "Max retries exceeded"


def eramba_get_all(path, max_pages=50):
    """Fetch all pages from a list endpoint. Returns list of records."""
    records = []
    page = 1
    limit = 50
    while page <= max_pages:
        result, err = eramba_request('GET', f"{path}?page={page}&limit={limit}")
        if err:
            if records:
                log(f"GET {path} page {page}: {err} — stopping, using {len(records)} records so far", 'WARN')
            else:
                log(f"GET {path} page {page}: {err}", 'ERR')
            break
        if not result:
            break
        # Unwrap {success: true, data: [...]} envelope if present
        if isinstance(result, dict) and 'data' in result:
            result = result['data']
        items = result if isinstance(result, list) else []
        if not isinstance(items, list):
            records.append(items)
            break
        if not items:
            break
        records.extend(items)
        if len(items) < limit:
            break
        page += 1
        time.sleep(API_DELAY)
    return records


def github_request(path):
    """Fetch a file or directory listing from GitHub API."""
    encoded = urllib.parse.quote(path, safe='/')
    url = f"https://api.github.com/repos/{GITHUB_REPO}/contents/{encoded}?ref={GITHUB_BRANCH}"
    headers = {'User-Agent': 'eramba-sync', 'Accept': 'application/vnd.github.v3+json'}
    if GITHUB_TOKEN:
        headers['Authorization'] = f'token {GITHUB_TOKEN}'
    req = urllib.request.Request(url, headers=headers)
    try:
        with urllib.request.urlopen(req, timeout=30, context=SSL_CONTEXT) as r:
            return json.loads(r.read()), None
    except urllib.error.HTTPError as e:
        return None, f"HTTP {e.code}: {e.read().decode()[:200]}"
    except Exception as e:
        return None, str(e)


def github_get_file_content(path):
    """Download raw file content from GitHub. Returns string."""
    result, err = github_request(path)
    if err:
        return None, err
    if isinstance(result, dict) and result.get('encoding') == 'base64':
        content = base64.b64decode(result['content'].replace('\n', '')).decode('utf-8')
        return content, None
    return None, "Unexpected response format"


def github_list_dir(path):
    """List files in a GitHub directory. Returns list of {name, path, type}."""
    result, err = github_request(path)
    if err:
        return [], err
    if isinstance(result, list):
        return result, None
    return [], "Not a directory"


# ---------------------------------------------------------------------------
# Data loading
# ---------------------------------------------------------------------------

def load_policies_from_github():
    """
    Load all policy .md files from GitHub.
    Returns list of dicts: {name, short_description, description, filename}
    """
    policies_path = f"{GITHUB_BASE}/Policies"
    files, err = github_list_dir(policies_path)
    if err:
        log(f"Cannot list Policies dir: {err}", 'ERR')
        return []

    policies = []
    for f in files:
        if not f['name'].endswith('.md') or f['name'].startswith('00_'):
            continue
        content, err = github_get_file_content(f['path'])
        if err:
            log(f"Cannot read {f['name']}: {err}", 'WARN')
            continue

        # Extract title from first # heading
        name = f['name'].replace('.md', '').replace('_', ' ')
        for line in content.split('\n'):
            if line.startswith('# '):
                name = line[2:].strip()
                break

        # Extract short description from ## Purpose section
        short_desc = ''
        in_purpose = False
        for line in content.split('\n'):
            if line.startswith('## Purpose'):
                in_purpose = True
                continue
            if in_purpose and line.startswith('## '):
                break
            if in_purpose and line.strip():
                short_desc = line.strip()[:255]
                break

        html = md_converter.markdown(content, extensions=['tables', 'fenced_code', 'nl2br'])

        policies.append({
            'name': name,
            'short_description': short_desc,
            'description': content,
            'html': html,
            'filename': f['name'],
        })
        time.sleep(0.1)

    log(f"Loaded {len(policies)} policies from GitHub")
    return policies


def load_controls_from_github():
    """
    Load controls from internal_controls.csv on GitHub.
    Returns list of dicts matching CSV columns.
    """
    csv_path = f"{GITHUB_BASE}/Controls/internal_controls.csv"
    content, err = github_get_file_content(csv_path)
    if err:
        log(f"Cannot read internal_controls.csv: {err}", 'ERR')
        return []

    reader = csv.DictReader(StringIO(content))
    controls = list(reader)
    log(f"Loaded {len(controls)} controls from GitHub")
    return controls


def load_mappings_from_github():
    """
    Load mapping_controls_to_requirements.csv from GitHub.
    Returns dict: {control_title: {framework_label: [req_ids]}}
    """
    csv_path = f"{GITHUB_BASE}/Controls/mapping_controls_to_requirements.csv"
    content, err = github_get_file_content(csv_path)
    if err:
        log(f"Cannot read mapping_controls_to_requirements.csv: {err}", 'ERR')
        return {}

    reader = csv.DictReader(StringIO(content))
    mappings = {}
    for row in reader:
        title = row['Control Title']
        mappings[title] = {}
        for fw, ids_str in row.items():
            if fw == 'Control Title' or ids_str == '—' or not ids_str.strip():
                continue
            mappings[title][fw] = [x.strip() for x in ids_str.split(',') if x.strip()]
    return mappings


# ---------------------------------------------------------------------------
# eramba state loading
# ---------------------------------------------------------------------------

def load_eramba_policies():
    """Returns dict: {name_lower: record}"""
    records = eramba_get_all('/api/security-policies/index')
    result = {}
    for r in records:
        key = r.get('index', r.get('name', '')).lower().strip()
        result[key] = r
    log(f"Found {len(result)} existing policies in eramba")
    return result


def load_eramba_controls():
    """Returns dict: {name_lower: record}"""
    records = eramba_get_all('/api/security-services/index')
    result = {}
    for r in records:
        key = r.get('name', '').lower().strip()
        result[key] = r
    log(f"Found {len(result)} existing controls in eramba")
    return result


def load_eramba_compliance_analysis():
    """
    Returns list of all compliance analysis records.
    Each record has an id and links to a compliance package requirement.
    """
    records = eramba_get_all('/api/compliance-managements/index')
    log(f"Found {len(records)} compliance analysis records in eramba")
    return records


def load_eramba_compliance_packages():
    """Returns dict: {name_lower: record}"""
    records = eramba_get_all('/api/compliance-package-regulators/index')
    result = {}
    for r in records:
        key = r.get('name', '').lower().strip()
        result[key] = r
    log(f"Found {len(result)} compliance packages in eramba")
    return result


# ---------------------------------------------------------------------------
# Sync: Policies
# ---------------------------------------------------------------------------

def sync_policies(dry_run=False):
    log("\n=== POLICIES ===")
    gh_policies = load_policies_from_github()
    eramba_policies = load_eramba_policies()

    created = updated = skipped = errors = 0

    for pol in gh_policies:
        key = pol['name'].lower().strip()
        existing = eramba_policies.get(key)

        if existing:
            # UPDATE — must GET full record first, then PUT all fields
            rec_id = existing.get('id')
            if not dry_run:
                full, err = eramba_request('GET', f"/api/security-policies/{rec_id}")
                if err:
                    log(f"GET policy {pol['name']}: {err}", 'ERR')
                    errors += 1
                    continue
                # Merge: keep all existing fields, update only content fields
                full['description'] = pol['html']
                full['short_description'] = pol['short_description']
                _, err = eramba_request('PUT', f"/api/security-policies/{rec_id}", full)
                if err:
                    log(f"PUT policy {pol['name']}: {err}", 'ERR')
                    errors += 1
                    continue
                time.sleep(API_DELAY)
            log(f"{'[DRY] ' if dry_run else ''}Updated policy: {pol['name']}", 'OK' if not dry_run else 'DRY')
            updated += 1

        else:
            # CREATE
            payload = {
                **POLICY_DEFAULTS,
                'index': pol['name'],
                'short_description': pol['short_description'],
                'description': pol['html'],
                'published_date': str(date.today() - __import__('datetime').timedelta(days=1)),
                'next_review_date': str(date.today().replace(year=date.today().year + 10)),
            }
            if not dry_run:
                res, err = eramba_request('POST', '/api/security-policies/add', payload)
                if err:
                    log(f"POST policy {pol['name']}: {err}", 'ERR')
                    errors += 1
                    continue
                # Check eramba success envelope
                if isinstance(res, dict) and not res.get('success', True):
                    log(f"POST policy {pol['name']} failed: {res}", 'ERR')
                    errors += 1
                    continue
                log(f"POST policy {pol['name']} response: {json.dumps(res)[:200]}")
                time.sleep(API_DELAY)
            log(f"{'[DRY] ' if dry_run else ''}Created policy: {pol['name']}", 'OK' if not dry_run else 'DRY')
            created += 1

    log(f"\nPolicies: {created} created, {updated} updated, {skipped} skipped, {errors} errors")
    return errors == 0


# ---------------------------------------------------------------------------
# Sync: Controls
# ---------------------------------------------------------------------------

def sync_controls(dry_run=False):
    log("\n=== CONTROLS ===")
    gh_controls = load_controls_from_github()
    eramba_controls = load_eramba_controls()
    eramba_policies = load_eramba_policies()

    created = updated = errors = 0

    # Load policy mapping so we can link controls to their policy
    pol_csv_path = f"{GITHUB_BASE}/Controls/mapping_controls_to_policies.csv"
    pol_csv, err = github_get_file_content(pol_csv_path)
    ctrl_to_policy = {}
    if not err:
        for row in csv.DictReader(StringIO(pol_csv)):
            ctrl_to_policy[row['Control Title'].strip()] = row.get('Policy', '').strip()
    else:
        log(f"Could not load policy mapping: {err}", 'WARN')

    for ctrl in gh_controls:
        title = ctrl.get('Title', '').strip()
        if not title:
            continue
        key = title.lower()
        existing = eramba_controls.get(key)

        # Resolve linked policy eramba ID
        pol_name = ctrl_to_policy.get(title, '').lower()
        pol_rec  = eramba_policies.get(pol_name)
        pol_ids  = [{'id': pol_rec['id']}] if pol_rec else []

        # Map CSV columns to eramba fields
        payload_fields = {
            'name': title,
            'objective': ctrl.get('Description', ''),
            'audit_metric_description': ctrl.get('Audit Methodology', ''),
            'security_policies': pol_ids,
        }

        if existing:
            rec_id = existing.get('id')
            if not dry_run:
                full, err = eramba_request('GET', f"/api/security-services/{rec_id}")
                if err:
                    log(f"GET control {title}: {err}", 'ERR')
                    errors += 1
                    continue
                full.update(payload_fields)
                _, err = eramba_request('PUT', f"/api/security-services/{rec_id}", full)
                if err:
                    log(f"PUT control {title}: {err}", 'ERR')
                    errors += 1
                    continue
                time.sleep(API_DELAY)
            log(f"{'[DRY] ' if dry_run else ''}Updated control: {title}", 'OK' if not dry_run else 'DRY')
            updated += 1

        else:
            payload = {
                **CONTROL_DEFAULTS,
                **payload_fields,
            }
            if not dry_run:
                res, err = eramba_request('POST', '/api/security-services/add', payload)
                if err:
                    log(f"POST control {title}: {err}", 'ERR')
                    errors += 1
                    continue
                if isinstance(res, dict) and not res.get('success', True):
                    log(f"POST control {title} failed: {res}", 'ERR')
                    errors += 1
                    continue
                log(f"POST control {title} response: {json.dumps(res)[:200]}")
                time.sleep(API_DELAY)
            log(f"{'[DRY] ' if dry_run else ''}Created control: {title}", 'OK' if not dry_run else 'DRY')
            created += 1

    log(f"\nControls: {created} created, {updated} updated, {errors} errors")
    return errors == 0


# ---------------------------------------------------------------------------
# Sync: Compliance Analysis links
# ---------------------------------------------------------------------------

def sync_compliance(dry_run=False):
    """
    For each compliance analysis record in eramba, check if the repo
    mapping CSV has controls/policies for that requirement.
    If yes, update the record to add those links.

    Strategy:
    1. Load all compliance package regulators → find our 16 frameworks by name
    2. For each matched framework, fetch its package items → build {item_id_str: item_pk}
    3. Load compliance analysis records → build {item_pk: ca_record}
    4. For each control→requirement mapping, find the ca record and update it
    """
    log("\n=== COMPLIANCE ANALYSIS LINKS ===")

    # Step 1: load all compliance package regulators
    log("Loading compliance package regulators...")
    regulators = eramba_get_all('/api/compliance-package-regulators/index')
    reg_by_name = {r.get('name', '').lower().strip(): r for r in regulators}
    log(f"Found {len(regulators)} regulators in eramba")

    # The CSV column headers exactly match the eramba compliance package regulator names.
    # This is the complete mapping — no fuzzy matching needed.
    # If you add a new framework, add it here too.
    FW_EXACT_NAMES = [
        'PCI-DSS',
        'ISO 27001',
        'ISO 27002',
        'SOC2',
        'NIST SP 800-53 Rev. 5',
        'CIS Controls',
        'Secure Control Framework',
        'NIS2 - Article 21',
        'ISO 27701',
        'ISO 45001',
        'ISO 42001',
        'ISO 37001',
        'ISO 9001',
        'OWASP Top 10 for LLM Applications',
        'NIST AI 600-1 Generative AI Profile',
        'EU AI Act',
    ]

    # Find regulator IDs using exact name match
    fw_regulator_ids = {}
    for fw_name in FW_EXACT_NAMES:
        reg = reg_by_name.get(fw_name.lower().strip())
        if reg:
            fw_regulator_ids[fw_name] = reg['id']
            log(f"  Matched '{fw_name}' (id={reg['id']})")
        else:
            log(f"  Not found in eramba: '{fw_name}'", 'WARN')

    if not fw_regulator_ids:
        log("No framework regulators matched — check regulator names in eramba", 'ERR')
        return False

    # Step 2: fetch compliance package items per regulator directly
    # Each regulator has a nested list of packages, each package has items.
    # We fetch the regulator detail to get its packages, then fetch items per package.
    log("\nBuilding requirement item lookup per framework...")
    item_str_to_pk = {}   # (fw_label, item_id_str_lower) → item_pk
    item_pk_to_fw  = {}   # item_pk → (fw_label, item_id_str)

    for fw_label, reg_id in fw_regulator_ids.items():
        # Fetch the regulator detail to get its package IDs
        reg_detail, err = eramba_request('GET', f"/api/compliance-package-regulators/{reg_id}")
        if err:
            log(f"  Cannot fetch regulator {reg_id} ({fw_label}): {err}", 'WARN')
            continue

        # Unwrap {success, data} if needed
        if isinstance(reg_detail, dict) and 'data' in reg_detail:
            reg_detail = reg_detail['data']

        # Get package IDs from the regulator — they may be nested
        pkg_ids = []
        packages = reg_detail.get('compliance_packages', []) or []
        for pkg in packages:
            pid = pkg.get('id') if isinstance(pkg, dict) else pkg
            if pid:
                pkg_ids.append(pid)

        if not pkg_ids:
            log(f"  No packages in regulator {reg_id} ({fw_label}) — trying item fetch directly", 'WARN')
            # Fallback: fetch items filtered by regulator via query param
            items = eramba_get_all(f'/api/compliance-package-items/index?compliance_package_regulator_id={reg_id}')
            for item in items:
                item_pk  = item.get('id')
                item_str = item.get('item_id', item.get('index', '')).strip()
                if item_pk and item_str:
                    item_str_to_pk[(fw_label, item_str.lower())] = item_pk
            log(f"  {fw_label}: {len(items)} items loaded (direct fetch)")
            continue

        fw_item_count = 0
        for pkg_id in pkg_ids:
            items = eramba_get_all(f'/api/compliance-package-items/index?compliance_package_id={pkg_id}')
            for item in items:
                item_pk  = item.get('id')
                item_str = item.get('item_id', item.get('index', '')).strip()
                if item_pk and item_str:
                    item_str_to_pk[(fw_label, item_str.lower())] = item_pk
                    item_pk_to_fw[item_pk] = (fw_label, item_str)
                    fw_item_count += 1
            time.sleep(API_DELAY)

        log(f"  {fw_label}: {fw_item_count} items loaded")

    log(f"\nTotal items in lookup: {len(item_str_to_pk)}")

    # Step 4: load compliance analysis records → {item_pk: ca_record}
    log("\nLoading compliance analysis records...")
    ca_records = eramba_get_all('/api/compliance-managements/index')
    log(f"Found {len(ca_records)} compliance analysis records")

    ca_by_item_pk = {}
    for rec in ca_records:
        item_pk = rec.get('compliance_package_item_id')
        if item_pk:
            ca_by_item_pk[item_pk] = rec

    # Step 5: load controls and policies from eramba
    eramba_ctrls = load_eramba_controls()
    eramba_pols  = load_eramba_policies()

    # Step 6: load mappings from GitHub
    gh_mappings = load_mappings_from_github()

    # Load policy mappings
    pol_csv_path = f"{GITHUB_BASE}/Controls/mapping_controls_to_policies.csv"
    pol_csv, err = github_get_file_content(pol_csv_path)
    ctrl_to_policy = {}
    if not err:
        for row in csv.DictReader(StringIO(pol_csv)):
            ctrl_to_policy[row['Control Title'].strip()] = row.get('Policy', '').strip()

    # Step 7: iterate mappings and update compliance analysis records
    updated = skipped = not_found = errors = 0

    for ctrl_title, fw_map in gh_mappings.items():
        ctrl_rec = eramba_ctrls.get(ctrl_title.lower())
        if not ctrl_rec:
            continue  # control not yet in eramba

        ctrl_id = ctrl_rec['id']
        pol_name = ctrl_to_policy.get(ctrl_title, '').lower()
        pol_rec  = eramba_pols.get(pol_name)
        pol_id   = pol_rec['id'] if pol_rec else None

        for fw_label, req_ids in fw_map.items():
            if fw_label not in fw_regulator_ids:
                continue  # framework not in eramba

            for req_id in req_ids:
                key = (fw_label, req_id.lower())
                item_pk = item_str_to_pk.get(key)
                if not item_pk:
                    not_found += 1
                    continue

                ca_rec = ca_by_item_pk.get(item_pk)
                if not ca_rec:
                    not_found += 1
                    continue

                ca_id = ca_rec['id']

                if dry_run:
                    log(f"[DRY] Would link '{ctrl_title}' + policy '{pol_name}' → {fw_label} {req_id}", 'DRY')
                    updated += 1
                    continue

                # GET full record (PUT requires all fields)
                full, err = eramba_request('GET', f"/api/compliance-managements/{ca_id}")
                if err:
                    log(f"GET ca {ca_id}: {err}", 'ERR')
                    errors += 1
                    continue

                # Unwrap {success, data} wrapper if present
                if isinstance(full, dict) and 'data' in full:
                    full = full['data']

                # Merge control and policy IDs
                existing_svcs = full.get('security_services') or []
                existing_pols = full.get('security_policies') or []
                svc_ids = [s['id'] if isinstance(s, dict) else s for s in existing_svcs]
                pol_ids = [p['id'] if isinstance(p, dict) else p for p in existing_pols]

                changed = False
                if ctrl_id not in svc_ids:
                    svc_ids.append(ctrl_id)
                    changed = True
                if pol_id and pol_id not in pol_ids:
                    pol_ids.append(pol_id)
                    changed = True

                if not changed:
                    skipped += 1
                    continue

                full['security_services'] = [{'id': i} for i in svc_ids]
                full['security_policies']  = [{'id': i} for i in pol_ids]

                _, err = eramba_request('PUT', f"/api/compliance-managements/{ca_id}", full)
                if err:
                    log(f"PUT ca {ca_id} ({fw_label} {req_id}): {err}", 'ERR')
                    errors += 1
                    continue
                time.sleep(API_DELAY)
                updated += 1

    log(f"\nCompliance links: {updated} updated, {skipped} already linked, {not_found} req IDs not found in eramba, {errors} errors")
    return errors == 0


def validate_env():
    missing = []
    if not ERAMBA_URL:   missing.append('ERAMBA_URL')
    if not ERAMBA_USER:  missing.append('ERAMBA_USER')
    if not ERAMBA_PASSWORD: missing.append('ERAMBA_PASSWORD')
    if missing:
        print(f"ERROR: Missing required environment variables: {', '.join(missing)}")
        print("\nSet them before running:")
        for var in missing:
            print(f"  export {var}=<value>")
        sys.exit(1)


def test_connection():
    """Quick connectivity check before running."""
    log("Testing eramba connection...")
    result, err = eramba_request('GET', '/api/security-policies/index?page=1&limit=1')
    if err:
        log(f"Cannot connect to eramba: {err}", 'ERR')
        log(f"URL: {ERAMBA_URL}", 'ERR')
        sys.exit(1)
    log("Connection OK", 'OK')


# ---------------------------------------------------------------------------
# Entry point
# ---------------------------------------------------------------------------

def main():
    parser = argparse.ArgumentParser(description='Sync eramba GRC templates from GitHub')
    parser.add_argument('--dry-run', action='store_true', help='Print what would happen without making changes')
    parser.add_argument('--only', choices=['policies', 'controls', 'compliance'],
                        help='Run only one sync step')
    args = parser.parse_args()

    dry_run = args.dry_run or DRY_RUN

    validate_env()

    print(f"\neramba GRC Template Sync")
    print(f"========================")
    print(f"Instance : {ERAMBA_URL}")
    print(f"User     : {ERAMBA_USER}")
    print(f"Repo     : {GITHUB_REPO} @ {GITHUB_BRANCH}")
    print(f"Dry run  : {dry_run}")
    print()

    if dry_run:
        log("DRY RUN MODE — no changes will be made", 'WARN')

    test_connection()

    success = True

    if args.only in (None, 'policies'):
        success &= sync_policies(dry_run=dry_run)

    if args.only in (None, 'controls'):
        success &= sync_controls(dry_run=dry_run)

    if args.only in (None, 'compliance'):
        success &= sync_compliance(dry_run=dry_run)

    print()
    if success:
        log("Sync completed successfully", 'OK')
        sys.exit(0)
    else:
        log("Sync completed with errors", 'ERR')
        sys.exit(1)


if __name__ == '__main__':
    main()
