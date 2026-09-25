# Internal Control Automations

Ready-to-use eramba automations that **audit your internal controls automatically**.
Each automation connects to a system you already use (AWS, Azure, Okta, …), checks that a
control is really operating, and records the audit result in eramba — Passed or Failed,
a written conclusion, and the evidence — on the dates you plan.

They are built for the controls and policies of the
[eramba GRC templates](../../GRC%20Templates/LLM%20-%20GRC%20Templates/), but work with your own
controls too.

> **Status of this library:** early. See the [catalogue](#catalogue) for what is available today.
> *eramba does not provide support for developing or troubleshooting custom automation code.*

---

## How it fits together

```
 Policy  ──────────────►  Internal Control  ──────────────►  Audit  (one per planned date)
 "Business Continuity"    "Backup Execution and           2026-12-25 · 2027-03-25 · …
  › Backup Execution and    Restore Test"                        │
    Verification"           Audit execution: Automated           │  on the planned date
                            Automation: aws-backup-…  ◄──────────┘  eramba runs the automation
                                                                    │
                                          AWS Backup (read only) ◄──┤ 1. collects the data
                                                                    │ 2. evaluates the checks
                                                                    ▼ 3. writes the result
                                                       Audit: Passed/Failed + conclusion
                                                       Comment + evidence CSV attached
```

| Concept | What it is | Where it comes from |
|---|---|---|
| **Policy / procedure** | *How* something must be done | GRC templates › `Policies/` |
| **Internal control** | The activity that proves it is done, with its *Audit Methodology* | GRC templates › `Controls/internal_controls.csv` |
| **Audit** | One test of a control on a planned date | eramba creates them from the control's audit dates |
| **Automation** | PHP script that performs the audit on a given technology | This folder |
| **Secrets** | Credentials the automation uses, stored encrypted in eramba | You create them in eramba |
| **Variables** | Settings at the top of the script: scope, thresholds, periods | You adjust them per installation |

How controls and automations relate:

- **Every automation tests one or more controls.** Its README says which, and which policy procedure they belong to.
- **A control can have several automations in this library, one per technology.** The same backup control is tested differently on AWS, Azure or an internal system: you install the one that matches what you use.
- **In eramba, attach one automation per control.** Each automation writes the audit result, so two automations on the same control would overwrite each other. If you need two (e.g. AWS and Azure, or two AWS accounts), create one control per technology or account, e.g. *Backup Execution and Restore Test – AWS* and *– Azure*.
- **An automation tests what the control's methodology asks for.** Each README has a coverage table: which parts of the methodology are automated, and which parts the auditor still checks by hand.
- **If no automation matches your technology**, the control is audited manually as usual, or you adapt an existing automation (see [Writing automations](docs/writing-automations.md)).

---

## Getting started

1. **Import the templates you use** (policies and internal controls), or use your own controls.
2. **Find your control in the [catalogue](#catalogue)** and pick the automation for your technology.
3. **Read its README.** Check *Before you start*: prerequisites, permissions, and what stays manual.
4. **Install it** following the README. The generic steps are in [Installing an automation](docs/installing.md):
   - create a read-only account on the target system;
   - create the secrets in eramba;
   - create the automation in eramba, paste the code, set composer packages and timeout;
   - adjust the variables;
   - on the control: *Audits required*, audit dates, *Audit execution: Automated*, select the automation.
5. **Test it** with the *Test* button and read the output. ⚠️ A test is a real run: the audit you select gets its result. Test on the next planned audit only if you are happy for it to be completed today (see [Installing › Test](docs/installing.md#5-test)).
6. **Let eramba run it** on every planned audit date. Failed runs (credentials expired, permissions changed…) leave the audit open and eramba emails the administrators.

Plan roughly 30–45 minutes for the first installation of an automation.

### Things to know before you start

| | |
|---|---|
| **eramba edition** | Automations are an eramba Enterprise feature. |
| **5 automations per instance** | Choose the controls where automation brings most value. |
| **Runtime limits** | Max 240 s per run, 64 MB memory, outbound HTTP/HTTPS only, output truncated at 10 KB. |
| **Credentials** | Always a dedicated, read-only account. Each README lists the minimum permissions. |
| **Your data** | Automations only read metadata (job status, settings, dates). They never read or change your business data. |
| **Customising** | Every automation has variables for scope and thresholds. Defaults follow the control's methodology; organisation-specific values (RPO, RTO, …) must be set by you. |

---

## Catalogue

*Recommended audits* is the audit frequency that lets the automation cover the control's methodology with that technology (see each README §1).

Status: `draft` (written, not tested) · `tested` (tested on a real eramba and target system) · `stable` (used in production by several installations).

### By control

| Control | Policy › Procedure | Recommended audits | Automations |
|---|---|---|---|
| Backup Execution and Restore Test | Business Continuity › Backup Execution and Verification | Monthly | **AWS:** [aws-backup-jobs-restore-tests](AWS/aws-backup-jobs-restore-tests/) · **Azure:** planned |

### By technology

| Technology | Automation | Controls | Status | Tested on |
|---|---|---|---|---|
| AWS Backup | [aws-backup-jobs-restore-tests](AWS/aws-backup-jobs-restore-tests/) | Backup Execution and Restore Test | tested | eramba 3.31.0 |

---

## Folder structure

```
Automation/Internal Controls/
├── README.md                        ← this page: concepts and catalogue
├── docs/
│   ├── installing.md                ← generic installation, upgrade and removal in eramba
│   └── writing-automations.md       ← standard for writing or adapting automations
├── tools/                           ← local test harness (eramba stubs, fake endpoints, scenario runner)
├── _TEMPLATE/                       ← starting point for a new automation
│   ├── README.md
│   └── run.php
└── <Technology>/                    ← AWS, Azure, Google Cloud, Microsoft 365, Okta, Generic…
    └── <technology>-<check>/        ← one folder per automation
        ├── README.md                ← documentation (starts with machine-readable metadata)
        └── run.php                  ← code to paste into eramba
```

---

## FAQ

**Does an automation replace the auditor?**
No. It performs the repeatable, evidence-heavy part of the audit and documents it. The coverage table of each README lists what still needs human judgement.

**What happens if the automation cannot connect?**
Nothing is written to the audit. The run is marked as failed in *Automation Logs*, eramba emails the administrators, and the audit stays open until someone fixes the cause and runs it again (or audits it manually).

**Can I use it with my own controls instead of the templates?**
Yes. Link the automation to any control whose methodology matches what the automation checks. Read the coverage table first.

**I use a different technology.**
Check whether the control has an automation for it. If not, copy the closest automation and replace the data-collection part: the rest of the script (evaluation, results, evidence) stays the same. See [Writing automations](docs/writing-automations.md).

**How do I update an automation to a newer version?**
See [Installing › Upgrading](docs/installing.md#upgrading). Your secrets stay; you re-apply your variables on the new code.
