# Account Review Automations

PHP scripts that send eramba the list of accounts and their access, or the list of groups, policies and applications and what they grant, for an eramba **Account Review feed** (Security Operations → Account Reviews → Feeds). eramba owns the review schedule, comparisons, reviewer decisions and findings.

This page explains everything that is the same for every Account Review automation. Each automation's own README only covers what is specific to it: what one row looks like, what it connects to, the permission it needs, its options and its setup.

## How Account Review automations work

- An automation sends eramba a **feed**: a list of rows.
- Reviewers see the list and make **one yes/no decision for each row**: keep it or revoke it. So every row must be something a person can judge at a glance.
- Each row has three parts:
  - an **identifier**, for example an email address or a group name;
  - a **description**, shown to reviewers to help them decide but never compared between reviews;
  - a list of **roles**. A "role" is simply one item the reviewer must accept for that row, such as a group, a licence or a permission.
- The scripts **only read**. They never change anything in the source system.
- **Revoking in eramba removes nothing in the source system.** A revoke decision is a finding in eramba: someone must still remove the access in the system itself.

## Two review families

Every automation belongs to exactly one family. The family decides what a row is and which question the reviewer answers.

| | **Account review** | **Permission review** |
|---|---|---|
| **Each row is** | One account (a person, or a guest) | One thing that grants access: a group, a policy, a role, an application |
| **The roles of a row are** | The accesses that account holds | The permissions that thing grants |
| **Question for the reviewer** | Should this person still have this account and these accesses? | Does this grant more than it should? |
| **Typical reviewer** | The person's manager, the application owner, IT | IT, security, the owner of the group or policy |
| **Example (Entra ID)** | `ana@example.com` → Group: Finance, Application: Salesforce (role Sales Manager), License: Office 365 E3 | `Contoso Expenses` → Application permission (admin consent): Microsoft Graph / User.Read.All, Delegated permission (admin consent): Microsoft Graph / Mail.Read |
| **Example (AWS IAM)** | `ana` → Policy: ReadOnlyAccess, Group: Developers | `Policy: DevelopersPolicy` → `s3:GetObject on arn:…`, `ec2:Describe*` |
| **What a Differential review flags** | A person who gained or lost an access; accounts created or deleted | A group, policy or role that gained or lost a permission; things created or deleted |
| **Exit reviews** | Yes: accounts that belong to no current employee | No: there is no person to compare |

The two families complement each other: an account review shows **who has** the IT group; a permission review shows **what** the IT group gives. Neither replaces the other.

### Which family applies to an application

- **Applications with groups or roles that carry permissions** (Entra ID, AWS, Salesforce, ServiceNow, Okta…): both families make sense. The account review lists the groups and roles each person has; the permission review lists what each group, role or policy grants.
- **Applications where permissions sit on the account itself** (no groups, or groups that grant nothing): only the account review applies, and its roles are the permissions themselves (for example "José: can read the whole directory", "Pedro: view only").
- **Groups that grant nothing** (for example email distribution lists) are not part of a permission review.

Each automation's README says which case applies.

### Narrowing an account review

"Who are the administrators", "who can sign in to Salesforce" and "who is in the Finance group" are still account reviews: each row is still one person. They are **options of the account automation** (set in its variables), not separate automations. This keeps one row per person and avoids a row like "the Finance group, with its 2,000 members", which nobody can judge with a single yes or no.

## Review types

eramba reviews one feed, and for Exit reviews compares it with a second feed of people:

| Review | Feeds | Scenarios reviewers get |
|---|---|---|
| **Snapshot** | One feed | *Account pulled*: every row, with its roles if the feed lists them |
| **Differential** | One feed | *Added account* and *Deleted account* since the last successful pull; *Change in roles* when the feed lists roles |
| **Exits** | Accounts + current or former employees | *Creeping account*: an account that matches no current employee (or matches a former employee), *with roles* when the feed lists them. Account reviews only |

The scenario names, and the first column of a review, say *account*. In a permission review, "account" means the group, policy, role or application of the row.

Each account review script has a `ROLES` variable. With roles, reviews also show what each account can do and Differential reviews report role changes. With `ROLES = []` the feed only lists accounts: reviews then answer whether someone has an account at all, which is often enough for Exit reviews and is faster to collect.

For Exit reviews, the usual setup compares each application (GitHub, Zoom, Zendesk, AI tools…) with the identity provider, where access is removed when someone leaves, for example with [Entra ID – Current employees](Microsoft/Entra%20ID%20Current%20Employees/). An HR list can play the same role.

Matching uses the `user` value of both feeds, ignoring case. Choose each script's `USER_KEY` so both feeds hold the same value, usually the work email.

### What counts as a change in a Differential review

**Reported**

- A new row, or a row that disappeared (the account, group or policy was deleted).
- A role added or removed: a group, an administrator right, an application, a licence, a permission.
- A renamed group, role or application. It shows as one role removed and one added.
- A changed identifier. If `USER_KEY` is changed once a Differential review has a baseline, every account shows as removed and added again, so keep it unchanged.

**Not reported**

- Any change in the description, such as a name change or an account changing between employee and guest.
- An account being disabled or enabled again.
- In an account review, a change in what a group, role or application allows. A permission review shows that.

## Results and failures

When you test an automation, its log ends with a **SUMMARY** block and one result line:

| Result | What it means | What eramba does |
|---|---|---|
| `RESULT: OK` | The list was sent. The result line gives the number of rows published; the lines below it give the roles listed per kind | Uses the new list for the review |
| `RESULT: NOT PUBLISHED` | There were no rows to publish (for example the provider returned an empty list), so nothing was sent. Each README says when an empty list is allowed | eramba records a failed pull and keeps the last successful pull |
| `RESULT: FAILED` | The script stopped with an error, given on the same line | Records the failed pull, emails the eramba administrator and keeps the last successful pull |

- **A failed run publishes nothing.** The scripts never send part of a list: if one page, permission or limit fails, nothing is sent. Otherwise a Differential review would report the missing accounts as removed.
- **Review results are never failures.** Added, deleted or creeping accounts are worked out by eramba after the pull. Differential and Exit reviews never make a run fail.
- **Logs contain counts only**, never account names, identifiers, tokens or provider responses.

## Catalogue

Status, once for every table:

- `draft`: written and documented, not yet validated end to end on a real eramba and the provider.
- `tested`: run successfully on a real eramba against the real provider.
- `stable`: tested, and in use without open issues.

### Account reviews

Feed type *List of accounts to be reviewed*. Snapshot, Differential and Exit reviews.

| Automation | Each row is | Roles listed | Status |
|---|---|---|---|
| [Entra ID – Accounts](Microsoft/Entra%20ID/) | One user account, employee or guest, of the tenant or of one group | Nested groups, scoped directory roles, application role assignments and licences | tested |
| [Okta – Accounts](Okta/Workforce%20Identity/) | One user account, deactivated included | Groups, application assignments and admin roles | draft |
| [Auth0 – Accounts](Auth0/Auth0/) | One user account (up to 1000) | Roles and organization memberships | draft |
| [AWS IAM – Accounts](AWS/IAM/) | One IAM user or the root user of one AWS account | Groups, policies, permissions boundaries and active credentials | draft |
| [AWS IAM Identity Center – Accounts](AWS/IAM%20Identity%20Center/) | One Identity Center user | Permission sets per AWS account, direct or through a group, and groups | draft |
| [Google Workspace – Accounts](Google/Workspace/) | One user account, suspended and archived included | Groups, admin roles and optionally org unit | draft |
| [GitHub – Accounts](GitHub/Organizations/) | One member or outside collaborator of one or more organizations | Org role and teams | draft |
| [Atlassian – Accounts](Atlassian/Cloud/) | One organization user | Product access per site (all products or only the ones in `PRODUCTS`), groups and platform roles | draft |
| [Slack – Accounts](Slack/Slack/) | One workspace member, deactivated and invited included | Workspace role and user groups | draft |
| [Zoom – Accounts](Zoom/Zoom/) | One user, active, inactive or pending | Role, licence type and groups | draft |
| [Salesforce – Accounts](Salesforce/Salesforce/) | One user, inactive included | Profile, role, permission sets and permission set groups | draft |
| [ServiceNow – Accounts](ServiceNow/ServiceNow/) | One user, inactive and locked included | Direct and inherited roles, groups | draft |
| [OpenAI – Accounts](OpenAI/Organization/) | One organization member | Organization role and project roles | draft |
| [Anthropic – Accounts](Anthropic/Organization/) | One organization member | Organization role and workspace roles | draft |

### Permission reviews

Feed type *List of accounts to be reviewed*. Snapshot and Differential reviews only.

| Automation | Each row is | Roles listed | Status |
|---|---|---|---|
| [Entra ID – Application permissions](Microsoft/Entra%20ID%20Application%20Permissions/) | One connected application (enterprise application) with at least one permission; Microsoft applications optional | Application permissions and delegated permissions, consented by an administrator or by individual users | tested |

### Employee lists (for Exit reviews)

Feed type *List of current employees*. Used as the second feed of an Exit review.

| Automation | Each row is | Roles listed | Status |
|---|---|---|---|
| [Entra ID – Current employees](Microsoft/Entra%20ID%20Current%20Employees/) | One enabled member user of the tenant or of one group, optionally only those with an Employee ID | None | tested |
| [Workday – Current employees](Workday/Current%20Employees/) | One active worker from a Workday custom report (RaaS); also a former-employees mode | None | draft |

## Install

1. Choose an automation above and read its README: what one row is, prerequisites and permissions.
2. Create a dedicated read-only identity at the provider and store its credentials in eramba **Automation Secrets** with the exact names in the README.
3. Add an **Account Review Feed** with **Source = Automation** and the feed type given in the catalogue, and add the automation from that form: PHP version, Composer packages, timeout and the full `run.php`.
4. Test the automation and the feed, and read the result line (see **Results and failures** above). Compare the rows and roles with the provider before scheduling a review.
5. Attach the feed to an Account Review and configure frequency and reviewers.

## How feed scripts differ from control automations

They follow the same structure as the [Internal Controls](../Internal%20Controls/) scripts (numbered sections, `$secrets`, `$config`, official Composer packages) and reuse the [shared code blocks](../_shared/), with three differences:

- **stdout is reserved** for the artifact declaration written by `writeAccountReviewArtifact()`. The step log goes to stderr, which feed automations accept when the run exits 0, and ends with `tpl_logSummary()` or `tpl_logFailure()`. Never `echo` to stdout.
- **Each row** has `user`, `description` and `roles`. Keys must be unique ignoring case, as eramba compares them ignoring case, and values must fit 255 characters.
- **Never publish a partial list.** Any collection, permission or limit error exits 1 without publishing (see **Results and failures** above). An empty population must be allowed explicitly.

## Contributing

Start from the [accounts template](_TEMPLATE/Accounts/) or the [employees template](_TEMPLATE/Employees/): copy it to `<Vendor>/<Product>/`, keep the shared blocks you need and fill them with `php Automation/tools/sync.php update <run.php>`. Only write section 5 (collection) and the README; never edit code between shared-block markers (see [shared code blocks](../_shared/)). Run `php Automation/tools/sync.php check` before committing.

In the README, state the family (Account review or Permission review) and fill sections 1–3 of the template README. Document exact read permissions, all variables and limits, and what is not covered. Do not repeat what this page explains. Commit only public documentation and placeholder configuration: no credentials, tenant identifiers, account exports or screenshots of real installations. Keep integrations `draft` until validated on a real eramba and provider.
