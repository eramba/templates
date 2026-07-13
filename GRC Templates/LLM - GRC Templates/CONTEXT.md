# Project Context — LLM GRC Templates

This file exists so that an LLM starting a new session can read it and continue working on this project without needing a briefing. Last updated: 2026-07-13.

---

## What This Project Is

eramba is an open-source GRC (Governance, Risk, and Compliance) platform. This project builds a library of reusable **policy documents** and **internal controls** that eramba customers can import and customise for their specific compliance frameworks.

**Goals:**
- Templates that satisfy multiple compliance frameworks simultaneously
- Per-framework tagging so customers can trim to their specific package
- Automated sync from this GitHub repo into eramba instances via `sync_eramba.py`

---

## Repository

- **Repo:** `https://github.com/eramba/templates`
- **Working directory:** `GRC Templates/LLM - GRC Templates/`
- **Branch workflow:** ALWAYS push to `claude/grc-templates`, open a PR, NEVER push directly to `master`
- **Token:** the repo owner (kisero) has a GitHub personal access token with `repo` scope

```
GRC Templates/LLM - GRC Templates/
├── README.md
├── CONTEXT.md                         ← this file
├── Policies/
│   ├── 00_mapping_table.md            ← SOURCE OF TRUTH: rebuilt from policy file tags
│   ├── Access_Management.md
│   └── ... (22 policies total)
└── Controls/
    ├── internal_controls.csv
    ├── mapping_controls_to_requirements.csv   ← column headers = eramba package names exactly
    └── mapping_controls_to_policies.csv

GRC Templates/update_script/
├── sync_eramba.py                     ← main sync script
├── README.md
└── logs/                              ← local only, not in git
```

---

## Frameworks Covered (16 total)

| CSV column header | eramba package name | eramba regulator ID |
|---|---|---|
| PCI DSS v4.0.1 | PCI-DSS | 99 |
| ISO 27001:2022 | ISO 27001 | 74 |
| ISO 27002:2022 | ISO 27002 | 75 |
| SOC 2 (TSP 2017) | SOC2 | 100 |
| NIST 800-53 v5 | NIST 800-53 v5 | 12 |
| CIS Controls v8.1 | CIS Controls | 8 |
| SCF 2025 | Secure Control Framework | 2 |
| NIS2 Article 21 | NIS2 - Article 21 | 68 |
| ISO 27701:2025 | ISO 27701 | 94 |
| ISO 45001:2018 | ISO 45001 | 91 |
| ISO 42001:2023 | ISO 42001 | 92 |
| ISO 37001:2025 | ISO 37001 | 93 |
| ISO 9001:2015 | ISO 9001 | 90 |
| OWASP LLM Top 10 2025 | OWASP Top 10 for LLM Applications | 97 |
| NIST AI 600-1 2024 | NIST AI 600-1 Generative AI Profile | 96 |
| EU AI Act 2024/1689 | EU AI Act | 81 |

**CRITICAL:** The column headers in `mapping_controls_to_requirements.csv` and the framework names in `sync_eramba.py` must exactly match the eramba package names above. If you rename one, update all three.

---

## Policy Structure

Every policy has these sections:

```
# Policy Name
## Purpose
## Scope
## Responsibilities
## Policy Statements
### Section Title
**Applies to all frameworks**
- bullet

**Framework Tag Label (req_id1, req_id2)**
- framework-specific bullet

## Standards
## Procedures
### Procedure Title
**Applies to all frameworks**
1. universal step

**Framework Tag Label (req_id1, req_id2)**
N. framework-specific step
```

### Framework Tag Labels (used inside .md files)

These are the labels used in `**bold**` tags inside policy files. They differ from eramba package names:

| Tag in .md file | eramba package name |
|---|---|
| PCI DSS v4.0.1 | PCI-DSS |
| ISO 27001:2022 | ISO 27001 |
| ISO 27002:2022 | ISO 27002 |
| SOC 2 (TSP 2017) | SOC2 |
| NIST 800-53 Rev5 | NIST 800-53 v5 |
| CIS Controls v8.1 | CIS Controls |
| SCF 2025 | Secure Control Framework |
| NIS2 Article 21 | NIS2 - Article 21 |
| ISO 27701:2025 | ISO 27701 |
| ISO 45001:2018 | ISO 45001 |
| ISO 42001:2023 | ISO 42001 |
| ISO 37001:2025 | ISO 37001 |
| ISO 9001:2015 | ISO 9001 |
| OWASP Top 10 for LLM Applications 2025 | OWASP Top 10 for LLM Applications |
| NIST AI 600-1 Generative AI Profile 2024 | NIST AI 600-1 Generative AI Profile |
| EU AI Act 2024/1689 | EU AI Act |

### ISO 37001 requirement IDs

ISO 37001 req IDs in the policy files are stored with a `37001:2025 - ` prefix (e.g. `37001:2025 - 8.7`). The eramba package stores them WITHOUT this prefix (e.g. `8.7`). The `00_mapping_table.md` uses the prefixed format. The sync script matches by stripping the prefix at lookup time — **do not remove the prefix from the policy files**.

---

## 00_mapping_table.md — Source of Truth

This file is the authoritative cross-reference of which policies cover which framework requirements. It is **rebuilt from the policy files**, not maintained manually.

To rebuild it after changing policy files:
1. Read all 22 `.md` files
2. Extract every `**Framework Tag (req1, req2)**` block
3. Validate each req ID against the source framework CSVs
4. Regenerate the table

Do NOT edit `00_mapping_table.md` manually — edit the policy `.md` files and rebuild.

---

## Controls Structure

99 controls across 3 CSV files. Column headers in `mapping_controls_to_requirements.csv` exactly match eramba package names (see table above).

Controls were built from ISO 27002 and CIS Controls v8.1 as the primary sources, then mapped to all 16 frameworks.

---

## Sync Script (`GRC Templates/update_script/sync_eramba.py`)

**Instance:** `https://templates-prod.cloud.eramba.org`
**Auth:** HTTP Basic (username:password base64) over TLS, SSL verification disabled (self-signed cert)

**Environment variables required:**
```bash
export ERAMBA_URL=https://templates-prod.cloud.eramba.org
export ERAMBA_USER=admin
export ERAMBA_PASSWORD=<password>
export GITHUB_TOKEN=ghp_...   # required to avoid GitHub rate limits
```

**Usage:**
```bash
python3 sync_eramba.py                          # full sync
python3 sync_eramba.py --only policies          # policies only
python3 sync_eramba.py --only controls          # controls only
python3 sync_eramba.py --only compliance        # compliance links only
python3 sync_eramba.py --dry-run                # preview without changes
python3 sync_eramba.py --only compliance --max-pages 127   # limit pagination for testing
```

**Sync order matters:** always run policies before controls (controls need policy IDs).

**Compliance sync pagination:**
- The compliance analysis index has ~12,617 records across 127 pages
- Pages 1-126 return 100 records each, page 127 returns 17
- The script uses 1-second delays between pages and retries 502/503 errors
- Full run takes ~15 minutes
- Use `--max-pages N` to test with fewer pages

**Key API behaviours:**
- PUT requires ALL fields — script GETs first, merges changes, then PUTs
- Compliance analysis has no POST — links are update-only
- Response envelope: `{success: true, data: {...}}` — script unwraps this
- `security_policies` on a control record = the control→policy link (array of integer IDs)

**Policy POST/PUT payload key fields:**
```json
{
  "index": "Policy Name",
  "short_description": "...",
  "description": "<html>converted from markdown</html>",
  "security_policy_document_type_id": 1,
  "use_attachments": 0,
  "status": 1,
  "permission": "public",
  "version": "1.0",
  "asset_label_id": null,
  "owners": ["User-1"],
  "collaborators": ["User-1"],
  "projects": [1],
  "related_documents": [1],
  "tags": [],
  "url": "",
  "published_date": "YYYY-MM-DD",
  "next_review_date": "YYYY-MM-DD"
}
```

**Control POST/PUT payload key fields:**
```json
{
  "name": "Control Title",
  "objective": "Description",
  "audit_metric_description": "Audit Methodology",
  "security_service_type_id": 4,
  "service_owners": ["User-1"],
  "collaborators": ["User-1"],
  "audit_owners": ["User-1"],
  "audit_evidence_owners": ["User-1"],
  "maintenance_owners": ["User-1"],
  "maintenance_metric_description": "Undefined",
  "audit_success_criteria": "User Defined",
  "service_contracts": [1],
  "security_policies": [5],
  "opex": 1, "capex": 1, "resource_utilization": 1,
  "projects": null
}
```

**Compliance analysis PUT payload:**
```json
{
  "compliance_treatment_strategy_id": 1,
  "efficacy": 0,
  "description": "",
  "owners": ["User-1"],
  "projects": [],
  "compliance_exceptions": [],
  "security_policies": [4],
  "security_services": [],
  "risks": [""],
  "third_party_risks": [""],
  "business_continuities": [""],
  "compliance_analysis_findings": [""],
  "assets": [""],
  "legal_id": [""]
}
```

---

## Current State (as of 2026-07-13)

- 22 policies created in eramba ✓
- 99 controls: creation pending (API 502 issues on security-services index — to resolve with devs Monday)
- Compliance analysis links: 975 policy→requirement links created across 13 frameworks ✓
- NIST 800-53 and ISO 37001 compliance links: pending (req ID format mismatch being investigated)
- 788 completely unmapped requirements across all frameworks: pending decision

---

## Known Issues / Pending

- **Controls sync 502:** `GET /api/security-services/index` returns 502 — eramba devs to advise Monday
- **NIST 800-53 req IDs:** our IDs (AC-2, CM-3 etc.) may not match eramba's item_id format — verify with devs
- **ISO 37001 req IDs in compliance sync:** the `37001:2025 - ` prefix needs stripping at lookup — currently handled in mapping table, verify once controls are working
- **788 unmapped requirements:** requirements in source CSVs not covered by any policy — to be reviewed and either mapped or documented as out of scope

---

## Branch / PR Workflow

ALWAYS:
1. Push changes to `claude/grc-templates` branch
2. Open a PR to `master`
3. Let the user (kisero) review and merge
4. Update `README.md` change log and this `CONTEXT.md`
5. NEVER push directly to `master`

---

## How to Resume a Session

Tell the LLM:
> "Read https://github.com/eramba/templates/blob/master/GRC%20Templates/LLM%20-%20GRC%20Templates/CONTEXT.md and continue the project. The GitHub token is: ghp_..."

The LLM should then fetch CONTEXT.md, read it fully, and ask what to work on next.
