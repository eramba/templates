---
id: entra-account-review
name: Entra ID – Accounts
version: 0.1.1
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Account Review Feeds
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - USER_KEY
  - ROLES
  - USER_IDS
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Entra ID – Accounts

**Technology:** Microsoft Entra ID (formerly Azure Active Directory), including Microsoft 365 licences.

Sends eramba every user account in your Microsoft Entra ID and the access each one has: its groups, its administrator rights, the business applications it can sign in to and its licences. Reviewers then keep or revoke each account and each access in eramba. **Draft: tested on a lab tenant, not yet released as tested.**

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One user account (a person or a guest), identified by its sign-in name, for example `ana.garcia@example.com` |
| **Connects to** | Your Microsoft Entra ID, through Microsoft's official API (Microsoft Graph). Read-only |
| **Permission needed** | Microsoft Graph application permissions `Directory.Read.All` and `Member.Read.Hidden`, read-only. An Entra administrator creates the connection and grants admin consent (§5) |
| **Included** | Every user account: employees and guests, active and disabled. To test, or to review only some people, list their user object IDs in `USER_IDS` (§7) |
| **Not included** | Accounts used by applications, services and devices rather than people; deleted users; administrator rights people can switch on only when needed (Entra PIM "eligible" roles); applications open to everyone without an assignment, apps people allowed to read their data, and Azure cloud resources; sign-in activity, multi-factor status and ownership; other Entra organisations (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short |
| **Roles listed** | Groups (nested included), administrator rights with their scope, business applications with their role, and licences |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds. This automation lists four kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Group** | The person belongs to this group, directly or because they are in another group that belongs to it. Groups usually give access to files, applications or email lists. All group types are listed, including email lists that give no access | `Group: Finance [<id>]` |
| **Directory role** | An administrator right in Entra itself, such as *User Administrator*, given to the person or to a group they are in. `scope=/` means the right covers the whole organisation; any other scope means only part of it | `Directory role: User Administrator [<id>; scope=/]` |
| **Application** | A business application the person may sign in to, such as Salesforce, and the role it gives them there. *Default access* means they can sign in but have no specific role | `Application: Salesforce / Sales Manager [<id> / <id>]` |
| **License** | A Microsoft 365 or other paid licence assigned to the person. Licences show Microsoft's product codes: for example `ENTERPRISEPACK` is Office 365 E3 | `License: ENTERPRISEPACK [<id>]` |

```
Account:     ana.garcia@example.com
Description: Ana García; Member; enabled; object=d7b79fe7-…
Roles:       Application: Salesforce / Sales Manager [3a1d… / 9b2e…]
             Directory role: User Administrator [fe930be7-…; scope=/]
             Group: Finance [7f3c…]
             Group: All employees [12db…]
             License: ENTERPRISEPACK [6fd2c87f-…]
```

The codes in square brackets are Microsoft's internal identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives the name, whether the account is an employee account (*Member*) or a guest (*Guest*), and whether it is *enabled* or *disabled*.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed.

A row shows *that* Ana is in *Finance* or is a *User Administrator*, not what those give her. Not yet verified: whether administrator rights switched on temporarily (Entra PIM) appear while they are active.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each group grants | *Entra ID – Group permissions* (planned) |
| See what data each connected application can reach | *Entra ID – Application permissions* (planned) |
| See who the administrators are, and only them | This automation, scope option (planned) |
| Find people who left | Pair this feed with [Entra ID – Current employees](../Entra%20ID%20Current%20Employees/) in an Exit review. Guests match no employee, so they also appear; disabled accounts of leavers stay listed until deleted |
| See what someone can do inside an application, such as Salesforce | That application's own automation, for example [Salesforce](../../Salesforce/Salesforce/) |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()` (**Tools → Actions → Account Review Artifact**).
- A dedicated Entra app registration and an administrator who can grant admin consent.
- Start with a few users in `USER_IDS` that cover each access type.

## 5. Setup on Entra ID

1. **Microsoft Entra admin center → App registrations → New registration**: single tenant, no redirect URI.
2. Copy the **Directory (tenant) ID** and **Application (client) ID**.
3. **Certificates & secrets → New client secret**. Copy the secret **value** and base64-encode it on one line.
4. **API permissions → Microsoft Graph → Application permissions**, then **Grant admin consent**:

| Permission | Why |
|---|---|
| `Directory.Read.All` | Users, memberships, directory role definitions/assignments, app role assignments, application names and subscribed licences |
| `Member.Read.Hidden` | Members of groups with hidden membership |

No write permission is needed. Directory-wide read access is broad: protect and rotate the secret.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` (the same names as the Entra control automations; reuse them only if the same app registration is intended).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the accounts and roles with Entra.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `userPrincipalName` | Account identifier: `userPrincipalName`, `id` or `mail`. Must exist on every account; no fallback. |
| `USER_IDS` | `[]` | Empty: whole tenant. Otherwise a list of user object IDs. |
| `ROLES` | `['groups', 'directory_roles', 'applications', 'licenses']` | Access listed as roles. `[]` lists accounts only (no membership calls, no role changes). |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 accounts; exceeding it aborts instead of truncating. Above a few thousand accounts, list roles only for a `USER_IDS` subset or set `ROLES` to `[]`: every account adds membership calls to the 200-second budget. For more accounts, split the review into several feeds with `USER_IDS`. |
| `MAX_OBJECTS` | `2000` | 1–10000 records per API collection and roles per account; exceeding it aborts. |

Fixed limits: 200 seconds of collection (`TPL_RUN_SECONDS`), 4 MB per response, 100 pages per collection, 8 MiB of feed, 48 MiB of memory, three attempts when Microsoft throttles for up to 30 seconds. Requests are grouped 20 at a time with Graph JSON batching; only the token endpoint and Graph reads are allowed (`TPL_ALLOWED_REQUESTS`).

The key must be unique ignoring case, as eramba compares accounts ignoring case. Changing `USER_KEY` or `USER_IDS` changes the population: take a new baseline.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary counts enabled and disabled accounts, members and guests, and the roles listed per kind. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `writeAccountReviewArtifact() is unavailable` | Create the automation from the Account Review feed form, not as a control automation |
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret value |
| `Microsoft API HTTP 401/403` | Check secret expiry, admin consent and both permissions |
| Collection incomplete or changed | Directory changes during the pull; retry later |
| No accounts found | Check `USER_IDS`; set `ALLOW_EMPTY_FEED` only if zero accounts is intended |
| Keys collide or field missing | Choose a `USER_KEY` present and unique on every account (`mail` can be empty) |
| Time, size or collection limit | Split the review with `USER_IDS` |

## 10. Customising

Adjust the documented variables. Other access types need new collection code; they are not enabled by changing the role format. Section 4 of `run.php` is a copy of the [shared code blocks](../../../_shared/): do not edit it there.

## 11. Removing

Stop or reconfigure the Account Reviews that use the feed before removing it. Remove the app registration and secrets when no other automation uses them. Removing the automation does not revoke any access.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | Directory role assignments no longer select `appScopeId` (rejected by Graph) and deprecated built-in roles missing from the role list are read by ID; directory role values change from `[<id>; directory=…; app=…]` to `[<id>; scope=…]`, so a Differential review with an earlier baseline shows one Change in Roles per directory-role holder; `MAX_USERS` limited to 5000 (default 2000); progress logged every 1,000 accounts; the log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow), [users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0), [transitiveMemberOf](https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0), [role assignments](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roleassignments?view=graph-rest-1.0), [role definitions](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roledefinitions?view=graph-rest-1.0), [app role assignments](https://learn.microsoft.com/en-us/graph/api/user-list-approleassignments?view=graph-rest-1.0), [service principal](https://learn.microsoft.com/en-us/graph/api/serviceprincipal-get?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching), [throttling](https://learn.microsoft.com/en-us/graph/throttling).
