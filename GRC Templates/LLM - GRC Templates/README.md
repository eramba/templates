# LLM - GRC Templates

A library of reusable policy and internal control templates for eramba customers, covering 13 compliance frameworks simultaneously. Customers import templates and trim or customise them for their specific framework package.

---

## Directory Structure

```
LLM - GRC Templates/
├── Policies/
│   ├── 00_mapping_table.md       ← Policy-to-framework cross-reference
│   ├── Access_Management.md
│   ├── AI_Governance.md
│   └── ... (22 policies total)
└── Controls/
    ├── internal_controls.csv
    ├── mapping_controls_to_requirements.csv
    └── mapping_controls_to_policies.csv
```

---

## Frameworks Covered

| # | Framework | Version |
|---|-----------|---------|
| 1 | PCI DSS | v4.0.1 |
| 2 | ISO 27001 | 2022 |
| 3 | ISO 27002 | 2022 |
| 4 | SOC 2 | TSP 2017 |
| 5 | NIST 800-53 | Rev5 (base controls) |
| 6 | CIS Controls | v8.1 |
| 7 | SCF | 2025 |
| 8 | NIS2 | Article 21 |
| 9 | ISO 27701 | 2025 |
| 10 | ISO 45001 | 2018 |
| 11 | ISO 42001 | 2023 |
| 12 | ISO 37001 | 2025 |
| 13 | ISO 9001 | 2015 |
| 14 | OWASP Top 10 for LLM Applications | 2025 |
| 15 | NIST AI 600-1 Generative AI Profile | 2024 |
| 16 | EU AI Act | Regulation 2024/1689 |

---

## Policy Structure

### Logic

Each policy document covers one security or governance domain. Policies were built by:

1. Starting with the 8 original frameworks (PCI DSS, ISO 27001, ISO 27002, SOC 2, NIST 800-53, CIS, SCF, NIS2) and identifying all requirements per domain.
2. Adding 5 additional frameworks (ISO 27701, ISO 45001, ISO 42001, ISO 37001, ISO 9001) by mapping their requirements into existing policies, or creating new policies where no home existed.
3. Gap analysis after each framework addition — if a framework required something with no existing procedure, a new procedure was added. If a framework required something outside all existing policies, a new policy was created (e.g. Endpoint Security, Secure Development, Occupational Health Safety, AI Governance, Anti-Bribery, Quality Management).

### Document Format

Every policy follows this structure:

```
# Policy Name
## Purpose
## Scope
## Responsibilities
## Policy Statements
### Section Title
**Applies to all frameworks**
- Universal requirement bullet

**Framework Name (req IDs)**
- Framework-specific requirement bullet

## Standards
## Procedures
### Procedure Title
**Applies to all frameworks**
1. Universal step

**Framework Name (req IDs)**
N. Framework-specific step
```

### Tagging Model

Both policy statements and procedure steps are grouped by framework using `**bold**` headings. This serves two purposes:

- **Manual trimming**: customers removing a framework package delete the corresponding tagged blocks.
- **LLM trimming** (future): an LLM can be instructed to remove blocks for frameworks not selected.

The tagging is at the step level (Option B), not the procedure level, so customers importing a single framework retain the full procedure with only the relevant steps, rather than losing entire procedures.

### 22 Policies

| Policy | Primary Frameworks |
|--------|--------------------|
| Access Management | All 13 |
| AI Governance | ISO 42001, SCF, EU AI Act, NIST AI 600-1, OWASP LLM |
| Anti-Bribery | ISO 37001 |
| Asset Management | All 13 |
| Business Continuity | All 13 |
| Change Management | All 13 |
| Cryptography | All 13 |
| Endpoint Security | PCI DSS, ISO 27002, SOC 2, NIST, CIS, SCF, NIS2 |
| Human Resources Security | All 13 |
| Incident Management | All 13 |
| Information Classification | All 13 |
| Logging & Monitoring | All 13 |
| Network Security | All 13 |
| Occupational Health & Safety | ISO 45001 |
| Physical Security | All 13 |
| Privacy | SOC 2, NIST, CIS, SCF, NIS2, ISO 27701 |
| Quality Management | ISO 9001, SCF |
| Risk Management | All 13 |
| Secure Development | All 13 |
| Supplier Management | All 13 |
| System Maintenance | NIST, CIS, SCF, NIS2 |
| Vulnerability Management | All 13 |

---

## Controls Structure

### Logic

Internal controls operationalise the procedures defined in policies. Each control is an **activity** an organisation actually performs — not a policy document, not a compliance requirement. Controls were built by:

1. Reading all requirements for ISO 27002 and CIS Controls v8.1 in full and mapping them to existing policy procedures.
2. Where multiple requirements described the same operational activity, they were collapsed into a single control mapped to all applicable req IDs.
3. Where requirements had no existing procedure home, new procedures were added to existing policies or new policies were created.
4. All remaining 11 frameworks were then mapped to the same 99 controls (reuse where the control already satisfied the requirement) or new controls were added.

### Control Format (CSV columns)

| Column | Description |
|--------|-------------|
| Title | The activity name — what the organisation does |
| Description | What the activity is and why it exists |
| Audit Methodology | Evidence required → analysis to perform → conclusion and documentation → frequency |
| Policy Procedure | The specific policy and procedure section this control operationalises |

### 82 Controls

Controls are distributed across 16 policies:

| Policy | Controls |
|--------|----------|
| Access Management | 7 |
| Asset Management | 3 |
| Business Continuity | 2 |
| Change Management | 1 |
| Cryptography | 1 |
| Endpoint Security | 6 |
| Human Resources Security | 5 |
| Incident Management | 3 |
| Information Classification | 3 |
| Logging & Monitoring | 4 |
| Network Security | 4 |
| Occupational Health & Safety | 2 |
| Physical Security | 3 |
| Privacy | 2 |
| Quality Management | 2 |
| Risk Management | 12 |
| Secure Development | 6 |
| Supplier Management | 3 |
| Vulnerability Management | 5 |
| AI Governance | 2 |
| Anti-Bribery | 3 |

---

## Policy-to-Framework Mapping

See `Policies/00_mapping_table.md` for the full cross-reference table (22 policies × 13 frameworks with matched requirement IDs).

Summary (✓ = framework has requirements mapped to this policy):

| Policy | PCI | 27001 | 27002 | SOC2 | NIST | CIS | SCF | NIS2 | 27701 | 45001 | 42001 | 37001 | 9001 |
|--------|-----|-------|-------|------|------|-----|-----|------|-------|-------|-------|-------|------|
| Access Management | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | |
| AI Governance | | | | | | | ✓ | | | | ✓ | | |
| Anti-Bribery | | | | | | | | | | | | ✓ | |
| Asset Management | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | ✓ |
| Business Continuity | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | |
| Change Management | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | ✓ |
| Cryptography | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | |
| Endpoint Security | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | |
| Human Resources Security | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Incident Management | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | |
| Information Classification | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | |
| Logging & Monitoring | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | | |
| Network Security | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | |
| Occupational Health & Safety | | | | | | | | | | ✓ | | | |
| Physical Security | ✓ | | ✓ | ✓ | ✓ | | ✓ | | ✓ | ✓ | | | |
| Privacy | | | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | |
| Quality Management | | | | | | | ✓ | | | | | | ✓ |
| Risk Management | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| Secure Development | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | ✓ | | |
| Supplier Management | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| System Maintenance | | | | | ✓ | ✓ | ✓ | ✓ | | | | | |
| Vulnerability Management | ✓ | | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ | | | | | |

---

## Controls-to-Framework Mapping

See `Controls/mapping_controls_to_requirements.csv` for the full cross-reference (99 controls × 13 frameworks with matched requirement IDs).

---

## Controls-to-Policies Mapping

See `Controls/mapping_controls_to_policies.csv` for the mapping of each control to its policy and specific procedure section.

The relationship is: **Policy Procedure** defines how something should be done → **Internal Control** is the operational activity that proves it is being done → **Audit Methodology** is how you verify the control works.

---

## Change Log

| Date | Change | Author |
|------|--------|--------|
| 2026-07-02 | Initial library: 22 policies, 99 controls, 13 frameworks (PCI DSS v4.0.1, ISO 27001:2022, ISO 27002:2022, SOC 2, NIST 800-53 Rev5, CIS v8.1, SCF 2025, NIS2, ISO 27701, ISO 45001, ISO 42001, ISO 37001, ISO 9001). Procedure tags added for all 13 frameworks. | LLM |
| 2026-07-13 | Sync script built and operational. 22 policies and 975 compliance links created in eramba. Mapping table rebuilt from policy file tags. NIST renamed to NIST 800-53 v5. ISO 37001 prefix fixed. CONTEXT.md fully updated. | LLM |
| 2026-07-12 | Added 3 new AI frameworks: OWASP LLM Top 10 2025, NIST AI 600-1 2024, EU AI Act 2024/1689. Added 17 new controls. Updated AI_Governance, Secure_Development, Risk_Management, Supplier_Management, Logging_Monitoring policies. | LLM |
| 2026-07-02 | Renamed directory from `Claude - GRC Templates` to `LLM - GRC Templates`. Moved `00_mapping_table.md` into `Policies/` subdirectory. Added this README. | LLM |
