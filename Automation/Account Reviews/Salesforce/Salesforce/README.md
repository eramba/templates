---
id: salesforce-account-review
name: Salesforce Accounts and Assigned Access
version: 0.1.1
status: draft
technology: Salesforce
vendor: Salesforce
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - salesforce_my_domain
  - salesforce_client_id
  - salesforce_client_secret_b64
variables:
  - USER_KEY
  - ROLES
  - USER_TYPES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
  - MAX_ASSIGNMENTS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Salesforce Accounts and Assigned Access

**Technology:** Salesforce Platform (Sales Cloud, Service Cloud and any other org).

Send Salesforce users with their profile, role, permission sets and permission set groups to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

Supplies the current list of users of one Salesforce org to review: who has an account, whether it is active, and which profile, role, permission sets and permission set groups grant their access. eramba owns the schedule, comparison, reviewers, decisions and findings. The script is read-only: it only runs SOQL queries.

**Review types:** use this script as a feed of type *List of accounts to be reviewed* in Snapshot, Differential or Exit reviews. For Exit reviews, compare it with an employee feed such as [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) whose key holds the same value (the work email by default); see [review types](../../README.md#review-types).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | `Email` by default; configurable to `Username` or the 18-character user `Id` |
| `description` | Name, `active` or `inactive`, user type (e.g. `Standard`) and user ID |
| `roles` | `Profile: <profile name> [<profile id>]` (one per user) |
| | `Role: <role name> [<role id>]` (the user's position in the role hierarchy, when set) |
| | `Permission set: <label> [<permission set id>]` for every permission set assigned directly |
| | `Permission set group: <label> [<permission set group id>]` for every permission set group assigned |

All users are included, inactive ones too (`IsActive = false`), of every user type unless `USER_TYPES` narrows it.

## 3. Coverage

Collected through the REST API query resource (API v62.0) with complete `nextRecordsUrl` pagination: `User` and `PermissionSetAssignment`. The `totalSize` Salesforce reports must stay the same on every page and match the records received, otherwise the run stops.

- Permission sets owned by a profile (`PermissionSet.IsOwnedByProfile = true`) are not listed separately: they are the profile, listed as `Profile`.
- An assignment of a permission set group is listed only as the group, not as the group's aggregate permission set or its member permission sets.

Not covered: frozen users (`UserLogin.IsFrozen`; a frozen user still shows as `active`), public groups and queues, sharing rules and manual sharing, delegated administration, permission set licences, Experience Cloud (community) membership details, and other orgs. Access is listed as granted, not as effectively used.

**To validate on a real org:** the minimum permissions of the run-as user (§5), the permission set group fields on `PermissionSetAssignment` (`PermissionSetGroupId`, `PermissionSetGroup.MasterLabel`), and run time on orgs with many users and assignments.

Any collection error, an assignment for a user outside the collected list, or a limit aborts the run and nothing is published.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A Salesforce administrator who can create an external client app (or connected app) and an integration user.
- The org's **My Domain** host, e.g. `example.my.salesforce.com` (sandboxes: `example--uat.sandbox.my.salesforce.com`), from **Setup → My Domain**. The client credentials flow only works on the My Domain host, not on `login.salesforce.com`.

## 5. Setup on Salesforce

Salesforce recommends the **OAuth 2.0 client credentials flow** for server-to-server integrations without a user present, and **external client apps** as the newer replacement for connected apps (a connected app with the same settings works too).

1. Create a dedicated integration user (for example with the **Salesforce Integration** user licence and the **Minimum Access - API Only Integrations** profile) and assign a permission set granting only:

| Permission | Why |
|---|---|
| API Enabled | Use the REST API |
| View Setup and Configuration | Read profiles, roles, permission sets, permission set groups and their assignments |
| View All Users | See every user, whatever the org's user sharing settings |

   Do not give it Modify All Data, Manage Users or any write permission: the script only reads.
2. **Setup → External Client App Manager → New External Client App** (distribution **Local**). Enable OAuth with the scope **Manage user data via APIs (`api`)**, and enable **Client Credentials Flow**.
3. In the app's **Policies**, enable the client credentials flow and set **Run As** to the integration user. Restrict IP ranges if your eramba egress IP is fixed.
4. In the app's **Settings → OAuth Settings → Consumer Key and Secret**, copy the consumer key and consumer secret. Base64-encode the secret on one line.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `salesforce_my_domain` (host only), `salesforce_client_id` (consumer key), `salesforce_client_secret_b64`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the users and access with Salesforce (**Setup → Users**).
5. Attach the feed to an Account Review.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Account identifier: `email`, `username` or `id`. Salesforce does not require emails to be unique: two users with the same email abort the run; use `username` then. |
| `ROLES` | `['profile', 'role', 'permission_sets', 'permission_set_groups']` | Access listed as roles. `[]` lists users only; queries for kinds not listed are skipped. |
| `USER_TYPES` | `[]` | Salesforce user types to include, e.g. `['Standard']` for internal licensed users only (excludes Experience Cloud, Chatter and automated users). `[]` includes every type. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–5000 users; exceeding it aborts instead of truncating. For more accounts, split the review into several feeds with `USER_TYPES`. |
| `MAX_ASSIGNMENTS` | `100000` | 1–500000 permission set (group) assignments; exceeding it aborts. |

Fixed limits: 200 seconds of collection, 4 MB per response, 8 MiB of feed, 48 MiB of memory. Salesforce API request limits apply to the org (one request per 2000 records). Only `POST https://<my domain>/services/oauth2/token` and `GET https://<my domain>/services/data/v62.0/query` are allowed; the token must be issued for the same My Domain.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only, never emails, names or tokens. Accounts are published once, after the whole list is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts published and the roles listed per kind; `RESULT: NOT PUBLISHED` when the provider returned no accounts and `ALLOW_EMPTY_FEED` is off; or `RESULT: FAILED` with the error. Added, deleted or creeping accounts are computed by eramba afterwards: they are review results, never a failure of the script.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names and that the value is the base64 of the consumer secret |
| `The Salesforce My Domain must look like…` | Use the host only, e.g. `example.my.salesforce.com`, not `login.salesforce.com` or a URL with a path |
| `HTTP 400` on the token | Client credentials flow not enabled, no Run As user, or wrong consumer key or secret |
| `issued the token for another instance URL` | The secret is not the org's My Domain host |
| `HTTP 401` / `HTTP 403` on a query | The run-as user lacks API Enabled or View Setup and Configuration |
| Fewer users than in Setup | The run-as user cannot see all users: grant View All Users |
| Two accounts share the same key | Duplicate emails; set `USER_KEY` to `username` |
| Access outside the collected users | Users changed during the pull, or `USER_TYPES` changed; retry later |
| Limit exceeded | Raise `MAX_USERS` / `MAX_ASSIGNMENTS`, or narrow `USER_TYPES` |

## 10. Customising

Adjust the documented variables. Frozen state, public groups or queues need new collection code. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

## 11. Removing

Stop the Account Reviews that use the feed, then remove the feed, delete the external client app, deactivate the integration user and delete the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
| 0.1.1 | The log ends with a SUMMARY block: `RESULT: OK` (list published, with counts), `RESULT: NOT PUBLISHED` (empty list refused) or `RESULT: FAILED` (error, nothing published); `MAX_USERS` defaults to 2000 and is limited to 5000 |

References: [OAuth 2.0 client credentials flow](https://help.salesforce.com/s/articleView?id=sf.remoteaccess_oauth_client_credentials_flow.htm), [external client apps](https://help.salesforce.com/s/articleView?id=sf.external_client_apps.htm), [REST API query](https://developer.salesforce.com/docs/atlas.en-us.api_rest.meta/api_rest/resources_query.htm), [User](https://developer.salesforce.com/docs/atlas.en-us.object_reference.meta/object_reference/sforce_api_objects_user.htm), [PermissionSetAssignment](https://developer.salesforce.com/docs/atlas.en-us.object_reference.meta/object_reference/sforce_api_objects_permissionsetassignment.htm), [PermissionSetGroup](https://developer.salesforce.com/docs/atlas.en-us.object_reference.meta/object_reference/sforce_api_objects_permissionsetgroup.htm), [API request limits](https://developer.salesforce.com/docs/atlas.en-us.salesforce_app_limits_cheatsheet.meta/salesforce_app_limits_cheatsheet/salesforce_app_limits_platform_api.htm).
