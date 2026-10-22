---
id: google-workspace-account-review
name: Google Workspace Users and Assigned Access
version: 0.1.1
status: draft
technology: Google Workspace
vendor: Google
eramba_module: Account Review Feeds
trigger: Account Review pull
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

# Google Workspace Users and Assigned Access

**Technology:** Google Workspace (Admin SDK Directory API), one customer account.

Send Google Workspace users, their group memberships and admin roles to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Google Workspace users to review: active, suspended and archived. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or an HR list whose key holds the same value (usually the work email); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Primary email by default; configurable to the immutable user ID |
| `description` | Full name; `active`, `suspended (<reason>)` and/or `archived`; `super admin` or `delegated admin`; org unit path; last sign-in date or `never signed in`; creation date; user ID |
| `roles` | `Group: <group email> (owner)`, `(manager)` or `(member) [<group id>]` for direct memberships |
| | `Group: <group email> (nested) [<group id>]` when the user belongs through a nested group |
| | `Group: <group email> (all users) [<group id>]` on every user when the group contains the whole organization |
| | `Admin role: <role name> [<role id>]` for roles assigned to the whole organization |
| | `Admin role: <role name> (org unit <org unit id>) [<role id>/<org unit id>]` for roles limited to an organizational unit |
| | `Admin role: <role name> (via group <group email>) [<role id>]` for roles assigned to a group the user belongs to, directly or nested |
| | ` (conditional)` is added to the role name when the assignment has a condition |
| | `Org unit: <path> [path]` with the optional `org_unit` kind |

The super admin role is returned by Google under its system name `_SEED_ADMIN_ROLE`. A user with both a direct and a nested membership of the same group gets the direct one only.

## 3. Coverage

Every user of the customer account (all domains and organizational units), from `users.list`, including suspended and archived users. Deleted users are not accounts and are not listed.

Groups and memberships are read once per group (`groups.list`, then `members.list` per group) and inverted, so the cost grows with the number of groups, not users. Nested groups are resolved; members that are not users or groups of this customer (external addresses, groups of other organizations) are ignored and only counted in the log. Admin roles come from `roles.list` and `roleAssignments.list`; roles assigned to groups are expanded to the group's members, nested included. Role assignments to service accounts or other principals that are not users are counted in the log, not listed.

Not covered: third-party SAML/OIDC application assignments, Google Cloud IAM, shared drives and file sharing, group settings such as who can post, dynamic group queries (their current members are listed like any other group), aliases, and the effective privileges of each admin role. Org unit IDs in role scopes are not resolved to paths (that needs another scope).

Any collection error, a role assignment pointing to a role that was not listed, a repeated page token, or a limit aborts the run and nothing is published.

Limits: `MAX_USERS` users, `MAX_GROUPS` groups, 100,000 direct memberships, 10,000 roles or role assignments, 200 pages per list, 200 seconds of collection. Reading memberships takes one request per 200 members of each group: with about 0.2–0.5 seconds per request, a few hundred groups fit the budget. **To validate on a real tenant:** the time per request, that `roleAssignments.list` includes super admin assignments, the exact admin privileges listed in §5, and that `scopeType` and `assigneeType` values match the reference.

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
| `MAX_USERS` | `2000` | 1–10000 users; exceeding it aborts instead of truncating. |
| `MAX_GROUPS` | `1000` | 1–5000 groups; exceeding it aborts. Lower `ROLES` to `['admin_roles']` or `[]` for very large tenants. |

Keep `USER_KEY` unchanged once a Differential review has a baseline. Renaming a group email or an admin role appears as a role change.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never emails, names or keys. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published. Errors show the HTTP status and Google's one-word reason, never response bodies.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

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
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [users.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/users/list), [groups.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/groups/list), [members.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/members/list), [roles.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/roles/list), [roleAssignments.list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/roleAssignments/list), [authorization scopes](https://developers.google.com/workspace/admin/directory/v1/guides/authorizing), [domain-wide delegation](https://support.google.com/a/answer/162106), [assign admin roles to groups](https://support.google.com/a/answer/9807615), [Google API PHP client](https://github.com/googleapis/google-api-php-client).
