# eramba Sync Script

Syncs the GRC template library from this GitHub repo into an eramba instance.

## What it does

| Step | What happens |
|------|-------------|
| **Policies** | Creates new policies / updates existing ones (matched by name). Content is the full Markdown from the repo. |
| **Controls** | Creates new controls / updates existing ones (matched by name). Maps Title → `name`, Description → `objective`, Audit Methodology → `audit_metric_description`. |
| **Compliance links** | Updates existing compliance analysis records in eramba to link them to the relevant controls and policies. Compliance packages must already be imported in eramba — this script only adds the links. |

## Requirements

- Python 3.8+
- No third-party libraries needed (uses only stdlib)

## Setup

### 1. Create a dedicated eramba API account

In eramba: Settings → Users → Add user  
- Enable the **REST APIs** toggle  
- Create a group called `API Documentation` and assign it to the account  
- Enable `API Documentation` on each section you want to sync (Policies, Internal Controls, Compliance Management)

### 2. Set environment variables

```bash
export ERAMBA_URL=https://templates-prod.cloud.eramba.org
export ERAMBA_USER=api-sync-user
export ERAMBA_PASSWORD=your-password

# Optional: GitHub token (avoids rate limits, required for private repos)
export GITHUB_TOKEN=ghp_xxxxx
```

### 3. Run

```bash
# Full sync (policies + controls + compliance links)
python sync_eramba.py

# Dry run — see what would happen without making changes
python sync_eramba.py --dry-run

# Sync only one section
python sync_eramba.py --only policies
python sync_eramba.py --only controls
python sync_eramba.py --only compliance
```

## Important notes

### PUT requires all fields
eramba's API requires that PUT requests send ALL fields of a record, not just the changed ones.  
The script handles this automatically: it GETs the existing record, merges the updated fields, then PUTs the full object.

### Compliance links require packages to exist
The compliance analysis sync only updates existing records — it cannot create new requirement entries. Import all compliance packages in eramba manually first, then run the sync to attach controls and policies to each requirement.

### Matching strategy
Records are matched by **name** (case-insensitive). If a policy or control with the same name already exists in eramba, it is updated. If not, it is created.

### Framework name mapping
The CSV column headers in `mapping_controls_to_requirements.csv` exactly match the compliance package regulator names in eramba. Do not rename either side without updating both.

| CSV column header | eramba package name |
|---|---|
| PCI-DSS | PCI-DSS |
| ISO 27001 | ISO 27001 |
| ISO 27002 | ISO 27002 |
| SOC2 | SOC2 |
| NIST SP 800-53 Rev. 5 | NIST SP 800-53 Rev. 5 |
| CIS Controls | CIS Controls |
| Secure Control Framework | Secure Control Framework |
| NIS2 - Article 21 | NIS2 - Article 21 |
| ISO 27701 | ISO 27701 |
| ISO 45001 | ISO 45001 |
| ISO 42001 | ISO 42001 |
| ISO 37001 | ISO 37001 |
| ISO 9001 | ISO 9001 |
| OWASP Top 10 for LLM Applications | OWASP Top 10 for LLM Applications |
| NIST AI 600-1 Generative AI Profile | NIST AI 600-1 Generative AI Profile |
| EU AI Act | EU AI Act |

### First run
On first run, all 22 policies and 99 controls will be created. Subsequent runs will update them.

## GitHub Actions (future)

When you're ready to automate, create `.github/workflows/sync.yml`:

```yaml
name: Sync to eramba
on:
  push:
    branches: [master]
    paths:
      - 'GRC Templates/LLM - GRC Templates/**'

jobs:
  sync:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: actions/setup-python@v5
        with:
          python-version: '3.11'
      - name: Run sync
        env:
          ERAMBA_URL: ${{ secrets.ERAMBA_URL }}
          ERAMBA_USER: ${{ secrets.ERAMBA_USER }}
          ERAMBA_PASSWORD: ${{ secrets.ERAMBA_PASSWORD }}
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: python "GRC Templates/update_script/sync_eramba.py"
```

Add `ERAMBA_URL`, `ERAMBA_USER`, and `ERAMBA_PASSWORD` as repository secrets in GitHub Settings → Secrets.

## Troubleshooting

**Connection refused / 403**  
Check that the eramba user has REST API enabled and the correct group permissions.

**PUT fails with validation error**  
The script GETs the full record before PUT. If you see this, check the eramba API response body in the error message — it will list which field failed validation.

**Compliance links not updating**  
Run `--only compliance` with `--dry-run` to see what the script finds. If it reports "No compliance analysis records found", import your compliance packages in eramba first.

**Rate limiting**  
The script has a 0.3s delay between API calls. Increase `API_DELAY` in the script if you see 429 errors.
