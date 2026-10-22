---
id: atlassian-cloud-account-review
name: Atlassian – Accounts
version: 0.1.1
status: draft
technology: Atlassian Cloud (Jira, Confluence, Jira Service Management…)
vendor: Atlassian
eramba_module: Account Review Feeds
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

# Atlassian – Accounts

**Technology:** Atlassian Cloud organizations (Jira, Confluence, Jira Service Management and the other products of the organization).

Sends eramba every user of your Atlassian organization and the access each one has: its product access (Jira, Confluence, Jira Service Management and others), its groups and its organization admin roles. Reviewers then mark in eramba which users and accesses should stay and which should be removed; nothing is changed in Atlassian.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Atlassian user of your organization, identified by its email, for example `ana.garcia@example.com` (or its Atlassian account ID with `USER_KEY`) |
| **Connects to** | Your Atlassian organization, through the Atlassian Admin API of every directory the key can manage. Read-only |
| **Permission needed** | An organization API key without scopes, created in Atlassian Administration by an organization admin. Atlassian does not offer read-only scopes for these requests yet, so the key can do more than the script uses; the script only sends the three requests listed in §5 |
| **Included** | Every user the Admin API's user search returns: active, suspended, not invited, deactivated and for deletion |
| **Not included** | Permissions inside products (Jira project roles and permission schemes, Confluence space permissions); Jira Service Management customer accounts outside the organization's directories; app and bot accounts the search does not return; API tokens; Bitbucket workspace permissions; site names (sites show as an ARI containing the site's cloud ID); other organizations (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. To split a larger population, use `PRODUCTS` (one feed per product) |
| **Roles listed** | Product access per site, groups and organization-level platform roles |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

One automation can review every product at once, or you can create one per product and set `PRODUCTS`, for example `['jira-software']`: the feed then lists only the users who can access that product, with only that product's roles, so each product can have its own reviewers and schedule.

## 2. What one row looks like

One row is one person's Atlassian user. Its roles are every access that user holds. This automation lists three kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Product access** | The person can use this product on this site, with the role shown, directly or through a group. Roles such as `atlassian/user`, `atlassian/admin`, `atlassian/guest`. One entry per product and site | `Product access: jira-software (atlassian/admin) [ari:cloud:jira-software::site/<cloud id>]` |
| **Group** | The person is a member of this group | `Group: jira-administrators [<id>]` |
| **Platform role** | An organization-level administrator role: `org-admin`, `site-admin`, `user-access-admin`, `ai-access` or `unit-admin` | `Platform role: org-admin [atlassian/org-admin]` |

```
Account:     ana.garcia@example.com
Description: Ana García; active; managed; id=712020:…
Roles:       Group: jira-administrators [3f9a…]
             Platform role: org-admin [atlassian/org-admin]
             Product access: confluence (atlassian/user) [ari:cloud:confluence::site/<cloud id>]
```

The text in square brackets is Atlassian's identifier. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, the account status (`active`, `suspended`, `not_invited`, `deactivated` or `for_deletion`), whether the account is `managed` or `unmanaged` by your organization, and the account ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the users are listed.

A row shows *that* Ana is in *jira-administrators* or has access to Confluence, not what she can do inside it.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each group grants | *Atlassian – Group permissions* (planned) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Suspended and deactivated users of leavers stay listed until deleted |

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
| `MAX_USERS` | `2000` | 1–5000 users; exceeding it aborts instead of truncating. For more accounts, split the review into several feeds with `PRODUCTS`. |
| `MAX_OBJECTS` | `2000` | 1–10000 groups, group members or role assignments per list; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list (100 records per page), 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried with `Retry-After` when the wait fits the budget.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary counts the roles listed per kind. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000; platform roles, `unit-admin` included, are listed once under `Platform role` and no longer repeated under `Product access` |

References: [Admin API: users](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-users/), [groups](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-groups/), [directories](https://developer.atlassian.com/cloud/admin/organization/rest/api-group-directory/), [Admin API scopes](https://developer.atlassian.com/cloud/admin/scopes/), [manage an organization with the Admin APIs](https://support.atlassian.com/organization-administration/docs/manage-an-organization-with-the-admin-apis/).
