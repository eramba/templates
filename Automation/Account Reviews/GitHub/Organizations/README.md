---
id: github-account-review
name: GitHub Organization Members and Assigned Access
version: 0.1.1
status: draft
technology: GitHub (GitHub.com, Free to Enterprise Cloud)
vendor: GitHub
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - github_token
  - github_app_private_key_b64
variables:
  - ORGANIZATIONS
  - AUTH
  - APP_CLIENT_ID
  - USER_KEY
  - ROLES
  - INCLUDE_OUTSIDE_COLLABORATORS
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# GitHub Organization Members and Assigned Access

**Technology:** GitHub.com organizations, Free, Team and Enterprise Cloud plans.

Send the members and outside collaborators of one or more GitHub organizations, with their organization role and teams, to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of people who can reach your GitHub organizations. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value; see [review types](../../README.md#review-types).

GitHub accounts belong to people, not to your company: a login such as `octo-dev` is not an email and the API never returns a member's work email. Exit reviews only match when both feeds hold the same value, so either use `USER_KEY = 'saml_name_id'` or `'email'` when your organizations use **organization-level SAML SSO** (§7), or keep `login` and compare with an employee feed that contains GitHub logins.

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | GitHub login by default; configurable to the numeric user ID, or to the SAML NameID or SAML email linked to the account |
| `description` | Login, account type (`User` or `Bot`), user ID and the organizations with the relationship in each, e.g. `octo-dev; User; id=123; example-org: owner, second-org: member` |
| `roles` | `Org role: owner [<org>]`, `Org role: member [<org>]` or `Org role: outside collaborator [<org>]` for each organization |
| | `Team: <org>/<team name> [<team id>]` for every team the account belongs to, directly or through a child team |

An account in several organizations is one row with the roles of all of them. Outside collaborators are listed when `INCLUDE_OUTSIDE_COLLABORATORS` is on; they have repository access without being members.

## 3. Coverage

The organizations in `ORGANIZATIONS` on GitHub.com (`api.github.com`). Owners and members are read with the `role` filter of the members list, outside collaborators with their own list, and teams with their member lists (which include members of child teams).

Not covered: pending invitations, billing managers, enterprise owners and enterprise members that belong to no listed organization, team maintainer roles, repository-level permissions and custom repository or organization roles, deploy keys, GitHub Apps and their installations, personal access tokens approved for the organization, GitHub Enterprise Server and GHE.com (data residency) hosts. GitHub.com has no disabled state for organization members: removed people are absent. 2FA status is not read (the `2fa_disabled` filter needs an owner).

`saml_name_id` and `email` keys read **organization-level** SAML identities through GraphQL (`externalIdentities`). Enterprise-level SAML and Enterprise Managed Users identities live on the enterprise account and are not read: use `login` (for Enterprise Managed Users, the login already carries the IdP-derived short code).

Any collection error, a team member that is not a listed member, an account listed twice, a missing identity for a SAML key, or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- HTTPS reach from eramba to `api.github.com`.
- Either a **GitHub App** installed on every listed organization (recommended: no user seat, one-hour tokens), or a **personal access token** of a dedicated account.
- The list of organization logins, as in `https://github.com/<org>`.

## 5. Setup on GitHub

**Option A, GitHub App (recommended, `AUTH = 'app'`)**

1. **Organization settings → Developer settings → GitHub Apps → New GitHub App** (or in the settings of a dedicated account). Disable the webhook.
2. **Permissions → Organization permissions → Members: Read-only**. No repository or account permissions. *Members: read* covers the members list with role filter, outside collaborators, teams, team members and, through GraphQL, SAML identities.
3. Allow installation **Only on this account** if all organizations are under it; otherwise **Any account**, and install it only where you need it.
4. **Generate a private key** (a `.pem` file) and note the **Client ID**.
5. **Install App** on every organization in `ORGANIZATIONS`. The app has no repository permissions, so the repository selection grants nothing it uses; choose the narrowest option offered.
6. Base64-encode the `.pem` on one line (`base64 -i key.pem | tr -d '\n'` on macOS, `base64 -w0 key.pem` on Linux) for the secret.

Each run creates a JWT valid for 9 minutes and, for each organization, an installation token that the script narrows to `members: read`; GitHub expires it after one hour.

**Option B, personal access token (`AUTH = 'token'`)**

- **Fine-grained token** of a dedicated account: **Resource owner** = the organization (one token per organization owner: a fine-grained token targets a single owner, so with several organizations use a classic token or the app), **Organization permissions → Members: Read-only**, no repository access. Organizations may need to approve it.
- **Classic token**: scope `read:org` only. Authorize it for SAML SSO (**Configure SSO**) on each organization that enforces SAML.

The account must be an **organization owner** for a complete list: concealed members, secret teams and SAML identities are only visible to owners. Its token can therefore read more than this script uses; keep it in eramba secrets only and set an expiry.

## 6. Setup in eramba

1. Create the secret of your `AUTH` method in **Settings → Application Configuration → Automation Secrets**: `github_app_private_key_b64` (option A) or `github_token` (option B).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Set `ORGANIZATIONS`, `AUTH` and, for the app, `APP_CLIENT_ID` in section 2.
4. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
5. Save, test the feed and compare the accounts and roles with **Organization → People** and **Teams**.
6. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `ORGANIZATIONS` | `['example-org']` | 1–20 organization logins. |
| `AUTH` | `'token'` | `token` (secret `github_token`) or `app` (secret `github_app_private_key_b64`). |
| `APP_CLIENT_ID` | `''` | With `AUTH = 'app'`: the app's Client ID (or numeric App ID). |
| `USER_KEY` | `login` | `login`, `id` (numeric, survives renames), `saml_name_id` or `email`. No fallback: see below. |
| `ROLES` | `['org_role', 'teams']` | Access listed as roles. `[]` lists accounts only (one members list per organization). |
| `INCLUDE_OUTSIDE_COLLABORATORS` | `true` | Also list outside collaborators. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 accounts; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 records per list (members, teams, team members, identities); exceeding it aborts. |

`saml_name_id` uses the NameID of the account's linked SAML identity, often the work email; `email` uses its primary SAML email (or the only one). Both require organization-level SAML SSO on every listed organization and a linked identity for **every** listed account, the same in all organizations. Outside collaborators never have one, so set `INCLUDE_OUTSIDE_COLLABORATORS = false` with these keys and review collaborators in a separate feed keyed by `login`. A login changes when the user renames the account; `id` does not.

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. The run stops when fewer than 5 requests remain in the GitHub rate limit (5,000 per hour for tokens). Allowed requests: `GET https://api.github.com/orgs/`, `POST https://api.github.com/graphql` (queries for SAML keys) and `POST https://api.github.com/app/installations/` (installation tokens).

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never logins, emails or tokens. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secret of your `AUTH` method with exactly that name |
| `HTTP 401` | Token expired or revoked, or wrong private key / `APP_CLIENT_ID` |
| `HTTP 403` | Missing *Members: read*, token not authorized for SAML SSO, fine-grained token not approved, or the rate limit was exhausted by other tools |
| `HTTP 404` | Wrong organization login, or the app is not installed on it |
| `GitHub API rate limit almost spent` | Retry after the hourly reset or use a GitHub App |
| `A team member is not a listed member…` | Memberships changed during the pull, or the token cannot see concealed members (not an owner); retry |
| `…no organization-level SAML SSO…` / `…no linked SAML identity…` | Use `login` or `id`, or exclude outside collaborators (§7) |
| Time or collection limit | Many teams; raise `MAX_OBJECTS`, choose `ROLES = ['org_role']` or split organizations across feeds |

## 10. Customising

Adjust the documented variables. Repository permissions, team maintainers, 2FA status or enterprise-level identities need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, uninstall and delete the GitHub App (or revoke the token), and remove the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [organization members](https://docs.github.com/en/rest/orgs/members), [outside collaborators](https://docs.github.com/en/rest/orgs/outside-collaborators), [teams](https://docs.github.com/en/rest/teams/teams), [team members](https://docs.github.com/en/rest/teams/members), [fine-grained token permissions](https://docs.github.com/en/rest/authentication/permissions-required-for-fine-grained-personal-access-tokens), [GitHub App JWT](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-json-web-token-jwt-for-a-github-app), [installation tokens](https://docs.github.com/en/rest/apps/apps#create-an-installation-access-token-for-an-app), [GraphQL `OrganizationIdentityProvider`](https://docs.github.com/en/graphql/reference/objects#organizationidentityprovider), [pagination](https://docs.github.com/en/rest/using-the-rest-api/using-pagination-in-the-rest-api), [rate limits](https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api).
