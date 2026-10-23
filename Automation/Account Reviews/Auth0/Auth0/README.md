---
id: auth0-account-review
name: Auth0 – Accounts
version: 0.1.1
status: draft
technology: Auth0
vendor: Auth0 (Okta)
eramba_module: Account Review Feeds
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

# Auth0 – Accounts

**Technology:** Auth0.

Sends eramba every user account of one Auth0 tenant and the access each one has: its roles and its organization memberships. Reviewers then mark in eramba which accounts and accesses should stay and which should be removed; nothing is changed in Auth0.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Auth0 user account, identified by its email, for example `ana.garcia@example.com` (or its user ID with `USER_KEY`) |
| **Connects to** | Your Auth0 tenant, through Auth0's official Management API. Read-only |
| **Permission needed** | A machine-to-machine application authorized for the Auth0 Management API with `read:users` and `read:roles`, plus `read:organizations` and `read:organization_members` when organizations are listed (§5). A tenant administrator creates it |
| **Included** | Every user of the tenant, blocked ones too |
| **Not included** | Permissions given directly to users (outside roles); roles given inside an organization; application metadata used for authorization; other tenants (one per automation); tenants with more than 1000 users |
| **Size limit** | At most 1000 users, the most Auth0's user list returns. A larger tenant stops the run instead of publishing part of it; this version does not use Auth0's user export |
| **Roles listed** | Roles and organization memberships |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Role** | An Auth0 role assigned to the person. Roles are what applications that sign in with Auth0 usually check to decide what a person may do | `Role: Admin [<id>]` |
| **Organization** | The person is a member of this Auth0 organization, which groups the users of one customer or business unit | `Organization: Acme Corp [<id>]` |

```
Account:     ana.garcia@example.com
Description: Ana García; active; id=auth0|64f1…
Roles:       Organization: Acme Corp [org_Ab12…]
             Role: Admin [rol_Cd34…]
```

The codes in square brackets are Auth0's identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives the name, whether the account is *active* or *blocked*, and the user ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed. Users without an email (some social or passwordless sign-ins) stop the run with the default key; use `user_id` then.

A row shows *that* Ana has the *Admin* role, not what that role allows in each application.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each Auth0 role allows | Not covered by any automation yet. Roles show what a person was granted in Auth0, not how each application uses them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Choose `USER_KEY` so both feeds hold the same value, usually the work email |

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

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
