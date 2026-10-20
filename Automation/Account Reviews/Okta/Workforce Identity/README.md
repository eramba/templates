---
id: okta-account-review
name: Okta Accounts and Assigned Access
version: 0.1.0
status: draft
technology: Okta Workforce Identity
vendor: Okta
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - okta_org_url
  - okta_api_token
variables:
  - USER_KEY
  - INCLUDE_DEPROVISIONED
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Okta Accounts and Assigned Access

**Technology:** Okta Workforce Identity.

Send Okta users and their assigned access to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Okta users to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Okta login by default; configurable to email or user ID |
| `description` | Display name (or first and last name), Okta status and user ID |
| `roles` | `Group: <name> [<group id>]` for every group, Okta, app and built-in groups included |
| | `Application: <label> [<app id>]` for every application assignment, direct or through a group |
| | `Admin role: <label> [<type>]` for standard admin roles, `[<role id>]` for custom roles, direct or through a group |

All users are included, suspended and locked ones too. Okta's user list hides deactivated (`DEPROVISIONED`) users; they are fetched separately unless `INCLUDE_DEPROVISIONED` is off.

## 3. Coverage

One Okta org per automation. Not covered: resource sets and targets of admin roles, application-level permissions inside each app, devices, API service integrations, and Okta Identity Governance entitlements. An application assignment shows who can sign in, not what they can do inside the app.

Access is collected per group and per application, so the number of calls grows with groups and applications, not with users. Admin roles of groups are read for Okta groups only, the only type that can hold them.

Any collection error, access assigned to a user outside the collected list, or limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An Okta administrator account dedicated to eramba with the **Read-only Administrator** role, and an API token created by that account.
- HTTPS reach from eramba to your Okta org.

## 5. Setup on Okta

1. **Admin Console → Directory → People → Add person**: a dedicated service account for eramba.
2. **Security → Administrators → Add administrator**: give it **Read-only Administrator**.
3. Sign in as that account. **Security → API → Tokens → Create token**, and copy the value.
4. Note your org URL, e.g. `https://example.okta.com` (not the `-admin` URL).

An API token acts with the permissions of the account that created it and expires after 30 days without use. The automation's regular pulls keep it active; rotate it with your credential process.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `okta_org_url` and `okta_api_token`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the accounts and roles with Okta.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `login` | Account identifier: `login`, `email` or `id`. Must exist on every user; no fallback. |
| `INCLUDE_DEPROVISIONED` | `true` | Also list deactivated users. Turn off to review active, suspended and locked users only. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 users; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 groups, applications or members per list; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Okta rate limits are respected (`X-Rate-Limit-Reset`) when the wait fits the budget. Only `GET https://<org>/api/v1/` requests are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, logins or tokens. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secrets with exactly these names |
| `The Okta org URL must look like…` | Use `https://<your-org>.okta.com` without a path |
| `HTTP 401` | The token is invalid, revoked or expired; create a new one |
| `HTTP 403` | The token's account lacks the Read-only Administrator role |
| Access assigned to a user outside the population | Users changed during the pull; retry later |
| Throttling | Okta rate limit reached; schedule the pull outside busy hours |
| Time or collection limit | Many groups or applications; raise `MAX_OBJECTS` or split the review |

## 10. Customising

Adjust the documented variables. Other access types need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, revoke the API token, remove the service account and the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |

References: [users](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/User/), [groups](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/Group/), [applications](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/Application/), [role assignments](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/RoleAssignmentAUser/), [pagination](https://developer.okta.com/docs/api/#pagination), [rate limits](https://developer.okta.com/docs/reference/rl-best-practices/).
