---
id: aws-client-vpn-remote-access
name: Remote Access Security Review
version: 0.2.0
status: draft
technology: AWS Client VPN and Directory Service
vendor: AWS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Remote Access Security Review
policies:
  - Network Security > Remote Access
audit_frequency: monthly
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - REGIONS
  - ENDPOINT_REGEX
  - LOOKBACK_DAYS
  - SPLIT_TUNNEL_APPROVALS
  - MAX_ENDPOINTS
  - MAX_LOG_EVENTS
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - aws/aws-sdk-php:^3.398
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Remote Access Security Review

**Technology:** AWS Client VPN and Directory Service.

Review MFA, session timeout, split tunnelling, encrypted channels and connection logs for your AWS Client VPN endpoints. **Draft: installation validation pending.**

## 1. Controls and policies

Tests **Remote Access Security Review** from the [control templates](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Policy mapping: Network Security › Remote Access.

Run on the **1st of each month**, reviewing the preceding 90 days. This exceeds the methodology's annual minimum and provides overlapping log windows. Shorter windows need a matching execution schedule.

## 2. What it checks

| Check | Passing condition |
|---|---|
| Population | At least one identifiable endpoint in scope; each is available |
| MFA | Active Microsoft AD or AD Connector directory with RADIUS status Enabled; no unsupported authentication methods |
| Split tunnelling | Disabled, or an existing documented approval is referenced in the configuration |
| Session timeout | A positive maximum session duration is configured |
| Encrypted channel | OpenVPN with a server certificate configured |
| Logging | Enabled; log group predates the review window and retains at least that much history |
| Access logs | At least one successful session record per endpoint; no malformed or unrecognised records in the collected evidence |

All checks are mandatory. SAML MFA and insufficient/unrecognised activity evidence produce **Pending**, preserving observations without changing the result or completion dates. Confirmed violations produce **Failed** only when no required review remains. Empty scope cannot pass. API, configuration or persistence errors abort the execution; they are not control failures. Failed login attempts remain in evidence but do not themselves fail this control.

## 3. Coverage of the audit methodology

| Methodology | Evidence |
|---|---|
| MFA enabled and enforced for remote access | Endpoint authentication options and Directory Service MFA status. AWS requires password and MFA code when directory MFA is enabled. |
| Split tunnelling disabled or approved | SplitTunnel setting and optional approval reference |
| Session timeout configured | SessionTimeoutHours; automatic reconnection setting retained as context |
| Approved encrypted channels, no legacy protocols | AWS Client VPN OpenVPN configuration and server certificate ARN |
| Access logs for the review period | CloudWatch connection event timestamps, types, outcomes and connection identifiers |
| Document exceptions; review at least annually | Audit conclusion and evidence, on the schedule in §1 |

Use this for a control scoped to **AWS Client VPN with AWS Managed Microsoft AD or AD Connector**, where AWS Client VPN is an approved access channel. All endpoints in the selected regions are discovered by default. SAML endpoints remain in scope and leave MFA verification pending until effective enforcement is established in the IdP. Certificate-only authentication does not prove MFA. This script does not validate external identity-provider policies.

The test checks current settings and retained activity, not historic configuration continuity or a live authentication attempt. Session duration is not an idle timeout. Deleted endpoints and other remote-access services are outside discovery. No sessions in the period means insufficient evidence to complete the test. Do not use this result alone for a control covering other remote-access platforms.

## 4. Before you start

You need eramba Enterprise, AWS Client VPN with Directory Service MFA, connection logging and sufficient retained history. The script reads existing evidence; it does not enable MFA, create logs or connect to the VPN. Choose your regions; no endpoint list is required.

## 5. Setup on AWS

### 5.1 Read-only identity

Create a dedicated IAM identity and access key for eramba. Apply the following policy under **Permissions → Create inline policy → JSON**. Store the credentials in eramba Secrets. Allow HTTPS access to EC2, Directory Service and CloudWatch Logs in your regions.

### 5.2 Minimum permissions

```json
{
  "Version": "2012-10-17",
  "Statement": [{
    "Effect": "Allow",
    "Action": [
      "ec2:DescribeClientVpnEndpoints",
      "ds:DescribeDirectories",
      "logs:DescribeLogGroups",
      "logs:FilterLogEvents"
    ],
    "Resource": "*"
  }]
}
```

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md).

| Setting | Value |
|---|---|
| Secrets | `aws_access_key_id`, `aws_secret_access_key` |
| Name | Remote Access Security Review |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |
| Audit dates | 1st of every month; Automated execution |

First use `DRY_RUN=true` to inspect collection, then restore `false` and verify the result and attachments on a test audit before enabling the schedule.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `REGIONS` | `['eu-west-1']` | 1–20 unique deployment regions |
| `ENDPOINT_REGEX` | `/.*/` | Optional filter on endpoint IDs; default includes all |
| `LOOKBACK_DAYS` | `90` | 1–90 days; execution intervals must not exceed this window |
| `SPLIT_TUNNEL_APPROVALS` | `[]` | Optional endpoint ID → existing approval reference; never creates approval |
| `MAX_ENDPOINTS` | `20` | 1–100; exceeding the limit aborts without a partial audit |
| `MAX_LOG_EVENTS` | `5000` | 1–10000 total events examined, including other endpoints in shared log groups |
| `DRY_RUN` | `false` | True prevents uploads, edits and comments |
| `RESULT_PASSED_ID` | `2` | Passed option ID |
| `RESULT_FAILED_ID` | `1` | Failed option ID, distinct from Passed |
| `MAX_LOG_ITEMS` | `5` | 1–10 failure examples in the conclusion |

Collection is bounded to 190 seconds plus its in-flight request, 100 pages per API query and approximately 2,500 evidence rows. Split a large scope across controls instead of silently dropping records.

## 8. Results in eramba

Saves Passed/Failed and dates when the test is complete. Pending saves evidence and a review comment only. It does not clear an existing result or schedule a retry; use a fresh audit. Evidence is CSV plus configuration TXT. Session evidence omits usernames, IP addresses and raw messages; Directory Service secrets are never attached.

Collection errors save no result. An upload can remain after a later failure; a comment failure can leave a saved result. Inspect the audit before retrying.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Invalid configuration or missing secret | Check §6–7 and select a control audit for execution |
| AWS access denied | Check the identity's permissions in §5.2 |
| MFA failed or pending | Check Directory Service evidence; for SAML, verify effective MFA in the IdP instead of changing authentication merely to pass this script |
| Log evidence fails | Check connection logging, retention, log-group age and successful activity in the period |
| Collection limit or timeout | Narrow endpoint/region scope or shorten the window with a matching schedule |
| eramba write error | Inspect existing attachments/result before retrying |

## 10. Customising

Use `ENDPOINT_REGEX` for a subset. If split tunnelling has already been approved, add its reference to `SPLIT_TUNNEL_APPROVALS`, for example `['cvpn-endpoint-0123456789abcdef0' => 'Approved exception EX-123']`. Review these references when approvals change. Use separate controls for other accounts or authentication providers.

## 11. Removing

Unlink the automation, retain historical evidence and remove dedicated keys/permissions when no longer used. Keep the VPN and its operational logging enabled.

## 12. Changelog

| Version | Change |
|---|---|
| 0.2.0 | Distinguish unsupported evidence from violations; pending review and stricter API collection. Real AWS/eramba validation pending |
| 0.1.0 | Initial implementation; AWS/eramba installation validation pending |

References: [Directory MFA](https://docs.aws.amazon.com/vpn/latest/clientvpn-admin/ad.html), [endpoint settings](https://docs.aws.amazon.com/vpn/latest/clientvpn-admin/cvpn-working-endpoint-create.html), [connection logs](https://docs.aws.amazon.com/vpn/latest/clientvpn-admin/connection-logging.html).
