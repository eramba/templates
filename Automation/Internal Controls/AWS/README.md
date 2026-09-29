# AWS control coverage

Start with the control's testing methodology and the AWS services actually in scope. An AWS configuration check is not automatically a complete control test. All integrations below remain drafts pending real AWS/eramba validation.

## Available implementations

| Control | Supported evidence scope | Boundary before relying on the result |
|---|---|---|
| [Backup Execution and Restore Test](Backup%20Execution%20and%20Restore%20Test/) | AWS Backup job history, protected resources and restore jobs | Confirm the critical-system population, recovery objectives and representative restore sample. Resources never protected by AWS Backup need independent coverage. |
| [Capacity Planning Review](Capacity%20Planning%20Review/) | EC2 Auto Scaling CPU, alarms and predictive forecasts | Suitable for a defined compute scope; it does not assess database, storage, memory or application capacity. A business capacity plan is not inferred from a scaling policy. |
| [Capacity and Performance Monitoring Review](Capacity%20and%20Performance%20Monitoring%20Review/) | EC2 Auto Scaling CPU thresholds, policies, headroom and activities | Evidence collection only: leaves completion pending until capacity plans and available elastic capacity are verified. |
| [Endpoint Encryption Compliance Review](Endpoint%20Encryption%20Compliance%20Review/) | WorkSpaces Personal root/user encryption and KMS key metadata | Only virtual desktops in this service; physical clients and other endpoint platforms need separate coverage. |
| [Remote Access Security Review](Remote%20Access%20Security%20Review/) | AWS Client VPN configuration and connection logs | Directory Service MFA is supported; SAML enforcement must be checked in its IdP. Insufficient activity or federated-MFA evidence remains pending. |
| [Cryptographic Key and Certificate Lifecycle Review](Cryptographic%20Key%20and%20Certificate%20Lifecycle%20Review/) | KMS, ACM and ALB/NLB front-end TLS | Manual rotation needs its schedule/history. Other TLS termination points and application key stores require separate coverage. |

These six implementations have the evidence boundaries listed above. A narrower scope must reflect the actual control definition; do not narrow it merely to obtain Passed.

## Additional controls: evidence still needed

The following are plausible AWS evidence sources, not implemented full-control automations. The missing evidence comes from the existing methodology, not additional policy requirements.

This assessment covers the 137 methodologies in [the control catalogue](../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). It identifies evidence dependencies; it is not a claim that every possible AWS deployment has been tested. No additional complete default implementation has been established from those definitions. Reassess a candidate when its required evidence and actual deployment scope are available.

| Control | AWS evidence that could contribute | What prevents an AWS-only default implementation |
|---|---|---|
| Internal Vulnerability Scan | Inspector coverage and findings | Complete authenticated-scan scope, remediation owners/deadlines and prior critical finding disposition. |
| External Vulnerability Scan | Public asset inventory | Actual external scan results, remediation owners/deadlines and prior critical closures. Inspector is not a substitute for an external scanner. |
| Patch Deployment Review | Systems Manager patch reports | Agreed SLA, reliable patch age, exceptions and proof that deployment followed change management. |
| Log Collection and Configuration Review | CloudTrail, CloudWatch Logs and source settings | Complete source inventory, all required event classes and time synchronization on every source. |
| Log Retention Compliance Review | Retention settings, storage and delivery observations | Capacity sufficiency, no silent loss, and effective write access limited to approved service identities. Retention days alone are insufficient. |
| Endpoint Configuration Baseline Review | Systems Manager configuration assessments | Approved baseline, annual review evidence and critical-deviation remediation. |
| Malware Protection Review | Workload inventory and supplied endpoint telemetry | Actual anti-malware deployment, signature timestamps, scan status and behavior monitoring. GuardDuty findings alone do not supply these. |
| Firewall Ruleset Review | Security groups, network ACLs and Network Firewall | Approved rule owners/business purposes, unused-rule evidence and sensitive-zone isolation. |
| Network Segmentation Verification | Network topology and policies | Required segmentation test results and the approved zone model; configuration alone is insufficient. |
| Cryptographic Key Storage and Access Review | KMS configuration and access policies | Approved custody requirements and evidence that effective custodians are minimized. |
| Scheduled Maintenance Compliance Review | Systems Manager windows and execution history | Authoritative schedule, authorization and change records, including missed work. A registered task is not approval evidence. |
| Cloud Security Controls Review | Config/Security Hub findings and inventory | Approved services, applicable baselines and actual access reviews. A CSPM score is not full compliance. |
| Web Application Security Review | WAF and TLS configuration | Application scan results and evidence of implemented input validation. Attaching a web ACL does not establish either. |
| Session and Concurrent Access Controls Review | Session configuration of the selected AWS service | Evidence of all three required controls: idle timeout, session lock and concurrent limits. A maximum session duration alone does not cover them. |
| Service Provider Log Collection Review | Ingested service-provider logs | Authoritative critical-provider inventory, required event classes and the applicable retention policy for every provider. |
| Log Review and Anomaly Investigation | Security findings and investigation records | Analyst dispositions, weekly manual review and confirmation of incident escalation within the agreed SLA. |
| Vulnerability Remediation Tracking | Inspector findings and linked remediation records | Remediation progress, accepted risks, agreed SLAs and completed escalation. Finding severity and age alone are insufficient. |
| Cardholder Data Discovery Scan | Sensitive-data discovery results for supported stores | Complete system scope, the approved CDE boundary and evidence that out-of-scope PAN findings were investigated and resolved. |
| AI System Logging and Record-Keeping Review | AI invocation logging and storage configuration | Full AI-system inventory, output traceability, the applicable retention period and effective protection against tampering. |

Other methodologies require records beyond native AWS configuration: HR and access approvals; approved inventories and classifications; change and remediation records; training and physical inspections; supplier contracts; legal assessments; or governance and application testing. Hosting these records in AWS does not make their meaning or completeness automatically verifiable. Supply the authoritative records and their relationships before implementing those tests.

Do not create a standalone full-control script until these evidence contracts are available. Additional data may be held in AWS, but its location does not establish its business meaning or authorization. A supplied record should be evaluated as evidence, not treated as true because it is a tag or configuration variable.

For federated MFA, start at the IdP and verify application assignments, effective enforcement and bypasses. A service's SSO switch is not proof of MFA.

See [Client VPN federation](https://docs.aws.amazon.com/vpn/latest/clientvpn-admin/federated-authentication.html), [KMS rotation evidence](https://docs.aws.amazon.com/kms/latest/APIReference/API_GetKeyRotationStatus.html), [Systems Manager compliance](https://docs.aws.amazon.com/systems-manager/latest/userguide/systems-manager-compliance.html) and [patch reporting limitations](https://docs.aws.amazon.com/systems-manager/latest/userguide/patch-manager-selecting-patches.html).
