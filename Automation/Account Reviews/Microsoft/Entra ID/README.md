---
id: entra-account-review
name: Entra ID Accounts and Assigned Access
version: 0.1.1
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - USER_KEY
  - ROLES
  - USER_IDS
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Entra ID Accounts and Assigned Access

**Technology:** Microsoft Entra ID (public cloud), including Microsoft 365 licences.

Send Entra user accounts and their assigned access to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of accounts to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../Entra%20ID%20Current%20Employees/) whose key holds the same value; see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | User principal name by default; configurable to object ID or mail |
| `description` | Display name, Member/Guest, enabled/disabled and object ID |
| `roles` | `Group: <name> [<id>]` for direct and nested group memberships |
| | `Directory role: <name> [<role id>; scope=<directory scope>]` for active role assignments, direct or through a role-assignable group |
| | `Application: <app> / <role> [<app id> / <role id>]` for app role assignments, direct or through a group, including default access |
| | `License: <SKU part number> [<SKU id>]` for Microsoft 365 and other licences assigned to the user, directly or through a group |

All users are included, disabled users and guests too, so reviewers see accounts that still exist after someone left. To list the people who should have access, use [Entra ID Current Employees](../Entra%20ID%20Current%20Employees/).

## 3. Coverage

One tenant per automation. Not covered: deleted users, service principals, managed identities, devices, Azure resource RBAC, eligible (inactive) PIM roles and permissions inside each application. An application assignment shows who can sign in, not what they can do inside the app.

Any collection error, count mismatch or limit aborts the run and nothing is published. An incomplete list is never sent: a differential review would report the missing accounts as removed.

Names and IDs are kept together, so renaming a group, role or application appears as a role change in differential reviews. A name that would make a role longer than 255 bytes is shortened; the IDs are always kept whole.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()` (**Tools → Actions → Account Review Artifact**).
- A dedicated Entra app registration and an administrator who can grant admin consent.
- Start with a few users in `USER_IDS` that cover each access type.

## 5. Setup on Entra ID

1. **Microsoft Entra admin center → App registrations → New registration**: single tenant, no redirect URI.
2. Copy the **Directory (tenant) ID** and **Application (client) ID**.
3. **Certificates & secrets → New client secret**. Copy the secret **value** and base64-encode it on one line.
4. **API permissions → Microsoft Graph → Application permissions**, then **Grant admin consent**:

| Permission | Why |
|---|---|
| `Directory.Read.All` | Users, memberships, directory role definitions/assignments, app role assignments, application names and subscribed licences |
| `Member.Read.Hidden` | Members of groups with hidden membership |

No write permission is needed. Directory-wide read access is broad: protect and rotate the secret.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` (the same names as the Entra control automations; reuse them only if the same app registration is intended).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the accounts and roles with Entra.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `userPrincipalName` | Account identifier: `userPrincipalName`, `id` or `mail`. Must exist on every account; no fallback. |
| `USER_IDS` | `[]` | Empty: whole tenant. Otherwise a list of user object IDs. |
| `ROLES` | `['groups', 'directory_roles', 'applications', 'licenses']` | Access listed as roles. `[]` lists accounts only (no membership calls, no role changes). |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 accounts; exceeding it aborts instead of truncating. Above a few thousand accounts, list roles only for a `USER_IDS` subset or set `ROLES` to `[]`: every account adds membership calls to the 200-second budget. For more accounts, split the review into several feeds with `USER_IDS`. |
| `MAX_OBJECTS` | `2000` | 1–10000 records per API collection and roles per account; exceeding it aborts. |

Fixed limits: 200 seconds of collection (`TPL_RUN_SECONDS`), 4 MB per response, 100 pages per collection, 8 MiB of feed, 48 MiB of memory, three attempts when Microsoft throttles for up to 30 seconds. Requests are grouped 20 at a time with Graph JSON batching; only the token endpoint and Graph reads are allowed (`TPL_ALLOWED_REQUESTS`).

The key must be unique ignoring case, as eramba compares accounts ignoring case. Changing `USER_KEY` or `USER_IDS` changes the population: take a new baseline.

## 8. Results

stdout only contains the artifact declaration written by `writeAccountReviewArtifact()`. **Unlike the control automations, the step log goes to stderr**, which feed automations accept when the run exits 0. Logs contain counts only, never account names, IDs, tokens or Microsoft responses.

Accounts are staged in a private temporary file and published once, after the whole population is collected. Exit 1 means nothing was published and the pull failed.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `writeAccountReviewArtifact() is unavailable` | Create the automation from the Account Review feed form, not as a control automation |
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret value |
| `Microsoft API HTTP 401/403` | Check secret expiry, admin consent and both permissions |
| Collection incomplete or changed | Directory changes during the pull; retry later |
| No accounts found | Check `USER_IDS`; set `ALLOW_EMPTY_FEED` only if zero accounts is intended |
| Keys collide or field missing | Choose a `USER_KEY` present and unique on every account (`mail` can be empty) |
| Time, size or collection limit | Split the review with `USER_IDS` |

## 10. Customising

Adjust the documented variables. Other access types need new collection code; they are not enabled by changing the role format. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop or reconfigure the Account Reviews that use the feed before removing it. Remove the app registration and secrets when no other automation uses them. Removing the automation does not revoke any access.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | Directory role assignments no longer select `appScopeId` (rejected by Graph) and deprecated built-in roles missing from the role list are read by ID; directory role values change from `[<id>; directory=…; app=…]` to `[<id>; scope=…]`, so a Differential review with an earlier baseline shows one Change in Roles per directory-role holder; `MAX_USERS` limited to 5000 (default 2000); progress logged every 1,000 accounts; the log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow), [users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0), [transitiveMemberOf](https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0), [role assignments](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roleassignments?view=graph-rest-1.0), [role definitions](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roledefinitions?view=graph-rest-1.0), [app role assignments](https://learn.microsoft.com/en-us/graph/api/user-list-approleassignments?view=graph-rest-1.0), [service principal](https://learn.microsoft.com/en-us/graph/api/serviceprincipal-get?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching), [throttling](https://learn.microsoft.com/en-us/graph/throttling).
