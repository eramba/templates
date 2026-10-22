---
id: aws-identity-center-account-review
name: AWS IAM Identity Center Users and Assigned Access
version: 0.1.0
status: draft
technology: AWS IAM Identity Center
vendor: AWS
eramba_module: Account Review Feeds
trigger: Account Review pull
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

# AWS IAM Identity Center Users and Assigned Access

**Technology:** AWS IAM Identity Center (successor to AWS Single Sign-On), one organization instance.

Send IAM Identity Center users, their groups and their AWS account access (permission sets) to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of IAM Identity Center users to review, with the AWS accounts and permission sets each one can use. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | The user's primary email by default; configurable to `UserName` or `UserId` |
| `description` | Display name, user name, `enabled` / `disabled` (or `status not reported`), creation date and user ID |
| `roles` | `Group: <group name> [<group id>]` for every Identity Store group the user belongs to |
| | `Permission set: <account id> / <permission set name> [<permission set ARN>]` for an account assignment made directly to the user |
| | `Permission set: <account id> / <permission set name> via <group name> [<permission set ARN>]` for an assignment made to one of the user's groups |

Disabled users are included with their state in the description. The same access granted directly and through a group, or through two groups, appears once per path, so a reviewer can see why the user has it.

## 3. Coverage

One IAM Identity Center organization instance per automation, in the region set in `REGION`. The script collects every user and group of the instance's identity store, every permission set, every AWS account each permission set is provisioned to, and every account assignment of each permission set and account pair. Users come from the identity store whatever the identity source (Identity Center directory, or an external identity provider through SCIM).

Not covered: application assignments (customer managed and AWS managed applications), what each permission set allows (managed, inline and customer managed policies, permissions boundary), IAM users and roles inside the accounts (see [AWS IAM](../IAM/)), account names (the 12-digit account ID is shown), and Active Directory identity sources, whose users are only partially visible through the Identity Store API (to validate on a real tenant). A group member or an assignment pointing to a user or group that was not collected aborts the run.

Account assignments are found through the accounts each permission set is provisioned to (`ListAccountsForProvisionedPermissionSet`), which avoids calling every permission set against every account of the organization. To validate on a real tenant: an assignment whose provisioning failed may not be listed.

Call volume: one call per 100 users, groups or permission sets; one `DescribePermissionSet` and one account listing per permission set; one assignment listing per permission set and account pair; one membership listing per group (only the groups that hold assignments when `ROLES` has no `groups`). Large organizations may hit the 200-second budget; the AWS SDK retries throttled requests.

Any collection error, an unexpected answer, a repeating pagination token or an exceeded limit aborts the run and nothing is published.

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
| `MAX_USERS` | `5000` | 1–50000 users (also members of one group); exceeding it aborts instead of truncating. |
| `MAX_GROUPS` | `2000` | 1–10000 groups, and permission sets. |
| `MAX_ASSIGNMENTS` | `20000` | 1–100000 account assignments, and permission set / account pairs. |

Fixed limits: 200 seconds of collection, 1000 pages per list, 8 MiB of feed, 48 MiB of memory.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never emails, names or IDs. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published.

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

References: [ListInstances](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListInstances.html), [ListPermissionSets](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListPermissionSets.html), [DescribePermissionSet](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_DescribePermissionSet.html), [ListAccountsForProvisionedPermissionSet](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListAccountsForProvisionedPermissionSet.html), [ListAccountAssignments](https://docs.aws.amazon.com/singlesignon/latest/APIReference/API_ListAccountAssignments.html), [Identity Store ListUsers](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListUsers.html), [ListGroups](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListGroups.html), [ListGroupMemberships](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_ListGroupMemberships.html), [User](https://docs.aws.amazon.com/singlesignon/latest/IdentityStoreAPIReference/API_User.html), [delegated administration](https://docs.aws.amazon.com/singlesignon/latest/userguide/delegated-admin.html), [AWS SDK for PHP](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/welcome.html).
