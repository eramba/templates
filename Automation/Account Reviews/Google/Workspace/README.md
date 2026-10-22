---
id: google-workspace-account-review
name: Google Workspace – Accounts
version: 0.1.1
status: draft
technology: Google Workspace
vendor: Google
eramba_module: Account Review Feeds
secrets:
  - google_service_account_json_b64
variables:
  - USER_KEY
  - ROLES
  - CUSTOMER_ID
  - DELEGATED_ADMIN_EMAIL
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_GROUPS
dependencies:
  - google/apiclient:^2.18
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Google Workspace – Accounts

**Technology:** Google Workspace (Admin SDK Directory API), one customer account.

Sends eramba every user account in your Google Workspace and the access each one has: its groups and its administrator roles, and optionally its organizational unit. Reviewers then mark in eramba which accounts and accesses should stay and which should be removed; nothing is changed in Google Workspace.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One user account, identified by its primary email, for example `ana.garcia@example.com` (or its immutable user ID with `USER_KEY`) |
| **Connects to** | Your Google Workspace, through Google's official Admin SDK Directory API. Read-only |
| **Permission needed** | A service account with domain-wide delegation for the read-only scopes `admin.directory.user.readonly`, `admin.directory.group.readonly` and `admin.directory.rolemanagement.readonly`, acting as an administrator you name in `DELEGATED_ADMIN_EMAIL`. A Google Cloud administrator creates the service account and a super admin authorizes the delegation once (§5) |
| **Included** | Every user of the customer account, across all domains and organizational units: active, suspended and archived |
| **Not included** | Deleted users; applications assigned through SAML or OIDC; Google Cloud IAM; shared drives and file sharing; aliases; what each admin role can do; members of groups that are not users or groups of your customer (external addresses are ignored) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000) and 1000 groups (`MAX_GROUPS`, maximum 5000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Groups (nested included), administrator roles with their scope, and optionally the organizational unit |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds. This automation lists three kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Group** | The person belongs to this group, as *owner*, *manager* or *member*, or *nested* when they are in another group that belongs to it. *all users* means the group contains the whole organization, so it appears on every account. Groups usually give access to files, shared mailboxes or applications; email-only groups are listed too | `Group: finance@example.com (member) [<id>]` |
| **Admin role** | An administrator role in Google Workspace, such as *User Management Admin*, given to the person or to a group they are in (*via group*). *org unit* means it covers only that part of the organization; *conditional* means the assignment has a condition. A super admin shows as `_SEED_ADMIN_ROLE`, Google's name for that role | `Admin role: User Management Admin [<id>]` |
| **Org unit** | The organizational unit the account sits in. Listed only when you add `org_unit` to `ROLES` | `Org unit: /Sales [path]` |

```
Account:     ana.garcia@example.com
Description: Ana García; active; org unit /Sales; last sign-in 2026-10-02; created 2023-04-17; id=1034…
Roles:       Admin role: User Management Admin [1234…]
             Group: finance@example.com (member) [03abc…]
             Group: all-staff@example.com (nested) [04def…]
```

The codes in square brackets are Google's identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives the name, whether the account is *active*, *suspended* or *archived*, whether it is a *super admin* or *delegated admin*, its organizational unit, its last sign-in date (or *never signed in*) and its creation date.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed.

A row shows *that* Ana is in *finance@example.com* or holds *User Management Admin*, not what those give her.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each group grants | *Google Workspace – Group permissions* (planned) |
| See what each administrator role allows | *Google Workspace – Admin role permissions* (planned) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Suspended and archived accounts of leavers stay listed until deleted |
| See what someone can do inside an application, such as Salesforce | That application's own automation, if there is one in the [catalogue](../../README.md), for example [Salesforce](../../Salesforce/Salesforce/) |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A Google Cloud project where you can enable the Admin SDK API and create a service account, and a super admin to authorize domain-wide delegation once.
- A Workspace administrator account for the script to act as (`DELEGATED_ADMIN_EMAIL`), with read access to users, groups and admin roles of the whole customer.
- HTTPS reach from eramba to `oauth2.googleapis.com` and `admin.googleapis.com`.

## 5. Setup on Google Workspace

1. In Google Cloud, enable the **Admin SDK API**, create a dedicated service account and a JSON key. Protect and rotate the key. The Google Workspace control automations can share the same service account and secret.
2. In the Admin console, **Security → Access and data control → API controls → Manage domain-wide delegation**, add the service account's numeric client ID with these scopes only:

| Scope | Needed for |
|---|---|
| `https://www.googleapis.com/auth/admin.directory.user.readonly` | Users (always) |
| `https://www.googleapis.com/auth/admin.directory.group.readonly` | `groups`, and `admin_roles` (roles assigned to groups) |
| `https://www.googleapis.com/auth/admin.directory.rolemanagement.readonly` | `admin_roles` |

   The script only requests the scopes its `ROLES` need, but each requested scope must be authorized here or the token request fails. If you add the control automation's client ID entry, extend that entry instead of creating a second one.

3. Choose the administrator the script acts as (`DELEGATED_ADMIN_EMAIL`). Domain-wide delegation gives the script at most the intersection of the scopes above and that administrator's privileges. Prefer a dedicated account with a custom admin role that can read users, groups and admin roles across the whole organization, over a super admin. **To validate on a real tenant:** the minimal Admin API privileges for reading roles and role assignments; a role limited to some org units makes the list incomplete without any error.
4. Encode the downloaded JSON key as single-line base64 (for example `base64 -i key.json | tr -d '\n'` on macOS, `base64 -w0 key.json` on Linux). Base64 avoids quoting problems; it does not encrypt the key.

Only the signing fields of the key are used; its credential URLs are ignored, and the token is always requested from Google's endpoint.

## 6. Setup in eramba

1. Create the secret in **Settings → Application Configuration → Automation Secrets**: `google_service_account_json_b64` with the base64 value (the same name as the Google Workspace control automations).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `google/apiclient:^2.18` and `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php), with `DELEGATED_ADMIN_EMAIL` set |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users, groups and admin roles with the Admin console.
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `primaryEmail` | Account identifier: `primaryEmail` (matches identity-provider and HR feeds) or `id` (immutable; survives email renames). |
| `ROLES` | `['groups', 'admin_roles']` | Access listed as roles: `groups`, `admin_roles`, `org_unit`. `[]` lists users only, with one request per 500 users. |
| `CUSTOMER_ID` | `my_customer` | The customer account of the delegated administrator, or a customer ID such as `C0abc123`. |
| `DELEGATED_ADMIN_EMAIL` | empty | Administrator the service account acts as. Required. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero users is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 users; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |
| `MAX_GROUPS` | `1000` | 1–5000 groups; exceeding it aborts. Lower `ROLES` to `['admin_roles']` or `[]` for very large tenants. |

Keep `USER_KEY` unchanged once a Differential review has a baseline. Renaming a group email or an admin role appears as a role change.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary counts the roles listed per kind. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create `google_service_account_json_b64` with exactly that name |
| `… is not valid single-line base64` | Encode the JSON key again without line breaks |
| `Directory users: Google returned HTTP 401` or `400` | Domain-wide delegation is missing a requested scope, the client ID is wrong, or `DELEGATED_ADMIN_EMAIL` is not an active user |
| `Google returned HTTP 403, forbidden` | The delegated administrator lacks a privilege, or the Admin SDK API is not enabled in the project |
| `… exceeds the configured limit` | Raise `MAX_USERS` or `MAX_GROUPS` |
| `Time budget exceeded` | Too many groups for one run: use `ROLES = ['admin_roles']` or `[]` |
| Role assignment refers to a role that was not listed | Roles changed during the pull; retry |

## 10. Customising

Adjust the documented variables. Other access (SAML applications, Cloud IAM, drives) needs new collection code and scopes. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed and, if no other automation uses them, the domain-wide delegation entry, the service-account key, the delegated administrator's role and the secret.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [users.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/users/list), [groups.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/groups/list), [members.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/members/list), [roles.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/roles/list), [roleAssignments.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/roleAssignments/list), [authorization scopes](https://developers.google.com/workspace/admin/directory/v1/guides/authorizing), [domain-wide delegation](https://support.google.com/a/answer/162106), [assign admin roles to groups](https://support.google.com/a/answer/9807615), [Google API PHP client](https://github.com/googleapis/google-api-php-client).
