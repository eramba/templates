# Project Context — LLM GRC Templates

This file exists so that an LLM starting a new session can read it and continue working on this project without needing a briefing. Last updated: 2026-07-02.

---

## What This Project Is

eramba is an open-source GRC (Governance, Risk, and Compliance) platform. This project builds a library of reusable **policy documents** and **internal controls** that eramba customers can import with one click and then customise for their specific compliance frameworks.

**Goals:**
- Templates that satisfy multiple compliance frameworks simultaneously
- Per-framework tagging so customers can trim to their specific package
- Future: LLM-assisted customisation based on customer technology stack (Azure, AWS, on-prem, etc.)
- Future: automated control testing

---

## Repository

- **Repo:** `https://github.com/eramba/templates`
- **Working directory:** `GRC Templates/LLM - GRC Templates/`
- **Branch workflow:** always push to `claude/grc-templates`, open a PR, never push directly to `master`
- **Token:** the repo owner (kisero) has a GitHub personal access token with `repo` scope

```
GRC Templates/LLM - GRC Templates/
├── README.md                          ← Summary of everything, keep updated
├── Policies/
│   ├── 00_mapping_table.md            ← 22 policies × 13 frameworks with req IDs
│   ├── Access_Management.md
│   ├── AI_Governance.md
│   ├── Anti_Bribery.md
│   ├── Asset_Management.md
│   ├── Business_Continuity.md
│   ├── Change_Management.md
│   ├── Cryptography.md
│   ├── Endpoint_Security.md
│   ├── Human_Resources_Security.md
│   ├── Incident_Management.md
│   ├── Information_Classification.md
│   ├── Logging_Monitoring.md
│   ├── Network_Security.md
│   ├── Occupational_Health_Safety.md
│   ├── Physical_Security.md
│   ├── Privacy.md
│   ├── Quality_Management.md
│   ├── Risk_Management.md
│   ├── Secure_Development.md
│   ├── Supplier_Management.md
│   ├── System_Maintenance.md
│   └── Vulnerability_Management.md
└── Controls/
    ├── internal_controls.csv
    ├── mapping_controls_to_requirements.csv
    └── mapping_controls_to_policies.csv
```

---

## Frameworks Covered (13 total)

| Key | Label | Source CSV (in /mnt/user-data/uploads/) |
|-----|-------|----------------------------------------|
| pci | PCI DSS v4.0.1 | PCI_DSS_v4_0_1.csv |
| iso27001 | ISO 27001:2022 | ISO27001_2022.csv |
| iso27002 | ISO 27002:2022 | ISO27002_2022__1_.csv |
| soc2 | SOC 2 (TSP 2017) | SOC2.csv |
| nist | NIST 800-53 Rev5 | NIST_800-53_Rev5.csv |
| cis | CIS Controls v8.1 | CIS_v8_1.csv |
| scf | SCF 2025 | SCF_2025.csv |
| nis2 | NIS2 Article 21 | NIS2.csv |
| iso27701 | ISO 27701:2025 | ISO27701.csv |
| iso45001 | ISO 45001:2018 | ISO45001.csv |
| iso42001 | ISO 42001:2023 | ISO42001.csv |
| iso37001 | ISO 37001:2025 | ISO37001.csv |
| iso9001 | ISO 9001:2015 | ISO9001.csv |

The CSV files have columns: section_id, section_title, req_id, req_title, description (exact columns vary slightly per framework).

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

**Framework Label (req_id1, req_id2)**
- framework-specific bullet

## Standards
## Procedures
### Procedure Title
**Applies to all frameworks**
1. universal step

**Framework Label (req_id1, req_id2)**
N. framework-specific step
```

**Key rule:** Both statements AND procedure steps are tagged by framework. Each `**bold**` heading groups the requirements of that framework for that section/procedure. This lets users delete the blocks for frameworks they don't need.

---

## Controls Structure

82 controls across 3 CSV files:

**internal_controls.csv** — 4 columns:
- `Title` — the activity name
- `Description` — what the activity is and why it exists
- `Audit Methodology` — evidence required → analysis → conclusion/documentation → frequency
- `Policy Procedure` — `PolicyName > Procedure Section`

**mapping_controls_to_requirements.csv** — 14 columns:
- `Control Title` + one column per framework with matched req IDs (comma-separated), `—` if none

**mapping_controls_to_policies.csv** — 3 columns:
- `Control Title`, `Policy`, `Procedure Section`

**Key rule about controls:** A control is an *operational activity* the organisation actually performs — not a policy, not a compliance requirement. It has an owner, happens regularly, costs money, and is testable. eramba links controls to compliance requirements, risks, and policies.

---

## What Has Been Done

1. Built 22 policy documents in Markdown covering all 13 frameworks
2. Tagged all policy statements by framework (done)
3. Tagged all procedure steps by framework for all 13 frameworks (done)
4. Built 82 internal controls with full audit methodologies
5. Built 3 mapping CSVs (controls × requirements, controls × policies)
6. Built policy mapping table (22 policies × 13 frameworks)
7. Pushed everything to GitHub under `GRC Templates/LLM - GRC Templates/`
8. Set up branch + PR workflow

---

## What Remains / Known Issues

- **Procedure step quality review:** The framework-specific steps were generated from requirement text but have not been manually reviewed for accuracy. Some steps may be too generic or slightly off.
- **Future: technology-specific customisation** — when a customer specifies their tech stack (Azure, AWS, on-prem, Oracle, etc.), the templates should become more specific (e.g. "run `az ad user list` to export account list" instead of "export account list from identity provider").
- **Future: automated control testing** — controls currently describe manual audit methodology. Automated testing hooks will be added when LLM integration is built.
- **Future: gap detection** — when a customer imports a second compliance package after customising for a first, the system should identify which policies need updating.

---

## How to Continue Working

### Start a new session

1. Read this file
2. Ask the repo owner for the GitHub token (scope: `repo` on `eramba/templates`)
3. The framework source CSVs are in `/mnt/user-data/uploads/` if uploaded, or need to be re-uploaded
4. All build scripts and data are local to the previous session — they do NOT persist. You will need to re-read files from GitHub or ask the user to re-upload CSVs.

### Making changes

Always:
1. Push to branch `claude/grc-templates`
2. Open a PR to `master` with a clear description
3. Let the user merge
4. Update `README.md` and this `CONTEXT.md` to reflect changes
5. Add a row to the change log in `README.md`

### Reading current policies from GitHub

```python
import urllib.request, urllib.parse, json, base64

TOKEN = '<ask user>'
REPO = 'eramba/templates'
BASE = 'GRC Templates/LLM - GRC Templates'

def get_file(path):
    req = urllib.request.Request(
        f'https://api.github.com/repos/{REPO}/contents/{urllib.parse.quote(path)}',
        headers={'Authorization': f'token {TOKEN}', 'User-Agent': 'claude',
                 'Accept': 'application/vnd.github.v3+json'})
    with urllib.request.urlopen(req) as r:
        data = json.loads(r.read())
    return base64.b64decode(data['content']).decode('utf-8')

policy = get_file(f'{BASE}/Policies/Access_Management.md')
```

---

## eramba-Specific Notes

- eramba imports policies as HTML or Markdown
- Internal controls in eramba have: Title, Description, Audit Methodology, links to Compliance Requirements, Risks, Policies
- Controls are tested via Audits (pass/fail) — manual today, automated in future
- The eramba MCP server is available at `https://mcp.eramba.org/mcp` for direct platform interaction
- eramba documentation: `https://www.eramba.org/learning`

---

## Decisions Made

| Decision | Rationale |
|----------|-----------|
| Markdown format | Works with eramba import; easy for LLM processing |
| Tagged-section model (Option B — step level, not procedure level) | More granular than procedure-level tagging; consistent with policy statement tagging; enables LLM trimming |
| One control per operational activity, mapped to multiple frameworks | Avoids duplication; reflects how organisations actually operate |
| Audit methodology format: evidence → analysis → conclusion → frequency | Matches eramba audit structure; gives assessors clear instructions |
| 22 policies | Driven by framework coverage — new policies created when no existing policy covered a framework's control family |
| No "quarterly" in control titles | Frequency belongs in methodology, not title |
