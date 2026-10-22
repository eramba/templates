---
id: slack-account-review
name: Slack Members and Assigned Access
version: 0.1.1
status: draft
technology: Slack
vendor: Slack
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - slack_token
variables:
  - USER_KEY
  - ROLES
  - INCLUDE_BOTS
  - TEAM_ID
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Slack Members and Assigned Access

**Technology:** Slack.

Send the members of a Slack workspace and their workspace role and user groups to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Slack members to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Member email (`profile.email`) by default; configurable to the Slack member ID |
| `description` | Real name (or display name), kind (`member`, `guest` or `bot`), state (`active`, `invited` or `deactivated`) and member ID |
| `roles` | `Workspace role: <label> [<id>]`, one per member, the highest that applies: `Primary Owner [primary_owner]`, `Owner [owner]`, `Admin [admin]`, `Single-channel guest [ultra_restricted]`, `Multi-channel guest [restricted]`, `Member [member]` or `Bot [bot]` |
| | `User group: <name> [<user group id>]` for every enabled user group the member belongs to (optional, `user_groups`) |

Deactivated members (`deleted: true`) are listed with state `deactivated`, as Slack keeps them in the member list. Invited members who have not signed in yet are listed as `invited`.

**Bots and app users:** bot users (`is_bot`), workflow bots (`is_workflow_bot`) and Slackbot (`USLACKBOT`, for which Slack reports `is_bot: false`) are excluded by default: they have no email and no person behind them. Set `INCLUDE_BOTS` (with `USER_KEY = id`) to list them with `Workspace role: Bot [bot]`. Members flagged `is_app_user` are people who authorised an app; they are ordinary members and are always listed.

## 3. Coverage

One Slack workspace per automation (on Enterprise Grid, one workspace of the org per automation, with `TEAM_ID`). Members of other organisations visible through Slack Connect (`is_stranger`) are excluded and counted: review them in their own organisation.

Not covered: channel memberships and channel managers, Enterprise Grid org-level roles (Org Owner, Org Admin) and custom admin roles, app installations and their scopes, Slack Connect channels, and SCIM or identity-provider group sync. A user group shows a mention group, not a permission; it is useful when user groups mirror teams or are used to manage channel access.

Calls: `users.list` with 200 members per page, then (only with `user_groups`) one `usergroups.list` and one `usergroups.users.list` per enabled user group. These are Tier 2 methods (about 20 calls per minute); Slack throttling (HTTP 429) is retried with `Retry-After` when the wait fits the budget. Large workspaces with many user groups can exceed the 200-second budget; the run then aborts.

Any collection error, a member appearing twice, a user group member outside the collected list, a member without email (with `USER_KEY = email`), or a limit aborts the run and nothing is published.

To validate on a real workspace: that deactivated members keep `profile.email`, and the exact behaviour of `usergroups.users.list` for very large user groups (the method documents no pagination).

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A Slack workspace on which you may create and install an internal app (Workspace Owner or Admin, or app approval).
- User groups are a paid-plan feature: on free workspaces keep `ROLES = ['workspace_role']`.
- HTTPS reach from eramba to `slack.com`.

## 5. Setup on Slack

1. Go to **api.slack.com/apps → Create New App → From scratch**, name it e.g. *eramba account review*, and choose the workspace.
2. **OAuth & Permissions → Scopes → Bot Token Scopes**: add only
   - `users:read`: list members and their role flags;
   - `users:read.email`: read `profile.email`, the default account key (without it Slack omits the email);
   - `usergroups:read`: only if `ROLES` includes `user_groups`.
3. **Install to Workspace** and approve. Copy the **Bot User OAuth Token** (`xoxb-…`).
4. Do not enable token rotation for this app: the script does not refresh tokens.

On Enterprise Grid with an org-level app, install it on the org, then set `TEAM_ID` to the workspace ID (`T…`) to review; Slack requires `team_id` with org tokens. A user token (`xoxp-…`) with the same user scopes also works but acts as that person; prefer the bot token.

## 6. Setup in eramba

1. Create the secret in **Settings → Application Configuration → Automation Secrets**: `slack_token` (the `xoxb-…` value).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the members and roles with **Workspace settings → Manage members**.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email` (`profile.email`) or `id` (member ID, e.g. `U0123ABCD`). Must exist on every listed member; no fallback. |
| `ROLES` | `['workspace_role']` | Access listed as roles: `workspace_role`, `user_groups`. `[]` lists members only. |
| `INCLUDE_BOTS` | `false` | Also list bots, workflow bots and Slackbot. Requires `USER_KEY = id`. |
| `TEAM_ID` | `''` | Enterprise Grid org-level token only: workspace ID to list. Leave empty with a workspace token. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 listed members (excluded bots and external members count too); exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 user groups, and members per user group; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages, 8 MiB of feed, 48 MiB of memory. Only `GET https://slack.com/api/users.list`, `usergroups.list` and `usergroups.users.list` requests are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, emails or tokens. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret 'slack_token' is missing` | Create the secret with exactly this name |
| `The Slack token must be…` | Paste the Bot User OAuth Token (`xoxb-…`), without spaces |
| `error 'invalid_auth'`, `'token_revoked'`, `'account_inactive'` | The token is wrong or the app was uninstalled; reinstall and copy the new token |
| `error 'missing_scope'` | Add the scopes in §5 and reinstall the app |
| `error 'missing_argument'` or `'team_access_not_granted'` | Org-level token: set `TEAM_ID` and grant the app access to that workspace |
| `A member has no email address` | Add `users:read.email` and reinstall, or set `USER_KEY = id` |
| User group member outside the collected population | Members changed during the pull; retry later |
| Throttling | Slack rate limit reached; schedule the pull outside busy hours |
| Time or collection limit | Many members or user groups; raise the limits or drop `user_groups` |

## 10. Customising

Adjust the documented variables. Other access types (channel membership, org-level roles) need new collection code and other scopes. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, uninstall the Slack app (which revokes its token) and delete the secret.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [users.list](https://docs.slack.dev/reference/methods/users.list), [user object](https://docs.slack.dev/reference/objects/user-object), [usergroups.list](https://docs.slack.dev/reference/methods/usergroups.list), [usergroups.users.list](https://docs.slack.dev/reference/methods/usergroups.users.list), [pagination](https://docs.slack.dev/apis/web-api/pagination), [rate limits](https://docs.slack.dev/apis/web-api/rate-limits), [scopes](https://docs.slack.dev/reference/scopes).
