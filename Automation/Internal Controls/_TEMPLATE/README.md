---
# ─── Metadata: keep in sync with run.php. Used to build the catalogue. ───
id: <technology>-<what-it-checks>        # = folder name, kebab-case
name: <Technology> – <Control title>     # = name of the automation in eramba
version: 0.1.0                           # = AUTOMATION_VERSION in run.php
status: draft                            # draft | tested | stable | deprecated
technology: <Product>                    # e.g. AWS Backup, Azure Backup, Okta
vendor: <Vendor>                         # e.g. AWS, Microsoft, Okta
eramba_module: Internal Controls
trigger: Audit planned date
controls:                                # exact titles from internal_controls.csv
  - <Control title>
policies:                                # "Policy > Procedure" from mapping_controls_to_policies.csv
  - <Policy> > <Procedure section>
audit_frequency: <quarterly>             # recommended, derived from the control's methodology
secrets:                                 # names to create in Automation Secrets
  - <technology_secret_name>
variables:                               # every key of $config in run.php
  - <VARIABLE_NAME>
dependencies:                            # composer packages as pasted in eramba; [] if none
  - <vendor/package:^1.0>
timeout_seconds: 60                      # value for the automation's Timeout field (max 240)
eramba_version_tested: <x.y.z>
last_tested: <YYYY-MM-DD>
---

<!--
  Writing guide: docs/writing-automations.md §5.
  Reader: a customer who has never seen the code. Plain English, short sentences.
  Keep the 12 section numbers: run.php and docs/installing.md refer to them.
  Relative links assume the final location <Technology>/<id>/README.md.
  Replace every <placeholder>; delete these comments.
-->

# <Technology> – <Control title>

> <One or two sentences: what this automation verifies, in which system, and what it writes to eramba.>

| | |
|---|---|
| **Tests the control** | [`<Control title>`](#1-controls-and-policies) |
| **Technology** | <Product> (<Vendor>) |
| **Recommended audits** | <Monthly (12 audit dates per year)> |
| **Writes to eramba** | Audit result (Passed/Failed), conclusion, comment with evidence (CSV) |
| **Setup effort** | About <30> minutes |
| **Status** | <draft> · v0.1.0 · <tested on eramba x.y.z> |

---

## 1. Controls and policies

| Type | eramba template | Section |
|---|---|---|
| Internal control | `<Control title>` | Audit Methodology |
| Policy | `<Policy>` | Procedure: `<Procedure section>` |

Frameworks covered through the control: <list from mapping_controls_to_requirements.csv, e.g. ISO 27002 8.13 · SOC 2 A1.2 · …>.

**Recommended audit schedule:** <frequency and why, quoting the methodology. Consecutive audits must cover the whole period without gaps, within the vendor's history limits>.

## 2. What it checks

<!-- One row per check. Check ids = the ids used in results and evidence. -->

| # | Check | Passes when | Fails when | Switch |
|---|---|---|---|---|
| A | `<check_id>` | <condition> | <condition> | `CHECK_<X>` |

**PASSED:** every enabled check passes.
**FAILED:** any item fails, or nothing matched the scope. The conclusion lists the failing items.
**ERROR (nothing written):** credentials, permissions or network problem. The audit stays open, the reason is in STDERR and eramba emails the administrators.

## 3. Coverage of the audit methodology

<!-- Every item of the control's Audit Methodology and every relevant policy step. Nothing invented. -->

| Source | Requirement | Covered | How | Variables |
|---|---|---|---|---|
| Control | <methodology item> | ✅ | <check / API> | `<VAR>` |
| Policy step <n> | <procedure step> | ⚠️ Partial | <what is missing> | — |
| Control | <methodology item> | ❌ Manual | <why it cannot be automated> | — |

## 4. Before you start

- [ ] eramba Enterprise with a free automation slot (5 per instance).
- [ ] <Prerequisite on the target system, e.g. backups managed with X>.
- [ ] <Values you must decide: e.g. your RPO and RTO>.
- [ ] Permission to create a read-only account on <target system>.
- [ ] eramba can reach `<endpoint>` on port 443.

## 5. Setup on <target system>

<!-- Console steps a non-specialist can follow. Prefer wizards; also give the JSON/role names. -->

### 5.1 <Create the read-only account>

1. <step>

### 5.2 Minimum permissions

| Permission | Why |
|---|---|
| `<permission>` | <what it reads> |

## 6. Setup in eramba

Step-by-step guide: [Installing an automation](../../docs/installing.md). Values for this automation:

**Secrets** (Settings › Application Configuration › Automation Secrets):

| Secret name | Value |
|---|---|
| `<technology_secret_name>` | <what it contains, format> |

**Automation** (Control Catalog › Internal Controls › Audits › ⋮ › Automation):

| Field | Value |
|---|---|
| Name | `<Technology> – <Control title>` |
| Timeout | `<60>` |
| Composer packages | `<vendor/package:^1.0>` |
| Code | `run.php`, then edit the variables (§7) |

**Control:** Audits required · <12 audit dates, one per month> · Audit execution: **Automated** · select this automation · keep the Audit Methodology in the control description.

## 7. Variables

<!-- Every key of $config. Mark with ⚠️ the ones the customer must review. -->

| Variable | Default | Description |
|---|---|---|
| ⚠️ `<VARIABLE>` | `<default>` | <what it controls; example> |

## 8. Results in eramba

| Where | What |
|---|---|
| Audit Result | Passed / Failed |
| Audit Conclusion | Result, one line per check with counts, failing items (max `MAX_LOG_ITEMS`). Every enabled check always appears |
| Start / End date | Date of the run |
| Comment | `[<id> vX.Y.Z] PASSED/FAILED` with `evidence-<id>-<date>.csv` (one row per item checked) and `config-<id>-<date>.txt` (variables used) |
| Automation Logs | Full STDOUT/STDERR of every run |

Real output of a test run (identifiers masked):

```text
<paste real STDOUT>
```

## 9. Troubleshooting

| Message (STDERR) | Cause | Fix |
|---|---|---|
| `Secret <X> is missing` | Secret not created or named differently | Create it with the exact name (§6) |
| `<vendor error>` | <cause> | <fix> |
| `eramba upload … failed: validation.mimes` | eramba rejected the attachment | Set `ATTACH_EVIDENCE = false` and report it |
| `eramba edit audit failed: … Validation error` | Custom audit result options | Check `RESULT_PASSED_ID` / `RESULT_FAILED_ID` |

## 10. Customising

| I want to… | Change |
|---|---|
| <Narrow the scope> | `<VARIABLE = value>` |
| <Skip a check audited manually> | `CHECK_<X> = false` |
| <Test a different system> | Use the automation for that technology, or copy this one and replace `collectResults()` and §5 |

Other automations for the same control:

| Technology | Automation |
|---|---|
| <Technology> | <link or *planned*> |

## 11. Removing

1. In eramba: see [Installing › Removing](../../docs/installing.md#removing-an-automation).
2. On <target system>: <delete the account, keys and permissions created in §5>.

## 12. Changelog

| Version | Date | Change |
|---|---|---|
| 0.1.0 | <YYYY-MM-DD> | First version |

---

*eramba does not provide support for developing or troubleshooting custom automation code. Test in a non-production instance before use.*
