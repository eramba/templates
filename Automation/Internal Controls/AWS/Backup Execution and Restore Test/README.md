---
# ─── Metadata: keep in sync with run.php. Used to build the catalogue. ───
id: aws-backup-jobs-restore-tests
name: Backup Execution and Restore Test
version: 0.3.1
status: draft                            # draft | tested | stable | deprecated
technology: AWS Backup
vendor: AWS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Backup Execution and Restore Test
policies:
  - Business Continuity > Backup Execution and Verification
audit_frequency: at_least_weekly
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
  - BACKUP_LOOKBACK_DAYS
  - MIN_COMPLETED_BACKUP_JOBS
  - RESTORE_LOOKBACK_DAYS
  - REQUIRED_RESTORE_RESOURCE_ARNS
  - ONLY_RESTORE_TESTING_PLANS
  - MAX_RPO_HOURS
  - MAX_RESTORE_MINUTES
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - aws/aws-sdk-php:^3.398
timeout_seconds: 60
eramba_version_tested: null
last_tested: null
---

# Backup Execution and Restore Test

**Technology:** AWS Backup.

Reviews backup jobs and restore tests with ready-to-run defaults for the Backup Execution and Restore Test control. Saves the audit result, conclusion and evidence in eramba.

| | |
|---|---|
| **Control** | Backup Execution and Restore Test |
| **Technology** | AWS Backup |
| **Schedule** | At least weekly; restore evidence covers the last 90 days |
| **Version** | 0.3.1 — candidate for validation |
| **Validation** | Validate this version and its audit calendar in your AWS/eramba installation before use |
| **Default execution** | Live: saves results and evidence |
| **Default RPO / RTO** | 24 hours / 240 minutes |
| **Default restore scope** | All AWS Backup protected resources in the configured regions; no ARN list required |

**Setup:** create permissions and secrets (§5–6), select your regions and paste `run.php`. Defaults discover all protected resources and use RPO 24 hours / RTO 240 minutes. Adjust these reference objectives to your organisation.

## 1. Controls and policies

The test implements the **Audit Methodology** of *Backup Execution and Restore Test* in the [internal control templates](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Its linked policy is *Business Continuity › Backup Execution and Verification*. The policy provides traceability; the methodology defines the test requirements.

**Schedule:** the methodology requires weekly backup review. With eramba's recurring day/month calendar, configure **1, 8, 15, 22 and 28 in each month**. These 60 dates have a maximum gap of seven days, including leap years. Verify that your installation saves all dates and generates the expected audits before activation. Each live execution completes one audit, adds one comment and uploads two attachments. Review notification settings for this volume.

Keep the default 30-day backup window so consecutive reviews overlap. A monthly retrospective review does not fulfil the weekly frequency. Restore tests use a rolling 90-day window, a conservative interpretation of at least quarterly. A missed execution requires follow-up: the script does not verify historical scheduler operation.

## 2. What it checks

All checks below are mandatory. Configuration controls the scope, sample, periods and recovery objectives.

| Check | Passing condition |
|---|---|
| `backup_jobs` | The configured backup scope has at least `MIN_COMPLETED_BACKUP_JOBS` completed jobs in total. No returned job has a failed, aborted, expired, partial or unrecognised state. |
| `restore_tests` | Completed tests exist for the selected sample; no selected restore has a failed or unrecognised status, failed/pending validation, or unusable source identity. |
| `restore_scope` | The automatically discovered population or optional explicit sample is nonempty |
| `restore_sample` | Every required source ARN has at least one completed restore with valid timestamps, acceptable validation and RTO/RPO within your objectives. |
| `restore_time` | Every completed restore evaluated for the sample has a valid duration no greater than `MAX_RESTORE_MINUTES`. |
| `restore_rpo` | Every completed restore evaluated for the sample has a valid recovery-point age no greater than `MAX_RPO_HOURS`. |

Backup review covers resources matching the scope filters. With the default empty `REQUIRED_RESTORE_RESOURCE_ARNS`, restore evaluation discovers all in-scope protected resources using [ListProtectedResources](https://docs.aws.amazon.com/aws-backup/latest/APIReference/API_ListProtectedResources.html). This covers the whole discovered population without requiring you to choose a sample before the first run. Each discovered resource needs a qualifying restore; the population is not selected from successful restores.

Discovery cannot include systems never protected by AWS Backup. An optional ARN sample replaces discovery; every member remains required, even when excluded by filters. Empty scope fails. Resource and backup minima apply across regions, not per region.

A returned restore without `SourceResourceArn` cannot be attributed to the sample. It is retained as a failed evidence item, even if another restore succeeds; it does not abort the run or credit any sample member. With `ONLY_RESTORE_TESTING_PLANS=true`, manual restores are excluded before this check.

Known in-progress jobs are recorded but do not count as completed jobs. Unknown states fail the corresponding evidence item. Backup failures remain failures even if a later retry succeeds. Composite parent jobs are excluded to avoid counting their child jobs twice. Missing backup size is recorded as unavailable, never zero.

**RPO measurement:** `(restore CreationDate − RecoveryPointCreationDate) / 3600`. The restore creation time is the reference instant for this test. This measures the age of the recovery point selected for the restoration, not continuous RPO compliance over the review period. Missing dates, a point newer than the restore, or a future restore creation time fail the check. These fields are documented by [AWS](https://docs.aws.amazon.com/aws-backup/latest/APIReference/API_RestoreJobsListMember.html).

**RTO measurement:** `(CompletionDate − CreationDate) / 60`. This measures the AWS restore job duration, not application readiness. Missing, reversed or future timestamps fail the check. An absent AWS validation status is reported as such; the script does not perform an application-level data-integrity test.

## 3. Coverage of the audit methodology

| Methodology requirement | Implementation |
|---|---|
| Backup completion report: status, size and failures | CSV row for every returned in-scope non-parent backup job; unavailable sizes explicitly marked |
| Confirm no backup jobs failed silently | Failed and unrecognised states fail the audit; recovered failures remain in evidence |
| Restore record: data restored, date and time | Source ARN, restore job ID, creation/completion times and duration |
| Restore record: comparison against RPO | Recovery-point timestamp, restore creation timestamp, calculated age and configured RPO |
| Representative sample of critical systems | By default, test the entire discovered population. Optionally replace it with an approved explicit sample; every required resource must have a qualifying restore |
| Restore time meets RTO | Duration compared with the configured RTO for each completed sample restore |
| Document failures and restore results | Conclusion, CSV evidence, configuration attachment and comment |
| Weekly backup review | Configure and verify the schedule in §1; monitor missed/failed executions |
| Restore tests at least quarterly | A qualifying restore for each sample member in the rolling 90-day window |

Automatic scope is refreshed on every run. If you supply an explicit sample, review it when critical systems change. The script checks existing AWS Backup evidence; it does not perform backups or launch restore tests. Restore evidence must remain available for the selected window. Policy-only requirements such as off-site copies, vault access and encryption are outside this audit's test methodology.

## 4. Before you start

- eramba Enterprise with an available automation slot.
- Backups and restore tests managed by AWS Backup.
- Connection details and regions. Restore scope is discovered automatically; an explicit sample is optional.
- Default RPO of 24 hours and RTO of 240 minutes, adjustable to your agreed recovery objectives.
- Read-only AWS permissions listed in §5.2.
- Connectivity to `backup.<region>.amazonaws.com`, `sts.<region>.amazonaws.com` and, when using tag filters, `tagging.<region>.amazonaws.com` on HTTPS.
- A test control/audit for installation validation before enabling scheduled execution.

## 5. Setup on AWS

This example authenticates with **a dedicated user whose access key can only assume a read-only role, using an External ID**. Every AWS call then uses 15-minute temporary credentials.

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
| Role `eramba-backup-audit` | `backup:ListBackupJobs` | Backup job report |
| | `backup:ListProtectedResources` | Discover the default restore population; not a backup-freshness check. Can be removed only when using an explicit ARN sample |
| | `backup:ListRestoreJobs` | Restore sample, RTO and RPO |
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

Follow [Installing an automation](../../docs/installing.md), using these values:

| Secret name | Value |
|---|---|
| `aws_access_key_id` | Access key of the dedicated IAM user |
| `aws_secret_access_key` | Its secret access key |

| Automation field | Value |
|---|---|
| Name | Backup Execution and Restore Test |
| Timeout | 60 seconds initially; allow more for additional regions, maximum 240 seconds |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Code | Full content of `run.php` |
| Recurrent Automation | Off; execute on the control's planned audit dates |

Set regions and optional role in §7. For an initial simulation use `DRY_RUN=true`; restore `false` for scheduled execution. On a test audit, verify result, dates, conclusion, comment and both attachments. Validate the weekly calendar (§1) before activation and retain the methodology in the control description.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `AWS_ROLE_ARN` | `''` | Set the read-only role ARN. Empty uses the key directly; the recommended user's key only has permission to assume the role |
| `AWS_EXTERNAL_ID` | `''` | External ID from the role trust policy |
| `REGIONS` | `['eu-west-1']` | Regions containing the backup scope and all sampled resources |
| `RESOURCE_TYPES` | `[]` | Optional AWS resource-type filter for backups and sampled restores |
| `RESOURCE_ARN_REGEX` | `'/.*/'` | Optional source-resource ARN filter |
| `RESOURCE_TAGS` | `[]` | Tag key → regular expression; all filters must match. Requires `tag:GetResources` |
| `BACKUP_LOOKBACK_DAYS` | `30` | Integer from 7 to 30; keep overlapping history with weekly executions |
| `RESTORE_LOOKBACK_DAYS` | `90` | Integer from 1 to 90; shorter windows require more frequent restore tests |
| `MIN_COMPLETED_BACKUP_JOBS` | `1` | Minimum completed backups across all configured regions |
| `REQUIRED_RESTORE_RESOURCE_ARNS` | `[]` | Default: automatically discover all protected resources in scope. Optional explicit sample: Example: `['arn:aws:rds:eu-west-1:111111111111:db:critical-db']` |
| `ONLY_RESTORE_TESTING_PLANS` | `false` | Set true to use only restores made by AWS restore testing plans |
| `MAX_RPO_HOURS` | `24` | Reference RPO in hours; adjust to your agreed objective; positive decimals accepted |
| `MAX_RESTORE_MINUTES` | `240` | Reference RTO in minutes; adjust to your agreed objective; positive decimals accepted |
| `DRY_RUN` | `false` | Set true only for an explicit simulation; no audit writes occur |
| `RESULT_PASSED_ID` | `2` | Passed option ID in your installation |
| `RESULT_FAILED_ID` | `1` | Failed option ID; must differ from Passed |
| `MAX_LOG_ITEMS` | `20` | Maximum failures listed in the conclusion; the CSV retains all items |

The mandatory checks and evidence attachments have no on/off switches. Different test requirements require an explicitly different methodology and implementation.

## 8. Results in eramba

A live run saves Passed/Failed, the UTC execution dates and a conclusion, then adds a comment linking two attachments:

- `evidence-<automation-id>-<date>.csv`: observations, check results and reasons.
- `config-<automation-id>-<date>.txt`: settings used, without credentials.

A failed control is a completed audit. A collection error saves no result. Uploads can remain if saving fails; a comment failure can leave the result already saved. Inspect the audit before retrying.

`DRY_RUN=true` displays the outcome without uploads, edits or comments.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Invalid configuration or audit context | Check §7 and run against an internal-control audit. |
| Missing secret / SDK | Check secret names and Composer package in §6. |
| AWS authentication or AccessDenied | Check credentials, role trust, External ID and §5.2 permissions. |
| Timeout, collection/evidence limit or pagination error | Check connectivity; split a large scope into separate controls. No partial result is saved. |
| Missing, stale or unknown evidence | Inspect the CSV and required checks in §2–3; missing evidence cannot pass. |
| eramba upload/edit/comment error | Inspect the audit before retrying; see §8 for possible partial writes. |
| Simulation completes without a result | Set `DRY_RUN=false` for scheduled execution. |

## 10. Customising

| Goal | Configuration |
|---|---|
| Audit production backups only | Set `RESOURCE_TAGS` or `RESOURCE_ARN_REGEX`; ensure the sample remains in scope |
| Use an approved sample instead of all protected resources | Set `REQUIRED_RESTORE_RESOURCE_ARNS` to the approved source ARNs; leave empty for automatic full-population discovery |
| Use automated restore tests only | Set `ONLY_RESTORE_TESTING_PLANS=true` |
| Apply your recovery objectives | Set `MAX_RPO_HOURS` and `MAX_RESTORE_MINUTES` |
| Cover multiple regions | Add `REGIONS`; each sample ARN is required once, in its own region |
| Require more frequent restore tests | Shorten `RESTORE_LOOKBACK_DAYS` |
| Use several AWS accounts | Install separately per account and control; each script uses one credential context |

## 11. Removing

Follow [Removing an automation](../../docs/installing.md#removing-an-automation), then remove the dedicated AWS user's access keys and the read-only role if they are no longer used.

## 12. Changelog

| Version | Date | Change |
|---|---|---|
| 0.3.1 | 2026-09-27 | Ready-to-run test defaults: automatic restore population, RPO 24 hours, RTO 240 minutes; explicit sampling remains optional |
| 0.3.0 | 2026-09-25 | Adds restore-test RPO and explicit ARN sample; removes policy-only checks and mandatory-check switches; evaluates sample coverage across regions; retains invalid records as failed evidence; adds explicit simulation; documents weekly execution and recovery objectives |
| 0.2.0 | 2026-09-25 | Summary items for empty checks; 30-day backup history; missing timestamp handling |
| 0.1.1 | 2026-09-25 | Clearer summaries and README structure |
| 0.1.0 | 2026-09-25 | Initial AWS Backup integration; tested on eramba 3.31.0 |

*eramba does not provide support for developing or troubleshooting custom automation code. Validate the automation in a test installation before production use.*
