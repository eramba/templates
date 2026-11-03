---
id: auth0-account-review
name: Auth0 – Accounts
version: 0.2.0
status: tested
vendor: Okta
technology:
  - Auth0
eramba_module: Account Reviews
domains:
  - identity-access
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
eramba_version_tested: 3.31.0
last_tested: 2026-10-23
---

# Auth0 – Accounts

**Technology:** Auth0.

Sends eramba every user account of one Auth0 tenant and the access each one has: its roles, the organizations it belongs to and its roles inside each organization. Reviewers then mark in eramba which accounts and accesses should stay and which should be removed; nothing is changed in Auth0.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Auth0 user account, identified by its email, for example `ana.garcia@example.com` (or its user ID with `USER_KEY`) |
| **Connects to** | Your Auth0 tenant, through Auth0's official Management API. Read-only |
| **Permission needed** | A machine-to-machine application authorized for the Auth0 Management API with `read:users` and `read:roles`, plus `read:organizations`, `read:organization_members` and `read:organization_member_roles` when organizations are listed (§5). A tenant administrator creates it |
| **Included** | Every user of the tenant, blocked ones too |
| **Not included** | • Permissions given directly to users (outside roles)<br>• Roles and organization roles a person gets through an enterprise group, not assigned to them directly<br>• What each role allows in each application<br>• Application metadata used for authorization<br>• Other tenants (one per automation)<br>• Tenants with more than 1000 users |
| **Size limit** | At most 1000 users, the most Auth0's user list returns. A larger tenant stops the run instead of publishing part of it; this version does not use Auth0's user export |
| **Roles listed** | Roles, organization memberships and organization roles |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../docs/account-reviews.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds, written with the names shown in the Auth0 dashboard. This automation lists three kinds:

| Kind | What it means for the person | How reviewers see it | Where to change it in Auth0 |
|---|---|---|---|
| **Role** | An Auth0 role assigned to the person for the whole tenant. Applications that sign in with Auth0 usually check roles to decide what a person may do | `Role: Editor` | **User Management → Users →** the person **→ Roles**, or **User Management → Roles →** the role **→ Users** |
| **Organization** | The person is a member of this Auth0 organization, which groups the users of one customer, partner or business unit | `Organization: Lab Partners` | **Organizations →** the organization **→ Members** |
| **Organization role** | A role the person holds only inside one organization | `Organization role: Admin (in organization Lab Partners)` | **Organizations →** the organization **→ Members →** the person **→ Roles** |

```
Account:     ana.garcia@example.com
Description: Ana García; active; user ID auth0|64f1…
Roles:       Organization role: Admin (in organization Lab Partners)
             Organization: Lab Partners
             Role: Editor
```

The description gives the name, whether the account is *active* or *blocked*, and the user ID, which you can paste in the Auth0 user search.

Organization names (the *display name*) can repeat in Auth0. When two organizations share one, each also shows its identifier name, which Auth0 keeps unique, for example `Organization: Acme (acme-eu)` and `Organization: Acme (acme-us)`. Renaming a role or an organization changes the role text, so the next Differential review shows the old name as removed and the new one as added.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed. Users without an email (some social or passwordless sign-ins) stop the run with the default key; use `user_id` then.

A row shows *that* Ana has the *Editor* role, not what that role allows in each application.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each Auth0 role allows | Not covered by any automation yet. Roles show what a person was granted in Auth0, not how each application uses them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20-%20Current%20employees/) or [Workday – Current employees](../../Workday/Workday%20-%20Current%20employees/). Choose `USER_KEY` so both feeds hold the same value, usually the work email |

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
| `read:organizations` | Organizations (only if `ROLES` contains `organizations` or `organization_roles`) |
| `read:organization_members` | Members of each organization (only if `ROLES` contains `organizations` or `organization_roles`) |
| `read:organization_member_roles` | Roles of each member inside an organization (only if `ROLES` contains `organization_roles`) |

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
| `ROLES` | `['roles', 'organizations', 'organization_roles']` | Access listed as roles (§2). Organizations that are not used cost nothing; `[]` lists users only. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_OBJECTS` | `1000` | 1–10000 roles or organizations per list; exceeding it aborts instead of truncating. |

Fixed limits: 1000 users, 200 seconds of collection, 4 MB per response, 8 MiB of feed, 48 MiB of memory. Auth0 rate limits are respected (`X-RateLimit-Reset`) when the wait fits the budget. Only the token request and `GET https://<domain>/api/v2/` are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../docs/account-reviews.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret |
| `HTTP 401` on the token | Wrong client ID or secret, or the application is not authorized for the Management API |
| `HTTP 403` | A scope from §5 is missing. After roles are collected it is usually `read:organization_members` or `read:organization_member_roles`, needed as soon as the tenant has an organization. Add the scope, or remove `organization_roles` from `ROLES` |
| `Auth0 reports N users; this feed supports up to 1000` | The tenant is too large for this version |
| `Auth0 reports N roles, more than the limit…` / `Auth0 has more than N organizations…` | Raise `MAX_OBJECTS` (up to 10000) |
| Account key missing | Some users have no email; set `USER_KEY` to `user_id` |
| Access outside the collected users | Users changed during the pull; retry later |

## 10. Customising

Adjust the documented variables. Direct user permissions or organization roles received through groups need new collection code. Section 4 is a copy of the [shared code blocks](../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the machine-to-machine application and the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); documents the `read:organization_members` scope, without which tenants with organizations fail with HTTP 403 |
| 0.2.0 | Roles written for reviewers, with the names shown in the Auth0 dashboard and no IDs (`Role: Editor`, `Organization: Lab Partners`); organizations with the same name also show their identifier name. New kind *Organization role* (`organization_roles`, scope `read:organization_member_roles`). A refused request (HTTP 403) names the missing scope; limit errors name `MAX_OBJECTS`. Tested on eramba 3.31.0 |

References: [Management API access tokens](https://auth0.com/docs/secure/tokens/access-tokens/management-api-access-tokens), [users](https://auth0.com/docs/api/management/v2/users/get-users), [roles](https://auth0.com/docs/api/management/v2/roles/get-roles), [role users](https://auth0.com/docs/api/management/v2/roles/get-role-user), [organizations](https://auth0.com/docs/api/management/v2/organizations/get-organizations), [organization members and their roles](https://auth0.com/docs/api/management/v2/organizations/get-organization-members), [pagination](https://auth0.com/docs/api/management/v2#!/introduction/pagination), [rate limits](https://auth0.com/docs/troubleshoot/customer-support/operational-policies/rate-limit-policy).
