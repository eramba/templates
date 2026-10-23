---
id: entra-account-review
name: Entra ID – Accounts
version: 0.3.0
status: tested
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
  - GROUP_ID
  - USER_IDS
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: 3.31.0
last_tested: 2026-10-23
---

# Entra ID – Accounts

**Technology:** Microsoft Entra ID (formerly Azure Active Directory), including Microsoft 365 licences.

Sends eramba every user account in your Microsoft Entra ID and the access each one has: its groups, its administrator rights, the business applications it can sign in to and its licences. Reviewers then mark in eramba which accounts and accesses should stay and which should be removed; nothing is changed in Entra.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One user account (an employee or a guest), identified by its sign-in name, for example `ana.garcia@example.com` |
| **Connects to** | Your Microsoft Entra ID, through Microsoft's official API (Microsoft Graph). Read-only |
| **Permission needed** | Microsoft Graph application permissions `Directory.Read.All` and `Member.Read.Hidden`, read-only. An Entra administrator creates the connection and grants admin consent (§5) |
| **Included** | Every user account: employees and guests, active and disabled. To review only the members of one group (nested groups included), set `GROUP_ID`; to test with a few people, list their user object IDs in `USER_IDS` (§7) |
| **Not included** | Accounts used by applications, services and devices rather than people; deleted users; administrator rights people can switch on only when needed (Entra PIM "eligible" roles); applications open to everyone without an assignment, apps a person gave permission to read their own data (user consent), and Azure cloud resources; sign-in activity, multi-factor status and ownership; other Entra organisations (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short; split it into one feed per group with `GROUP_ID` |
| **Roles listed** | Groups (nested included), admin roles with where they apply, business applications with their role, and licences, each saying when it comes through a group |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's account. Its roles are every access that account holds. This automation lists four kinds, named like the tabs of the person's page in Entra (Users › the person), so each access can be found and removed where it was given:

| Kind | What it means for the person | How reviewers see it | Where to change it in Entra |
|---|---|---|---|
| **Group** | The person belongs to this group. *through group …* means they are not in it themselves but in another group that belongs to it: remove them from that group instead. Groups usually give access to files or applications; email-only groups are listed too, even though they give no access | `Group: Finance`, `Group: Finance (through group Finance EU)` | The person's **Groups** tab, or the group named after *through group* |
| **Admin role** | An administrator right in Entra itself, such as *User Administrator*. Without details it covers the whole organisation; *administrative unit …* or *only for …* means only part of it. *through group …* means a group holds the right | `Admin role: User Administrator`, `Admin role: Helpdesk Administrator (administrative unit Sales)` | The person's **Assigned roles** tab, or **Roles & admins** |
| **Application** | A business application the person may sign in to, such as Salesforce, and the role it gives them there (*role …*). Without a role they can sign in but have no specific role | `Application: Salesforce (role Sales Manager)`, `Application: Salesforce (through group Sales)` | The person's **Applications** tab, or the application's **Users and groups** |
| **License** | A Microsoft 365 or other paid licence, shown with the product name of the Microsoft 365 admin center. A few less common licences keep Microsoft's product code | `License: Office 365 E3`, `License: Microsoft 365 E5 (through group All staff)` | The person's **Licenses** tab, or the group's licences |

```
Account:     ana.garcia@example.com
Description: Ana García; Member; enabled; object=d7b79fe7-…
Roles:       Admin role: User Administrator
             Application: Salesforce (role Sales Manager)
             Group: All employees (through group Finance)
             Group: Finance
             License: Office 365 E3
```

Here Ana is in *Finance* herself, and in *All employees* because *Finance* belongs to it. Roles show names only. When two different objects of the same kind share a name, for example two groups called *Finance*, both show the first characters of their Entra object ID, `Group: Finance [7f3c2a10]`, so reviewers can tell them apart. The description gives the name, whether the account is an employee account (*Member*) or a guest (*Guest*), and whether it is *enabled* or *disabled*.

You choose which kinds are listed with `ROLES` (§7). With none, only the accounts are listed.

A row shows *that* Ana is in *Finance* or is a *User Administrator*, not what those give her. Not yet verified: whether administrator rights switched on temporarily (Entra PIM) appear while they are active.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each group grants | *Entra ID – Group permissions* (planned) |
| See what data each connected application can reach | [Entra ID – Application permissions](../Entra%20ID%20Application%20Permissions/) |
| See who the administrators are, and only them | This automation, scope option (planned) |
| Review one department or team | This automation with `GROUP_ID` set to that group |
| Find people who left | Pair this feed with [Entra ID – Current employees](../Entra%20ID%20Current%20Employees/) in an Exit review. Guests match no employee, so they also appear; disabled accounts of leavers stay listed until deleted |
| See what someone can do inside an application, such as Salesforce | That application's own automation, if there is one in the [catalogue](../../README.md), for example [Salesforce](../../Salesforce/Salesforce/) |

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
| `GROUP_ID` | `''` | Empty: whole tenant. Otherwise the object ID of one group: only its members are listed, members of nested groups included. |
| `USER_IDS` | `[]` | Empty: whole tenant or `GROUP_ID`. Otherwise a list of user object IDs, for testing. Not together with `GROUP_ID`. |
| `ROLES` | `['groups', 'directory_roles', 'applications', 'licenses']` | Access listed as roles. `[]` lists accounts only (no membership calls, no role changes). |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 accounts; exceeding it aborts instead of truncating. Above a few thousand accounts, set `ROLES` to `[]` or review one group with `GROUP_ID`: every account adds membership calls to the 200-second budget. For more accounts, split the review into one feed per group with `GROUP_ID`. |
| `MAX_OBJECTS` | `2000` | 1–10000 records per API collection and roles per account; exceeding it aborts. |

Fixed limits: 200 seconds of collection (`TPL_RUN_SECONDS`), 4 MB per response, 100 pages per collection, 8 MiB of feed, 48 MiB of memory, three attempts when Microsoft throttles for up to 30 seconds. Requests are grouped 20 at a time with Graph JSON batching; only the token endpoint and Graph reads are allowed (`TPL_ALLOWED_REQUESTS`).

The key must be unique ignoring case, as eramba compares accounts ignoring case. Changing `USER_KEY`, `GROUP_ID` or `USER_IDS` changes the population: take a new baseline.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: below the `RESULT: OK` line, the summary also counts enabled and disabled accounts, members and guests. An empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `writeAccountReviewArtifact() is unavailable` | Create the automation from the Account Review feed form, not as a control automation |
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret value |
| `Microsoft API HTTP 401/403` | Check secret expiry, admin consent and both permissions |
| Collection incomplete or changed | Directory changes during the pull; retry later |
| No accounts found | Check `GROUP_ID` and `USER_IDS`; set `ALLOW_EMPTY_FEED` only if zero accounts is intended |
| `The tenant has N accounts, more than MAX_USERS` | Raise `MAX_USERS` (up to 5000), or set `GROUP_ID` and create one feed per group |
| `GROUP_ID must be empty or an Entra group object ID` / `Set GROUP_ID or USER_IDS, not both` | Use the group's Object ID from Entra › Groups, and leave `USER_IDS` empty |
| Keys collide or field missing | Choose a `USER_KEY` present and unique on every account (`mail` can be empty) |
| Time, size or collection limit | Split the review into one feed per group with `GROUP_ID` |

## 10. Customising

Adjust the documented variables. Other access types need new collection code; they are not enabled by changing the role format. Section 4 of `run.php` is a copy of the [shared code blocks](../../../_shared/): do not edit it there.

## 11. Removing

Stop or reconfigure the Account Reviews that use the feed before removing it. Remove the app registration and secrets when no other automation uses them. Removing the automation does not revoke any access.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | Directory role assignments no longer select `appScopeId` (rejected by Graph) and deprecated built-in roles missing from the role list are read by ID; directory role values change from `[<id>; directory=…; app=…]` to `[<id>; scope=…]`, so a Differential review with an earlier baseline shows one Change in Roles per directory-role holder; `MAX_USERS` limited to 5000 (default 2000); progress logged every 1,000 accounts; the log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |
| 0.2.0 | New `GROUP_ID` variable: review only the members of one group (nested included). A population above `MAX_USERS` now names the real number of accounts and the variable to change. Tested on eramba 3.31.0: Snapshot, Differential and Exit reviews, including the upgrade from 0.1.1 (no role changes) |
| 0.3.0 | Roles are written for reviewers, named like the tabs of a person's page in Entra: `Group`, `Admin role`, `Application`, `License`. No object IDs, except the first characters when two objects share a name; inherited groups and access held through a group say *through group …*; admin roles name their administrative unit; licences use product names (for example *Office 365 E3*). A Differential review with an earlier baseline shows every account once as *Change in Roles*: approve that pull or take a new baseline |

References: [client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow), [users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0), [transitiveMemberOf](https://learn.microsoft.com/en-us/graph/api/user-list-transitivememberof?view=graph-rest-1.0), [role assignments](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roleassignments?view=graph-rest-1.0), [role definitions](https://learn.microsoft.com/en-us/graph/api/rbacapplication-list-roledefinitions?view=graph-rest-1.0), [app role assignments](https://learn.microsoft.com/en-us/graph/api/user-list-approleassignments?view=graph-rest-1.0), [service principal](https://learn.microsoft.com/en-us/graph/api/serviceprincipal-get?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching), [throttling](https://learn.microsoft.com/en-us/graph/throttling).
