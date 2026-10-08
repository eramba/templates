---
id: entra-mfa-coverage
name: Multi-Factor Authentication Coverage Review
version: 0.1.1
status: draft
technology: Microsoft Entra ID
vendor: Microsoft
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Multi-Factor Authentication Coverage Review
policies:
  - Access Management > Authentication
audit_frequency: monthly
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - MAX_OBJECTS
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

**Technology:** Microsoft Entra ID.

Read service evidence and save the audit result in eramba. **Draft: functional and installation validation pending.**

## 1. Controls and policies

Tests **Multi-Factor Authentication Coverage Review** from the [control templates](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Policy mapping: Access Management > Authentication. The methodology defines the checks.

**Schedule:** monthly; this is a current-state review. It does not prove configuration was unchanged between executions.

## 2. What it checks

| Check | Passing condition |
|---|---|
| `mfa_coverage` | Every discovered enabled application has proven MFA coverage; empty population fails. |
| `admin_coverage` | An enabled all-resources policy requires MFA for all users and client types. |
| `policy_evidence` | Records the policy and why it can or cannot establish coverage; this row is informational. |

Passed requires every mandatory check to pass. Missing/unsupported evidence produces Failed. Authentication, permission, incomplete collection and reporting errors abort execution; they do not become control failures.

## 3. Coverage

| Methodology requirement | Implementation |
|---|---|
| MFA configuration and enforcement | Enabled Conditional Access policy requiring MFA (or an authentication strength satisfying MFA) for all users, resources and client types, without exclusions or conditional restrictions. OR with another grant does not prove mandatory MFA. |
| Externally accessible systems and administrative interfaces | Enabled application service principals, plus an all-resources policy for administrative/first-party resources not listed in the tenant inventory. |
| Coverage and gaps | Per-application result, policy evidence and coverage percentage. Exclusions and unsupported policy conditions remain visible and cannot establish coverage. |

**Scope:** Entra-authenticated access in one public-cloud tenant. Applications using local credentials, other IdPs, standalone VPNs and workload identities are outside this integration. Confirm your control is scoped accordingly.

This version proves a strict all-users/all-resources policy without exceptions. It does not combine conditional policies to infer equivalent coverage, accept report-only mode or infer enforcement from registered factors. Security Defaults and per-user MFA do not establish coverage in this version. Existing exceptions need separate review of their compensating controls; they are reported as unproven coverage, not silently accepted. An approved exception may therefore prevent an automatic Passed result.

## 4. Before you start

- eramba Enterprise and a disposable audit for installation validation.
- Entra Conditional Access licensing and an application registration in your tenant.
- Your in-scope applications authenticate through Entra; review local-login alternatives separately.
- No manual application list is needed: discovery includes enabled application service principals.

## 5. Setup on Entra ID

### 5.1 Identity

In Entra **App registrations**, register an application in your tenant. Add the Microsoft Graph **application permissions** below and grant administrator consent. Create a client secret; store its single-line base64-encoded value as `entra_client_secret_b64`, and store tenant ID and application/client ID unchanged in their Secrets. Base64 prevents raw PHP substitution errors; it is not encryption. The same Secrets serve the other Entra automations. Allow HTTPS to `login.microsoftonline.com` and `graph.microsoft.com`. This version supports the Microsoft public cloud.

### 5.2 Permissions

- `Policy.Read.All`
- `Application.Read.All`

No directory, policy or device write permissions are required.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md).

| Setting | Value |
|---|---|
| Secrets | `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |
| Audit dates | 1st of each month; Automated execution |

Default execution saves results. Use `DRY_RUN=true` for initial inspection; restore `false` and validate a disposable audit before scheduling.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `MAX_OBJECTS` | `2000` | Maximum records per API collection, 1–10000; exceeding it aborts instead of truncating. |
| `DRY_RUN` | `false` | True prevents uploads, audit edits and comments. |
| `RESULT_PASSED_ID` | `2` | Passed option ID. |
| `RESULT_FAILED_ID` | `1` | Failed option ID; must differ from Passed. |
| `MAX_LOG_ITEMS` | `5` | 1–10 failure examples; full detail remains in CSV. |

Collection has a bounded time, page and evidence budget. Exceeding a limit aborts without saving partial results. Secrets and access tokens are excluded from evidence.

## 8. Results

Saves Passed/Failed, conclusion and UTC dates, then adds a comment with an evidence CSV and configuration TXT. Collection errors save no result. Uploads may remain after a failed edit; a failed comment can leave a saved result. Inspect before retrying.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Missing secret / invalid configuration | Check §6–7 and run against an internal-control audit |
| API authentication or permission error | Check credentials, Microsoft Graph application permissions, administrator consent and licensing |
| Missing or unsupported evidence | Inspect the CSV and coverage limits in §3; do not infer success from absence |
| Collection, pagination or evidence limit | Reduce/split the scope with a suitable integration; no partial audit is saved |
| eramba write error | Inspect the audit before retrying |
| Simulation without saved result | Restore DRY_RUN=false for scheduled use |

## 10. Customising

Adjust the documented variables to your environment. Broader control scopes and unsupported evidence patterns require additional integration; changing a result threshold cannot supply missing evidence.

## 11. Removing

Unlink the automation, retain historical audit evidence and remove its dedicated secrets and permissions when unused elsewhere. Leave operational monitoring and security controls enabled.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.1 | Client secret stored as base64 in `entra_client_secret_b64`, shared with the other Entra automations (replaces `entra_client_secret`). Enabled `Legacy` and `ServiceIdentity` service principals covered by MFA policies now pass instead of always failing. Functional and installation validation pending |
| 0.1.0 | Initial draft; functional and installation validation pending |

References: [Conditional Access policies](https://learn.microsoft.com/graph/api/conditionalaccessroot-list-policies), [grant controls](https://learn.microsoft.com/en-us/graph/api/resources/conditionalaccessgrantcontrols?view=graph-rest-1.0), [authentication strengths](https://learn.microsoft.com/en-us/graph/api/authenticationstrengthroot-list-policies?view=graph-rest-1.0).
