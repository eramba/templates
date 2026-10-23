---
id: zoom-account-review
name: Zoom – Accounts
version: 0.1.1
status: draft
technology: Zoom Workplace
vendor: Zoom
eramba_module: Account Review Feeds
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

# Zoom – Accounts

**Technology:** Zoom Workplace.

Sends eramba every user of your Zoom account and the access each one has: its role, its user type (licence) and, optionally, its groups. Reviewers then mark in eramba which users and accesses should stay and which should be removed; nothing is changed in Zoom.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Zoom user of one Zoom account, identified by its email, for example `ana.garcia@example.com` (or its Zoom user ID with `USER_KEY`) |
| **Connects to** | Your Zoom account, through Zoom's official REST API (`api.zoom.us`). Read-only |
| **Permission needed** | A Server-to-Server OAuth app with only the read scopes `user:read:list_users:admin`, `role:read:list_roles:admin` (to list roles) and `group:read:list_groups:admin` (to list groups). A Zoom account admin creates and activates it (§5) |
| **Included** | Users with the statuses in `STATUSES`: by default `active`, `inactive` (deactivated) and `pending` (invited, not yet activated) |
| **Not included** | The privileges inside each role; add-on licences (Phone, Webinars, Contact Center); IM directory groups; sub-accounts of a master account; Zoom Rooms; meeting participants and guests with no user in the account; Zoom for Government; other accounts (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. To split a larger population, use `STATUSES` |
| **Roles listed** | Role, user type (licence) and, optionally, groups |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's Zoom user. Its roles are every access that user holds. This automation lists three kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Role** | The user's account role: *Owner*, *Admin*, *Member* or a custom role | `Role: Admin [<id>]` |
| **User type** | The licence the user holds: *Basic*, *Licensed*, *Unassigned without Meetings Basic* or *None* | `User type: Licensed [2]` |
| **Group** | The user belongs to this Zoom group. Listed only when you add `groups` to `ROLES` | `Group: IT [<id>]` |

```
Account:     ana.garcia@example.com
Description: Ana García; Licensed; active; id=AbC12…
Roles:       Role: Admin [1a2b…]
             User type: Licensed [2]
```

The text in square brackets is Zoom's identifier or code. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, the user type, the status (`active`, `inactive` or `pending`) and the user ID. Zoom gives pending users no ID, so theirs reads `id=none (pending)`, and a pending user with no role yet has no `Role:` entry.

You choose which kinds are listed with `ROLES` (§7). With none, only the users are listed.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what someone can do in Zoom | In Zoom, permissions sit on the account itself (its role and licence), so this account review already shows them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Inactive users of leavers stay listed until deleted |

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
| `MAX_USERS` | `2000` | 1–5000 users across all statuses; exceeding it aborts instead of truncating. For more accounts, split the review into several feeds with `STATUSES`. |
| `MAX_OBJECTS` | `2000` | 1–10000 roles or groups in the account, and groups per user; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Only `POST https://zoom.us/oauth/token` and `GET https://api.zoom.us/v2/users`, `/v2/roles` and `/v2/groups` requests are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [Server-to-Server OAuth](https://developers.zoom.us/docs/internal-apps/s2s-oauth/), [Users API (List users, List groups)](https://developers.zoom.us/docs/api/users/), [Accounts API (List roles)](https://developers.zoom.us/docs/api/accounts/), [granular scopes](https://developers.zoom.us/docs/integrations/oauth-scopes-overview/), [rate limits](https://developers.zoom.us/docs/api/rate-limits/).
