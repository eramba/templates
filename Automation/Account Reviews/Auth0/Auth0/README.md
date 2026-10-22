---
id: auth0-account-review
name: Auth0 Accounts and Assigned Access
version: 0.1.1
status: draft
technology: Auth0
vendor: Auth0 (Okta)
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - auth0_domain
  - auth0_client_id
  - auth0_client_secret_b64
variables:
  - USER_KEY
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Auth0 Accounts and Assigned Access

**Technology:** Auth0.

Send Auth0 users, their roles and organization memberships to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of users of one Auth0 tenant to review, for example the administrators and staff of an application that signs in with Auth0. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value; see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Email by default; configurable to the Auth0 user ID |
| `description` | Name, active or blocked, and user ID |
| `roles` | `Role: <name> [<role id>]` for every role assigned to the user |
| | `Organization: <display name> [<org id>]` for every organization membership |

All users are included, blocked ones too.

## 3. Coverage

**At most 1000 users.** Auth0's user list returns the first 1000 results; with more users the run stops with a clear error instead of publishing part of the tenant. Larger tenants need Auth0's user export job, which this version does not use.

Not covered: permissions assigned directly to users (outside roles), roles assigned inside an organization, application-specific metadata (`app_metadata`) used as authorization, and other tenants. Roles show what the user was granted in Auth0, not how each application uses them.

Any collection error, a role or organization member outside the collected users, or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An Auth0 tenant administrator who can create a machine-to-machine application.
- The tenant's **canonical domain** (e.g. `example.eu.auth0.com`), not a custom domain: the Management API is only served there.

## 5. Setup on Auth0

1. **Applications → Applications → Create Application**: type **Machine to Machine**, named for eramba.
2. Authorize it for the **Auth0 Management API** with only these scopes:

| Scope | Why |
|---|---|
| `read:users` | Users |
| `read:roles` | Roles and the users of each role |
| `read:organizations` | Organizations (only if `ROLES` contains `organizations`) |
| `read:organization_members` | Members of each organization (only if `ROLES` contains `organizations`) |

3. In the application's **Settings**, copy the **Domain**, **Client ID** and **Client Secret**. Base64-encode the client secret on one line.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `auth0_domain`, `auth0_client_id`, `auth0_client_secret_b64`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users and roles with Auth0.
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email` or `user_id`. Users without email (some social or passwordless connections) abort the run; use `user_id` then. |
| `ROLES` | `['roles', 'organizations']` | Access listed as roles. Remove `organizations` if the tenant does not use Organizations; `[]` lists users only. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_OBJECTS` | `1000` | 1–10000 roles or organizations per list; exceeding it aborts instead of truncating. |

Fixed limits: 1000 users, 200 seconds of collection, 4 MB per response, 8 MiB of feed, 48 MiB of memory. Auth0 rate limits are respected (`X-RateLimit-Reset`) when the wait fits the budget. Only the token request and `GET https://<domain>/api/v2/` are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never emails, IDs or tokens. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret |
| `HTTP 401` on the token | Wrong client ID or secret, or the application is not authorized for the Management API |
| `HTTP 403` | A scope from §5 is missing; after roles are collected, it is usually `read:organization_members` (needed as soon as the tenant has an organization) |
| `Auth0 reports N users; this feed supports up to 1000` | The tenant is too large for this version |
| Account key missing | Some users have no email; set `USER_KEY` to `user_id` |
| Access outside the collected users | Users changed during the pull; retry later |

## 10. Customising

Adjust the documented variables. Direct user permissions or organization roles need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the machine-to-machine application and the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); documents the `read:organization_members` scope, without which tenants with organizations fail with HTTP 403 |

References: [Management API access tokens](https://auth0.com/docs/secure/tokens/access-tokens/management-api-access-tokens), [users](https://auth0.com/docs/api/management/v2/users/get-users), [roles](https://auth0.com/docs/api/management/v2/roles/get-roles), [role users](https://auth0.com/docs/api/management/v2/roles/get-role-user), [organizations](https://auth0.com/docs/api/management/v2/organizations/get-organizations), [organization members](https://auth0.com/docs/api/management/v2/organizations/get-organization-members), [pagination](https://auth0.com/docs/api/management/v2#!/introduction/pagination), [rate limits](https://auth0.com/docs/troubleshoot/customer-support/operational-policies/rate-limit-policy).
