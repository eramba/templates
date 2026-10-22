---
id: entra-current-employees
name: Entra ID Current Employees
version: 0.1.1
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Account Review Feeds
trigger: Account Review pull
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

# Entra ID Current Employees

**Technology:** Microsoft Entra ID (public cloud).

Send the people who should have access today, as Entra knows them, to an eramba feed of type **List of current employees**. **Draft: functional and installation validation pending.**

## 1. Purpose

The comparison side of an **Exit** review. When someone leaves, their Entra account is disabled, but their accounts in other applications (GitHub, Zoom, Zendesk, AI tools…) can stay active. An Exit review compares an application's account feed with this list and flags every application account that matches no current employee.

Entra is the source of truth here because it is where access is removed. If your HR system keeps a more reliable list, an HR feed can play the same role.

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Work email (`mail`) by default; configurable to user principal name or object ID |
| `description` | Display name and Employee ID when present |

Employee feeds have no roles. Included: enabled users of type Member, of the whole tenant or of one group (nested members included). Excluded: disabled users and guests.

## 3. Coverage

The key must hold the same value as the application accounts it is compared with, usually the work email. An application account with a different email (a personal address, an alias, a service account) will be flagged; that is expected and reviewers decide on it.

Users without the chosen key field are left out and counted in the log. Leaving someone out of this list can only add findings, never hide one. An empty list aborts the run: it would flag every account.

Any collection error or limit aborts the run and nothing is published.

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
| `MAX_USERS` | `10000` | 1–50000 users; exceeding it aborts instead of truncating. |

Fixed limits: 200 seconds of collection, 4 MB per response, 100 pages, 8 MiB of feed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only (employees, and how many were left out and why). Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of current employees eramba received, or `RESULT: FAILED` with the reason.

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
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [list users](https://learn.microsoft.com/en-us/graph/api/user-list?view=graph-rest-1.0), [advanced queries](https://learn.microsoft.com/en-us/graph/aad-advanced-queries), [group transitive members](https://learn.microsoft.com/en-us/graph/api/group-list-transitivemembers?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching).
