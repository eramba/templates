---
id: entra-app-permissions-review
name: Entra ID – Application permissions
version: 0.1.0
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Account Review Feeds
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - INCLUDE_MICROSOFT_APPS
  - ALLOW_EMPTY_FEED
  - MAX_APPS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Entra ID – Application permissions

**Technology:** Microsoft Entra ID (formerly Azure Active Directory), including Microsoft 365.

Sends eramba every connected application in your Microsoft Entra ID that has been allowed to reach your organisation's data, such as mail, files or the user directory, and the permissions it holds. Reviewers then mark in eramba which applications should keep which permissions; nothing is changed in Entra.

## 1. At a glance

| | |
|---|---|
| **Family** | Permission review |
| **Each row is** | One connected application (an *enterprise application* in Entra) that has been granted access to your organisation's data, identified by its name and application ID, for example `Contoso Expenses [1f2e3d4c-…]`. The reviewer answers: *should this application still have these permissions?* This differs from [Entra ID – Accounts](../Entra%20ID/), where each row is a person |
| **Connects to** | Your Microsoft Entra ID, through Microsoft's official API (Microsoft Graph). Read-only |
| **Permission needed** | Microsoft Graph application permission `Directory.Read.All`, read-only: the same app registration as *Entra ID – Accounts* can be used. An Entra administrator grants admin consent (§5) |
| **Included** | Every application with at least one permission: apps your organisation built and apps from other companies. Applications owned by Microsoft are left out unless `INCLUDE_MICROSOFT_APPS` is on (§7) |
| **Not included** | What each permission lets the application reach inside SharePoint, Exchange or other services beyond the permission's name; which users consented for themselves (only their number is given); applications with no permission at all; who may sign in to each application (that is in *Entra ID – Accounts*); Azure cloud resource roles; what each group grants (planned automation *Entra ID – Group permissions*); other Entra organisations (one per automation) |
| **Size limit** | Up to 2000 applications by default (`MAX_APPS`, maximum 5000). More stops the run instead of being cut short. A tenant with more than 10,000 enterprise applications in total, Microsoft's included, also stops the run |
| **Roles listed** | Application permissions (including default access to another application) and delegated permissions consented for all users or for some users |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one application. Its roles are every permission that application holds. eramba's first column is labelled *Account*: here it holds the application. This automation lists three kinds:

| Kind | What it means, in plain words | How reviewers see it |
|---|---|---|
| **Application permission** | The application can do this on its own, without anyone signed in. These are usually the most powerful permissions, for example reading every mailbox. *Default access* means the application was given access to another of your applications without a specific role | `Application permission: Microsoft Graph / User.Read.All [<id> / <id>]` |
| **Delegated permission (all users)** | An administrator allowed the application to act for any user who signs in to it, within what that user may do | `Delegated permission (all users): Microsoft Graph / Mail.Read [<id> / Mail.Read]` |
| **Delegated permission (some users)** | Individual users allowed the application to act for them. The description says how many users did | `Delegated permission (some users): Microsoft Graph / Files.Read [<id> / Files.Read]` |

Each permission is shown as *the service it applies to / the permission name*, for example *Microsoft Graph / Mail.Read*: the application can read mail through Microsoft Graph.

```
Account:     Contoso Expenses [1f2e3d4c-…]
Description: Third party; enabled; sign-in: everyone; some-user consents: 3 users; appId=1f2e3d4c-…
Roles:       Application permission: Microsoft Graph / User.Read.All [5b07… / df02…]
             Delegated permission (all users): Microsoft Graph / Mail.Read [5b07… / Mail.Read]
             Delegated permission (some users): Microsoft Graph / Files.Read [5b07… / Files.Read]
```

The codes in square brackets are Microsoft's internal identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives:

- who owns the application: *Your organization* (built in your Entra), *Third party* (another company) or *Microsoft* (only with `INCLUDE_MICROSOFT_APPS`);
- whether it is *enabled* or *disabled* (a disabled application cannot sign in or use its permissions);
- who may sign in to it: *everyone* in your organisation, or *assigned users only*;
- how many users consented for themselves (*some-user consents*), and the application ID.

Microsoft applications are recognised by the Microsoft organisation that owns them. A few Microsoft tools are owned by other Microsoft organisations, for example Graph Explorer or Microsoft Graph Command Line Tools, and applications with no owner, such as managed identities, are not marked as Microsoft either: they appear as *Third party*. They are shown, not hidden, so reviewers can judge them like any other application.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See who has access: each person's groups, administrator rights, applications and licences | [Entra ID – Accounts](../Entra%20ID/) |
| See what each group grants | *Entra ID – Group permissions* (planned) |
| Find people who left | Pair [Entra ID – Accounts](../Entra%20ID/) with [Entra ID – Current employees](../Entra%20ID%20Current%20Employees/) in an Exit review |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()` (**Tools → Actions → Account Review Artifact**).
- An Entra app registration with `Directory.Read.All` and an administrator who can grant admin consent. If *Entra ID – Accounts* is already set up, its app registration and secrets can be reused.

## 5. Setup on Entra ID

If you reuse the app registration of *Entra ID – Accounts*, skip to §6: it already has `Directory.Read.All`. Otherwise:

1. **Microsoft Entra admin center → App registrations → New registration**: single tenant, no redirect URI.
2. Copy the **Directory (tenant) ID** and **Application (client) ID**.
3. **Certificates & secrets → New client secret**. Copy the secret **value** and base64-encode it on one line.
4. **API permissions → Microsoft Graph → Application permissions**, then **Grant admin consent**:

| Permission | Why |
|---|---|
| `Directory.Read.All` | Enterprise applications with their roles, application permission assignments and delegated permission grants |

No write permission is needed. Directory-wide read access is broad: protect and rotate the secret.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` (the same names as *Entra ID – Accounts* and the Entra control automations; reuse them only if the same app registration is intended).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the applications and permissions with **Enterprise applications → (application) → Permissions** in Entra.
5. Attach the feed to an Account Review (Snapshot or Differential). For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `INCLUDE_MICROSOFT_APPS` | `false` | True also lists applications owned by Microsoft. Usually left off: there are many and their permissions are set by Microsoft. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero applications with permissions is a legitimate result. |
| `MAX_APPS` | `2000` | 1–5000 applications in the feed; exceeding it aborts instead of truncating. The run also reads up to ten times this number of enterprise applications in total, capped at 10,000. |
| `MAX_OBJECTS` | `10000` | 1–10000 delegated permission grants in the tenant, and application permission assignments per application; exceeding it aborts. |

Fixed limits: 200 seconds of collection (`TPL_RUN_SECONDS`), 4 MB per response, 100 pages per collection, 8 MiB of feed, 48 MiB of memory, three attempts when Microsoft throttles for up to 30 seconds. Requests are grouped 20 at a time with Graph JSON batching; only the token endpoint and Graph reads are allowed (`TPL_ALLOWED_REQUESTS`).

Changing `INCLUDE_MICROSOFT_APPS` changes the population: take a new baseline.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed:

- Below the `RESULT: OK` line, the summary counts the applications by owner (third-party, your organization, Microsoft) and the permissions listed per kind.
- An empty list is not published (`RESULT: NOT PUBLISHED`); the reason suggests turning on `INCLUDE_MICROSOFT_APPS` to include Microsoft applications, or `ALLOW_EMPTY_FEED` if no application with permissions is expected.
- A Differential review reports an application that gained or lost a permission (*Change in roles*), and a new application with permissions (*Added account*). An application whose last permission was removed, or that was deleted, shows as *Deleted account*. The application's name is part of the row's identifier, so renaming an application shows it as deleted and added again.
- The number of users who consented for themselves is in the description and is never compared: a new user consenting to a permission already listed is not reported.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `writeAccountReviewArtifact() is unavailable` | Create the automation from the Account Review feed form, not as a control automation |
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the client secret value |
| `Microsoft API HTTP 401/403` or `HTTP 403 for a collection` | Check secret expiry, `Directory.Read.All` and admin consent |
| `No applications with permissions found` | Turn on `INCLUDE_MICROSOFT_APPS` to include Microsoft applications, or `ALLOW_EMPTY_FEED` if none is expected |
| `More applications than MAX_APPS` | Raise `MAX_APPS` (up to 5000) |
| `Collection limit exceeded` | More than 10,000 enterprise applications or delegated permission grants: this version cannot review the tenant in one feed |
| `An application permission cannot be resolved` or collection changed | Applications changed during the pull; retry later |
| `unsupported consent type` | Microsoft added a consent type this version does not know; update the automation |
| Time or size limit | Turn off `INCLUDE_MICROSOFT_APPS` |

## 10. Customising

Adjust the documented variables. Other permission types need new collection code. Section 4 of `run.php` is a copy of the [shared code blocks](../../../_shared/): do not edit it there.

## 11. Removing

Stop or reconfigure the Account Reviews that use the feed before removing it. Remove the app registration and secrets when no other automation uses them. Removing the automation does not revoke any permission.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft |

References: [client credentials](https://learn.microsoft.com/en-us/entra/identity-platform/v2-oauth2-client-creds-grant-flow), [service principals](https://learn.microsoft.com/en-us/graph/api/serviceprincipal-list?view=graph-rest-1.0), [app role assignments](https://learn.microsoft.com/en-us/graph/api/serviceprincipal-list-approleassignments?view=graph-rest-1.0), [delegated permission grants](https://learn.microsoft.com/en-us/graph/api/oauth2permissiongrant-list?view=graph-rest-1.0), [JSON batching](https://learn.microsoft.com/en-us/graph/json-batching), [throttling](https://learn.microsoft.com/en-us/graph/throttling).
