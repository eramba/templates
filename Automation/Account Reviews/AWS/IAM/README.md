---
id: aws-iam-account-review
name: AWS IAM Users and Assigned Access
version: 0.1.1
status: draft
technology: AWS Identity and Access Management
vendor: AWS
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - USER_KEY
  - ROLES
  - INCLUDE_ROOT
  - ALLOW_EMPTY_FEED
  - MAX_USERS
dependencies:
  - aws/aws-sdk-php:^3.398
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# AWS IAM Users and Assigned Access

**Technology:** AWS Identity and Access Management (IAM), one AWS account.

Send IAM users, their groups, policies and credentials to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of IAM users of one AWS account to review. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value; see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | IAM user name by default; configurable to ARN or user ID |
| `description` | Path and name, MFA or no MFA, creation date and user ID |
| `roles` | `Group: <name> [<arn>]` for every group |
| | `Group policy: <group> / <policy> [<arn or inline>]` for the policies of each group |
| | `Policy: <name> [<arn>]` and `Inline policy: <name> [inline]` attached to the user |
| | `Permissions boundary: <name> [<arn>]` |
| | `Credential: Console password [password]` and `Credential: Access key [key 1]` or `[key 2]` when active |

The account root user is included by default with its MFA status and active access keys.

## 3. Coverage

One AWS account per automation. Not covered: IAM roles and who can assume them (working that out needs policy evaluation), IAM Identity Center users and permission sets (a separate API), resource-based policies, service control policies and other accounts of an organization. Policies show what was granted, not the effective permissions after boundaries, conditions and SCPs.

Users and groups come from `GetAccountAuthorizationDetails`; credentials from the IAM credential report, which AWS refreshes at most every four hours.

Any collection error, a user in a group that was not collected, a user missing from the credential report, or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A dedicated IAM user (or the one used by the AWS control automations) with an access key.
- HTTPS reach from eramba to `iam.amazonaws.com`.

## 5. Setup on AWS

Attach this policy to the IAM user used by eramba:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": [
      "iam:GetAccountAuthorizationDetails",
      "iam:GenerateCredentialReport",
      "iam:GetCredentialReport"
    ],
    "Resource": "*"
  }]
}
```

The AWS managed policy `SecurityAudit` also includes these actions.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `aws_access_key_id` and `aws_secret_access_key` (the same names as the AWS control automations).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users and access with IAM.
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `UserName` | Account identifier: `UserName`, `Arn` or `UserId`. |
| `ROLES` | `['groups', 'policies', 'credentials']` | Access listed as roles: groups, policies (user, group, inline, boundary) and active credentials. `[]` lists users only. |
| `INCLUDE_ROOT` | `true` | Also list the account root user. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 IAM users or groups; exceeding it aborts instead of truncating. |

Fixed limits: 200 seconds of collection, 200 pages, up to 20 seconds waiting for the credential report, 8 MiB of feed, 48 MiB of memory. The AWS SDK retries throttled requests.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never user names or keys. Access key IDs are not included in the feed. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secrets with exactly these names |
| `AWS AccessDenied` | Attach the policy from §5 |
| `The IAM credential report was not generated in time` | Retry; AWS generates it asynchronously |
| User missing from the credential report | The user was created after the report; retry after four hours |
| Group not collected | IAM changed during the pull; retry later |

## 10. Customising

Adjust the documented variables. IAM roles or Identity Center need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed and, if no other automation uses them, the access key, policy and secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [GetAccountAuthorizationDetails](https://docs.aws.amazon.com/IAM/latest/APIReference/API_GetAccountAuthorizationDetails.html), [credential report](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_getting-report.html), [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/welcome.html).
