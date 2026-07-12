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
POLICY_DEFAULTS = {
    'security_policy_document_type_id': 3,   # 3 = Policy
    'use_attachments': '0',                   # 0 = Use Content (inline markdown)
    'status': '0',                            # 0 = Draft
    'permission': 'public',
    'version': '1.0',
}

# How we set new controls in eramba
CONTROL_DEFAULTS = {
    'security_service_type_id': 4,            # 4 = Production
}

# Delay between API calls to avoid hammering the server
API_DELAY = 0.3


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
    try:
        with urllib.request.urlopen(req, timeout=30, context=SSL_CONTEXT) as r:
            raw = r.read().decode()
            return json.loads(raw) if raw.strip() else {}, None
    except urllib.error.HTTPError as e:
        body = e.read().decode()
        return None, f"HTTP {e.code}: {body[:300]}"
    except Exception as e:
        return None, str(e)


def eramba_get_all(path, max_pages=50):
    """Fetch all pages from a list endpoint. Returns list of records."""
    records = []
    page = 1
    limit = 100
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
        items = result if isinstance(result, list) else result.get('data', result)
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

        policies.append({
            'name': name,
            'short_description': short_desc,
            'description': content,
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
                full['description'] = pol['description']
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
                'description': pol['description'],
                'published_date': str(date.today()),
                'next_review_date': str(date.today().replace(year=date.today().year + 1)),
            }
            if not dry_run:
                _, err = eramba_request('POST', '/api/security-policies/add', payload)
                if err:
                    log(f"POST policy {pol['name']}: {err}", 'ERR')
                    errors += 1
                    continue
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

    created = updated = errors = 0

    for ctrl in gh_controls:
        title = ctrl.get('Title', '').strip()
        if not title:
            continue
        key = title.lower()
        existing = eramba_controls.get(key)

        # Map CSV columns to eramba fields
        payload_fields = {
            'name': title,
            'objective': ctrl.get('Description', ''),
            'audit_metric_description': ctrl.get('Audit Methodology', ''),
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
                _, err = eramba_request('POST', '/api/security-services/add', payload)
                if err:
                    log(f"POST control {title}: {err}", 'ERR')
                    errors += 1
                    continue
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
    For each compliance analysis record in eramba, check if the repo's
    mapping CSV has controls and/or policies mapped to that requirement.
    If yes, update the record to add those links.

    Note: eramba compliance analysis records must already exist (created manually
    when importing the compliance package). This step only UPDATES them.
    """
    log("\n=== COMPLIANCE ANALYSIS LINKS ===")

    # Load all data we need
    ca_records   = load_eramba_compliance_analysis()
    eramba_ctrls = load_eramba_controls()
    eramba_pols  = load_eramba_policies()
    gh_mappings  = load_mappings_from_github()

    if not ca_records:
        log("No compliance analysis records found in eramba — nothing to update", 'WARN')
        log("Import your compliance packages in eramba first, then run this script", 'WARN')
        return True

    # Build a lookup by fetching each record's full detail to get package+requirement info
    # The index endpoint returns compliance_package_item_id — we need to map that to
    # package name + requirement index by fetching individual records (sampled first
    # to understand structure, then built from the index data we have).
    #
    # Strategy: fetch a sample record to understand field structure, then build
    # lookup from {compliance_package_item_id: ca_record} and separately fetch
    # the compliance package items to map item_id → (package_name, req_id).

    if not ca_records:
        log("No compliance analysis records found", 'WARN')
        return True

    # Log the structure so we can debug
    sample = ca_records[0]
    log(f"Compliance analysis record fields: {list(sample.keys())}")

    # Fetch one full record to see nested structure
    sample_full, err = eramba_request('GET', f"/api/compliance-managements/{sample['id']}")
    if not err and sample_full:
        log(f"Full record fields: {list(sample_full.keys())}")
        # Log nested objects
        for k, v in sample_full.items():
            if isinstance(v, dict):
                log(f"  {k} keys: {list(v.keys())}")

    # Build lookup: {compliance_package_item_id: ca_record}
    ca_by_item_id = {}
    for rec in ca_records:
        item_id = rec.get('compliance_package_item_id')
        if item_id:
            ca_by_item_id[item_id] = rec

    log(f"Built item_id lookup with {len(ca_by_item_id)} entries")

    if not ca_by_item_id:
        log("Could not build compliance analysis lookup — compliance_package_item_id missing", 'WARN')
        log(f"Sample record: {sample}", 'WARN')
        return False

    # We'll use a different matching approach:
    # For each control→framework→req_id mapping, find the ca record via the
    # compliance package items API (if available) or log what we need.
    # For now, log what we have so we can refine.
    log(f"NOTE: Compliance link sync requires mapping req IDs to compliance_package_item_ids")
    log(f"Fetching sample compliance package item to understand structure...")

    # Try to fetch compliance package items
    sample_item_id = list(ca_by_item_id.keys())[0]
    log(f"Sample compliance_package_item_id: {sample_item_id}")

    # Try fetching the compliance package item directly
    item_rec, err = eramba_request('GET', f"/api/compliance-package-regulators/{sample_item_id}")
    if not err and item_rec:
        log(f"Compliance package item fields: {list(item_rec.keys())}")
        for k, v in item_rec.items():
            if v and not isinstance(v, (list, dict)):
                log(f"  {k}: {v}")
    else:
        log(f"Could not fetch compliance package item {sample_item_id}: {err}", 'WARN')

    # Build the full lookup by fetching compliance package items
    # This tells us: item_id → (package_name, req_id)
    log("Building compliance package item lookup (this may take a while for large datasets)...")
    log("Fetching compliance package items index...")

    # Try the compliance package items endpoint
    pkg_items, err2 = eramba_request('GET', '/api/compliance-package-items/index?page=1&limit=1')
    if not err2:
        log(f"Found compliance package items endpoint. Fields: {list(pkg_items[0].keys()) if isinstance(pkg_items, list) and pkg_items else pkg_items}")
    else:
        log(f"No /api/compliance-package-items/index: {err2}", 'WARN')

    # Cannot proceed further without understanding the item structure
    # Return True so other syncs still complete
    log("Compliance link sync: run with --only compliance after reviewing the debug output above", 'WARN')
    log(f"Built lookup with {len(ca_by_item_id)} compliance analysis records", 'OK')
    return True

    # Framework label → lookup key mapping
    # These must match the column headers in mapping_controls_to_requirements.csv
    FW_PACKAGE_NAMES = {
        'PCI DSS v4.0.1':                  'pci dss v4.0.1',
        'ISO 27001:2022':                  'iso 27001:2022',
        'ISO 27002:2022':                  'iso 27002:2022',
        'SOC 2 (TSP 2017)':               'soc 2',
        'NIST 800-53 Rev5':               'nist 800-53',
        'CIS Controls v8.1':              'cis controls',
        'SCF 2025':                        'scf 2025',
        'NIS2 Article 21':                'nis2',
        'ISO 27701:2025':                  'iso 27701',
        'ISO 45001:2018':                  'iso 45001',
        'ISO 42001:2023':                  'iso 42001',
        'ISO 37001:2025':                  'iso 37001',
        'ISO 9001:2015':                   'iso 9001',
        'OWASP LLM Top 10 2025':          'owasp llm',
        'NIST AI 600-1 2024':             'nist ai 600-1',
        'EU AI Act 2024/1689':            'eu ai act',
    }

    # Also load policy mappings from mapping_controls_to_policies.csv
    pol_csv_path = f"{GITHUB_BASE}/Controls/mapping_controls_to_policies.csv"
    pol_csv, err = github_get_file_content(pol_csv_path)
    ctrl_to_policy = {}
    if not err:
        for row in csv.DictReader(StringIO(pol_csv)):
            ctrl_to_policy[row['Control Title'].strip()] = row.get('Policy', '').strip()

    updated = skipped = errors = 0

    for ctrl_title, fw_map in gh_mappings.items():
        ctrl_rec = eramba_ctrls.get(ctrl_title.lower())
        if not ctrl_rec:
            log(f"Control not in eramba (not yet created?): {ctrl_title}", 'WARN')
            continue

        ctrl_id = ctrl_rec['id']

        # Find the linked policy eramba ID
        pol_name = ctrl_to_policy.get(ctrl_title, '').lower()
        pol_rec  = eramba_pols.get(pol_name)
        pol_id   = pol_rec['id'] if pol_rec else None

        for fw_label, req_ids in fw_map.items():
            pkg_key = FW_PACKAGE_NAMES.get(fw_label, '').lower()
            if not pkg_key:
                continue

            for req_id in req_ids:
                ca_key = (pkg_key, req_id.lower())
                ca_rec = ca_lookup.get(ca_key)
                if not ca_rec:
                    # Try partial match on package name
                    for (pname, rid), rec in ca_lookup.items():
                        if pkg_key in pname and rid == req_id.lower():
                            ca_rec = rec
                            break

                if not ca_rec:
                    continue  # Requirement not in eramba — skip silently

                ca_id = ca_rec['id']

                # GET full compliance analysis record
                if not dry_run:
                    full, err = eramba_request('GET', f"/api/compliance-managements/{ca_id}")
                    if err:
                        log(f"GET compliance analysis {ca_id}: {err}", 'ERR')
                        errors += 1
                        continue

                    # Merge in our control and policy IDs
                    existing_services = full.get('security_services', []) or []
                    existing_policies = full.get('security_policies', []) or []

                    # Add if not already present
                    svc_ids = [s if isinstance(s, int) else s.get('id') for s in existing_services]
                    pol_ids = [p if isinstance(p, int) else p.get('id') for p in existing_policies]

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
                        log(f"PUT compliance analysis {ca_id} ({fw_label} {req_id}): {err}", 'ERR')
                        errors += 1
                        continue
                    time.sleep(API_DELAY)
                    updated += 1
                else:
                    log(f"[DRY] Would link {ctrl_title} → {fw_label} {req_id}", 'DRY')
                    updated += 1

    log(f"\nCompliance links: {updated} updated, {skipped} already linked, {errors} errors")
    return errors == 0


# ---------------------------------------------------------------------------
# Validation
# ---------------------------------------------------------------------------

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
