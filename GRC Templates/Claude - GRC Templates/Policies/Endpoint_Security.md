# Endpoint Security

## Purpose
To ensure endpoint devices are configured, protected, and monitored to prevent compromise and unauthorised access to organisational information.

## Scope
All endpoint devices owned or managed by the organisation including laptops, desktops, mobile devices, tablets, and servers used for information processing.

## Responsibilities

- **IT Operations**: Deploy, configure, and maintain endpoint security controls.
- **Information Security**: Define endpoint security standards and monitor compliance.
- **All Users**: Operate endpoints in accordance with the acceptable use policy and report suspected compromises immediately.

## Policy Statements

### Endpoint Configuration and Hardening

**Applies to all frameworks**
- All endpoint devices must be configured against an approved hardened baseline before deployment.
- Automatic screen locking must be enforced after a defined inactivity period not exceeding 15 minutes for workstations and 2 minutes for mobile devices.
- Configuration baselines must be documented, version-controlled, and reviewed at least annually.
- Deviations from the baseline must be detected, investigated, and remediated.

**ISO 27002:2022 (8.1, 8.9)**
- Endpoint security configuration must cover operating system hardening, service minimisation, and application controls.

**CIS Controls v8.1 (4.1, 4.3, 4.6)**
- Default accounts must be disabled or renamed; default credentials must be changed on all devices before deployment.
- Unnecessary services, ports, and protocols must be disabled on all endpoints.

### Malware Protection

**Applies to all frameworks**
- Anti-malware software must be deployed and active on all endpoint devices.
- Malware signatures and definitions must be updated automatically and within 24 hours of release.
- Behaviour-based and heuristic detection must be enabled where supported.
- Anti-malware management must be centralised and provide visibility across all endpoints.

**CIS Controls v8.1 (10.1, 10.2, 10.6, 10.7)**
- Anti-exploitation features must be enabled on all endpoints where supported.

### Web and Email Filtering

**Applies to all frameworks**
- DNS filtering must be applied to all endpoint devices to block access to known malicious domains.
- Web content filtering must restrict access to categories posing security risk.
- Email security controls must include DMARC, SPF, and DKIM enforcement to prevent spoofing.
- Email gateway anti-malware scanning must be enabled for all inbound attachments.
- Unnecessary file types must be blocked at the email gateway.

### Software Installation Control

**Applies to all frameworks**
- Only authorised software from approved sources may be installed on endpoint devices.
- Application allowlisting or equivalent technical controls must prevent execution of unauthorised software.
- The authorised software list must be reviewed and updated at least every six months.
- Unauthorised software detected on endpoints must be removed or escalated for exception handling.

### Endpoint Encryption

**Applies to all frameworks**
- Full-disk encryption must be enabled on all portable endpoints including laptops and mobile devices.
- Removable media must be encrypted when used to store sensitive data.
- Encryption key management must follow the organisation's cryptography policy.

### Data Loss Prevention

**Applies to all frameworks**
- DLP controls must be applied to detect and prevent unauthorised transmission of sensitive data via email, web upload, and removable media.
- DLP policies must be aligned with the information classification scheme.
- DLP incidents must be reviewed and responded to within defined timeframes.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above.*

## Procedures

### Endpoint Configuration Review

**Applies to all frameworks**
1. Run configuration compliance scan across all in-scope endpoints using approved tooling.
2. Compare results against the approved hardened baseline.
3. Identify and classify deviations by severity.
4. Assign remediation owners and deadlines for non-compliant devices.
5. Verify remediation and document results.
6. Update the baseline if changes are approved; retain evidence of review.

### Malware Protection Review

**Applies to all frameworks**
1. Pull coverage and signature update report from endpoint protection platform.
2. Identify devices with protection disabled, missing agent, or outdated signatures.
3. Investigate and remediate within defined SLA.
4. Review malware detection events for the period; escalate significant incidents.
5. Document results and retain as audit evidence.

### Web and Email Filtering Review

**Applies to all frameworks**
1. Review web filtering platform configuration and confirm DNS filtering is applied to all users including remote workers.
2. Verify DMARC, SPF, and DKIM are configured and enforced for all organisation-owned domains.
3. Review email gateway anti-malware scan configuration and confirm it covers all inbound attachments.
4. Review filtering event logs; confirm blocked events are being logged and significant incidents investigated.
5. Document configuration status and any gaps identified.

### Software Installation Control

**Applies to all frameworks**
1. Review application allowlisting or equivalent control configuration across all endpoints.
2. Pull log of software installation attempts for the period; identify blocked and authorised events.
3. Confirm all authorised installations were approved and logged.
4. Review the authorised software list for currency; remove end-of-life titles.
5. Document compliance rate and any unauthorised software found.
