---
id: slack-account-review
name: Slack – Accounts
version: 0.1.1
status: draft
technology: Slack
vendor: Slack
eramba_module: Account Review Feeds
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

# Slack – Accounts

**Technology:** Slack.

Sends eramba every member of your Slack workspace and the access each one has: its workspace role and, optionally, its user groups. Reviewers then mark in eramba which members and accesses should stay and which should be removed; nothing is changed in Slack.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One member of one Slack workspace, identified by its email, for example `ana.garcia@example.com` (or its Slack member ID with `USER_KEY`) |
| **Connects to** | Your Slack workspace, through Slack's official Web API. Read-only |
| **Permission needed** | A Slack app installed in the workspace with the bot token scopes `users:read` and `users:read.email`, plus `usergroups:read` only if you list user groups. A workspace admin installs it (§5) |
| **Included** | Every member: full members, guests and invited members who have not signed in, plus deactivated members. Members of other organizations seen through Slack Connect are left out and counted. Bots, workflow bots and Slackbot are left out unless you set `INCLUDE_BOTS` |
| **Not included** | Channel memberships and channel managers; Enterprise Grid organization roles and custom admin roles; app installations and their scopes; Slack Connect channels; SCIM or identity-provider group sync; other workspaces (one per automation, with `TEAM_ID` on Enterprise Grid) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. On Enterprise Grid, use one feed per workspace (`TEAM_ID`) for more |
| **Roles listed** | Workspace role and, optionally, user groups |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's Slack account. Its roles are every access that account holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Workspace role** | The highest role the person has in the workspace: *Primary Owner*, *Owner*, *Admin*, *Single-channel guest*, *Multi-channel guest*, *Member* or, with `INCLUDE_BOTS`, *Bot*. One per row | `Workspace role: Admin [admin]` |
| **User group** | The person belongs to this enabled user group. A user group is a mention group, not a permission, but it often mirrors a team or manages channel access. Listed only when you add `user_groups` to `ROLES` | `User group: security-team [<id>]` |

```
Account:     ana.garcia@example.com
Description: Ana García; member; active; id=U0123ABCD
Roles:       Workspace role: Admin [admin]
```

The text in square brackets is Slack's identifier. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, whether the account is a `member`, `guest` or `bot`, whether it is `active`, `invited` or `deactivated`, and the member ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the members are listed.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what someone can do in Slack | In Slack, permissions sit on the account itself (its workspace role), so this account review already shows them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Deactivated members of leavers stay listed as deactivated |

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
| `MAX_USERS` | `2000` | 1–5000 listed members (excluded bots and external members count too); exceeding it aborts instead of truncating. For more members on Enterprise Grid, use one feed per workspace (`TEAM_ID`). |
| `MAX_OBJECTS` | `2000` | 1–10000 user groups, and members per user group; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages, 8 MiB of feed, 48 MiB of memory. Only `GET https://slack.com/api/users.list`, `usergroups.list` and `usergroups.users.list` requests are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result. The log also counts the members left out: Slack Connect members and bots.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [users.list](https://docs.slack.dev/reference/methods/users.list), [user object](https://docs.slack.dev/reference/objects/user-object), [usergroups.list](https://docs.slack.dev/reference/methods/usergroups.list), [usergroups.users.list](https://docs.slack.dev/reference/methods/usergroups.users.list), [pagination](https://docs.slack.dev/apis/web-api/pagination), [rate limits](https://docs.slack.dev/apis/web-api/rate-limits), [scopes](https://docs.slack.dev/reference/scopes).
