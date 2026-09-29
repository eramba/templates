---
id: aws-workspaces-encryption
name: Endpoint Encryption Compliance Review
version: 0.1.0
status: draft
vendor: AWS
technology: Amazon WorkSpaces Personal and AWS KMS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Endpoint Encryption Compliance Review
policies:
  - Endpoint Security > Endpoint Configuration Review
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - REGIONS
  - MAX_WORKSPACES
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - aws/aws-sdk-php:^3.398
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Endpoint Encryption Compliance Review

**Technology:** Amazon WorkSpaces Personal and AWS KMS. **Status:** draft; real AWS/eramba validation pending.

Check encryption on both root and user volumes of WorkSpaces Personal virtual desktops and verify the referenced KMS key metadata. This integration applies to an agreed WorkSpaces desktop scope; it does not assess physical laptops, mobile devices or ordinary EC2 servers.

## 1. Controls and policies

Source: **Endpoint Encryption Compliance Review**, identified by title in [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). The methodology requires an endpoint encryption report, full-disk encryption for sensitive-data endpoints, key management, and documented exceptions with compensating controls. No separate success criteria are supplied. The policy mapping adds no checks.

Schedule quarterly: January 1, April 1, July 1 and October 1.

## 2. What it checks

| Check | Evidence |
|---|---|
| Population | All WorkSpaces returned in selected regions, without filtering by encryption or user. Only terminated records are excluded. |
| Encryption | `RootVolumeEncryptionEnabled` and `UserVolumeEncryptionEnabled` must both be true. |
| Key management | Referenced key resolves to matching KMS metadata: enabled, symmetric encryption key, managed by AWS or the account. |
| Exceptions | Missing evidence and unencrypted desktops remain pending for exception/compensating-control review. |

Passed requires a nonempty population with complete positive evidence. Unknown lifecycle states and empty scope remain pending. API errors abort without evaluating partial evidence. Stopped or unhealthy desktops remain in scope because their volumes can still contain sensitive data.

## 3. Coverage

Treat every discovered WorkSpaces Personal desktop as potentially processing sensitive data. Confirm selected accounts/regions and reconcile the control's desktop inventory before relying on discovery. WorkSpaces Pools, Secure Browser, physical client devices, deleted desktops and other platforms need separate coverage. Do not use this automation alone for an enterprise-wide endpoint control.

WorkSpaces uses EBS volume encryption with AWS KMS. This is storage encryption evidence, not guest BitLocker policy. The script reads the actual desktop volume flags rather than inferring encryption from an account default. Key metadata establishes KMS management; it does not independently audit every IAM/key-policy permission, prove successful recovery or impose extra rotation requirements.

## 4. Before you start

You need WorkSpaces Personal desktops, eramba Enterprise, a PHP 8.4-compatible runner and a disposable audit. If your account has no WorkSpaces, this integration is not applicable. No desktops or encryption settings are created or changed.

## 5. Setup on AWS

Create a dedicated read-only identity. Grant discovery and key metadata access; scope key resources to the keys used by your desktops where practical. KMS key policies must also permit metadata access.

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {"Effect": "Allow", "Action": "workspaces:DescribeWorkspaces", "Resource": "*"},
    {"Effect": "Allow", "Action": "kms:DescribeKey", "Resource": "*"}
  ]
}
```

No decrypt, encrypt, data-key generation or key export permissions are required. Store credentials only in eramba Secrets. Uses the [official AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html), verified HTTPS, bounded requests and SDK retries. Allow the regional WorkSpaces and KMS endpoints. Audit the resolved Composer dependencies before installation.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md). Add `aws_access_key_id` and `aws_secret_access_key`, Composer `aws/aws-sdk-php:^3.398`, [run.php](run.php) and a 240-second timeout. Set regions. Test with `DRY_RUN=true`, then restore `false` and validate a disposable audit before scheduling.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `REGIONS` | `['eu-west-1']` | 1–20 unique deployment regions; set the complete agreed desktop scope. |
| `MAX_WORKSPACES` | `200` | 1–1000 records across all regions, including terminated records; excess aborts. |
| `DRY_RUN` | `false` | True suppresses every eramba write. |
| `RESULT_PASSED_ID` | `2` | Verify the Passed option in your installation. |
| `RESULT_FAILED_ID` | `1` | Common reporting setting; unresolved encryption cases remain pending in this evaluator. |
| `MAX_LOG_ITEMS` | `5` | 1–10 finding examples in the conclusion. |

Collection is limited to 100 pages per regional query, approximately 190 seconds plus a bounded active request, and 600 KB of evidence. Requests use 15-second timeouts and one SDK retry. Repeated keys are read once per region. Exceeding a limit never truncates into a pass.

## 8. Results

Uploads CSV observations and TXT settings. Evidence contains desktop IDs, encryption flags and key ARNs/states; no usernames, hostnames, IP addresses or key material. Pending saves a comment and attachments without editing the result or execution dates. It does not clear prior results or schedule retries; use a fresh audit. A complete pass saves result/dates and a comment.

Writes are not transactional. Inspect partial writes before retrying; reruns can duplicate comments or attachments. Dry-run writes nothing.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Empty population | Check applicability, regions and identity scope. |
| Pending encryption | Review the affected desktop and any documented exception with compensating controls. |
| Missing/disabled key | Check the desktop key reference and KMS lifecycle; do not expose key material. |
| AWS access denied | Check IAM and key policies; missing permissions are technical errors. |
| Duplicate, incomplete page or resource limit | Retry transient collection failures or split a genuinely separate control scope; no partial result is saved. |
| Persistence failure | Inspect the audit before retrying. |

## 10. Customising

Extend discovery only when additional desktop platforms are part of the actual control scope. Do not replace both-volume encryption with root-volume-only encryption or infer endpoint compliance from unrelated encrypted storage.

## 11. Removing

Unlink the automation and remove its unused credentials/permissions. Retain historical evidence. Desktops and keys remain unchanged.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial WorkSpaces Personal volume encryption and KMS metadata review; real validation pending. |

References: [WorkSpaces discovery](https://docs.aws.amazon.com/workspaces/latest/api/API_DescribeWorkspaces.html), [WorkSpaces encryption](https://docs.aws.amazon.com/workspaces/latest/adminguide/encrypt-workspaces.html).
