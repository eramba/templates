---
id: openai-account-review
name: OpenAI Organization Members and Project Access
version: 0.1.1
status: draft
technology: OpenAI API Platform
vendor: OpenAI
eramba_module: Account Review Feeds
trigger: Account Review pull
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

# OpenAI Organization Members and Project Access

**Technology:** OpenAI API Platform (`platform.openai.com`).

Send the members of an OpenAI API Platform organization, their organization role and their project memberships to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of OpenAI organization members to review. eramba owns the schedule, snapshot/differential comparison, reviewers, decisions and findings. The script is read-only: it approves nothing and revokes nothing.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Member email by default; configurable to the OpenAI user ID (`user-…`) |
| `description` | Name (or email), organization role, `service account`, `disabled` or `banned` when OpenAI reports it, and user ID |
| `roles` | `Organization role: <Role> [<role>]`, e.g. `Organization role: Owner [owner]` or `Organization role: Reader [reader]` (`org_role`) |
| | `Project: <project name> [<project id> / <project role>]`, e.g. `Project: Payments [proj_abc / owner]` (`projects`) |

Every member returned by the organization users list is included. The `disabled`, `banned` and `service account` markers come from optional fields of the user object and only appear when OpenAI returns them.

## 3. Coverage

One OpenAI organization per automation (the organization of the Admin API key). Calls, all `GET`:

- `/v1/organization/users`: organization members and their role.
- `/v1/organization/projects`: active projects (archived projects are not listed and grant no access).
- `/v1/organization/projects/{project_id}/users`: members of each project and their project role.

Not covered: pending invites, project service accounts and their API keys, project and organization API keys, groups, custom roles assigned through role-based access control, ChatGPT workspaces (ChatGPT Enterprise/Team members are managed elsewhere), and permissions inside each project. A project membership shows who can work in the project; what they can do there also depends on the project role and any custom roles.

To validate on a real organization: whether the organization users list includes service-account users and whether they have an email (with the default `USER_KEY`, a member without an email aborts the run), whether the optional `user.enabled` and `user.banned` fields are returned, and whether every member has a `role` in organizations that use custom roles (a missing role aborts the run while `org_role` is selected).

Lists are read with `limit=100` and the `after` cursor until `has_more` is false. A missing or repeated cursor, a duplicate member, a project member missing from the organization list, any other API error or a limit aborts the run and nothing is published.

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
| `MAX_USERS` | `2000` | 1–10000 members; exceeding it aborts instead of truncating. |
| `MAX_OBJECTS` | `2000` | 1–10000 projects, members per project, or roles per member; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 200 pages per list, 8 MiB of feed, 48 MiB of memory. Throttling (HTTP 429) is retried when the requested wait fits the budget. Only `GET https://api.openai.com/v1/organization/users` and `/v1/organization/projects` requests are allowed.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never names, emails or keys. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

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
| 0.1.1 | The log ends with a SUMMARY block (`RESULT: OK` or `RESULT: FAILED` and counts) |

References: [Administration API overview](https://platform.openai.com/docs/api-reference/administration), [users](https://platform.openai.com/docs/api-reference/users), [projects](https://platform.openai.com/docs/api-reference/projects), [project users](https://platform.openai.com/docs/api-reference/project-users), [Admin API keys](https://platform.openai.com/docs/api-reference/admin-api-keys), [roles and permissions](https://developers.openai.com/api/docs/guides/rbac), [OpenAPI specification](https://github.com/openai/openai-openapi).
