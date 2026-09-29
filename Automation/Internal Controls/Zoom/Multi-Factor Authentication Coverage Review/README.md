---
id: zoom-mfa-coverage
name: Multi-Factor Authentication Coverage Review
version: 0.1.0
status: draft
vendor: Zoom
technology: Zoom account settings and users REST API
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Multi-Factor Authentication Coverage Review
policies:
  - Access Management > Authentication
secrets:
  - zoom_account_id
  - zoom_client_id
  - zoom_client_secret_b64
variables:
  - MAX_USERS
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Multi-Factor Authentication Coverage Review

**Technology:** Zoom. **Status:** draft; real Zoom/eramba validation pending.

**Use the IdP integration first for SSO deployments.** This example is retained for local Zoom authentication; it is not a general recommendation for federated environments.

Review account-wide MFA enforcement for active Zoom users, including administration access. Automatic completion is supported for local work-email sign-in with mandatory 2FA for all users and no enabled alternative authentication paths. Other configurations produce evidence for manual completion.

## 1. Controls and policies

Source: **Multi-Factor Authentication Coverage Review**, identified by title in [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). No separate success criteria are supplied.

The methodology requires an inventory of external systems and administrative interfaces, enabled and enforced MFA, documented exceptions with compensating controls, a coverage rate and gaps. Schedule at least annually, for example January 1. The policy mapping adds no checks.

## 2. What it checks

| Check | Evaluation |
|---|---|
| Population | All active account users, with cursor pagination and matching totals. No filter for successful MFA evidence. |
| Native MFA | `sign_in_with_two_factor_auth` must be `all`, with work-email sign-in enabled. Selected groups/roles need further review. |
| Other access paths | SSO, Google, Facebook, Apple, Microsoft, phone, passkey, Outlook and OTP settings must explicitly show disabled for automatic completion. Enabled or missing settings require separate evidence. |
| Consistency | Authentication settings are read again after pagination; changed settings or user totals abort the run. |

Disabling alternative methods is **not an additional control requirement**. It defines the configuration this implementation can conclusively evaluate. SSO and passkeys can be secure; this script does not establish their effective MFA guarantees and leaves them pending rather than failing the control.

## 3. Coverage

Link only to an agreed Zoom account scope. Retain the inventory identifying Zoom user and administrative sign-in surfaces. Other systems, external delegated administrators, service/API credentials, Zoom Rooms and meeting guest access require separate scope/evidence. Meeting passcodes and waiting rooms are not account MFA evidence.

The export demonstrates enforcement configuration, not individual factor enrollment or historical MFA events. The coverage numerator contains users with fully established authentication-path enforcement; when alternative paths remain unresolved, the script reports coverage as not established rather than declaring those users unprotected. Missing settings never mean disabled. Unknown authentication settings require review.

If no active users are returned, or any check needs review, evidence and a pending comment are saved without a final audit result or execution dates. Otherwise the configured scope passes. No exception is approved automatically.

## 4. Before you start

Use a paid Zoom account with account settings API access, eramba Enterprise, a PHP 8.4-compatible runner and a disposable audit. Confirm your app can read the whole account, not only selected groups. Review alternative authentication paths before relying on automatic completion.

## 5. Setup on Zoom

Create and activate a dedicated [Server-to-Server OAuth app](https://developers.zoom.us/docs/internal-apps/s2s-oauth/) for the account. Grant only the granular read scopes `account:read:settings:admin` and `user:read:list_users:admin`. Do not grant write or master-account scopes. App creation/activation requires the appropriate account role.

Store account ID and client ID unchanged in their eramba Secrets. Store the client secret as single-line base64 in `zoom_client_secret_b64`; base64 protects PHP substitution syntax, not confidentiality. Rotate it according to your credential lifecycle.

Uses [official Guzzle](https://docs.guzzlephp.org/en/stable/overview.html#installation) to call Zoom's REST endpoints directly; no unofficial Zoom PHP wrapper is installed. This is a REST audit integration, not a Meeting or Video SDK application. Allow HTTPS to `zoom.us` for token acquisition and `api.zoom.us` for API reads. TLS verification remains enabled, redirects are disabled and response URLs are never used as destinations.

API references: [account settings](https://developers.zoom.us/docs/api/accounts/), [users](https://developers.zoom.us/docs/api/users/) and [pagination](https://developers.zoom.us/docs/api/pagination/). Review the resolved Composer dependencies and security advisories before installation.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md). Add `zoom_account_id`, `zoom_client_id`, `zoom_client_secret_b64`, Composer `guzzlehttp/guzzle:^7.9`, and [run.php](run.php). Set timeout to 240 seconds. Use `DRY_RUN=true` for initial inspection, then restore `false` and validate a disposable audit before scheduling.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `MAX_USERS` | `2000` | 1–5000 active users; excess population aborts instead of truncating. |
| `DRY_RUN` | `false` | True prevents uploads, edits and comments. |
| `RESULT_PASSED_ID` | `2` | Verify your installation's Passed option. |
| `RESULT_FAILED_ID` | `1` | Common reporting setting; this evaluator keeps unresolved cases pending. |
| `MAX_LOG_ITEMS` | `5` | 1–10 examples in the conclusion. |

No switch disables required MFA checks. Collection is capped at 100 pages, 2 MB per response, 600 KB of evidence and approximately 170 seconds plus a bounded request. Requests have a 20-second timeout; 429/503 responses retry at most twice with delays up to five seconds. Reporting shares the 240-second runner budget.

## 8. Results

CSV records authentication-setting observations, user IDs and role IDs. Names, email addresses, tokens and full account settings are not attached or logged. TXT captures non-secret settings.

Passed saves result/dates and an evidence comment. Pending saves evidence/comment only; it does not clear an existing result or arrange a retry. Use an audit without a previous result. Errors can leave partial writes, and repeated runs may duplicate attachments/comments; inspect before retrying. Dry-run performs no eramba writes.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Token/settings HTTP error | Check paid account access, app activation, credentials and read scopes. |
| Pending SSO/social/passkey/OTP | Obtain effective enforcement evidence for those paths; do not disable secure methods merely to make this script pass. |
| Missing security field | Inspect the API response/settings availability; the automation cannot infer a disabled feature. |
| Selected MFA groups/roles | Review effective memberships, uncovered users and exceptions. This version does not resolve those assignments. |
| Empty population | Check account scope and app visibility; do not assume compliance. |
| Changed totals/settings, duplicate or missing page | Retry after changes settle; no partial population is evaluated. |
| Persistence error | Inspect saved evidence/result before rerunning. |

## 10. Customising

Extend effective-policy evaluation when supporting group/role-scoped MFA or external identity providers. Preserve the full user population and independent enforcement evidence. A configuration flag saying SSO is enabled does not prove that the provider requires MFA.

## 11. Removing

Unlink the automation and deactivate/delete its unused OAuth app and Secrets. Keep historical audit evidence. Account security settings are never changed by this script.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial account-wide local MFA review with pending alternative-path assessment. Real installation validation pending. |
