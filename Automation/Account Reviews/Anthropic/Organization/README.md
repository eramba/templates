---
id: anthropic-account-review
name: Anthropic – Accounts
version: 0.1.1
status: draft
technology: Claude Console (Anthropic API)
vendor: Anthropic
eramba_module: Account Review Feeds
secrets:
  - anthropic_admin_key
variables:
  - USER_KEY
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Anthropic – Accounts

**Technology:** Claude Console (Anthropic API, `platform.claude.com`).

Sends eramba every member of your Claude Console organization and the access each one has: its organization role and its workspaces. Reviewers then mark in eramba which members and accesses should stay and which should be removed; nothing is changed in Anthropic.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One member of one Claude Console organization, identified by its email, for example `ana.garcia@example.com` (or its `user_…` ID with `USER_KEY`) |
| **Connects to** | Your Claude Console organization (`api.anthropic.com`), through Anthropic's official Admin API. Read-only requests |
| **Permission needed** | An Admin API key, created by an organization admin. Claude Console Admin keys have no selectable scopes: the key has full access to the Admin API, including writes, but the script only sends `GET` requests to read members and workspaces (§5) |
| **Included** | Every member the organization users list returns |
| **Not included** | Pending invitations; API keys, service accounts and Admin keys; what each member does inside a workspace; archived workspaces; Claude Enterprise (`claude.ai`) organizations beyond the organization role; Claude Platform on AWS; other organizations (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Organization role and workspace memberships with their workspace role |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's membership of the organization. Its roles are every access that member holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Organization role** | The member's role in the organization: *User*, *Claude code user*, *Developer*, *Billing* or *Admin* (*Owner* and *Primary owner* also exist). One per row | `Organization role: Admin [admin]` |
| **Workspace** | The member belongs to this workspace, with the workspace role shown (for example `workspace_developer`). One entry per workspace. The Default Workspace has no member list: access to it follows the organization role | `Workspace: Production [wrkspc_01… / workspace_developer]` |

```
Account:     ana.garcia@example.com
Description: Ana García; organization admin; id=user_…
Roles:       Organization role: Admin [admin]
             Workspace: Production [wrkspc_01… / workspace_developer]
```

The text in square brackets is Anthropic's role code or workspace ID, with the workspace role. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, the organization role and the user ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the members are listed.

Organization admins automatically hold Workspace Admin in every workspace, and billing members Workspace Billing; the organization role in the feed keeps that inherited access visible.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what someone can do in Anthropic | In Anthropic, permissions sit on the account itself (its organization role and workspace roles), so this account review already shows them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). A leaver's membership stays listed until it is removed from the organization |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A member with the **admin** organization role in the Claude Console, to create the Admin API key.
- HTTPS reach from eramba to `api.anthropic.com`.

## 5. Setup on Anthropic

1. Sign in to the Claude Console as an organization admin and open **Settings → Admin keys** (`platform.claude.com/settings/admin-keys`).
2. **Create admin key**, name it after eramba and choose an expiration that matches your rotation process.
3. Copy the key (starting with `sk-ant-admin01-`; it is shown once) and store it as the eramba secret.

Claude Console Admin keys have no selectable scopes: every key has full access to the Admin API, including writes. The script only sends `GET` requests to the three endpoints above, but treat the key as highly privileged, store it only in eramba's secrets and rotate it before it expires. Regular (workspace) API keys cannot call the Admin API.

## 6. Setup in eramba

1. Create the secret in **Settings → Application Configuration → Automation Secrets**: `anthropic_admin_key` (the Admin API key).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the members and roles with **Settings → Members** and each workspace's members.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email` or `id` (the stable `user_…` ID). Must exist on every member; no fallback. |
| `ROLES` | `['org_role', 'workspaces']` | Access listed as roles. `[]` lists members only and skips the workspace calls. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero members is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 members; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |
| `MAX_OBJECTS` | `2000` | 1–10000 workspaces, members per workspace, or roles per member; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried when the `retry-after` wait fits the budget. Only `GET https://api.anthropic.com/v1/organizations/users` and `/v1/organizations/workspaces` requests are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secret `anthropic_admin_key` |
| `HTTP 401` | The key is invalid, expired or archived, or it is a regular API key instead of an Admin key |
| `HTTP 403` | The key cannot read these endpoints (for Claude Enterprise keys, the `read:members` scope is missing, or `workspaces` is selected) |
| `HTTP 404` on workspaces | The organization has no workspace endpoints (Claude Enterprise); set `ROLES` to `['org_role']` |
| Two accounts share the same key | Two members have the same email ignoring case; set `USER_KEY` to `id` |
| Workspace member not in the organization list | Members changed during the pull; retry later |
| Throttling or time limit | Schedule the pull outside busy hours or set `ROLES` to `['org_role']` |

## 10. Customising

Adjust the documented variables. Other access types (API keys, service accounts, invites) need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the Admin key in **Settings → Admin keys**, and remove the secret.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [Admin API](https://platform.claude.com/docs/en/manage-claude/admin-api), [Admin API keys](https://platform.claude.com/docs/en/manage-claude/admin-api-keys), [list users](https://platform.claude.com/docs/en/api/admin-api/users/list-users), [list workspaces](https://platform.claude.com/docs/en/api/admin-api/workspaces/list-workspaces), [list workspace members](https://platform.claude.com/docs/en/api/admin-api/workspace_members/list-workspace-members), [workspaces and roles](https://platform.claude.com/docs/en/manage-claude/workspaces), [API versioning](https://platform.claude.com/docs/en/api/versioning).
