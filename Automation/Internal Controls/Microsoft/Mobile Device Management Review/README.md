---
id: intune-mobile-management
name: Mobile Device Management Review
version: 0.1.0
status: draft
vendor: Microsoft
technology: Intune and Entra ID
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Mobile Device Management Review
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

# Mobile Device Management Review

**Technology:** Microsoft Intune and Entra ID. **Draft:** real Intune/eramba validation pending.

Reconcile mobile-device enrollment and reported encryption. Check the configuration prerequisites for native remote wipe on bulk-enrolled iOS/iPadOS devices. Unsupported wipe configurations remain pending; Android is inventoried but cannot receive an automatic Passed result in this version.

## 1. Controls and policies

Source: **Mobile Device Management Review**, identified by title in [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv).

Methodology: MDM inventory, policy configuration and compliance report; confirm all devices enrolled, encryption and wipe configured. Review quarterly. Policy mapping: Endpoint Security > Remote Working Security; it adds no acceptance conditions.

Schedule 1 January, April, July and October. This is a current-state review, not continuous historical assurance.

## 2. What it checks

| Check | Evidence |
|---|---|
| Inventory | Union of enabled Entra mobile devices and Intune mobile records. Unmatched and unknown-platform records remain visible. |
| Enrollment | Intune MDM channel and recent successful synchronization. |
| Encryption | Strict `isEncrypted=true` with a fresh report; aggregate compliance alone cannot pass it. |
| Wipe configuration | iOS/iPadOS bulk device enrollment, MDM channel, current device management certificate and unexpired tenant Apple push certificate. |

All checks must pass for Passed. Missing, stale, unsupported or conflicting evidence leaves completion pending. API/configuration errors abort instead of saving an incomplete result.

## 3. Coverage

The supported automatic wipe assessment covers `appleBulkWithUser` and `appleBulkWithoutUser`. It checks the configured full-device MDM channel used by Intune's remote wipe. It does not send a wipe, verify an operator's authorization, test delivery, or require automatic erasure after failed passcodes. The methodology requires configured wipe, not a destructive test.

Android, Apple User Enrollment, generic/unknown enrollment types and unknown platforms remain pending for review of their actual wipe configuration. Do not change a legitimate enrollment method solely to obtain Passed. A corporate-data-only erase may be appropriate but needs review against your scope.

Windows, macOS and Linux are outside this mobile-device control. Disabled directory records are excluded unless also present in Intune. Devices unknown to both sources cannot be discovered: reconcile the scope with your authoritative asset inventory before relying on the result. No operating-system or device exclusion switches are provided to hide gaps.

The MDM enrollment and certificate configuration provides the native remote-wipe configuration evidence; the script does not assess every Intune restriction policy. It reports current device compliance status as context, without imposing unrelated compliance rules.

## 4. Before you start

You need active Intune licensing, an Entra tenant, managed mobile devices and eramba Enterprise. Confirm that the directory and Intune inventories cover your control scope. Use a disposable audit without an existing result for validation.

## 5. Setup on Microsoft

Create a dedicated app registration with administrator-consented **application** permissions:

- `Device.Read.All`
- `DeviceManagementManagedDevices.Read.All`
- `DeviceManagementServiceConfig.Read.All` (Apple push certificate metadata)

Store tenant/client UUIDs in eramba Secrets. Store the client secret as single-line base64 in `entra_client_secret_b64`; base64 protects PHP substitution syntax, not confidentiality. Allow HTTPS to `login.microsoftonline.com` and `graph.microsoft.com`. Only the Microsoft public cloud is supported.

No wipe, privileged-operation or write permission is required. Requests select metadata only; recovery passwords, Apple account identifiers and certificate contents are not requested. The official [Microsoft Graph API](https://learn.microsoft.com/en-us/graph/api/resources/intune-devices-manageddevice?view=graph-rest-1.0) is called through the official [Guzzle HTTP library](https://docs.guzzlephp.org/en/stable/overview.html#installation); no unofficial Microsoft wrapper is used.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md). Paste [run.php](run.php), set `guzzlehttp/guzzle:^7.9` as the Composer dependency and a 240-second timeout. Create the three Secrets listed above and configure quarterly audit dates.

Start with `DRY_RUN=true`, then validate persistence on a disposable audit with `false`. The shipped default is live evidence/result reporting.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `MAX_OBJECTS` | `2000` | 1–10000 records per collection; exceeding the limit aborts. |
| `MAX_SYNC_AGE_HOURS` | `168` | 1–720 hours; report freshness reference default, not a methodology threshold. |
| `DRY_RUN` | `false` | True suppresses uploads, edits and comments. |
| `RESULT_PASSED_ID` | `2` | Verify the Passed option in your installation. |
| `RESULT_FAILED_ID` | `1` | Reserved reporting option; this version leaves unresolved gaps pending. Must differ from Passed. |
| `MAX_LOG_ITEMS` | `5` | 1–10 summary observations; full details remain in CSV. |

Requests use verified TLS, no redirects, limited retries, a 180-second collection budget plus a bounded active request, 100 pages per collection, 2 MB per response and 600 KB of evidence.

## 8. Results

Uploads a CSV of device IDs and observations and a TXT of settings. Passed saves result, conclusion and UTC execution dates. Pending saves attachments and a review comment only; it does not clear a previous result or guarantee a scheduled retry. CSV PASS/FAIL labels describe individual evidence checks, not the final pending audit.

Dry-run writes nothing. Repeated runs can duplicate attachments/comments. Uploads may remain after a failed edit; a comment may fail after the result is saved. Inspect persistence before retrying.

## 9. Troubleshooting

- Authentication/permission error: verify application permissions, consent, licensing and Secrets.
- Pending wipe: inspect enrollment type, certificates and the platform limitations in §3.
- Missing devices or stale evidence: reconcile the inventories and inspect last successful sync.
- Collection limit: use an appropriately scoped integration; do not silently truncate the population.
- Persistence error: inspect the audit for partial writes before retrying.

## 10. Customising

Adjust the report freshness objective and collection limits to your environment. Other platforms and enrollment methods need their own evidence evaluation; aggregate compliance or device ownership alone does not establish wipe configuration.

## 11. Removing

Unlink the automation and remove its dedicated credentials and permissions when unused. Retain historical evidence and leave device-management configuration intact.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Mobile inventory reconciliation, encryption reporting and Apple bulk-enrollment wipe configuration review; unsupported cases pending. |

References: [Intune wipe prerequisites](https://learn.microsoft.com/en-us/intune/device-management/actions/wipe), [Apple User Enrollment limitations](https://learn.microsoft.com/en-us/intune/device-enrollment/apple/user-enrollment-methods-ios), [Apple push certificate API](https://learn.microsoft.com/en-us/graph/api/intune-devices-applepushnotificationcertificate-get?view=graph-rest-1.0).
