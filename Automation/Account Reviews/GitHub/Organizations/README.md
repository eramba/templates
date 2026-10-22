---
id: github-account-review
name: GitHub – Accounts
version: 0.1.1
status: draft
technology: GitHub (GitHub.com, Free to Enterprise Cloud)
vendor: GitHub
eramba_module: Account Review Feeds
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

# GitHub – Accounts

**Technology:** GitHub.com organizations, Free, Team and Enterprise Cloud plans.

Sends eramba every member and outside collaborator of the GitHub organizations you list, with their organization role and teams. Reviewers then mark in eramba which people and accesses should stay and which should be removed; nothing is changed in GitHub.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One GitHub account that belongs to at least one of your organizations, identified by its login, for example `octo-dev`. An account in several organizations is one row with the roles of all of them |
| **Connects to** | GitHub.com (`api.github.com`), through GitHub's official REST and GraphQL APIs. Read-only |
| **Permission needed** | A GitHub App installed on every listed organization with the organization permission **Members: Read-only** (recommended), or a token of a dedicated organization owner with that read access. An organization owner creates it (§5) |
| **Included** | Owners and members of the organizations in `ORGANIZATIONS`, and their outside collaborators (`INCLUDE_OUTSIDE_COLLABORATORS`, on by default) |
| **Not included** | Pending invitations; billing managers; enterprise owners and members who belong to no listed organization; team maintainer roles; repository permissions and custom roles; deploy keys, GitHub Apps and personal access tokens; GitHub Enterprise Server and GHE.com hosts; two-factor status. GitHub has no disabled state for members: people who were removed are simply absent |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. To review more, split the organizations into several feeds with `ORGANIZATIONS` |
| **Roles listed** | Organization role (per organization) and teams |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one GitHub account. Its roles are every access that account holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Org role** | The account is an *owner* or a *member* of the organization, or an *outside collaborator* with access to its repositories without being a member. One entry per organization | `Org role: owner [example-org]` |
| **Team** | The account belongs to this team, directly or through a child team. Shown as *organization/team* | `Team: example-org/platform [<id>]` |

```
Account:     octo-dev
Description: octo-dev; User; id=123456; example-org: owner, second-org: member
Roles:       Org role: member [second-org]
             Org role: owner [example-org]
             Team: example-org/platform [7890123]
```

The text in square brackets is the organization name or GitHub's team ID. It keeps two items with the same name apart; reviewers can ignore it. The description gives the login, whether the account is a `User` or a `Bot`, its ID and its relationship to each organization.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed.

A row shows *that* octo-dev is in the *platform* team, not which repositories that team can reach.

A GitHub login is not an email and GitHub never returns a member's work email. Exit reviews only match when both feeds hold the same value, so either set `USER_KEY` to `saml_name_id` or `email` (for organizations that use organization-level SAML SSO, §7; with these keys also turn off `INCLUDE_OUTSIDE_COLLABORATORS`, because outside collaborators have no SAML identity and the run would stop), or keep `login` and compare with an employee list that holds GitHub logins.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what someone can do in GitHub | The organization role sits on the account itself, so this account review already shows it. Repository access through teams is not read by any automation yet |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). See the note about keys in §2 |

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
| `MAX_USERS` | `2000` | 1–5000 accounts; exceeding it aborts instead of truncating. For more accounts, split the review into several feeds with `ORGANIZATIONS`. |
| `MAX_OBJECTS` | `2000` | 1–10000 records per list (members, teams, team members, identities); exceeding it aborts. |

`saml_name_id` uses the NameID of the account's linked SAML identity, often the work email; `email` uses its primary SAML email (or the only one). Both require organization-level SAML SSO on every listed organization and a linked identity for **every** listed account, the same in all organizations. Outside collaborators never have one, so set `INCLUDE_OUTSIDE_COLLABORATORS = false` with these keys and review collaborators in a separate feed keyed by `login`. A login changes when the user renames the account; `id` does not.

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. The run stops when fewer than 5 requests remain in the GitHub rate limit (5,000 per hour for tokens). Allowed requests: `GET https://api.github.com/orgs/`, `POST https://api.github.com/graphql` (queries for SAML keys) and `POST https://api.github.com/app/installations/` (installation tokens).

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary counts the roles listed per kind. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [organization members](https://docs.github.com/en/rest/orgs/members), [outside collaborators](https://docs.github.com/en/rest/orgs/outside-collaborators), [teams](https://docs.github.com/en/rest/teams/teams), [team members](https://docs.github.com/en/rest/teams/members), [fine-grained token permissions](https://docs.github.com/en/rest/authentication/permissions-required-for-fine-grained-personal-access-tokens), [GitHub App JWT](https://docs.github.com/en/apps/creating-github-apps/authenticating-with-a-github-app/generating-a-json-web-token-jwt-for-a-github-app), [installation tokens](https://docs.github.com/en/rest/apps/apps#create-an-installation-access-token-for-an-app), [GraphQL `OrganizationIdentityProvider`](https://docs.github.com/en/graphql/reference/objects#organizationidentityprovider), [pagination](https://docs.github.com/en/rest/using-the-rest-api/using-pagination-in-the-rest-api), [rate limits](https://docs.github.com/en/rest/using-the-rest-api/rate-limits-for-the-rest-api).
