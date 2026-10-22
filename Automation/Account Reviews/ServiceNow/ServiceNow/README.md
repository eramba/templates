---
id: servicenow-account-review
name: ServiceNow Accounts and Assigned Access
version: 0.1.1
status: draft
technology: ServiceNow
vendor: ServiceNow
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - servicenow_instance
  - servicenow_client_id
  - servicenow_client_secret_b64
  - servicenow_username
  - servicenow_password_b64
variables:
  - AUTH
  - USER_KEY
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_ASSIGNMENTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# ServiceNow Accounts and Assigned Access

**Technology:** ServiceNow AI Platform (Now Platform) instance.

Send ServiceNow users with their roles (direct and inherited) and group memberships to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of users of one ServiceNow instance to review: who has an account, whether it is active or locked out, which roles they hold and which groups they belong to. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only: it only reads the Table API.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | `email` by default; configurable to `user_name` (User ID) or `sys_id` |
| `description` | Name, `active` or `inactive`, `locked out` when set, and `sys_id` |
| `roles` | `Role: <role name> [<role sys_id>]` for every role granted directly |
| | `Role (inherited): <role name> [<role sys_id>]` for every role inherited from a group or a containing role |
| | `Group: <group name> [<group sys_id>]` for every group membership |

All users are included, inactive and locked-out ones too. A role both granted directly and inherited appears twice, once with each kind.

## 3. Coverage

Collected through the Table API from `sys_user`, `sys_user_has_role` and `sys_user_grmember`, ordered by `sys_id`, with `sysparm_limit`/`sysparm_offset` pages of 5000 records. When ServiceNow returns `X-Total-Count`, it must stay the same on every page and match the records received; without it, a list ends at its first short page. `sys_id`s must keep increasing, so records shifting between pages stop the run.

Rows hidden by ACLs make the received count lower than `X-Total-Count`, and the run stops instead of publishing a partial list.

Not covered: delegated roles with a validity window beyond what `sys_user_has_role` shows, group roles that no member holds, domain separation scoping, contextual (application) roles managed outside `sys_user_has_role`, and other instances. Group active state and role elevation (`security_admin` elevation) are not shown.

**To validate on a real instance:** the minimum read access below, `X-Total-Count` behaviour on your release, the client credentials grant on your release, and run time on instances with many users (inherited roles can reach hundreds of thousands of records; each page is one request).

Any collection error, an assignment for a user outside the collected list, or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A ServiceNow administrator (`admin`, plus `oauth_admin` for OAuth) who can create an integration user and an OAuth application.
- The instance host, e.g. `example.service-now.com` (also accepted: `*.servicenowservices.com`). Custom instance URLs need the script's host check adjusted.

## 5. Setup on ServiceNow

ServiceNow's REST APIs accept OAuth 2.0 bearer tokens and basic authentication. **OAuth with the client credentials grant is preferred** (no password stored in eramba; tokens expire). Use basic authentication only if your release or policy does not allow client credentials.

1. **Integration user** (`sys_user`): a dedicated user, e.g. `integration.eramba`, with **Web service access only** selected. Give it read access only:

| Access | Why |
|---|---|
| Read on `sys_user` (`sys_id`, `user_name`, `email`, `name`, `active`, `locked_out`) | Users |
| Read on `sys_user_has_role` and `sys_user_role.name` | Roles of each user, direct and inherited |
| Read on `sys_user_grmember` and `sys_user_group.name` | Group memberships (only if `ROLES` contains `groups`) |

   The simplest out-of-the-box combination is the `user_admin` role together with `snc_read_only`, which blocks every write for that user. A custom role with read ACLs on the tables above is narrower; check that it sees every row (see §9).
2. **OAuth (preferred).** In **System Properties** (global scope) create `glide.oauth.inbound.client.credential.grant_type.enabled` = `true`. Then **System OAuth → Application Registry → New → Create an OAuth API endpoint for external clients**, set the grant type to **Client Credentials** and the **OAuth Application User** to the integration user. Copy the client ID and client secret; base64-encode the secret on one line. On releases that manage inbound integrations in the **Machine Identity Console**, create the client credentials integration there instead.
3. **Basic authentication (alternative).** Set a strong password on the integration user; base64-encode it on one line.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `servicenow_instance` (host only), then for `AUTH` = `oauth` `servicenow_client_id` and `servicenow_client_secret_b64`, or for `AUTH` = `basic` `servicenow_username` and `servicenow_password_b64`. Secrets of the other method are not needed.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users, roles and groups with ServiceNow.
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `AUTH` | `oauth` | `oauth` (client credentials grant) or `basic`. |
| `USER_KEY` | `email` | Account identifier: `email`, `user_name` or `sys_id`. Users without email (often system or integration users) abort the run; use `user_name` then. |
| `ROLES` | `['roles', 'groups']` | Access listed as roles. `[]` lists users only; tables for kinds not listed are not read. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `10000` | 1–20000 users; exceeding it aborts instead of truncating. |
| `MAX_ASSIGNMENTS` | `300000` | 1–1000000 records per list (role assignments, group memberships); exceeding it aborts. |

Fixed limits: 5000 records per page, 200 seconds of collection, 4 MB per response, 8 MiB of feed, 48 MiB of memory. Rate limits (HTTP 429) are respected when the wait fits the budget. Only `POST https://<instance>/oauth_token.do` (OAuth only) and `GET https://<instance>/api/now/table/sys_user`, `sys_user_has_role` and `sys_user_grmember` are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never emails, names or tokens. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names for the chosen `AUTH` and that values are base64 on one line |
| `HTTP 401` on `oauth_token.do` | Client credentials property not `true`, wrong client ID or secret, or no OAuth Application User |
| `HTTP 401` on a table | Wrong user or password, the user is locked out or inactive, or a REST API access policy blocks the token |
| `HTTP 403` | The integration user cannot read one of the tables in §5 |
| `list is incomplete (N of M)` | ACLs hide rows from the integration user; grant read on every row, or records changed during the pull (retry) |
| Account key is missing | Some users have no email; set `USER_KEY` to `user_name` |
| Time budget exceeded | Too many role records for one run; set `ROLES` to `['roles']` or `['groups']`, or contact eramba about larger instances |
| Access outside the collected users | Users changed during the pull; retry later |

## 10. Customising

Adjust the documented variables. Filtering users (for example only users with roles) or adding other access tables needs new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the OAuth application, deactivate the integration user and delete the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [Table API](https://www.servicenow.com/docs/csh?topicname=c_TableAPI.html&version=latest), [REST API authentication](https://www.servicenow.com/docs/csh?topicname=c_RESTAPI.html&version=latest), [OAuth client credentials grant](https://www.servicenow.com/docs/csh?topicname=client-credentials.html&version=latest), [read-only role `snc_read_only`](https://www.servicenow.com/docs/csh?topicname=r_ReadOnlyRole.html&version=latest), [web service access only users](https://www.servicenow.com/docs/csh?topicname=t_CreateAUser.html&version=latest).
