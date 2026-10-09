---
id: google-workspace-mfa
name: Multi-Factor Authentication Coverage Review
version: 0.1.0
status: draft
vendor: Google
technology: Google Workspace Admin SDK Directory
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Multi-Factor Authentication Coverage Review
policies:
  - Access Management > Authentication
secrets:
  - google_service_account_json_b64
variables:
  - CUSTOMER_ID
  - DELEGATED_ADMIN_EMAIL
  - MAX_USERS
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - google/apiclient:^2.18
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Multi-Factor Authentication Coverage Review

**Technology:** Google Workspace. **Status:** draft; real Google/eramba validation pending.

Check Google-native interactive user authentication, including administrators. Use this integration only for a control whose agreed scope is that authentication surface. A broader control needs additional system and access-path evidence before completion.

## 1. Controls and policies

Source: **Multi-Factor Authentication Coverage Review**, identified by title in [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). No separate success criteria are supplied.

The methodology requires a system/admin-interface inventory, enabled and enforced MFA, exceptions with compensating controls, a coverage rate and identified gaps. Review at least annually; schedule January 1 or your chosen annual review date. The policy mapping provides context only.

## 2. What it checks

| Check | Evaluation |
|---|---|
| Population | Every Directory user in the customer; no domain or MFA-success filter. Suspended or archived users are excluded from active coverage. |
| MFA | Active accounts must report both `isEnrolledIn2Sv=true` and `isEnforcedIn2Sv=true`. Enrollment alone is insufficient. |
| Administrators | Includes super administrators and delegated administrators in the same population. |
| Gaps | Missing fields, MFA gaps and an empty active population leave the audit pending for review. |

All active accounts with complete, positive evidence yield Passed **within the stated Google-native scope**. Exceptions cannot be accepted automatically: available observations are attached with a pending comment; result and completion dates remain untouched. The reviewer determines whether gaps represent failure or acceptable documented exceptions with compensating controls.

## 3. Coverage

Directory supplies an identity/configuration export, not an inventory of every external application, VPN or admin interface. Confirm and retain the system/access-path inventory defining this control's Google-native scope before linking the automation.

Federated SSO, app passwords, OAuth/API access, service accounts and systems outside Google-native user sign-in are not evaluated. Do not attach this script alone to an organization-wide MFA control. Directory settings do not prove a particular authentication event used MFA or establish the strength of its factors. The export reflects current configuration, not uninterrupted enforcement throughout the year.

## 4. Before you start

Use eramba Enterprise with a PHP 8.4-compatible runner, Composer access and a disposable audit. Establish the scope above and confirm the Directory identity can list all users, including all domains and organizational units. Selective permissions may silently reduce visibility.

## 5. Setup on Google

1. Enable the Admin SDK API in a Google Cloud project. Create a dedicated service account and signing key; protect and rotate that key.
2. Authorize domain-wide delegation for its numeric client ID with **only** `https://www.googleapis.com/auth/admin.directory.user.readonly`. Administrative approval is needed during setup.
3. Set `DELEGATED_ADMIN_EMAIL` to a Workspace administrator with user-read access across the complete customer. Do not use a super-admin identity for routine collection when a delegated role suffices.
4. Encode the downloaded service-account JSON as a single-line base64 value and store it in eramba Secret `google_service_account_json_b64`. Base64 prevents source-substitution quoting problems; it does not encrypt the key.

See Google's [service-account delegation guide](https://github.com/googleapis/google-api-php-client/blob/main/docs/oauth-server.md), [Users list](https://developers.google.com/workspace/admin/directory/reference/rest/v1/users/list) and [user field definitions](https://developers.google.com/workspace/admin/directory/reference/rest/v1/users).

Uses the [official Google PHP client](https://github.com/googleapis/google-api-php-client) and [official Guzzle](https://docs.guzzlephp.org/en/stable/overview.html#installation) to set transport limits. Only signing fields are imported from the credential; credential URLs are ignored. TLS verification stays enabled and redirects are disabled. Allow HTTPS to Google's OAuth and Admin SDK endpoints (`oauth2.googleapis.com`, `admin.googleapis.com`, and `www.googleapis.com` as used by the installed SDK).

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md). Paste [run.php](run.php), add the Secret above, set the two Composer entries from the metadata and use a 240-second timeout. Set the delegated identity, confirm the customer and test with `DRY_RUN=true` on a disposable audit. Restore `false` for live execution.

Review the resolved dependencies and run Composer's advisory audit in the installation environment. Official provenance is not a guarantee that every resolved release is free of vulnerabilities.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `CUSTOMER_ID` | `my_customer` | Customer of the delegated administrator; alternatively the explicit Google customer ID. |
| `DELEGATED_ADMIN_EMAIL` | Empty | Required user-read administrator to impersonate. Omitted from evidence attachments. |
| `MAX_USERS` | `2000` | 1–5000 including inactive users; overflow aborts without a partial result. |
| `DRY_RUN` | `false` | True suppresses all eramba writes. |
| `RESULT_PASSED_ID` | `2` | Verify the Passed option in your installation. |
| `RESULT_FAILED_ID` | `1` | Common reporting setting; this evaluator leaves unresolved gaps pending instead of automatically failing them. |
| `MAX_LOG_ITEMS` | `5` | 1–10 gap examples in the summary. |

Collection uses 100 users per page, at most 100 pages and approximately 170 seconds, with 20-second request limits and a 2 MB download cap per response. SDK retries are disabled: quota or transport errors abort for a later retry. Evidence is capped at 600 KB.

## 8. Results

CSV observations contain account IDs and MFA/admin flags, without names, email addresses or credentials. TXT captures settings, excluding the delegated email. Restrict evidence access appropriately.

Pending execution uploads evidence and adds a follow-up comment only. It does not clear an existing result or schedule a retry; use an audit without a previous result. A definitive pass saves result/dates and an evidence comment. Writes are not transactional: inspect partial writes before retrying; repeated execution may duplicate attachments/comments.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Client initialization | Check official Composer installation, signing key and service-account fields. |
| Directory request failure | Check delegation client ID/scope, administrator permissions, API enablement, quotas and network access. |
| Missing MFA/status fields | Confirm full admin-view access and inspect the export; missing data never counts as coverage. |
| Pending gaps | Review configuration, exceptions and compensating controls; complete the audit manually. |
| Empty users or limits | Verify customer and visibility, or reassess scope; no truncated population passes. |
| Persistence error | Inspect result, attachments and comments before retrying. |

## 10. Customising

For SSO-backed users, collect enforcement evidence from the identity provider and map the relevant applications before extending this implementation. Do not replace enforced MFA with enrollment or silently exclude exception accounts.

## 11. Removing

Unlink the automation, remove unused domain-wide delegation and revoke/delete its signing key. Retain historical audit evidence.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial Google-native MFA export and pending exception review. Real SDK authentication, installation and provider validation pending. |
