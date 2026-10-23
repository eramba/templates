---
id: salesforce-account-review
name: Salesforce – Accounts
version: 0.1.1
status: draft
technology: Salesforce
vendor: Salesforce
eramba_module: Account Review Feeds
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

# Salesforce – Accounts

**Technology:** Salesforce Platform (Sales Cloud, Service Cloud and any other org).

Sends eramba every user of your Salesforce org and the access each one has: its profile, role, permission sets and permission set groups. Reviewers then mark in eramba which users and accesses should stay and which should be removed; nothing is changed in Salesforce.

## 1. At a glance

| | |
|---|---|
| **Family** | Account review |
| **Each row is** | One Salesforce user of one org, identified by its email, for example `ana.garcia@example.com` (or its username or 18-character user ID with `USER_KEY`) |
| **Connects to** | Your Salesforce org (My Domain), through Salesforce's official REST API (v62.0) with read-only SOQL queries |
| **Permission needed** | A dedicated integration user with the permissions **API Enabled**, **View Setup and Configuration** and **View All Users**, reached through an external client app using the OAuth client credentials flow. A Salesforce administrator creates it (§5) |
| **Included** | Every user of the org, active or inactive, of every user type unless `USER_TYPES` narrows it |
| **Not included** | Frozen users (they still show as `active`); public groups and queues; sharing rules and manual sharing; delegated administration; permission set licences; Experience Cloud (community) membership details; what each profile or permission set allows; other orgs (one per automation) |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. To split a larger population, use `USER_TYPES` |
| **Roles listed** | Profile, role in the role hierarchy, permission sets and permission set groups |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

One row is one person's Salesforce user. Its roles are every access that user holds. This automation lists four kinds:

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| **Profile** | The user's base set of permissions. Every user has exactly one | `Profile: Standard User [<id>]` |
| **Role** | The user's position in the role hierarchy, which decides the records they can see. Listed when the user has one | `Role: Sales Manager [<id>]` |
| **Permission set** | An extra set of permissions assigned directly to the user, on top of the profile | `Permission set: Reports Export [<id>]` |
| **Permission set group** | A bundle of permission sets assigned to the user. Only the group is listed, not the sets inside it | `Permission set group: Sales Operations [<id>]` |

```
Account:     ana.garcia@example.com
Description: Ana García; active; Standard; id=005…
Roles:       Permission set: Reports Export [0PS…]
             Permission set group: Sales Operations [0PG…]
             Profile: Standard User [00e…]
             Role: Sales Manager [00E…]
```

The text in square brackets is Salesforce's identifier. It keeps two items with the same name apart; reviewers can ignore it. The description gives the name, whether the user is `active` or `inactive`, the user type and the user ID.

You choose which kinds are listed with `ROLES` (§7). With none, only the users are listed.

A row shows *that* Ana has the *Standard User* profile and a permission set, not what they allow.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| See what each permission set grants | *Salesforce – Permission set permissions* (planned) |
| Find people who left | Pair this feed with an employee list in an Exit review, for example [Entra ID – Current employees](../../Microsoft/Entra%20ID%20Current%20Employees/) or [Workday – Current employees](../../Workday/Current%20Employees/). Inactive users of leavers stay listed as inactive |

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

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
