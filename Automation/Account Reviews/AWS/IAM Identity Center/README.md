---
id: aws-identity-center-account-review
name: AWS IAM Identity Center – Accounts
version: 0.1.1
status: draft
technology: AWS IAM Identity Center
vendor: AWS
eramba_module: Account Review Feeds
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - REGION
  - INSTANCE_ARN
  - USER_KEY
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_GROUPS
  - MAX_ASSIGNMENTS
dependencies:
  - aws/aws-sdk-php:^3.398
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# AWS IAM Identity Center – Accounts

**Technology:** AWS IAM Identity Center (successor to AWS Single Sign-On), one organization instance.

Sends eramba every user of your IAM Identity Center and the access each one has: its groups and the permission sets that give it access to AWS accounts. Reviewers then mark in eramba which users and accesses should stay and which should be removed; nothing is changed in AWS.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One IAM Identity Center user, identified by its primary email, for example `ana.garcia@example.com` (or its user name or user ID with `USER_KEY`) |
| **Connects to** | One IAM Identity Center organization instance, in the region you set in `REGION`, through the official AWS SDK for PHP. Read-only |
| **Permission needed** | An IAM user with an access key, in the AWS Organizations management account or the delegated administrator account for IAM Identity Center, allowed to list instances, permission sets, account assignments, users, groups and group memberships (§5). An AWS administrator creates it |
| **Included** | Every user of the instance's identity store, enabled or disabled, whatever the identity source (Identity Center directory, or an external identity provider through SCIM) |
| **Not included** | Application assignments; what each permission set allows; IAM users and roles inside the AWS accounts (see §3); account names (the 12-digit account ID is shown); Active Directory identity sources, whose users are only partly visible through the Identity Store API; other instances (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000), 2000 groups and permission sets (`MAX_GROUPS`, maximum 10000) and 20000 account assignments (`MAX_ASSIGNMENTS`, maximum 100000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Groups and permission sets per AWS account, direct or through a group |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's Identity Center user. Its roles are every access that user holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Group** | The user is a member of this group in the Identity Center directory | `Group: Engineering [<id>]` |
| **Permission set** | The user can sign in to this AWS account with this set of permissions. Shown as *account ID / permission set*. *via* followed by a group name means the access comes from that group; with no *via* it was given to the user directly. The same access reached in two ways is listed once for each way | `Permission set: 123456789012 / AdministratorAccess via Engineering [arn:aws:sso:::permissionSet/ssoins-…/ps-…]` |

```
Account:     ana.garcia@example.com
Description: Ana García (ana.garcia); enabled; created 2024-03-12; id=9067…
Roles:       Group: Engineering [a1b2…]
             Permission set: 123456789012 / AdministratorAccess via Engineering [arn:aws:sso:::permissionSet/…]
             Permission set: 210987654321 / ReadOnlyAccess [arn:aws:sso:::permissionSet/…]
```

The text in square brackets is AWS's identifier. It keeps two items with the same name apart; reviewers can ignore it. The description gives the display name, user name, whether the user is *enabled* or *disabled*, the creation date and the user ID.

You choose which kinds are listed with `ROLES` (§7): `groups` and `permission_sets`. With none, only the users are listed.

A row shows *that* Ana can use *AdministratorAccess* in an AWS account, not what that permission set allows.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each permission set allows | *AWS IAM Identity Center – Permission set permissions* (planned) |
| Review the IAM users inside an AWS account | [AWS IAM – Accounts](../IAM/) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). A leaver's disabled Identity Center user stays listed until it is deleted |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An IAM user with an access key in the AWS Organizations **management account** or in the **delegated administrator account** for IAM Identity Center (the only accounts that can read an organization instance).
- The region where IAM Identity Center is enabled (IAM Identity Center console → **Settings**).
- HTTPS reach from eramba to `sso.<region>.amazonaws.com` and `identitystore.<region>.amazonaws.com`.

## 5. Setup on AWS

Attach this policy to the IAM user used by eramba:

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Sid": "ErambaIdentityCenterAccountReview",
    "Effect": "Allow",
    "Action": [
      "sso:ListInstances",
      "sso:ListPermissionSets",
      "sso:DescribePermissionSet",
      "sso:ListAccountsForProvisionedPermissionSet",
      "sso:ListAccountAssignments",
      "identitystore:ListUsers",
      "identitystore:ListGroups",
      "identitystore:ListGroupMemberships"
    ],
    "Resource": "*"
  }]
}
```

| Action | Why |
|---|---|
| `sso:ListInstances` | Finds the instance ARN and identity store ID |
| `sso:ListPermissionSets`, `sso:DescribePermissionSet` | Permission set ARNs and names (only with `permission_sets`) |
| `sso:ListAccountsForProvisionedPermissionSet`, `sso:ListAccountAssignments` | Accounts and the users and groups assigned to each permission set (only with `permission_sets`) |
| `identitystore:ListUsers` | The users |
| `identitystore:ListGroups`, `identitystore:ListGroupMemberships` | Group names and members (only when `ROLES` is not empty) |

The AWS managed policies `AWSSSOReadOnly` and `AWSSSODirectoryReadOnly` also include these actions, with more. You may narrow `Resource` to the instance, permission set and account ARNs described in the [service authorization reference](https://docs.aws.amazon.com/service-authorization/latest/reference/list_awsiamidentitycentersuccessortoawssinglesign-on.html).

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `aws_access_key_id` and `aws_secret_access_key` (the same names as the AWS control automations).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Timeout | 240 seconds |
| Code | [run.php](run.php), with `REGION` set in section 2 |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users and access with the IAM Identity Center console.
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `REGION` | `''` | Required: the region where IAM Identity Center is enabled, e.g. `eu-west-1`. |
| `INSTANCE_ARN` | `''` | `''` uses the only active instance visible in `REGION`; set the instance ARN when several are visible. |
| `USER_KEY` | `email` | Account identifier: `email` (the email marked primary, or the user's only email), `UserName` or `UserId`. A user without one aborts the run. |
| `ROLES` | `['groups', 'permission_sets']` | Access listed as roles. `[]` lists users only and skips every group and assignment call. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 users (also members of one group); exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |
| `MAX_GROUPS` | `2000` | 1–10000 groups, and permission sets. |
| `MAX_ASSIGNMENTS` | `20000` | 1–100000 account assignments, and permission set / account pairs. |

Fixed limits: 200 seconds of collection, 1000 pages per list, 8 MiB of feed, 48 MiB of memory.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secrets with exactly these names |
| `REGION must be …` | Set `REGION` to the Identity Center region |
| `0 active IAM Identity Center instances visible` | Wrong region, or the key is not in the management or delegated administrator account |
| `2 active … instances visible` | Set `INSTANCE_ARN` |
| `AWS AccessDeniedException` | Attach the policy from §5 |
| `A user has no primary email` | Set `USER_KEY` to `UserName` or `UserId` |
| Member or assignment not collected | Identity Center changed during the pull; retry later |
| Time budget exceeded | Use `ROLES = ['groups']` or `[]`, or split the review |

## 10. Customising

Adjust the documented variables. Account names (AWS Organizations `ListAccounts`), application assignments or permission set policies need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed and, if no other automation uses them, the access key, policy and secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [ListInstances](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListInstances.html), [ListPermissionSets](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListPermissionSets.html), [DescribePermissionSet](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_DescribePermissionSet.html), [ListAccountsForProvisionedPermissionSet](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListAccountsForProvisionedPermissionSet.html), [ListAccountAssignments](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListAccountAssignments.html), [Identity Store ListUsers](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListUsers.html), [ListGroups](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListGroups.html), [ListGroupMemberships](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListGroupMemberships.html), [User](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_User.html), [delegated administration](https://docs.aws.amazon.com/singlesignon/latest/userguide/delegated-admin.html), [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/welcome.html).
