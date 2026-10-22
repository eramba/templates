# Account Review Automations

PHP scripts that send the list of accounts and their access to an eramba **Account Review feed** (Security Operations → Account Reviews → Feeds). eramba owns the review schedule, comparisons, reviewer decisions and findings. These scripts only read; they never revoke access.

## Install

1. Choose an automation below and read its README: scope, prerequisites and permissions.
2. Create a dedicated read-only identity at the provider and store its credentials in eramba **Automation Secrets** with the exact names in the README.
3. Add an **Account Review Feed** with **Source = Automation** and **Type = List of accounts to be reviewed**, and add the automation from that form: PHP version, Composer packages, timeout and the full `run.php`.
4. Test the automation and the feed. The Test output ends with a **SUMMARY** block: `RESULT: OK` with the number of rows published (and roles per kind), `RESULT: NOT PUBLISHED` for an empty list the script refuses to send, or `RESULT: FAILED` with the error. Differences found later by the review are results, not failures. Compare the accounts and roles with the provider before scheduling a review.
5. Attach the feed to an Account Review and configure frequency and reviewers.

## Review types

eramba reviews one feed of accounts, and for Exit reviews compares it with a second feed of people:

| Review | Feeds | Scenarios reviewers get |
|---|---|---|
| **Snapshot** | Accounts | *Account pulled*: every account, with its roles if the feed lists them |
| **Differential** | Accounts | *Added account* and *Deleted account* since the last successful pull; *Change in roles* when the feed lists roles. Description changes are ignored. |
| **Exits** | Accounts + current or former employees | *Creeping account*: an account that matches no current employee (or matches a former employee), *with roles* when the feed lists them |

Each accounts script has a `ROLES` variable. With roles, reviews also show what each account can do and Differential reviews report role changes. With `ROLES = []` the feed only lists accounts: reviews then answer whether someone has an account at all, which is often enough for Exit reviews and is faster to collect.

Every accounts feed below works for the three review types. For Exit reviews, the usual setup compares each application (GitHub, Zoom, Zendesk, AI tools…) with the identity provider, where access is removed when someone leaves, for example with [Entra ID Current Employees](Microsoft/Entra%20ID%20Current%20Employees/). An HR list can play the same role.

Matching uses the `user` value of both feeds, ignoring case. Choose each script's `USER_KEY` so both feeds hold the same value, usually the work email. Keep it unchanged once a Differential review has a baseline: a changed key appears as a removed and an added account. Renaming a group, role or application appears as a role change.

## Catalogue

Status: `draft` (not yet validated on real eramba and the provider) · `tested` · `stable`.

**Accounts feeds** (type *List of accounts to be reviewed*)

| Provider | Scope | Version / status |
|---|---|---|
| [Microsoft Entra ID / Microsoft 365](Microsoft/Entra%20ID/) | Users, nested groups, scoped directory roles, application role assignments and licences | 0.1.0 draft |
| [Okta Workforce Identity](Okta/Workforce%20Identity/) | Users (deactivated included), groups, application assignments and admin roles | 0.1.0 draft |
| [Auth0](Auth0/Auth0/) | Up to 1000 users, roles and organization memberships | 0.1.0 draft |
| [AWS IAM](AWS/IAM/) | IAM users and root, groups, policies, permissions boundaries and active credentials (one account) | 0.1.0 draft |
| [AWS IAM Identity Center](AWS/IAM%20Identity%20Center/) | Users, groups and permission sets per AWS account, direct or through a group | 0.1.0 draft |
| [Google Workspace](Google/Workspace/) | Users (suspended and archived included), groups, admin roles and optionally org unit | 0.1.0 draft |
| [GitHub Organizations](GitHub/Organizations/) | Members and outside collaborators of one or more organizations, org role and teams | 0.1.0 draft |
| [Atlassian Cloud: Jira, Confluence, Jira Service Management…](Atlassian/Cloud/) | Organization users, product access per site, groups and platform roles; all products or only the ones in `PRODUCTS` | 0.1.0 draft |
| [Slack](Slack/Slack/) | Workspace members (deactivated and invited included), workspace role and user groups | 0.1.0 draft |
| [Zoom](Zoom/Zoom/) | Active, inactive and pending users, role, licence type and groups | 0.1.0 draft |
| [Salesforce](Salesforce/Salesforce/) | Users (inactive included), profile, role, permission sets and permission set groups | 0.1.0 draft |
| [ServiceNow](ServiceNow/ServiceNow/) | Users (inactive and locked included), direct and inherited roles, groups | 0.1.0 draft |
| [OpenAI](OpenAI/Organization/) | Organization members, organization role and project roles | 0.1.0 draft |
| [Anthropic](Anthropic/Organization/) | Organization members, organization role and workspace roles | 0.1.0 draft |

**Employee feeds** (type *List of current employees*, for Exit reviews)

| Source | Scope | Version / status |
|---|---|---|
| [Entra ID Current Employees](Microsoft/Entra%20ID%20Current%20Employees/) | Enabled member users of the tenant or of one group, optionally only those with an Employee ID | 0.1.0 draft |
| [Workday Current Employees](Workday/Current%20Employees/) | Active workers from a Workday custom report (RaaS); also a former-employees mode | 0.1.0 draft |

## How feed scripts differ from control automations

They follow the same structure as the [Internal Controls](../Internal%20Controls/) scripts (numbered sections, `$secrets`, `$config`, official Composer packages) and reuse the [shared code blocks](../_shared/), with three differences:

- **stdout is reserved** for the artifact declaration written by `writeAccountReviewArtifact()`. The step log goes to stderr, which feed automations accept when the run exits 0, and ends with `tpl_logSummary()` or `tpl_logFailure()`. Never `echo` to stdout.
- **Each row** has `user`, `description` and `roles`. Account keys must be unique ignoring case, as eramba compares them ignoring case, and values must fit 255 characters.
- **Never publish a partial list.** Any collection, permission or limit error exits 1 without publishing; otherwise a differential review would report the missing accounts as removed. An empty population must be allowed explicitly.

## Contributing

Start from the [accounts template](_TEMPLATE/Accounts/) or the [employees template](_TEMPLATE/Employees/): copy it to `<Vendor>/<Product>/`, keep the shared blocks you need and fill them with `php Automation/tools/sync.php update <run.php>`. Only write section 5 (collection) and the README; never edit code between shared-block markers (see [shared code blocks](../_shared/)). Run `php Automation/tools/sync.php check` before committing.

Document exact read permissions, all variables and limits, and what is not covered. Commit only public documentation and placeholder configuration: no credentials, tenant identifiers, account exports or screenshots of real installations. Keep integrations `draft` until validated on a real eramba and provider.
