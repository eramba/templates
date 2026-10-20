# Account Review Automations

PHP scripts that send the list of accounts and their access to an eramba **Account Review feed** (Security Operations → Account Reviews → Feeds). eramba owns the review schedule, comparisons, reviewer decisions and findings. These scripts only read; they never revoke access.

## Install

1. Choose an automation below and read its README: scope, prerequisites and permissions.
2. Create a dedicated read-only identity at the provider and store its credentials in eramba **Automation Secrets** with the exact names in the README.
3. Add an **Account Review Feed** with **Source = Automation** and **Type = List of accounts to be reviewed**, and add the automation from that form: PHP version, Composer packages, timeout and the full `run.php`.
4. Test the automation and the feed. Compare the accounts and roles with the provider before scheduling a review.
5. Attach the feed to an Account Review and configure frequency and reviewers.

Use **Snapshot** to review the current list, **Differential** to review changes between pulls, or a **Leaver** review against a separate employee feed with matching account keys.

## Catalogue

Status: `draft` (not yet validated on real eramba and the provider) · `tested` · `stable`.

| Provider | Scope | Version / status |
|---|---|---|
| [Microsoft Entra ID](Microsoft/Entra%20ID/) | Users, nested groups, scoped directory roles and application role assignments | 0.1.0 draft |
| [Okta Workforce Identity](Okta/Workforce%20Identity/) | Users (deactivated included), groups, application assignments and admin roles | 0.1.0 draft |
| [Auth0](Okta/Auth0/) | Up to 1000 users, roles and organization memberships | 0.1.0 draft |
| [AWS IAM](AWS/IAM/) | IAM users and root, groups, policies, permissions boundaries and active credentials (one account) | 0.1.0 draft |

## How feed scripts differ from control automations

They follow the same structure as the [Internal Controls](../Internal%20Controls/) scripts (numbered sections, `$secrets`, `$config`, official Composer packages) and reuse the [shared code blocks](../_shared/), with three differences:

- **stdout is reserved** for the artifact declaration written by `writeAccountReviewArtifact()`. The step log goes to stderr, which feed automations accept when the run exits 0. Never `echo` to stdout.
- **Each row** has `user`, `description` and `roles`. Account keys must be unique ignoring case, as eramba compares them case-insensitively, and values must fit 255 bytes.
- **Never publish a partial list.** Any collection, permission or limit error exits 1 without publishing; otherwise a differential review would report the missing accounts as removed. An empty population must be allowed explicitly.

## Contributing

Start from the [template](_TEMPLATE/): copy it to `<Vendor>/<Product>/`, keep the shared blocks you need and fill them with `php Automation/tools/sync.php update <run.php>`. Only write section 5 (collection) and the README; never edit code between shared-block markers (see [shared code blocks](../_shared/)). Run `php Automation/tools/sync.php check` before committing.

Document exact read permissions, all variables and limits, and what is not covered. Commit only public documentation and placeholder configuration: no credentials, tenant identifiers, account exports or screenshots of real installations. Keep integrations `draft` until validated on a real eramba and provider.
