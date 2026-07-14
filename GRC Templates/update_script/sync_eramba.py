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
# Logging setup — writes to logs/ directory next to the script
# ---------------------------------------------------------------------------

import logging
from pathlib import Path

def setup_file_logger():
    log_dir = Path(__file__).parent / 'logs'
    log_dir.mkdir(exist_ok=True)
    timestamp = __import__('datetime').datetime.now().strftime('%Y-%m-%d_%H-%M-%S')
    log_file = log_dir / f'sync_{timestamp}.log'
    logging.basicConfig(
        level=logging.INFO,
        format='%(message)s',
        handlers=[
            logging.FileHandler(log_file, encoding='utf-8'),
        ]
    )
    return log_file

LOG_FILE = setup_file_logger()

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
    'service_owners': ['User-1'],
    'collaborators': ['User-1'],
    'audit_calendar_type': 0,
    'maintenance_calendar_type': 0,
    'audit_success_criteria': 'User Defined',
}

# Delay between API calls to avoid hammering the server
API_DELAY = 0.5


# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

def log(msg, level='INFO'):
    prefix = {'INFO': '  ', 'OK': '✓ ', 'SKIP': '– ', 'WARN': '! ', 'ERR': '✗ ', 'DRY': '~ '}
    line = f"{prefix.get(level, '  ')}{msg}"
    print(line)
    logging.info(line)


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

        # Fix markdown list rendering: insert blank lines so parser
        # treats - and 1. items as proper lists not inline text
        import re as _re
        content_fixed = content
        # blank line before headings
        content_fixed = _re.sub(r'([^\n])\n(#{1,3} )', r'\1\n\n\2', content_fixed)
        # blank line after **bold** before bullet list
        content_fixed = _re.sub(r'(\*\*[^\n]+\*\*)\n(-)', r'\1\n\n\2', content_fixed)
        # blank line after **bold** before numbered list
        content_fixed = _re.sub(r'(\*\*[^\n]+\*\*)\n(\d+\.)', r'\1\n\n\2', content_fixed)
        # blank line after last bullet before next **bold**
        content_fixed = _re.sub(r'(- [^\n]+)\n(\*\*)', r'\1\n\n\2', content_fixed)
        # blank line after last numbered item before next **bold**
        content_fixed = _re.sub(r'(\d+\. [^\n]+)\n(\*\*)', r'\1\n\n\2', content_fixed)
        html = md_converter.markdown(content_fixed, extensions=['tables', 'fenced_code'])

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
    records = eramba_get_all('/api/v2/security-services/index')
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
                # Send only the fields we want to update — avoid sending
                # nested objects from GET that PUT does not accept
                update_payload = {
                    **POLICY_DEFAULTS,
                    'index': pol['name'],
                    'short_description': pol['short_description'],
                    'description': pol['html'],
                    'published_date': str(date.today() - __import__('datetime').timedelta(days=1)),
                    'next_review_date': str(date.today().replace(year=date.today().year + 10)),
                }
                res, err = eramba_request('PUT', f"/api/security-policies/{rec_id}", update_payload)
                if err:
                    log(f"PUT policy {pol['name']}: {err}", 'ERR')
                    errors += 1
                    continue
                if isinstance(res, dict) and not res.get('success', True):
                    log(f"PUT policy {pol['name']} failed: {res}", 'ERR')
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
        pol_ids  = [pol_rec['id']] if pol_rec else []

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
                full, err = eramba_request('GET', f"/api/v2/security-services/{rec_id}")
                if err:
                    log(f"GET control {title}: {err}", 'ERR')
                    errors += 1
                    continue
                full.update(payload_fields)
                _, err = eramba_request('PUT', f"/api/v2/security-services/{rec_id}", full)
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
                res, err = eramba_request('POST', '/api/v2/security-services', payload)
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

def load_policy_mapping_from_github():
    """
    Parse 00_mapping_table.md and return:
    {policy_name: {eramba_regulator_name: [req_id, ...]}}
    """
    path = f"{GITHUB_BASE}/Policies/00_mapping_table.md"
    content, err = github_get_file_content(path)
    if err:
        log(f"Cannot read 00_mapping_table.md: {err}", 'ERR')
        return {}

    # Column header → eramba regulator name
    FW_RENAME = {
        'PCI DSS v4.0.1':         'PCI-DSS',
        'ISO 27001:2022':          'ISO 27001',
        'ISO 27002:2022':          'ISO 27002',
        'SOC 2 (TSP 2017)':       'SOC2',
        'NIST 800-53 Rev5':       'NIST 800-53 v5',
        'CIS Controls v8.1':      'CIS Controls',
        'SCF 2025':                'Secure Control Framework',
        'NIS2 Article 21':        'NIS2 - Article 21',
        'ISO 27701:2025':          'ISO 27701',
        'ISO 45001:2018':          'ISO 45001',
        'ISO 42001:2023':          'ISO 42001',
        'ISO 37001:2025':          'ISO 37001',
        'ISO 9001:2015':           'ISO 9001',
        'OWASP LLM Top 10 2025':  'OWASP Top 10 for LLM Applications',
        'NIST AI 600-1 2024':     'NIST AI 600-1 Generative AI Profile',
        'EU AI Act 2024/1689':    'EU AI Act',
    }

    lines = [l.strip() for l in content.split('\n') if l.strip().startswith('|')]
    if len(lines) < 3:
        log("Could not parse 00_mapping_table.md", 'ERR')
        return {}

    headers = [h.strip() for h in lines[0].split('|')[1:-1]]
    fw_headers = headers[1:]  # skip 'Policy' column

    result = {}
    for line in lines[2:]:  # skip header and separator
        cells = [c.strip() for c in line.split('|')[1:-1]]
        if not cells:
            continue
        policy_name = cells[0].strip()
        result[policy_name] = {}
        for fw_label, req_str in zip(fw_headers, cells[1:]):
            if not req_str or req_str == '—':
                continue
            eramba_name = FW_RENAME.get(fw_label)
            if not eramba_name:
                continue
            req_ids = [r.strip() for r in req_str.split(',') if r.strip()]
            if req_ids:
                result[policy_name][eramba_name] = req_ids

    log(f"Loaded policy mapping: {len(result)} policies")
    return result


def sync_compliance(dry_run=False, max_pages=0):
    """
    Link policies to compliance analysis requirements using 00_mapping_table.md.

    For each policy → framework → req_id in the mapping table:
    1. Find the compliance analysis record (already in memory)
    2. Add the policy ID to its security_policies list
    3. PUT the updated record
    """
    log("\n=== COMPLIANCE ANALYSIS LINKS ===")

    # Step 1: paginate all compliance analysis records into memory
    # Load policies and mapping FIRST before the heavy pagination
    # (eramba may be too tired to respond after 14k+ records)
    eramba_pols  = load_eramba_policies()
    policy_map   = load_policy_mapping_from_github()

    if not eramba_pols:
        log("No policies found in eramba — run --only policies first", 'ERR')
        return False

    log("Loading all compliance analysis records (paginating)...")
    ca_records = []
    page = 1
    limit = 100
    consecutive_errors = 0
    while True:
        if max_pages and page > max_pages:
            log(f"Reached --max-pages limit ({max_pages}), stopping pagination")
            break
        result, err = eramba_request('GET', f"/api/compliance-managements/index?page={page}&limit={limit}")
        if err:
            consecutive_errors += 1
            log(f"Page {page} error ({consecutive_errors}): {err} — waiting 5s before retry", 'WARN')
            time.sleep(5)
            if consecutive_errors >= 3:
                log(f"3 consecutive errors — stopping at {len(ca_records)} records", 'WARN')
                break
            continue  # retry same page
        consecutive_errors = 0
        if isinstance(result, dict) and 'data' in result:
            result = result['data']
        if not isinstance(result, list) or not result:
            break
        ca_records.extend(result)
        log(f"  Page {page}: {len(result)} records ({len(ca_records)} total)")
        if not result:
            break
        page += 1
        time.sleep(1)

    log(f"Loaded {len(ca_records)} compliance analysis records")

    # Step 2: build lookup {(regulator_name_lower, item_id_lower): ca_record}
    ca_lookup = {}
    for rec in ca_records:
        item    = rec.get('compliance_package_item') or {}
        pkg     = item.get('compliance_package') or {}
        reg     = pkg.get('compliance_package_regulator') or {}
        reg_name = reg.get('name', '').lower().strip()
        item_id  = item.get('item_id', '').lower().strip()
        if reg_name and item_id:
            ca_lookup[(reg_name, item_id)] = rec

    log(f"Built lookup with {len(ca_lookup)} entries")

    # Show sample regulator names for debugging
    sample_regs = sorted(set(k[0] for k in ca_lookup.keys()))[:10]
    log(f"Sample regulator names in lookup: {sample_regs}")

    log(f"Framework labels in mapping table: {sorted(set(fw for v in policy_map.values() for fw in v.keys()))[:8]}...")

    updated = skipped = not_found = errors = 0
    not_found_list = []
    error_list = []

    # Step 4: iterate policy → framework → req_id and update compliance analysis
    for policy_name, fw_map in policy_map.items():
        pol_rec = eramba_pols.get(policy_name.lower().strip())
        if not pol_rec:
            log(f"Policy not in eramba: '{policy_name}'", 'WARN')
            continue
        pol_id = pol_rec['id']

        for reg_name, req_ids in fw_map.items():
            for req_id in req_ids:
                key = (reg_name.lower().strip(), req_id.lower().strip())
                ca_rec = ca_lookup.get(key)
                if not ca_rec:
                    not_found += 1
                    not_found_list.append(f"{policy_name} | {reg_name} | {req_id}")
                    continue

                ca_id = ca_rec['id']

                # Check if policy already linked
                existing_pols = ca_rec.get('security_policies') or []
                pol_ids = [p['id'] if isinstance(p, dict) else p for p in existing_pols]

                if pol_id in pol_ids:
                    skipped += 1
                    continue

                pol_ids.append(pol_id)

                if dry_run:
                    log(f"[DRY] {reg_name} {req_id} → policy {pol_id} ({policy_name})", 'DRY')
                    updated += 1
                    # Update in-memory so we don't double-count
                    ca_rec['security_policies'] = [{'id': i} for i in pol_ids]
                    continue

                # Keep existing security_services
                existing_svcs = ca_rec.get('security_services') or []
                svc_ids = [s['id'] if isinstance(s, dict) else s for s in existing_svcs]

                payload = {
                    "compliance_treatment_strategy_id": 1,
                    "efficacy": 0,
                    "description": "",
                    "owners": ["User-1"],
                    "projects": [],
                    "compliance_exceptions": [],
                    "security_policies": pol_ids,
                    "security_services": svc_ids,
                    "risks": [""],
                    "third_party_risks": [""],
                    "business_continuities": [""],
                    "compliance_analysis_findings": [""],
                    "assets": [""],
                    "legal_id": [""],
                }

                log(f"Linking {policy_name} → {reg_name} {req_id} (ca_id={ca_id})")
                res, err = eramba_request('PUT', f"/api/compliance-managements/{ca_id}", payload)
                if err:
                    msg = f"{policy_name} | {reg_name} | {req_id} | ca_id={ca_id} | {err}"
                    log(f"PUT failed: {msg}", 'ERR')
                    error_list.append(msg)
                    errors += 1
                    continue
                if isinstance(res, dict) and not res.get('success', True):
                    msg = f"{policy_name} | {reg_name} | {req_id} | ca_id={ca_id} | {res}"
                    log(f"PUT failed: {msg}", 'ERR')
                    error_list.append(msg)
                    errors += 1
                    continue

                # Update in-memory
                ca_rec['security_policies'] = [{'id': i} for i in pol_ids]
                time.sleep(API_DELAY)
                updated += 1
                log(f"✓ {updated} linked so far")

    log(f"\n{'='*50}")
    log(f"COMPLIANCE SYNC SUMMARY")
    log(f"{'='*50}")
    log(f"  Updated:        {updated}")
    log(f"  Already linked: {skipped}")
    log(f"  Not found:      {not_found}")
    log(f"  Errors:         {errors}")

    if not_found_list:
        log(f"\nNOT FOUND ({len(not_found_list)}) — req ID exists in mapping table but not in eramba:")
        for item in sorted(not_found_list):
            log(f"  - {item}")

    if error_list:
        log(f"\nERRORS ({len(error_list)}) — PUT request failed:")
        for item in error_list:
            log(f"  - {item}", 'ERR')

    log(f"\n{'='*50}")
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
    parser.add_argument('--max-pages', type=int, default=0,
                        help='Limit compliance pagination to N pages (for testing, 0 = all pages)')
    args = parser.parse_args()

    dry_run = args.dry_run or DRY_RUN

    validate_env()

    print(f"\neramba GRC Template Sync")
    print(f"Log file : {LOG_FILE}")
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
        success &= sync_compliance(dry_run=dry_run, max_pages=args.max_pages)

    print()
    if success:
        log("Sync completed successfully", 'OK')
        sys.exit(0)
    else:
        log("Sync completed with errors", 'ERR')
        sys.exit(1)


if __name__ == '__main__':
    main()
