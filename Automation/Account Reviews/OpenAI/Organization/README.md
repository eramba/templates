---
id: openai-account-review
name: OpenAI – Accounts
version: 0.1.1
status: draft
technology: OpenAI API Platform
vendor: OpenAI
eramba_module: Account Review Feeds
secrets:
  - openai_admin_key
variables:
  - USER_KEY
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_OBJECTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# OpenAI – Accounts

**Technology:** OpenAI API Platform (`platform.openai.com`).

Sends eramba every member of your OpenAI API Platform organization and the access each one has: its organization role and its projects. Reviewers then mark in eramba which members and accesses should stay and which should be removed; nothing is changed in OpenAI.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One member of one OpenAI organization, identified by its email, for example `ana.garcia@example.com` (or its `user-…` ID with `USER_KEY`) |
| **Connects to** | Your OpenAI organization (`api.openai.com`), through OpenAI's official Administration API. Read-only requests |
| **Permission needed** | An Admin API key of the organization, created by an organization owner. Admin keys are separate from project keys. Whether a read-only Admin key is enough is still to be confirmed (§5) |
| **Included** | Every member the organization users list returns |
| **Not included** | Pending invitations; project service accounts and API keys; groups; custom roles assigned through role-based access control; ChatGPT workspaces; what each project role allows; archived projects (they grant no access); other organizations (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. This version has no filter to split a larger population |
| **Roles listed** | Organization role and project memberships with their project role |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's membership of the organization. Its roles are every access that member holds. This automation lists two kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Organization role** | The member's role in the organization, such as *Owner* or *Reader*. One per row | `Organization role: Owner [owner]` |
| **Project** | The member can work in this project, with the project role shown. One entry per project | `Project: Payments [proj_abc / owner]` |

```
Account:     ana.garcia@example.com
Description: Ana García; organization owner; id=user-…
Roles:       Organization role: Owner [owner]
             Project: Payments [proj_abc / owner]
```

The text in square brackets is OpenAI's role code or project ID, with the project role. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, the organization role, `service account`, `disabled` or `banned` when OpenAI reports it, and the user ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the members are listed.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what someone can do in OpenAI | In OpenAI, permissions sit on the account itself (its organization role and project roles), so this account review already shows them |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). A leaver's membership stays listed until it is removed from the organization |

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- An **Organization Owner** of the OpenAI API Platform organization, to create the Admin API key.
- HTTPS reach from eramba to `api.openai.com`.

## 5. Setup on OpenAI

1. Sign in to the OpenAI platform as an organization owner and open **Settings → Organization → Admin keys** (`platform.openai.com/settings/organization/admin-keys`).
2. **Create new admin key**, name it after eramba. When the dialog offers permission choices, grant only read access to organization users and projects; the script never writes. Whether a read-only Admin key can read these endpoints is to validate on a real organization.
3. Copy the key (it is shown once) and store it as the eramba secret.

Admin API keys are separate from project API keys: a project key cannot call these endpoints. Treat the Admin key as highly privileged, store it only in eramba's secrets and rotate it with your credential process.

## 6. Setup in eramba

1. Create the secret in **Settings → Application Configuration → Automation Secrets**: `openai_admin_key` (the Admin API key).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the members and roles with **Settings → Organization → Members** and each project's members page.
5. Attach the feed to an Account Review. For differential reviews, take a deliberate first baseline.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email` or `id` (the stable `user-…` ID). Must exist on every member; no fallback. |
| `ROLES` | `['org_role', 'projects']` | Access listed as roles. `[]` lists members only and skips the project calls. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero members is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 members; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |
| `MAX_OBJECTS` | `2000` | 1–10000 projects, members per project, or roles per member; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried when the requested wait fits the budget. Only `GET https://api.openai.com/v1/organization/users` and `/v1/organization/projects` requests are allowed.

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the secret `openai_admin_key` |
| `HTTP 401` | The key is invalid, revoked, or a project key instead of an Admin key |
| `HTTP 403` | The Admin key lacks read permission for users or projects; create one with those permissions |
| `Account key is missing` | A member has no email (for example a service account); set `USER_KEY` to `id` |
| `Organization role is missing` | A member has no organization role; remove `org_role` from `ROLES` and report it |
| Project member not in the organization list | Members changed during the pull; retry later |
| Throttling or time limit | Many projects; schedule the pull outside busy hours or set `ROLES` to `['org_role']` |

## 10. Customising

Adjust the documented variables. Other access types (service accounts, groups, custom roles) need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the Admin key in **Settings → Organization → Admin keys**, and remove the secret.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [Administration API overview](https://platform.openai.com/docs/api-reference/administration), [users](https://platform.openai.com/docs/api-reference/users), [projects](https://platform.openai.com/docs/api-reference/projects), [project users](https://platform.openai.com/docs/api-reference/project-users), [Admin API keys](https://platform.openai.com/docs/api-reference/admin-api-keys), [roles and permissions](https://developers.openai.com/api/docs/guides/rbac), [OpenAPI specification](https://github.com/openai/openai-openapi).
