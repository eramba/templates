---
id: okta-account-review
name: Okta – Accounts
version: 0.1.1
status: draft
technology: Okta Workforce Identity
vendor: Okta
eramba_module: Account Review Feeds
secrets:
  - okta_org_url
  - okta_api_token
variables:
  - USER_KEY
  - ROLES
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

# Okta – Accounts

**Technology:** Okta Workforce Identity.

Sends eramba every user account in your Okta org and the access each one has: its groups, the applications assigned to it and its administrator roles. Reviewers then mark in eramba which accounts and accesses should stay and which should be removed; nothing is changed in Okta.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Okta user account, identified by its login, for example `ana.garcia@example.com` |
| **Connects to** | Your Okta org, through Okta's official Management API. Read-only |
| **Permission needed** | An API token created by an Okta administrator account that holds the **Read-only Administrator** role. The token acts with that account's permissions (§5) |
| **Included** | Every user: active, suspended, locked out and deactivated. Set `INCLUDE_DEPROVISIONED` to false to leave deactivated users out (§7) |
| **Not included** | What an administrator role is limited to (resource sets and targets); permissions inside each application; devices; API service integrations; Okta Identity Governance entitlements; other Okta orgs (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Groups, applications assigned to the user, and administrator roles |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds. This automation lists three kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Group** | The person is a member of this group. Okta groups, groups imported from other directories and built-in groups are all listed | `Group: Engineering [<id>]` |
| **Application** | The person is assigned this application, directly or through a group. It shows that they can sign in, not what they can do inside it | `Application: Salesforce [<id>]` |
| **Admin role** | An administrator role in Okta itself, given to the person or to a group they are in. Standard roles show Okta's role code in the brackets; custom roles show their ID | `Admin role: Read-only Administrator [READ_ONLY_ADMIN]` |

```
Account:     ana.garcia@example.com
Description: Ana García; ACTIVE; id=00u1abcd…
Roles:       Admin role: Read-only Administrator [READ_ONLY_ADMIN]
             Application: Salesforce [0oa2efgh…]
             Group: Engineering [00g3ijkl…]
```

The codes in square brackets are Okta's identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives the name and Okta's status for the account, for example `ACTIVE`, `SUSPENDED` or `DEPROVISIONED` (deactivated).

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed.

A row shows *that* Ana is in *Engineering* or has an application, not what those give her.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each group grants, such as its applications and administrator roles | *Okta – Group permissions* (planned) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Deactivated Okta accounts of leavers stay listed until deleted |
| See what someone can do inside an application, such as Salesforce | That application's own automation, if there is one in the [catalogue](../../README.md), for example [Salesforce](../../Salesforce/Salesforce/) |

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
| `ROLES` | `['groups', 'applications', 'admin_roles']` | Access listed as roles. `[]` lists users only. |
| `INCLUDE_DEPROVISIONED` | `true` | Also list deactivated users. Turn off to review active, suspended and locked users only. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 users; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |
| `MAX_OBJECTS` | `2000` | 1–10000 groups, applications or members per list; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Okta rate limits are respected (`X-Rate-Limit-Reset`) when the wait fits the budget. Only `GET https://<org>/api/v1/` requests are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [users](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/User/), [groups](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/Group/), [applications](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/Application/), [role assignments](https://developer.okta.com/docs/api/openapi/okta-management/management/tag/RoleAssignmentAUser/), [pagination](https://developer.okta.com/docs/api/#pagination), [rate limits](https://developer.okta.com/docs/reference/rl-best-practices/).
