---
id: anthropic-account-review
name: Anthropic Organization Members and Workspace Access
version: 0.1.1
status: draft
technology: Claude Console (Anthropic API)
vendor: Anthropic
eramba_module: Account Review Feeds
trigger: Account Review pull
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

# Anthropic Organization Members and Workspace Access

**Technology:** Claude Console (Anthropic API, `platform.claude.com`).

Send the members of a Claude Console organization, their organization role and their workspace memberships to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Claude Console organization members to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Member email by default; configurable to the Anthropic user ID (`user_…`) |
| `description` | Name (or email), organization role and user ID |
| `roles` | `Organization role: <Role> [<role>]`, e.g. `Organization role: Admin [admin]` or `Organization role: Claude code user [claude_code_user]` (`org_role`) |
| | `Workspace: <workspace name> [<workspace id> / <workspace role>]`, e.g. `Workspace: Production [wrkspc_01… / workspace_developer]` (`workspaces`) |

Organization roles are `user`, `claude_code_user`, `developer`, `billing` and `admin` in Console organizations (`owner` and `primary_owner` also exist). Workspace roles are `workspace_user`, `workspace_restricted_developer`, `workspace_developer`, `workspace_admin` and `workspace_billing`. Every member returned by the organization users list is included.

## 3. Coverage

One Claude Console organization per automation (the organization of the Admin API key). Calls, all `GET`, with the `x-api-key` and `anthropic-version: 2023-06-01` headers:

- `/v1/organizations/users`: organization members and their role.
- `/v1/organizations/workspaces`: active workspaces, without archived workspaces and without the Default Workspace.
- `/v1/organizations/workspaces/{workspace_id}/members`: members of each workspace and their workspace role.

The **Default Workspace** has no member list: access to it follows the organization role, which the `org_role` roles already show. Organization admins automatically hold Workspace Admin, and billing members Workspace Billing, in every workspace; whether the members endpoint lists those inherited roles is to validate on a real organization. The organization role is always in the feed when `org_role` is selected, so this inherited access stays visible.

Not covered: pending invites, API keys (workspace, personal and service account keys), service accounts, Admin API keys, and what each member does inside a workspace. **Claude Enterprise** (`claude.ai`) organizations: only the members endpoint applies to them, with a key carrying the `read:members` or `read:org_audit` scope; set `ROLES` to `['org_role']` (to validate on a real Enterprise organization). Claude Platform on AWS offers no member endpoints and is not supported.

Lists are read with `limit=100` and the `after_id` cursor until `has_more` is false. A missing or repeated cursor, a duplicate member, a workspace member missing from the organization list, any other API error or a limit aborts the run and nothing is published.

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
| `MAX_USERS` | `2000` | 1–10000 members; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 workspaces, members per workspace, or roles per member; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried when the `retry-after` wait fits the budget. Only `GET https://api.anthropic.com/v1/organizations/users` and `/v1/organizations/workspaces` requests are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, emails or keys. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [Admin API](https://platform.claude.com/docs/en/manage-claude/admin-api), [Admin API keys](https://platform.claude.com/docs/en/manage-claude/admin-api-keys), [list users](https://platform.claude.com/docs/en/api/admin-api/users/list-users), [list workspaces](https://platform.claude.com/docs/en/api/admin-api/workspaces/list-workspaces), [list workspace members](https://platform.claude.com/docs/en/api/admin-api/workspace_members/list-workspace-members), [workspaces and roles](https://platform.claude.com/docs/en/manage-claude/workspaces), [API versioning](https://platform.claude.com/docs/en/api/versioning).
