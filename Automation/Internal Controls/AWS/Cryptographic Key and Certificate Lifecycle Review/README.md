---
id: aws-key-certificate-lifecycle
name: Cryptographic Key and Certificate Lifecycle Review
version: 0.1.0
status: draft
technology: AWS KMS, ACM and Elastic Load Balancing
vendor: AWS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Cryptographic Key and Certificate Lifecycle Review
policies:
  - Cryptography > Certificate Management
audit_frequency: quarterly
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - REGIONS
  - EXPIRY_WARNING_DAYS
  - MAX_RESOURCES
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

# Cryptographic Key and Certificate Lifecycle Review

**Technology:** AWS KMS, ACM and Elastic Load Balancing.

Read service evidence and save the audit result in eramba. **Draft: functional and installation validation pending.**

## 1. Controls and policies

Tests **Cryptographic Key and Certificate Lifecycle Review** from the [control templates](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Policy mapping: Cryptography > Certificate Management. The methodology defines the checks.

**Schedule:** quarterly; this is a current-state review. It does not prove configuration was unchanged between executions.

## 2. What it checks

| Check | Passing condition |
|---|---|
| Keys | Active supported key metadata and a future automatic rotation date. |
| Certificates | Valid certificate, known SHA-256/384/512 RSA/ECDSA signature; expiry beyond the warning window or managed renewal currently pending. |
| TLS listeners | TLS 1.2/1.3 only; no DES, RC4 or MD5 ciphers; issued ACM certificates are inventoried. |

Passed requires every mandatory check to pass. Missing/unsupported evidence produces Failed. Authentication, permission, incomplete collection and reporting errors abort execution; they do not become control failures.

## 3. Coverage

| Methodology requirement | Implementation |
|---|---|
| Key register: algorithm, length, creation and next rotation | KMS metadata and automatic rotation status. Symmetric default keys use AES-256; numbered key specs identify the algorithm/size. |
| No overdue rotation | Active AWS_KMS symmetric keys must have automatic rotation enabled and a future next rotation date. Unsupported manual/imported/asymmetric rotation fails evidence coverage. |
| Certificate inventory and expiry | ACM details; certificates within 30 days need a current renewal in progress. Expired/revoked certificates fail. |
| No TLS 1.0/1.1 or deprecated algorithms | ALB/NLB front-end TLS policies, cipher names and ACM signature algorithms. Unknown algorithms fail rather than being assumed safe. |
| Findings and quarterly review | Evidence and conclusion on each quarterly audit. |

**Scope:** KMS keys, ACM certificates and TLS terminated on Application/Network Load Balancers in the configured regions. Classic Load Balancers, CloudFront, API Gateway, service-specific TLS, TLS behind TCP listeners, application key stores and other accounts are outside this integration.

This integration supports automatic rotation evidence for AWS_KMS symmetric keys. Other active key types remain visible but cannot pass rotation verification. Disabled/deletion-pending keys and unissued certificates are retained as inactive observations. Attached listener certificates must be issued and present in ACM; IAM-stored certificates cannot pass this integration.

Each required evidence category must be nonempty across the selected regions. Use a control scoped to these services; this is not a statement about every cryptographic use in an AWS account.

## 4. Before you start

- eramba Enterprise and a disposable audit for installation validation.
- KMS keys, ACM certificates and ALB/NLB TLS listeners in your chosen regions.
- Automatic rotation for active AWS_KMS symmetric keys; other rotation mechanisms need a different evidence integration.
- Read-only identity permitted by both IAM and the relevant KMS key policies.

## 5. Setup on AWS

### 5.1 Identity

Create a dedicated read-only IAM identity and access key. Apply the policy below and grant the required key-policy access. Allow HTTPS to the regional KMS, ACM and Elastic Load Balancing endpoints.

### 5.2 Permissions

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "kms:ListKeys",
        "kms:DescribeKey",
        "kms:GetKeyRotationStatus",
        "acm:ListCertificates",
        "acm:DescribeCertificate",
        "elasticloadbalancing:DescribeLoadBalancers",
        "elasticloadbalancing:DescribeListeners",
        "elasticloadbalancing:DescribeSSLPolicies",
        "elasticloadbalancing:DescribeListenerCertificates"
      ],
      "Resource": "*"
    }
  ]
}
```

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md).

| Setting | Value |
|---|---|
| Secrets | `aws_access_key_id`, `aws_secret_access_key` |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |
| Audit dates | 1 January, April, July and October; Automated execution |

Default execution saves results. Use `DRY_RUN=true` for initial inspection; restore `false` and validate a disposable audit before scheduling.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `REGIONS` | `['eu-west-1']` | 1–20 unique AWS regions. |
| `EXPIRY_WARNING_DAYS` | `30` | 30–365 days; cannot weaken the methodology's 30-day minimum. |
| `MAX_RESOURCES` | `100` | 1–500 resources/listeners; aborts when exceeded. |
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
| API authentication or permission error | Check credentials, IAM permissions and KMS key policies |
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
| 0.1.0 | Initial draft; functional and installation validation pending |

References: [Key rotation](https://docs.aws.amazon.com/kms/latest/APIReference/API_GetKeyRotationStatus.html), [certificate renewal](https://docs.aws.amazon.com/acm/latest/APIReference/API_RenewalSummary.html), [TLS policies](https://docs.aws.amazon.com/elasticloadbalancing/latest/APIReference/API_DescribeSSLPolicies.html).
