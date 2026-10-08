---
id: aws-capacity-performance-monitoring
name: Capacity and Performance Monitoring Review
version: 0.2.1
status: draft
technology: AWS EC2 Auto Scaling and Amazon CloudWatch
vendor: AWS
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Capacity and Performance Monitoring Review
policies:
  - Business Continuity > Business Impact Analysis and Recovery Objectives
audit_frequency: twice monthly (days 1 and 15)
secrets:
  - aws_access_key_id
  - aws_secret_access_key
variables:
  - AWS_ROLE_ARN
  - AWS_EXTERNAL_ID
  - REGIONS
  - GROUP_NAME_REGEX
  - LOOKBACK_DAYS
  - MIN_METRIC_COVERAGE_PERCENT
  - MAX_GROUPS
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

# Capacity and Performance Monitoring Review

**Technology:** AWS EC2 Auto Scaling and Amazon CloudWatch.

Collect CPU/compute capacity evidence for EC2 Auto Scaling groups. This version leaves the audit pending for review of the capacity plan and available elastic capacity.

| | |
|---|---|
| Control | `Capacity and Performance Monitoring Review` |
| Population | All current groups in the configured account/regions matching the optional name filter |
| Recommended audits | Days 1 and 15 each month (24 audits/year) |
| Evidence | Conclusion, comment, CSV observations and TXT configuration |
| Status | v0.2.1 draft; AWS/eramba installation validation pending |

## 1. Controls and policies

Source: [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv).

> Evidence: monitoring tool configuration, performance metrics, capacity plans, alerts. Confirm thresholds defined, elastic capacity available. Conduct quarterly.

Policy mapping: **Business Continuity > Business Impact Analysis and Recovery Objectives**. It is traceability, not an extra acceptance criterion.
Framework mappings: ISO 27002:2022 8.6; SOC 2 A1.1; SCF CAP-01, CAP-02, CAP-04.

**Schedule:** days 1 and 15 each month (24 audits/year), reviewing 28 days. This exceeds the quarterly review frequency because CloudWatch retains alarm history for only 30 days. Consecutive windows overlap. A quarterly execution would leave gaps. Each run creates two attachments and one comment.

## 2. What it checks

| Check | Passes when | Fails when |
|---|---|---|
| `population` | At least one identifiable group is discovered. | Empty scope or unidentified group. Empty regions alone do not fail. |
| `metrics` | At least 90% of hourly CPU buckets exist by default, latest data is recent, no malformed/duplicate records. | Missing, stale, invalid or insufficient data. |
| `thresholds` | At least one supported group CPU high alarm has enabled actions and usable state. | Missing coverage, actions disabled/absent, unknown or INSUFFICIENT_DATA state. |
| `alerts` | History is collected for each matching CPU alarm; zero events is valid. | No CPU alarm or unusable event date. API failures abort execution. |
| `capacity_plan` | Scaling policies are present and retained with minimum/desired/maximum capacity. | No policies. |
| `elasticity` | Maximum exceeds desired capacity, desired is positive and within minimum, enough healthy in-service capacity exists, CPU target tracking is enabled and linked to a valid high alarm, relevant processes are not suspended, and no recent activity fails validation. | Missing headroom, unhealthy/insufficient capacity, inactive/unlinked policy, suspended Launch/AlarmNotification/AddToLoadBalancer or failed/unknown activity. |
| `scaling_activity` | Each recent activity has a known status and usable start date; pending states are retained without claiming completion. | Failed, Cancelled, unknown status, missing/future start date. Later success does not erase a failed attempt. |

`alarm_configuration` and `utilisation_evidence` retain supporting observations; PASS on an evidence row means it was recorded, not that every setting in it is compliant.

**PENDING:** every successful collection saves evidence and a review comment, without changing the audit result or completion dates. PASS/FAIL rows describe technical observations, not a completed control test. **ERROR:** configuration, collection or persistence failed. Uploads can remain after a later failure; inspect the audit before retrying.

## 3. Coverage of the audit methodology

| Methodology requirement | Implementation |
|---|---|
| “monitoring tool configuration, performance metrics” | DescribeAlarms plus hourly CloudWatch AWS/EC2 CPUUtilization, retained as daily summaries with hourly counts, mean of hourly averages and peak instance CPU. |
| “alerts” | Alarm state/configuration and all available history in the review window for current matching CPU alarms, including configuration/action/state records. |
| “capacity plans” | Retains MinSize, DesiredCapacity, MaxSize and scaling policies. The capacity plan remains subject to review. |
| “thresholds defined” | Checks a supported high CPU alarm has a numeric threshold, evaluation period, enabled actions and an OK/ALARM state. |
| “elastic capacity available” | Tests configured headroom, healthy running capacity, linked CPU target tracking, suspended processes and recorded scaling failures. It does not prove unused EC2 quota or reserve physical capacity; see the scope qualification below. |
| “monitoring tool configuration” | Discover all selected EC2 Auto Scaling groups before retrieving evidence; no group is omitted because its metrics or policy are absent. Every discovered group is treated as critical. |
| “Conduct quarterly” | More frequent reviews preserve the provider history. See §1. |

**Scope:** current EC2 Auto Scaling groups, CPU/compute capacity and native group CPU alarms. Memory, storage, network/application bottlenecks, standalone EC2 and other services/accounts require separate coverage. Current inventory cannot reconstruct deleted resources or historical configuration; group metrics do not prove individual-instance monitoring.

Elasticity means configured headroom, healthy capacity and scaling configuration/history. It does not establish spare EC2 quotas, reserved capacity or workload readiness. The methodology requires available elastic capacity, so the final review remains pending in this version.

Use the actual control scope. This script supplies partial evidence and cannot complete the control without the remaining capacity review.

## 4. Before you start

- eramba Enterprise with an available automation slot; PHP 8.4-compatible runner and Composer access.
- AWS account and regions containing the groups you want this control to cover.
- EC2 detailed monitoring that publishes CPUUtilization with the AutoScalingGroupName dimension. Enough history for your selected window; newly created groups may fail until history accumulates.
- At least one static high CPU alarm per group: AWS/EC2, CPUUtilization, exactly the AutoScalingGroupName dimension, Average or Maximum, greater-than or greater-than-or-equal, numeric threshold in (0,100], period and evaluation count above zero, actions enabled and present. Metric-math/composite alarms do not establish coverage in this version.
- CPU target-tracking scaling per group, with its high alarm calling that policy. The group must have positive desired capacity, healthy in-service instances and maximum capacity above desired. This version does not accept step/simple/scheduled-only scaling as equivalent evidence.
- Read-only credentials (§5), configured regions and HTTPS access to regional Auto Scaling, CloudWatch and optional STS endpoints.

Defaults discover all groups; only connection details and regions need initial configuration. Missing infrastructure or evidence is recorded for review; completion remains pending. Reference thresholds are adjustable and are not mandated by the template.

## 5. Setup on AWS

### 5.1 Create a read-only identity

In IAM, create a dedicated identity for eramba. In **Permissions → Add permissions → Create inline policy → JSON**, use the policy below. Create an access key for this identity and store it only in eramba Secrets. For an assumed-role deployment, place the service permissions on the target role, give the source identity `sts:AssumeRole` for that role ARN, and configure its trust relationship and optional external ID. Set the role variables in §7.

The automation never enables monitoring, changes alarms/policies, scales a group or launches resources. Use your normal change process to address missing control infrastructure.

### 5.2 Minimum permissions

Apply this read-only policy to the identity or assumed role:

```json
{
  "Version": "2012-10-17",
  "Statement": [
    {
      "Effect": "Allow",
      "Action": [
        "autoscaling:DescribeAutoScalingGroups",
        "autoscaling:DescribePolicies",
        "cloudwatch:DescribeAlarms",
        "cloudwatch:DescribeAlarmHistory",
        "cloudwatch:GetMetricStatistics",
        "autoscaling:DescribeScalingActivities"
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
| Secret `aws_access_key_id` | Dedicated IAM access key ID |
| Secret `aws_secret_access_key` | Its secret access key |
| Automation name | Capacity and Performance Monitoring Review |
| Timeout | `240` seconds |
| Composer packages | `aws/aws-sdk-php:^3.398` |
| Code | Contents of [run.php](run.php) |
| Control | Audits required, dates 1 and 15 of each month, Automated execution, select this automation |

Keep the original methodology in the control description. Confirm that the control's scope is this account's selected Auto Scaling groups; see §3 before replacing a broader manual audit.

Default execution saves evidence and a review comment, leaving completion pending. Use `DRY_RUN=true` for an initial simulation, then restore `false` and verify a disposable audit. See the [installation guide](../../docs/installing.md).

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `AWS_ROLE_ARN` | `''` | Optional read-only role. Empty uses the supplied credentials directly. |
| `AWS_EXTERNAL_ID` | `''` | Optional external ID for that role; requires AWS_ROLE_ARN. |
| `REGIONS` | `['eu-west-1']` | Up to 20 unique regions included in this control; choose your deployment regions. |
| `GROUP_NAME_REGEX` | `/.*/` | Group-name regular expression. The actual default is `/.*/`: every discovered group. |
| `LOOKBACK_DAYS` | `28` | 7–28 days. Do not leave gaps between scheduled runs; data ends at the last complete UTC hour. |
| `MIN_METRIC_COVERAGE_PERCENT` | `90` | Reference minimum percentage of hourly CPU buckets, greater than 0 and at most 100. Latest bucket must also be within two hours of the end. |
| `MAX_GROUPS` | `20` | Maximum selected population, 1–100. Exceeding it aborts, rather than sampling or truncating. Runtime/evidence limits may require a smaller scope. |
| `DRY_RUN` | `false` | Explicit simulation when true: no attachments, result or comment are written. |
| `RESULT_PASSED_ID` | `2` | Reserved for compatibility; this version does not write a final result. |
| `RESULT_FAILED_ID` | `1` | Reserved for compatibility; must differ from Passed. |
| `MAX_LOG_ITEMS` | `5` | 1–10 failure examples in the conclusion and up to 3 capacity findings. Full details remain in CSV. |

The script stops collection after approximately 190 seconds plus its bounded in-flight request, or when accumulated evidence exceeds its size budget. This leaves time for reporting inside the 240-second runner limit. It never returns a partial population as Passed. Split large scopes across separate controls.

## 8. Results in eramba

A live run adds the review conclusion in a comment linking two attachments. It does not edit the result or execution dates:

- `evidence-<automation-id>-<date>.csv`: observations, check results and reasons.
- `config-<automation-id>-<date>.txt`: settings used, without credentials.

Complete the audit after reviewing the remaining requirements and technical findings. A collection error saves no result. Uploads can remain if a later upload or comment fails. Inspect the audit before retrying.

`DRY_RUN=true` displays the outcome without uploads, edits or comments.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| Invalid configuration or audit context | Check §7 and run against an internal-control audit. |
| Missing secret / SDK | Check secret names and Composer package in §6. |
| AWS authentication or AccessDenied | Check credentials, role trust, External ID and §5.2 permissions. |
| Timeout, collection/evidence limit or pagination error | Check connectivity; split a large scope into separate controls. No partial result is saved. |
| Missing, stale or unknown evidence | Inspect the CSV and required checks in §2–3; missing evidence cannot pass. |
| eramba upload/edit/comment error | Inspect the audit before retrying; see §8 for possible partial writes. |
| Simulation completes without a result | Set `DRY_RUN=false` for scheduled execution. |

## 10. Customising

Adjust scope, history and completeness using §7; maintain alarm thresholds in AWS. Keep execution intervals within LOOKBACK_DAYS. Use separate controls for different accounts and capacity domains.

## 11. Removing

Unlink the automation in eramba and remove dedicated credentials/permissions if unused elsewhere. Retain historical evidence. Application alarms and scaling policies remain operational.

## 12. Changelog

| Version | Date | Change |
|---|---|---|
| 0.2.1 | 2026-10-08 | `AWS_EXTERNAL_ID` is no longer copied into the config attachment; the four in-place-update and connection-draining scaling activity statuses are recognised instead of failing; evidence CSV cells cannot start a spreadsheet formula. |
| 0.2.0 | 2026-09-28 | Leaves completion pending: scaling configuration alone does not prove available elastic capacity or validate the capacity plan. |
| 0.1.1 | 2026-09-28 | Removes unused code for the companion control; concise documentation. Live validation pending. |
| 0.1.0 | 2026-09-28 | Initial implementation. |

API references: [CPU metrics](https://docs.aws.amazon.com/autoscaling/ec2/userguide/viewing-monitoring-graphs.html), [metric history](https://docs.aws.amazon.com/AmazonCloudWatch/latest/APIReference/API_GetMetricStatistics.html), [alarm history retention](https://docs.aws.amazon.com/AmazonCloudWatch/latest/monitoring/CloudWatch_Alarms.html), [scaling activities](https://docs.aws.amazon.com/autoscaling/ec2/APIReference/API_DescribeScalingActivities.html).

*eramba does not provide support for developing or troubleshooting custom automation code.*
