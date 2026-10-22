---
id: atlassian-cloud-account-review
name: Atlassian Cloud (Jira, Confluence…) Accounts and Assigned Access
version: 0.1.1
status: draft
technology: Atlassian Cloud (Jira, Confluence, Jira Service Management…)
vendor: Atlassian
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - atlassian_org_id
  - atlassian_admin_api_key
variables:
  - USER_KEY
  - ROLES
  - PRODUCTS
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Atlassian Cloud (Jira, Confluence…) Accounts and Assigned Access

**Technology:** Atlassian Cloud organizations (Jira, Confluence, Jira Service Management and the other products of the organization).

Send the users of an Atlassian organization, with their product access, groups and organization-level admin roles, to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of Atlassian accounts of your organization to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

**One automation for every product, or one per product.** Jira, Confluence, Jira Service Management and the other products share the organization's users, so one feed can review them all, with one role per product and site. To review a product on its own, with its own reviewers and schedule, create one automation and feed per product and set `PRODUCTS`, for example `['jira-software']` for Jira or `['confluence']` for Confluence: the feed then lists only the users who can access that product, with only that product's roles.

## 2. What it gathers

Data comes from the organization-level [Atlassian Admin API](https://developer.atlassian.com/cloud/admin/organization/rest/) for all directories the API key can manage (directory `-`), not from a Jira or Confluence site API.

| Feed field | Source |
|---|---|
| `user` | Account email by default; configurable to the Atlassian account ID |
| `description` | Name, status (`active`, `suspended`, `not_invited`, `deactivated` or `for_deletion`), claim status (`managed` or `unmanaged`) and account ID, e.g. `Ana Example; suspended; managed; id=712020:…` |
| `roles` | `Product access: <product> (<role>) [<resource ARI>]` for every product role on every site, direct or through a group, e.g. `Product access: jira-software (atlassian/admin) [ari:cloud:jira-software::site/<cloud id>]` |
| | `Group: <name> [<group id>]` for every group membership |
| | `Platform role: <role> [atlassian/<role>]` for organization-level admin roles: `org-admin`, `site-admin`, `user-access-admin`, `ai-access` |

Every user returned by the user search is listed, suspended and deactivated users included, with their status in the description.

## 3. Coverage

One organization per automation, every directory the API key can manage. Product roles are the Atlassian canonical roles (`atlassian/user`, `atlassian/admin`, `atlassian/guest`, `atlassian/customer`, `atlassian/basic`…); platform roles are listed once under `Platform role` and left out of `Product access`.

Not covered: permissions inside products (Jira project roles and permission schemes, Confluence space permissions), Jira Service Management customer accounts outside the organization's directories, app and bot accounts the user search does not return, API tokens, Bitbucket workspace permissions, and site names (sites are identified by their ARI, which contains the site's cloud ID). Unit admin roles are not read.

Product access needs one request per user (role assignments), so large organizations may reach the 200-second budget: the run then aborts, see §9. Groups need one search per group.

Any collection error, a group member outside the collected users, a repeated cursor, a user without email (with `USER_KEY = 'email'`) or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An **organization admin** of Atlassian Administration to create the API key (only organization admins can create keys).
- HTTPS reach from eramba to `api.atlassian.com`.

## 5. Setup on Atlassian

1. **Atlassian Administration** (`admin.atlassian.com`) → select your organization → **Organization settings** → **API keys**.
2. Select **API keys without scopes** → **Create API key**, give it a name such as `eramba account review` and the expiry your credential policy allows (at most one year). Copy the **Organization ID** and the **API key**: the key is shown once.
3. Store the key only in eramba secrets and rotate it before it expires.

Why a key without scopes: the user search (`POST /v2/orgs/{orgId}/directories/{directoryId}/users/search`) and group search are the current, non-deprecated list endpoints, and Atlassian's [scopes list](https://developer.atlassian.com/cloud/admin/scopes/) does not cover them yet; their deprecated `GET` predecessors stop working after June 30, 2027. A key without scopes can call every Admin API, including writes. The script limits itself to these requests, enforced by `TPL_ALLOWED_REQUESTS`:

| Request | Used for |
|---|---|
| `POST …/v2/orgs/{orgId}/directories/-/users/search` | Users, their platform roles, and group members (`groupIds` filter) |
| `POST …/v2/orgs/{orgId}/directories/-/groups/search` | Groups |
| `GET …/v2/orgs/{orgId}/directories/-/users/{accountId}/role-assignments` | Product access (scope `read:directories:admin`) |

When Atlassian adds read scopes for the searches, a key **with scopes** limited to read scopes (`read:directories:admin`, `read:groups:admin` and the search scopes) will be preferable.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `atlassian_org_id` and `atlassian_admin_api_key`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the accounts and roles with **Atlassian Administration → Directory → Users** and **Groups**.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | `email` or `account_id`. No fallback: a user without a visible email aborts the run; use `account_id` then (it never changes, but Exit reviews need an employee feed with the same IDs). |
| `ROLES` | `['product_access', 'groups', 'platform_roles']` | Access listed as roles. `[]` lists users only (one search). |
| `PRODUCTS` | `[]` | Empty: every product. Otherwise only users with access to these products, and only their roles, e.g. `['jira-software']`, `['jira-servicedesk']`, `['confluence']`. The key is the product part of the site ARI shown in `Product access` roles (to validate on a real organization). |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 users; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 groups, group members or role assignments per list; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list (100 records per page), 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried with `Retry-After` when the wait fits the budget.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, emails or keys. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secrets with exactly these names |
| `Secret 'atlassian_org_id' must be…` | Paste the organization ID only, not the admin URL |
| `HTTP 401` | Key expired, revoked or mistyped |
| `HTTP 403` | The key has scopes that do not cover the searches; create a key without scopes (§5) |
| `HTTP 404` | Wrong organization ID |
| `A user has no email address…` | Use `USER_KEY = 'account_id'` |
| `A group member is outside the collected users` | Users changed during the pull; retry later |
| Time budget exceeded | Product access needs one request per user: choose `ROLES = ['groups', 'platform_roles']`, or schedule a separate feed |
| Throttling | Admin API rate limit reached; schedule the pull outside busy hours |

## 10. Customising

Adjust the documented variables. Site names, project or space permissions, or a single directory need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, revoke the API key in **Organization settings → API keys** and remove the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [Admin API: users](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-users/), [groups](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-groups/), [directories](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-directory/), [Admin API scopes](https://developer.atlassian.com/cloud/admin/scopes/), [manage an organization with the Admin APIs](https://support.atlassian.com/organization-administration/docs/manage-an-organization-with-the-admin-apis/).
