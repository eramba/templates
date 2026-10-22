---
id: aws-iam-account-review
name: AWS IAM – Accounts
version: 0.1.1
status: draft
technology: AWS Identity and Access Management
vendor: AWS
eramba_module: Account Review Feeds
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

# AWS IAM – Accounts

**Technology:** AWS Identity and Access Management (IAM), one AWS account.

Sends eramba every IAM user of one AWS account, plus the account's root user, and the access each one has: its groups, policies, permissions boundary and active credentials. Reviewers then mark in eramba which users and accesses should stay and which should be removed; nothing is changed in AWS.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One IAM user of one AWS account, identified by its user name, for example `ana.garcia`, or the account's root user, shown as `<root_account>` |
| **Connects to** | AWS IAM, through the official AWS SDK for PHP. Read-only |
| **Permission needed** | An IAM user with an access key and a policy allowing `iam:GetAccountAuthorizationDetails`, `iam:GenerateCredentialReport` and `iam:GetCredentialReport`. The AWS managed policy `SecurityAudit` includes them. An AWS administrator creates it (§5) |
| **Included** | Every IAM user of the AWS account and, by default, the root user (`INCLUDE_ROOT`). Whether each has MFA is shown in the description |
| **Not included** | IAM roles and who can assume them; IAM Identity Center users and permission sets (see §3); resource-based policies; service control policies; other accounts of an organization (one per automation). Policies show what was granted, not what remains after boundaries, conditions and service control policies |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Groups, policies (of the user and of its groups), permissions boundary and active credentials |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one IAM user. Its roles are every access that user holds. This automation lists seven kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Group** | The user belongs to this IAM group | `Group: Developers [arn:aws:iam::123456789012:group/Developers]` |
| **Group policy** | A policy attached to one of the user's groups, so the user receives it through that group. Shown as *group / policy* | `Group policy: Developers / ReadOnlyAccess [arn:aws:iam::aws:policy/ReadOnlyAccess]` |
| **Group inline policy** | A policy written directly into one of the user's groups. Shown as *group / policy* | `Group inline policy: Developers / deploy-bucket [inline]` |
| **Policy** | A managed policy attached directly to the user | `Policy: AmazonS3FullAccess [arn:aws:iam::aws:policy/AmazonS3FullAccess]` |
| **Inline policy** | A policy written directly into the user | `Inline policy: temp-access [inline]` |
| **Permissions boundary** | A policy that caps what the user can be granted, whatever else is attached | `Permissions boundary: DeveloperBoundary [arn:aws:iam::123456789012:policy/DeveloperBoundary]` |
| **Credential** | A way to sign in or call AWS that is active: the console password or an access key (*key 1* or *key 2*). Access key IDs are not listed | `Credential: Access key [key 1]` |

```
Account:     ana.garcia
Description: /engineering/ana.garcia; MFA; created 2024-03-12; id=AIDA…
Roles:       Credential: Console password [password]
             Group policy: Developers / ReadOnlyAccess [arn:aws:iam::aws:policy/ReadOnlyAccess]
             Group: Developers [arn:aws:iam::123456789012:group/Developers]
             Policy: AmazonS3FullAccess [arn:aws:iam::aws:policy/AmazonS3FullAccess]
```

The text in square brackets is AWS's identifier (an ARN), `inline` for policies written into the user or group, or the credential slot. It keeps two items with the same name apart; reviewers can ignore it. The description gives the path and name, whether the user has MFA or *no MFA*, the creation date and the user ID. The root user has no groups or policies: it only shows its MFA status and active access keys.

You choose which kinds are listed with `ROLES` (§7): `groups`, `policies` (all policy and boundary kinds) and `credentials`. With none, only the accounts are listed.

A row shows *that* Ana is in *Developers* or has a policy, not what the policy allows.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each policy grants | *AWS IAM – Policy permissions* (planned) |
| Review people who sign in to AWS through IAM Identity Center | [AWS IAM Identity Center – Accounts](../IAM%20Identity%20Center/) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). A leaver's IAM user stays listed until it is deleted |

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
| `MAX_USERS` | `2000` | 1–5000 IAM users or groups; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |

Fixed limits: 200 seconds of collection, 200 pages, up to 20 seconds waiting for the credential report, 8 MiB of feed, 48 MiB of memory. The AWS SDK retries throttled requests.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary counts the roles listed per kind. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [GetAccountAuthorizationDetails](https://docs.aws.amazon.com/IAM/latest/APIReference/API_GetAccountAuthorizationDetails.html), [credential report](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_getting-report.html), [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/welcome.html).
