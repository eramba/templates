---
id: entra-current-employees
name: Entra ID – Current employees
version: 0.1.1
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Account Review Feeds
feed_type: List of current employees
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - USER_KEY
  - GROUP_ID
  - REQUIRE_EMPLOYEE_ID
  - MAX_USERS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Entra ID – Current employees

**Technology:** Microsoft Entra ID (public cloud).

Sends eramba the people who should have access today, as Entra ID knows them, as a list of type **List of current employees**. It is the comparison side of an Exit review: eramba flags the accounts of an application that match no one on this list, and reviewers decide whether each should stay or be removed. Nothing is changed in Entra.

## 1. At a glance

| | |
|---|---|
| **Family** | Employee list (for Exit reviews) |
| **Each row is** | One current employee, identified by their work email, for example `ana.garcia@example.com` (or user principal name or object ID with `USER_KEY`) |
| **Connects to** | Your Microsoft Entra ID, through Microsoft's official API (Microsoft Graph). Read-only |
| **Permission needed** | Microsoft Graph application permission `User.Read.All`, plus `GroupMember.Read.All` when `GROUP_ID` is set. An Entra administrator creates the connection and grants admin consent (§5) |
| **Included** | Enabled users of type Member, of the whole tenant or of one group (nested members included) |
| **Not included** | Disabled users and guests; users without the chosen key field (counted in the log); with `REQUIRE_EMPLOYEE_ID`, users without an Employee ID, such as admin and test accounts |
| **Size limit** | Up to 10000 people by default (`MAX_USERS`, maximum 30000). A larger list stops the run instead of being cut short |
| **Roles listed** | None: employee lists carry no roles |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| None | Employee lists carry no roles; the list only says who is an employee. | Only the person's identifier and name are shown. |

```
Account:     ana.garcia@example.com
Description: Ana García; employee 21001
```

The description gives the display name, and the Employee ID when Entra has one.

Entra is the source of truth here because it is where access is removed. When someone leaves, their Entra account is disabled, but their accounts in other applications can stay active. The key must hold the same value as the application accounts it is compared with, usually the work email; an application account with a different address, such as a personal email or an alias, is flagged, and reviewers decide on it.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| Review the accounts of an application against this list | Any account feed in the [catalogue](../../README.md), for example [Entra ID – Accounts](../Entra%20ID/), [Okta – Accounts](../../Okta/Workforce%20Identity/) or [Google Workspace – Accounts](../../Google/Workspace/) |
| Use HR as the source of employees | [Workday – Current employees](../../Workday/Current%20Employees/) |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An Entra app registration with `User.Read.All` (and `GroupMember.Read.All` when `GROUP_ID` is used), or the one used by the [Entra ID accounts feed](../Entra%20ID/), which already has `Directory.Read.All`.

## 5. Setup on Entra ID

1. **Microsoft Entra admin center → App registrations**: reuse the eramba app registration or create a single-tenant one.
2. **API permissions → Microsoft Graph → Application permissions**: `User.Read.All`, plus `GroupMember.Read.All` if you set `GROUP_ID`. **Grant admin consent.**
3. Copy the **Directory (tenant) ID**, **Application (client) ID** and a client secret **value**, base64-encoded on one line.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` (the same names as the other Entra automations).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of current employees**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test**: exit code `0`, stdout only shows the `account-review` artifact declaration.
4. Create an Account Review of type **Exits**: the application's account feed as the feed to review, this feed as the **Exit Feed**. eramba matches both feeds by `user`, ignoring case.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `mail` | `mail`, `userPrincipalName` or `id`. Must hold the same value as the compared application accounts. |
| `GROUP_ID` | `''` | Empty: all enabled member users. Otherwise the object ID of a group such as "All employees"; nested members included. |
| `REQUIRE_EMPLOYEE_ID` | `false` | True keeps only users with an Employee ID, typically those provisioned from HR, and leaves out admin and test accounts. |
| `MAX_USERS` | `10000` | 1–30000 users (eramba runs automations with 64 MB of memory); exceeding it aborts instead of truncating. |

Fixed limits: 200 seconds of collection, 4 MB per response, 100 pages, 8 MiB of feed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this list: below the `RESULT: OK` line, the summary counts the employees published and how many were left out and why. An empty list is never published, because an Exit review would flag every account.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names and the base64 value |
| `HTTP 403` | Grant `User.Read.All` (and `GroupMember.Read.All` for a group) with admin consent |
| `No employees found` | Check `GROUP_ID`, `REQUIRE_EMPLOYEE_ID` and that users have the `USER_KEY` field |
| Many users left out without `mail` | Use `userPrincipalName` if it equals the email in your tenant |
| Every application account flagged | The keys differ: compare a few values of both feeds |

## 10. Customising

Adjust the documented variables. Other filters (department, employee type, licences) need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Exit reviews that use the feed, then remove the feed and, if nothing else uses them, the permissions and secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | `MAX_USERS` limited to 30000 (memory); the log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published) |

References: [list users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0), [advanced queries](https://learn.microsoft.com/en-us/graph/aad-advanced-queries), [group transitive members](https://learn.microsoft.com/en-us/graph/api/group-list-transitivemembers?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching).
