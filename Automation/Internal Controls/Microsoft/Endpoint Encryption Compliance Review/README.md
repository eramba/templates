---
id: intune-windows-encryption
name: Endpoint Encryption Compliance Review
version: 0.2.0
status: draft
technology: Microsoft Intune and Entra ID
vendor: Microsoft
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Endpoint Encryption Compliance Review
policies:
  - Endpoint Security > Endpoint Configuration Review
audit_frequency: quarterly
secrets:
  - entra_tenant_id
  - entra_client_id
  - entra_client_secret_b64
variables:
  - MAX_OBJECTS
  - MAX_SYNC_AGE_HOURS
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

# Endpoint Encryption Compliance Review

**Technology:** Microsoft Intune and Entra ID.

Read service evidence and save the audit result in eramba. **Draft: functional and installation validation pending.**

## 1. Controls and policies

Tests **Endpoint Encryption Compliance Review** from the [control templates](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Policy mapping: Endpoint Security > Endpoint Configuration Review. The methodology defines the checks.

**Schedule:** quarterly; this is a current-state review. It does not prove configuration was unchanged between executions.

## 2. What it checks

| Check | Passing condition |
|---|---|
| Inventory/enrolment | Nonempty Windows population with identifiable devices and corresponding Intune reports. |
| Encryption | Every report shows isEncrypted=true and a valid sync within the configured age. |
| Key management | At least one valid OS-volume recovery-key metadata record per device. |

Passed requires every mandatory check to pass. Encryption gaps, missing/stale evidence and potential exceptions leave the audit pending for review; the script does not automatically reject an exception it cannot evaluate. Authentication, permission, incomplete collection and reporting errors abort execution; they do not become control failures.

## 3. Coverage

| Methodology requirement | Implementation |
|---|---|
| Encryption compliance report | Intune isEncrypted and lastSyncDateTime for each Windows device. All records for a device must be encrypted and fresh. |
| All in-scope endpoints | Union of enabled Windows devices registered in Entra and Windows Intune records, so a directory device without an Intune report remains visible as a gap. |
| Key management | Escrowed operating-system-volume BitLocker recovery-key metadata in Entra; recovery passwords are never requested. |
| Exceptions and conclusion | Unencrypted, unreported and stale devices are identified. Unresolved exceptions and compensating controls remain pending, without completion writes. |

**Scope:** Windows endpoints known to Entra ID or Intune in one public-cloud tenant. All discovered Windows devices are treated as potentially processing sensitive data. Devices unknown to both inventories, other operating systems and mobile-device encryption require separate coverage.

The result relies on Intune's reported device encryption status and OS-volume key escrow. It does not inspect every disk, establish recovery-key access governance or perform a recovery test. Review documented exceptions and compensating controls before completing a pending audit. The script neither approves nor rejects them automatically.

## 4. Before you start

- eramba Enterprise and a disposable audit for installation validation.
- Active Intune licensing and Windows devices reporting to Intune.
- Entra-escrowed BitLocker recovery keys.
- Entra directory inventory maintained as a source independent of the encryption report.

## 5. Setup on Intune

### 5.1 Identity

In Entra **App registrations**, register an application in your tenant. Add the Microsoft Graph **application permissions** below and grant administrator consent. Create a client secret; store its single-line base64-encoded value as `entra_client_secret_b64`, and store tenant ID and application/client ID unchanged in their Secrets. Base64 prevents raw PHP substitution errors; it is not encryption. Version 0.2.0 replaces the previous raw client-secret Secret. Allow HTTPS to `login.microsoftonline.com` and `graph.microsoft.com`. This version supports the Microsoft public cloud.

### 5.2 Permissions

- `Device.Read.All`
- `DeviceManagementManagedDevices.Read.All`
- `BitlockerKey.ReadBasic.All`

No directory, policy or device write permissions are required.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md).

| Setting | Value |
|---|---|
| Secrets | `entra_tenant_id`, `entra_client_id`, `entra_client_secret_b64` |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |
| Audit dates | 1 January, April, July and October; Automated execution |

Default execution saves results. Use `DRY_RUN=true` for initial inspection; restore `false` and validate a disposable audit before scheduling.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `MAX_OBJECTS` | `2000` | Maximum records per collection, 1–10000; includes directory devices, managed devices and key metadata. |
| `MAX_SYNC_AGE_HOURS` | `168` | Freshness objective for Intune reports, 1–720 hours; reference default, not prescribed by the methodology. |
| `DRY_RUN` | `false` | True prevents uploads, audit edits and comments. |
| `RESULT_PASSED_ID` | `2` | Passed option ID. |
| `RESULT_FAILED_ID` | `1` | Common reporting setting; this evaluator keeps unresolved gaps pending. Must differ from Passed. |
| `MAX_LOG_ITEMS` | `5` | 1–10 failure examples; full detail remains in CSV. |

Collection has a bounded time, page and evidence budget. Exceeding a limit aborts without saving partial results. Secrets and access tokens are excluded from evidence.

## 8. Results

When all evidence is satisfactory, saves Passed, conclusion and UTC dates, then adds an evidence comment. Otherwise, uploads the CSV and configuration TXT and adds a pending-review comment without changing result or execution dates. Use an audit without a previous result: pending runs do not clear it or schedule a retry. Repeated runs may duplicate attachments/comments. Collection errors save no result. Uploads may remain after a failed edit; a failed comment can leave a saved result. Inspect before retrying.

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
| 0.2.0 | Pending exception review, base64 client-secret storage and stricter collection validation; real installation validation pending |
| 0.1.0 | Initial draft; functional and installation validation pending |

References: [Device inventory](https://learn.microsoft.com/en-us/graph/api/device-list?view=graph-rest-1.0), [managed devices](https://learn.microsoft.com/en-us/graph/api/intune-devices-manageddevice-list?view=graph-rest-1.0), [BitLocker metadata](https://learn.microsoft.com/en-us/graph/api/bitlocker-list-recoverykeys?view=graph-rest-1.0).
