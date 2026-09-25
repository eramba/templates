# Writing automations

The standard every automation in this library follows. Use it to write a new automation,
to adapt one to another technology, or to review a contribution.

Start from [`_TEMPLATE/`](../_TEMPLATE/). The reference implementation is
[`AWS/aws-backup-jobs-restore-tests`](../AWS/aws-backup-jobs-restore-tests/).

To test locally without eramba or the real system, use the [test harness](../tools/) (§6).

---

## 1. Principles

1. **The control defines the checks.** Every check comes from the control's *Audit Methodology*
   (`GRC Templates/LLM - GRC Templates/Controls/internal_controls.csv`) or from the policy procedure
   the control operationalises (`mapping_controls_to_policies.csv` + the policy file). Nothing is invented.
   Anything the methodology asks for that cannot be automated is listed as manual in the README.
2. **Customers differ, the code does not.** Everything a customer may need to change — scope,
   regions, thresholds, periods, which checks run — is a variable with a documented default. Values
   the control leaves to the organisation (RPO, RTO, retention…) get a sensible default and are
   flagged in the README as *set this to your own value*.
3. **Read-only, least privilege.** The automation only reads metadata. The README lists the exact
   permissions and nothing more.
4. **Never guess a result.** If the automation cannot collect the data, it writes nothing, exits 1
   and explains why. A technical error must never look like a failed control.
5. **Always explain.** Every step is logged to STDOUT. The audit conclusion says what was checked,
   what failed and why. The evidence lists every item checked.
6. **Public and customer-facing.** No customer names, account IDs, hostnames, internal URLs or
   personal data in code, examples or screenshots. Use `111111111111`, `example.com`, etc.

## 2. Naming and structure

| Item | Rule | Example |
|---|---|---|
| Technology folder | Product family, Title Case | `AWS`, `Azure`, `Google Cloud`, `Microsoft 365`, `Okta`, `Generic` |
| Automation folder = `id` | `<technology>-<what-it-checks>`, kebab-case | `aws-backup-jobs-restore-tests` |
| Files | `README.md` + `run.php` | — |
| eramba automation name | `<Technology> – <Control title>` | `AWS Backup – Backup Execution and Restore Test` |
| Secrets | lowercase snake_case, prefixed with the technology | `aws_access_key_id`, `okta_api_token` |
| Variables | UPPER_SNAKE_CASE; `CHECK_<X>` booleans to switch checks on/off | `REGIONS`, `CHECK_RESTORE_TESTS`, `MAX_BACKUP_AGE_HOURS` |
| Check ids (in results/evidence) | lowercase snake_case | `backup_jobs`, `restore_time` |
| Version | semver in `run.php` (`AUTOMATION_VERSION`) and README metadata; bump on every change | `0.1.0` → `0.2.0` new check, `0.1.1` fix |

One automation tests one control on one technology. If two controls are tested with the same
data on the same technology, one automation may cover both (list both in `controls`).

## 3. `run.php` contract

Keep the sections of the template, in this order:

| # | Section | Changes per automation? |
|---|---|---|
| — | `<?php` + `declare(strict_types=1);` on **line 2**, then the header comment (name, id, version, composer packages, output and exit codes) | header only |
| 1 | `SECRETS` — `'%SECRET_<name>%'` placeholders | yes |
| 2 | `VARIABLES` — `$config` array, every entry commented | yes |
| 3 | `ERAMBA MACROS` — `$auditId = '%SECURITYSERVICEAUDIT_ID%'` | no |
| 4 | `HELPERS` — `logStep`, `logInfo`, `erambaCall`, `checkSecrets`, `abortMessage`, `result` | no (copy as is) |
| 5 | `COLLECT` — connect to the technology, return result items | **yes: the only technology-specific logic** |
| 6 | `EVALUATE` — results → Passed/Failed + conclusion | no |
| 7 | `REPORT` — evidence upload, audit result, comment | no |
| 8 | `MAIN` — steps 1–4, error handling | only technology-specific checks in step 1 and exception types |

`collectResults()` returns a list of items built with the `result()` helper (section 4):

```php
$results[] = result('backup_jobs', 'eu-west-1', 'arn:aws:ec2:…:volume/vol-…', true, 'Backup job … COMPLETED on 2026-09-25 08:02, 30,720.0 MB');
//                  check id       region ('' if none)  resource            passed  detail
```

- One item per thing checked (job, resource, account, setting).
- **Plus exactly one summary item per enabled check**, always, even when there was nothing to look
  at ("No protected resources in scope: nothing to verify" → failed). A check must never silently
  disappear from the result.
- `detail` is a full sentence an auditor understands without the code.
- Missing data (a date, a status) is never replaced by a guess: the item fails and says why.
- An empty result list means nothing matched the scope: the audit is **Failed**, never Passed.

Naming inside the code: `$secrets` keys are UPPER_SNAKE (`AWS_ACCESS_KEY_ID`); their values are the
eramba secret macros in lowercase (`'%SECRET_aws_access_key_id%'`). `configText()` and `evidenceCsv()`
live in section 7 (REPORT).

## 4. eramba runtime rules

Learned from eramba's code and from real runs. Breaking any of these breaks the automation.

| Rule | Why |
|---|---|
| `declare(strict_types=1);` directly after `<?php` on line 2, nothing before it (not even a comment) | eramba inserts its includes right after it; anything in between makes PHP fail with a fatal error |
| Do not `require` `vendor/autoload.php` | eramba adds the composer autoloader itself |
| Composer packages are one `vendor/package:version` per line, not a `composer.json` | That is the format of the automation's composer field; they are installed on every run |
| Secret macro = `%SECRET_` + secret name in lowercase, spaces/dashes → `_` | eramba builds the alias with a slug of the name |
| Secrets and macros are replaced as text before running | Always put them inside single quotes; a `'` in a value breaks the code |
| Audit id macro: `%SECURITYSERVICEAUDIT_ID%` (maintenances: `%SECURITYSERVICEMAINTENANCE_ID%`) | Macro prefix = model alias, singular, uppercase |
| Audit result edit needs 4 fields: `start_date`, `end_date`, `security_service_audit_result_option_id`, `result_description` | They are required by the audit form on edit |
| Result option ids: `1` = Failed, `2` = Passed (make them variables) | Default audit result options; customers can add their own |
| `editObjectMacro`, `addCommentMacro`, `uploadAttachmentMacro` return `"ERROR: …"`/`"WARNING: …"` strings instead of throwing | Wrap every call in `erambaCall()` |
| Attachments: CSV or plain text only; **no JSON** | eramba validates the real content type (`validation.mimes`) |
| Upload the evidence **before** writing the audit result | A failed upload must leave the audit untouched |
| Any STDERR output, or exit code ≠ 0, marks the run as failed and emails the administrators | Write to STDERR only on real errors; use STDOUT for everything else |
| STDOUT and STDERR are truncated at 10 KB | Summaries in STDOUT; per-item details in the evidence CSV; cap listed failures with `MAX_LOG_ITEMS` |
| Max 240 s per run (default 10 s), 64 MB memory, 1 MB storage per automation, outbound 80/443 only | Set `timeout_seconds` in the README; set client timeouts (e.g. connect 5 s, request 20 s) |
| eramba runs the automation on audits whose planned date is today and have no result | A test run is a real run: it writes the result |
| With automated audits, eramba replaces each audit's methodology with "Automation in use: …" | Tell customers to keep the methodology in the control description |
| Audit dates are day/month and repeat every year | Recommend a frequency (e.g. 12 dates for monthly) in the README |
| Dates written to eramba and file names use UTC (`gmdate`) | One clock everywhere; the conclusion says "UTC" |
| Vendor APIs often keep limited history (AWS Backup: 30 days of backup jobs) | Keep look-back periods within the vendor's history and choose the recommended audit frequency so consecutive audits cover the whole period without gaps |

## 5. README contract

Copy [`_TEMPLATE/README.md`](../_TEMPLATE/README.md). The reader is a customer who has never seen the code.
Keep the **12 numbered sections, with these exact numbers**: `run.php` messages and
[installing.md](installing.md) refer to them (§5.2 permissions, §6 secrets, §7 variables, §9 troubleshooting…).

| § | Section | Content |
|---|---|---|
| — | Metadata + title + *At a glance* | Front matter in sync with the code; technology, control, recommended audits, effort, status |
| 1 | Controls and policies | Control, policy › procedure, frameworks, **recommended audit schedule and why** |
| 2 | What it checks | One row per check: passes when / fails when / switch variable; PASSED, FAILED, ERROR |
| 3 | Coverage of the audit methodology | Every methodology item and every relevant policy step (quoted), ✅ / ⚠️ / ❌, how, variables |
| 4 | Before you start | Checklist: prerequisites, values to decide, permissions, network |
| 5 | Setup on <technology> | Console steps (5.1…), **5.2 minimum permissions** table |
| 6 | Setup in eramba | Secrets table, automation fields, control settings |
| 7 | Variables | Every `$config` key, default, description; ⚠️ on the ones to review |
| 8 | Results in eramba | What is written where + a **real** STDOUT, identifiers masked |
| 9 | Troubleshooting | Every error message the script can produce, cause, fix |
| 10 | Customising | "I want to… → change this"; other automations for the same control |
| 11 | Removing | Clean-up on the target system |
| 12 | Changelog | One line per version |

Links in the template (e.g. `../../docs/installing.md`) are written for the final location
`<Technology>/<id>/README.md`; they only resolve after copying.

Guidelines:

- **Setup on the target system**: the way a non-specialist can follow it. Prefer console wizards over
  pasting JSON; when JSON is needed, say exactly which editor it goes in (e.g. AWS trust policies only
  in *Trust relationships*).
- **Coverage**: quote policy steps by their text, not by number.
- Plain English, short sentences, no internal jargon.

## 6. Testing

1. **Syntax:** `php -l run.php` (PHP 8.4).
2. **Local run with simulated responses** (recommended): stub the eramba functions
   (`editObjectMacro`, `addCommentMacro`, `uploadAttachmentMacro`) and point the vendor SDK to a fake
   endpoint. Cover at least: all checks pass, each check fails, scope filters, missing secret,
   access denied, eramba write error, output size with many failures (< 10 KB).
3. **eramba test instance:** create secrets, automation and control as a customer would
   ([Installing](installing.md)), click **Test**, check the audit (result, conclusion, comment,
   attachments) and *Automation Logs*.
4. **Real target system:** at least one real run against the technology, ideally with data that
   makes some checks pass and some fail.

## 7. Release checklist

- [ ] Every check traced to the control's methodology or policy procedure (coverage table complete).
- [ ] Every enabled check always produces a summary item.
- [ ] Recommended audit frequency and look-back periods cover the methodology without gaps, within the vendor's history limits.
- [ ] Every customer-dependent value is a variable with a documented default.
- [ ] Permissions are read-only and minimal; README lists them.
- [ ] `declare(strict_types=1);` on line 2; `php -l` passes.
- [ ] Tested locally (all scenarios) and on a real eramba + real target system.
- [ ] README: real STDOUT pasted, identifiers masked; troubleshooting covers every error message.
- [ ] Metadata updated: `version`, `status`, `eramba_version_tested`, `last_tested`.
- [ ] Changelog entry.
- [ ] Catalogue updated in [`../README.md`](../README.md) (by control and by technology).
- [ ] No customer data, account IDs, hostnames or personal data anywhere.
