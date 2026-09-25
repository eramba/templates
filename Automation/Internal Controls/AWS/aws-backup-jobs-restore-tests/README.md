---
# ─── Metadata: keep in sync with run.php. Used to build the catalogue. ───
id: aws-backup-jobs-restore-tests
name: AWS Backup – Backup Execution and Restore Test
version: 0.2.0
status: tested                           # draft | tested | stable | deprecated
technology: AWS Backup
vendor: AWS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Backup Execution and Restore Test
policies:
  - Business Continuity > Backup Execution and Verification
audit_frequency: monthly
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - AWS_ROLE_ARN
  - AWS_EXTERNAL_ID
  - REGIONS
  - RESOURCE_TYPES
  - RESOURCE_ARN_REGEX
  - RESOURCE_TAGS
  - CHECK_BACKUP_JOBS
  - BACKUP_LOOKBACK_DAYS
  - BACKUP_FAILED_STATES
  - IGNORE_RECOVERED_FAILURES
  - MIN_COMPLETED_BACKUP_JOBS
  - CHECK_BACKUP_FRESHNESS
  - MAX_BACKUP_AGE_HOURS
  - CHECK_RESTORE_TESTS
  - RESTORE_LOOKBACK_DAYS
  - MIN_RESTORE_TESTS
  - ONLY_RESTORE_TESTING_PLANS
  - REQUIRED_RESTORE_RESOURCE_TYPES
  - CHECK_RESTORE_TIME
  - MAX_RESTORE_MINUTES
  - CHECK_OFFSITE_COPY
  - ATTACH_EVIDENCE
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - aws/aws-sdk-php:^3.300
timeout_seconds: 60
eramba_version_tested: 3.31.0
last_tested: 2026-09-25
---

# AWS Backup – Backup Execution and Restore Test

> Checks in AWS Backup that the backups of the period completed without failures, that every protected resource has a recent backup (RPO), and that restore tests were performed and met the RTO. Writes Passed/Failed, a conclusion and the evidence to the control's audit in eramba.

| | |
|---|---|
| **Tests the control** | [`Backup Execution and Restore Test`](#1-controls-and-policies) |
| **Technology** | AWS Backup (AWS) |
| **Recommended audits** | Monthly (12 audit dates per year) |
| **Writes to eramba** | Audit result (Passed/Failed), conclusion, comment with evidence (CSV) |
| **Setup effort** | About 30 minutes |
| **Status** | tested · v0.2.0 · eramba 3.31.0 + AWS Backup, 2026-09-25 |

---

## 1. Controls and policies

| Type | eramba template | Section |
|---|---|---|
| Internal control | `Backup Execution and Restore Test` | Audit Methodology |
| Policy | `Business Continuity` | Procedure: `Backup Execution and Verification` |

Frameworks covered through the control: PCI DSS 12.3.3 · ISO 27002 5.29, 8.13 · SOC 2 A1.2, A1.3 · NIST 800-53 CP-9 · CIS 11.1–11.5 · SCF BCD-11, BCD-12 · NIS2 Art. 21(2)(c) · ISO 27701 A.3.24.

**Recommended audit schedule:** monthly (one audit date per month). The methodology asks for a weekly backup review and restore tests "at minimum quarterly". AWS Backup only returns the **last 30 days** of backup jobs, so a monthly audit with the default 30-day period reviews every backup job of every week; quarterly audits would miss two months of jobs. Each audit also checks the current state of every protected resource and the restore tests of the last 90 days.

## 2. What it checks

In every region of `REGIONS`, for the resources in scope (resource types, ARN regex, tags):

| # | Check | Passes when | Fails when | Switch |
|---|---|---|---|---|
| A | `backup_jobs` | At least `MIN_COMPLETED_BACKUP_JOBS` backup jobs completed in the last `BACKUP_LOOKBACK_DAYS`, and none ended in `BACKUP_FAILED_STATES` | A job FAILED / ABORTED / EXPIRED / PARTIAL, or too few completed jobs | `CHECK_BACKUP_JOBS` |
| B | `backup_freshness` | Every protected resource has a last backup newer than `MAX_BACKUP_AGE_HOURS` | A resource's last backup is older (RPO breached) | `CHECK_BACKUP_FRESHNESS` |
| C | `restore_tests` | At least `MIN_RESTORE_TESTS` restores completed in the last `RESTORE_LOOKBACK_DAYS`, none failed, validation not failed, every type in `REQUIRED_RESTORE_RESOURCE_TYPES` restored | Too few restore tests, a failed restore or validation, a required type never restored | `CHECK_RESTORE_TESTS` |
| D | `restore_time` | Every completed restore took at most `MAX_RESTORE_MINUTES` | A restore took longer (RTO breached) | `CHECK_RESTORE_TIME` |
| E | `offsite_copy` (off by default) | Every resource backed up in the period has a completed copy to another region or account | A resource has no off-site copy | `CHECK_OFFSITE_COPY` |

Each check produces one item per thing it looks at (backup job, resource, restore job) plus one summary item. A check with nothing to look at (no protected resources, no restore to measure) fails through its summary item instead of silently passing. That is why a run reports, for example, "4 checks, 145 items checked".

**PASSED:** every enabled check passes in every region.
**FAILED:** any item fails, or nothing matched the scope. The conclusion lists the failing items.
**ERROR (nothing written):** credentials, permissions or network problem. The audit stays open, the reason is in STDERR and eramba emails the administrators.

## 3. Coverage of the audit methodology

| Source | Requirement | Covered | How | Variables |
|---|---|---|---|---|
| Control | Backup job completion report for the period with status, size and failures | ✅ | A: every job with status, size and error message in the evidence CSV | `BACKUP_LOOKBACK_DAYS` |
| Control | Confirm no backup jobs have failed silently | ✅ | A: every failed job fails the audit, even if a later job succeeded | `BACKUP_FAILED_STATES`, `IGNORE_RECOVERED_FAILURES` |
| Control | Restore test record: data restored, date, time to restore | ✅ | C/D: source resource, type, date, duration and validation of each restore | `RESTORE_LOOKBACK_DAYS` |
| Control | Restore test for a representative sample of critical systems | ⚠️ Partial | C: minimum number of tests and required resource types. What is "representative" is your decision | `MIN_RESTORE_TESTS`, `REQUIRED_RESTORE_RESOURCE_TYPES` |
| Control | Restore time meets defined RTO | ✅ | D: duration of each restore vs your RTO | ⚠️ `MAX_RESTORE_MINUTES` |
| Control · Policy | "Verify restore capability is consistent with defined RTO and RPO commitments" | ✅ | B: age of the last backup of each protected resource vs your RPO; D: restore duration vs your RTO | ⚠️ `MAX_BACKUP_AGE_HOURS`, `MAX_RESTORE_MINUTES` |
| Control | Weekly backup review | ⚠️ Partial | Every week's jobs are reviewed, but once a month (AWS keeps 30 days of job history; eramba audit dates are day/month). For a weekly cadence, review AWS Backup notifications weekly | `BACKUP_LOOKBACK_DAYS` |
| Control | Restore tests at minimum quarterly | ✅ | C: restores of the last 90 days. AWS does not document how long restore jobs stay listed: if your tests are less frequent than monthly, confirm older restores still appear | `RESTORE_LOOKBACK_DAYS` |
| Policy | "Execute backups per the defined schedule" · "Verify backup job completion and log results" | ✅ | A + B | — |
| Policy | "Perform a test restore on a defined periodic basis" | ✅ | C | `RESTORE_LOOKBACK_DAYS`, `MIN_RESTORE_TESTS` |
| Policy | "Document restore test results and retain records" | ✅ | Evidence CSV attached to every audit | `ATTACH_EVIDENCE` |
| Policy | "Confirm backup media is stored securely and access is restricted" | ❌ Manual | Vault access policies, Vault Lock and KMS keys are not evaluated | — |
| Policy | "Confirm at least one backup copy is stored off-site or in a geographically separate location" | ✅ Optional | E: copy jobs to another region or account | `CHECK_OFFSITE_COPY` |
| Control | Resources that should be backed up but never were | ❌ Manual | AWS Backup only knows resources it protected at least once. Review backup plan coverage (e.g. AWS Backup Audit Manager) | — |
| Control | Document failures and remediation | ⚠️ Partial | Failures are documented automatically; remediation is up to the control owner | — |

## 4. Before you start

- [ ] eramba Enterprise with a free automation slot (5 per instance).
- [ ] Backups are managed with **AWS Backup** (EBS, EC2, RDS, Aurora, EFS, FSx, DynamoDB, S3…). Snapshots made outside AWS Backup are not seen.
- [ ] For checks C/D: restores are done with AWS Backup, ideally with a **restore testing plan** (AWS Backup › Restore testing) that runs them automatically. Without restore tests, check C fails, as the control requires.
- [ ] You know your **RPO** (hours) and **RTO** (minutes) and the **regions** where your backups are.
- [ ] You can create IAM users and roles in the AWS account.
- [ ] eramba can reach `backup.<region>.amazonaws.com`, `sts.<region>.amazonaws.com` and, if you filter by tags, `tagging.<region>.amazonaws.com` on port 443.

## 5. Setup on AWS

eramba connects from outside AWS and cannot run credential helpers, so AWS's temporary-credential methods for external workloads (IAM Roles Anywhere, SAML, web identity) are not available. The most secure option is: **a user whose access key can only assume a read-only role, protected by an External ID**. Every AWS call then uses 15-minute temporary credentials.

Choose an External ID first: any long random text, e.g. `eramba-backup-` followed by 10 random characters. You will need it in step 5.1 and in the variables (§7).

### 5.1 Create the read-only role

1. AWS console › **IAM › Roles › Create role**.
2. **Trusted entity type: AWS account** › **This account** › tick **Require external ID** and enter your External ID. Do not tick *Require MFA*. **Next**.
3. **Add permissions:** select nothing. **Next**.
4. **Role name:** `eramba-backup-audit` › **Create role**.
5. Open the role and copy its **ARN** (`arn:aws:iam::<account-id>:role/eramba-backup-audit`).
6. Tab **Permissions › Add permissions › Create inline policy › JSON**, replace the content with the policy below (use your regions), **Next**, name `eramba-backup-read`, **Create policy**.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "backup:ListBackupJobs",
        "backup:ListProtectedResources",
        "backup:ListRestoreJobs",
        "backup:ListCopyJobs",
        "tag:GetResources"
      ],
      "Resource": "*",
      "Condition": { "StringEquals": { "aws:RequestedRegion": ["eu-west-1"] } }
    }
  ]
}
```

AWS Backup list actions do not support resource-level permissions, so `"Resource": "*"` is required. They return job metadata only, never backup data.

### 5.2 Minimum permissions

| Principal | Permission | Why |
|---|---|---|
| Role `eramba-backup-audit` | `backup:ListBackupJobs` | Check A |
| | `backup:ListProtectedResources` | Check B |
| | `backup:ListRestoreJobs` | Checks C, D |
| | `backup:ListCopyJobs` | Check E only (can be removed) |
| | `tag:GetResources` | Only if you use `RESOURCE_TAGS` (can be removed) |
| User `eramba-automation` | `sts:AssumeRole` on the role | Nothing else |

### 5.3 Create the user and its access key

1. **IAM › Users › Create user** › name `eramba-automation` › leave *console access* unticked › **Next** › attach nothing › **Create user**.
2. Open the user › **Add permissions › Create inline policy › JSON**, paste the policy below with the role ARN from 5.1, name `eramba-assume-backup-audit`, **Create policy**.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": "sts:AssumeRole",
      "Resource": "arn:aws:iam::<account-id>:role/eramba-backup-audit"
    }
  ]
}
```

3. Tab **Security credentials › Create access key** › **Application running outside AWS** › **Create access key**. Keep the *Access key* and *Secret access key* for step 6 (the secret is shown only once).

### 5.4 Optional hardening

Once it works, restrict the role to this user and, if eramba has a fixed public IP, to that IP: role › **Trust relationships › Edit trust policy**:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Principal": { "AWS": "arn:aws:iam::<account-id>:user/eramba-automation" },
      "Action": "sts:AssumeRole",
      "Condition": {
        "StringEquals": { "sts:ExternalId": "<your External ID>" },
        "IpAddress": { "aws:SourceIp": ["<eramba public IP>/32"] }
      }
    }
  ]
}
```

> This JSON goes **only** in *Trust relationships*. Pasted in a permissions policy editor, AWS rejects it ("Principal not supported").

Rotate the access key at least every 90 days (create a new key, update the secrets in eramba, delete the old key).

## 6. Setup in eramba

Step-by-step guide: [Installing an automation](../../docs/installing.md). Values for this automation:

**Secrets** (Settings › Application Configuration › Automation Secrets):

| Secret name | Value |
|---|---|
| `aws_access_key_id` | Access key of `eramba-automation` (`AKIA…`, 20 characters) |
| `aws_secret_access_key` | Secret access key of `eramba-automation` (40 characters) |

**Automation** (Control Catalog › Internal Controls › Audits › ⋮ › Automation):

| Field | Value |
|---|---|
| Name | `AWS Backup – Backup Execution and Restore Test` |
| Timeout | `60` (add ~20 s per extra region, max 240) |
| Composer packages | `aws/aws-sdk-php:^3.300` |
| Code | `run.php`, then set at least the variables marked ⚠️ in §7 |

**Control** *Backup Execution and Restore Test*: Audits required · 12 audit dates, one per month · Audit execution: **Automated** · select this automation (only this one) · keep the Audit Methodology in the control description · optionally link the policy *Business Continuity* for traceability.

## 7. Variables

Variables marked ⚠️ must be reviewed on every installation.

**Connection**

| Variable | Default | Description |
|---|---|---|
| ⚠️ `AWS_ROLE_ARN` | `''` | ARN of the role from §5.1. Empty = use the access key directly (not recommended) |
| ⚠️ `AWS_EXTERNAL_ID` | `''` | Your External ID |
| ⚠️ `REGIONS` | `['eu-west-1']` | Regions with backups, e.g. `['eu-west-1', 'eu-central-1']`. Must match the role policy |

**Scope** (applies to every check)

| Variable | Default | Description |
|---|---|---|
| `RESOURCE_TYPES` | `[]` (all) | AWS Backup resource types, e.g. `['RDS', 'EBS', 'EFS']` |
| `RESOURCE_ARN_REGEX` | `'/.*/'` (all) | PHP regex on the resource ARN, e.g. `'/:db:prod-/'` |
| `RESOURCE_TAGS` | `[]` (no filter) | Tag key ⇒ regex on value, all must match, e.g. `['Env' => '/^prod$/']`. Needs `tag:GetResources` |

**A – Backup jobs**

| Variable | Default | Description |
|---|---|---|
| `CHECK_BACKUP_JOBS` | `true` | Run check A |
| `BACKUP_LOOKBACK_DAYS` | `30` | Days of backup jobs reviewed (also used by check E). AWS returns at most 30 days; match it to your audit frequency |
| `BACKUP_FAILED_STATES` | `['FAILED', 'ABORTED', 'EXPIRED', 'PARTIAL']` | Job states that fail the audit |
| `IGNORE_RECOVERED_FAILURES` | `false` | `true` ignores a failure when a later job for the same resource completed |
| `MIN_COMPLETED_BACKUP_JOBS` | `1` | Minimum completed jobs per region in the period |

**B – Backup freshness (RPO)**

| Variable | Default | Description |
|---|---|---|
| `CHECK_BACKUP_FRESHNESS` | `true` | Run check B |
| ⚠️ `MAX_BACKUP_AGE_HOURS` | `24` | Your RPO: maximum age of each resource's last backup. `168` for weekly backups |

**C/D – Restore tests and RTO**

| Variable | Default | Description |
|---|---|---|
| `CHECK_RESTORE_TESTS` | `true` | Run check C |
| `RESTORE_LOOKBACK_DAYS` | `90` | Days of restore jobs reviewed |
| `MIN_RESTORE_TESTS` | `1` | Minimum completed restores per region in the period |
| `ONLY_RESTORE_TESTING_PLANS` | `false` | `true` counts only restores made by AWS Backup restore testing plans |
| `REQUIRED_RESTORE_RESOURCE_TYPES` | `[]` | Types that must have at least one completed restore, e.g. your critical systems `['RDS']` |
| `CHECK_RESTORE_TIME` | `true` | Run check D |
| ⚠️ `MAX_RESTORE_MINUTES` | `240` | Your RTO in minutes |

**E – Off-site copy**

| Variable | Default | Description |
|---|---|---|
| `CHECK_OFFSITE_COPY` | `false` | Run check E. Needs copy rules in your backup plans |

**Output**

| Variable | Default | Description |
|---|---|---|
| `ATTACH_EVIDENCE` | `true` | Attach the evidence CSV and the configuration used |
| `RESULT_PASSED_ID` / `RESULT_FAILED_ID` | `2` / `1` | Audit result option ids. Change only if you use custom results |
| `MAX_LOG_ITEMS` | `20` | Maximum failures listed in the output and conclusion (all are in the CSV) |

## 8. Results in eramba

| Where | What |
|---|---|
| Audit Result | Passed / Failed |
| Audit Conclusion | Result, one line per check with counts, failing items |
| Start / End date | Date of the run |
| Comment | `[aws-backup-jobs-restore-tests v0.2.0] PASSED/FAILED` with `evidence-aws-backup-jobs-restore-tests-<date>.csv` (one row per job, resource and restore checked) and `config-aws-backup-jobs-restore-tests-<date>.txt` (variables used) |
| Automation Logs | Full output of every run |

Real run (eramba 3.31.0, one region with daily EBS backups and no restore tests yet; account ID masked):

```text
aws-backup-jobs-restore-tests v0.2.0 — audit #1174

[13:39:17] STEP 1: Checking configuration
  Secrets present, AWS SDK 3.398.0 loaded.
  Regions: eu-west-1. Checks: backup_jobs, backup_freshness, restore_tests, restore_time.

[13:39:17] STEP 2: Collecting data from AWS Backup
  Assumed role arn:aws:iam::111111111111:role/eramba-backup-audit (temporary credentials, 15 min).
  ── Region eu-west-1
  Backup jobs (last 30 days): 136 in scope, 136 completed, 0 failed.
  Protected resources: 5 in scope, 0 without a backup in the last 24 h.
  Restore jobs (last 90 days): 0 in scope, 0 completed.

[13:39:19] STEP 3: Evaluating
Automated audit by aws-backup-jobs-restore-tests v0.2.0 on 2026-09-25 13:39 UTC.
Result: FAILED. 4 checks, 145 items checked, 143 passed, 2 failed.
  backup_jobs       OK (137/137 items ok)
  backup_freshness  OK (6/6 items ok)
  restore_time      FAILED (0/1 items ok)
  restore_tests     FAILED (0/1 items ok)
- [restore_time] eu-west-1 restores within RTO: No completed restore in the period to measure against the RTO
- [restore_tests] eu-west-1 completed restore tests: 0 completed restore jobs in 90 days, minimum 1

[13:39:19] STEP 4: Writing result to eramba
  Evidence uploaded: evidence-aws-backup-jobs-restore-tests-2026-09-25.csv
  Evidence uploaded: config-aws-backup-jobs-restore-tests-2026-09-25.txt
  Audit result and conclusion saved.
  Comment added with the evidence attached.

Done.
```

All 136 backup jobs of the last 30 days completed and every resource has a recent backup, but no restore test was done in the last 90 days, so the RTO cannot be demonstrated either: the audit fails, as the control requires. Start an AWS Backup restore testing plan, or set `CHECK_RESTORE_TESTS = false` and `CHECK_RESTORE_TIME = false` if restore tests are audited manually.

Evidence CSV (first rows):

```text
check,region,resource,result,detail
backup_jobs,eu-west-1,"completed backup jobs",PASS,"35 completed backup jobs, minimum 1"
backup_jobs,eu-west-1,arn:aws:ec2:eu-west-1:111111111111:volume/vol-0d81…,PASS,"Backup job BC626897-… COMPLETED on 2026-09-25 08:02, 30,720.0 MB"
```

## 9. Troubleshooting

| Message (STDERR) | Cause | Fix |
|---|---|---|
| `Secret 'aws_access_key_id' is missing` | Secret not created or named differently | Create `aws_access_key_id` / `aws_secret_access_key` exactly (§6) |
| `AWS SDK not loaded` | Composer package missing | Add `aws/aws-sdk-php:^3.300` to the composer field |
| `AWS InvalidClientTokenId` / `SignatureDoesNotMatch` | Wrong, inactive or deleted access key | Create a new key and update both secrets |
| `AWS AccessDenied AssumeRole` | Wrong role ARN or External ID, user lacks `sts:AssumeRole`, or trust policy IP condition | Check `AWS_ROLE_ARN`, `AWS_EXTERNAL_ID`, §5.3 step 2 and §5.4 |
| `AWS AccessDeniedException List…` + `HINT: AWS_ROLE_ARN is empty` | The role is not configured, so the script used the key directly | Set `AWS_ROLE_ARN` and `AWS_EXTERNAL_ID` (§7) |
| `AWS AccessDeniedException List…` + `HINT: compare the IAM permissions` | Action missing in the role policy, or region not in `aws:RequestedRegion` | Compare with §5.1 step 6 and `REGIONS` |
| Run stops without output / timeout | Too many regions or jobs for the timeout | Increase the timeout (max 240) or reduce `REGIONS` |
| `eramba upload … failed: validation.mimes` | eramba rejected the attachment | Set `ATTACH_EVIDENCE = false` and report it |
| `eramba edit audit failed: … Validation error` | Custom audit result options | Check `RESULT_PASSED_ID` / `RESULT_FAILED_ID` |
| Result FAILED: "Nothing matched the configured scope" | Scope variables exclude everything | Review `REGIONS`, `RESOURCE_TYPES`, `RESOURCE_ARN_REGEX`, `RESOURCE_TAGS` |

## 10. Customising

| I want to… | Change |
|---|---|
| Audit only production | `RESOURCE_TAGS = ['Env' => '/^prod$/']` or a naming pattern in `RESOURCE_ARN_REGEX` |
| Audit only databases | `RESOURCE_TYPES = ['RDS', 'Aurora', 'DynamoDB']` |
| Weekly backups instead of daily | `MAX_BACKUP_AGE_HOURS = 168` |
| Quarterly audits instead of monthly | Not recommended: only the last 30 days of backup jobs are visible. Keep monthly audits, or accept that each audit covers only the last month |
| Tolerate a failed job that was retried successfully | `IGNORE_RECOVERED_FAILURES = true` |
| Require restore tests of my critical systems | `REQUIRED_RESTORE_RESOURCE_TYPES = ['RDS', 'EFS']`, `ONLY_RESTORE_TESTING_PLANS = true` |
| Audit restore tests manually | `CHECK_RESTORE_TESTS = false`, `CHECK_RESTORE_TIME = false` |
| Verify the off-site copy | `CHECK_OFFSITE_COPY = true` |
| Several AWS accounts | One role per account, and per account one automation linked to its own control (e.g. *Backup Execution and Restore Test – Production account*). Mind the limit of 5 automations |
| Add a check | Add a block in `collectResults()` that appends `result('my_check', …)` items; evaluation and reporting pick it up |

Other automations for the same control:

| Technology | Automation |
|---|---|
| Azure Backup | *planned* |

## 11. Removing

1. In eramba: [Installing › Removing an automation](../../docs/installing.md#removing-an-automation).
2. In AWS: delete the user `eramba-automation` (this deletes its access keys and inline policy) and the role `eramba-backup-audit`.

## 12. Changelog

| Version | Date | Change |
|---|---|---|
| 0.2.0 | 2026-09-25 | Every enabled check always reports a summary item (a check with nothing to verify fails). Default `BACKUP_LOOKBACK_DAYS` 30 (AWS job history limit) and monthly audits. Warning when `AWS_ROLE_ARN` is empty. Secret error shows the name to create. Missing timestamps no longer pass the RTO check |
| 0.1.1 | 2026-09-25 | Clearer summary ("N checks, N items checked"); README restructured |
| 0.1.0 | 2026-09-25 | First version. Checks A–E. Tested on eramba 3.31.0 against a real AWS Backup account and with simulated responses for failure scenarios |

---

*eramba does not provide support for developing or troubleshooting custom automation code. Test in a non-production instance before use.*
