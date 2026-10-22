---
id: zoom-account-review
name: Zoom Users and Assigned Access
version: 0.1.1
status: draft
technology: Zoom Workplace
vendor: Zoom
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - zoom_account_id
  - zoom_client_id
  - zoom_client_secret_b64
variables:
  - USER_KEY
  - ROLES
  - STATUSES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Zoom Users and Assigned Access

**Technology:** Zoom Workplace.

Send the users of a Zoom account with their role, user type and groups to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Zoom users to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | User email by default; configurable to the Zoom user ID |
| `description` | Display name (or first and last name), user type, status (`active`, `inactive`, `pending`) and user ID |
| `roles` | `Role: <role name> [<role id>]`: the account role (Owner, Admin, Member or a custom role) |
| | `User type: <label> [<type>]`: `Basic [1]`, `Licensed [2]`, `Unassigned without Meetings Basic [4]` or `None [99]` |
| | `Group: <group name> [<group id>]` for every group the user belongs to (optional, `groups`) |

Users of the three statuses are listed by default: `active`, `inactive` (deactivated) and `pending` (invited, not yet activated). Zoom returns no user ID for pending users: their description shows `id=none (pending)`, and a pending user that has no role yet is listed without a `Role:` entry (the count is logged).

## 3. Coverage

One Zoom account per automation, on `api.zoom.us` (Zoom for Government is not covered). Not covered: the privileges inside each role (read them in **User Management → Roles**), add-on licences (Phone, Webinars, Contact Center), IM directory groups, sub-accounts of a master account, Zoom Rooms, and meeting participants or guests who have no Zoom user in the account.

Calls: one OAuth token request, then `GET /users` with 300 users per page for each status in `STATUSES`, one `GET /roles` (only with `role`) and `GET /groups` (only with `groups`). Group membership is read from each user's `group_ids`, so no call is made per group. Zoom rate limits (HTTP 429) are retried with `Retry-After` when the wait fits the budget.

Completeness: each list must reach a page without `next_page_token`, the number of records must equal `total_records`, and `total_records` must not change between pages. A user whose role or group is not in the collected lists, a duplicate user ID or key, an unknown user type, or any limit aborts the run and nothing is published.

To validate on a real account: that `GET /roles` lists the built-in Owner, Admin and Member roles with the IDs used in `role_id`, that `page_size = 300` is accepted for users, and whether pending users carry a `role_id`.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A Zoom Pro or higher account (roles and groups require it), and a user allowed to create Server-to-Server OAuth apps (Account Owner or Admin with the *Server-to-Server OAuth app* permission).
- HTTPS reach from eramba to `zoom.us` and `api.zoom.us`.

If the [Zoom Multi-Factor Authentication Coverage Review](../../../Internal%20Controls/Zoom/Multi-Factor%20Authentication%20Coverage%20Review/) control already runs, this feed uses the same secret names; add the scopes below to that app or create a separate app.

## 5. Setup on Zoom

1. **marketplace.zoom.us → Develop → Build App → Server-to-Server OAuth App**, named e.g. *eramba account review*.
2. Copy the **Account ID**, **Client ID** and **Client secret** from **App Credentials**.
3. **Scopes → Add Scopes**: add only these granular read scopes:
   - `user:read:list_users:admin`: list users of every status, with type, role ID and group IDs;
   - `role:read:list_roles:admin`: role names, only if `ROLES` includes `role`;
   - `group:read:list_groups:admin`: group names, only if `ROLES` includes `groups`.
4. Fill the information page and **Activate** the app. Do not add write scopes.

The token is requested on every run with the `account_credentials` grant and expires after one hour; nothing is stored.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**:
   - `zoom_account_id`: the Account ID;
   - `zoom_client_id`: the Client ID;
   - `zoom_client_secret_b64`: the Client secret, base64-encoded on one line (for example `printf '%s' '<secret>' | base64`).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users and roles with **User Management → Users**.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email` or `id` (Zoom user ID). Must exist on every user; no fallback. Pending users have no ID, so `id` requires removing `pending` from `STATUSES`. |
| `ROLES` | `['role', 'user_type']` | Access listed as roles: `role`, `user_type`, `groups`. `[]` lists users only. |
| `STATUSES` | `['active', 'inactive', 'pending']` | User statuses to list. Remove `inactive` to review only users who can sign in. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 users across all statuses; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 roles or groups in the account, and groups per user; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Only `POST https://zoom.us/oauth/token` and `GET https://api.zoom.us/v2/users`, `/v2/roles` and `/v2/groups` requests are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, emails or tokens. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secrets with exactly these names |
| `… is not valid single-line base64` | Encode the client secret again, without a line break |
| `zoom.us returned HTTP 400` or `401` | Wrong Account ID, Client ID or secret, or the app is not activated |
| `api.zoom.us returned HTTP 400` | A scope is missing (Zoom reports missing scopes as 400); add it in §5 |
| `api.zoom.us returned HTTP 403` | API access denied for the account, or the plan lacks roles or groups |
| Role or group not in the list | Roles or groups changed during the pull; retry later |
| `… changed during the pull` | Users were added or removed while paging; retry later |
| Throttling | Zoom rate limit reached; schedule the pull outside busy hours |

## 10. Customising

Adjust the documented variables. Other access types (role privileges, add-on licences) need new collection code and scopes. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, deactivate or delete the Server-to-Server OAuth app (or remove the added scopes if it is shared) and delete the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [Server-to-Server OAuth](https://developers.zoom.us/docs/internal-apps/s2s-oauth/), [Users API (List users, List groups)](https://developers.zoom.us/docs/api/users/), [Accounts API (List roles)](https://developers.zoom.us/docs/api/accounts/), [granular scopes](https://developers.zoom.us/docs/integrations/oauth-scopes-overview/), [rate limits](https://developers.zoom.us/docs/api/rate-limits/).
