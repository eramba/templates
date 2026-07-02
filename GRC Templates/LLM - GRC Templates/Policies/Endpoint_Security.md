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

**PCI DSS v4.0.1 (6.3.2, 6.3.3)**
7. Confirm hardened configuration baseline covers CDE systems per 6.3.2.
8. Confirm baseline is reviewed and updated annually per 6.3.2.

**SOC 2 (TSP 2017) (CC7.1)**
9. Confirm detection and monitoring procedures identify configuration non-compliance per CC7.1.

**ISO 27002:2022 (8.1, 8.9)**
10. Protect information stored on or accessible via user endpoint devices per 8.1.
11. Establish, document, implement, monitor, and review configurations per 8.9.

**NIST 800-53 Rev5 (CM-2, CM-6)**
12. Develop, document, and maintain under configuration control a current baseline configuration per CM-2.
13. Establish configuration settings reflecting the most restrictive mode per CM-6.

**CIS Controls v8.1 (4.1, 4.3)**
14. Establish and maintain a documented secure configuration process for enterprise assets per 4.1.
15. Configure automatic session locking after defined inactivity period per 4.3.

**SCF 2025 (CFG-02, END-01, END-02)**
16. Develop, document, and maintain secure baseline configurations per CFG-02.
17. Facilitate endpoint device management controls per END-01.
18. Protect confidentiality, integrity, availability, and safety of endpoint devices per END-02.

**NIS2 Article 21 (Article 21(2) (e))**
19. Confirm endpoint configuration controls support security in network and information systems per Article 21(2)(e).

**ISO 27701:2025 (A.3.22)**
20. Protect PII stored on or accessible via user endpoint devices per A.3.22.

### Malware Protection Review

**Applies to all frameworks**
1. Pull coverage and signature update report from endpoint protection platform.
2. Identify devices with protection disabled, missing agent, or outdated signatures.
3. Investigate and remediate within defined SLA.
4. Review malware detection events for the period; escalate significant incidents.
5. Document results and retain as audit evidence.

**PCI DSS v4.0.1 (5.2.1, 5.2.2, 5.3.1, 5.3.2, 5.3.3, 5.3.4, 5.3.5)**
6. Confirm anti-malware is deployed on all applicable CDE system components per 5.2.1.
7. Confirm signatures are updated automatically per 5.3.1.
8. Confirm anti-malware cannot be disabled by users without documented management approval per 5.3.5.
9. Confirm anti-malware logs are enabled and retained per 5.3.4.

**SOC 2 (TSP 2017) (CC6.8)**
10. Confirm controls prevent introduction of unauthorized or malicious software per CC6.8.

**ISO 27002:2022 (8.7)**
11. Implement malware protection supported by appropriate user awareness per 8.7.

**NIST 800-53 Rev5 (SI-3)**
12. Implement malicious code protection mechanisms at system entry and exit points per SI-3.

**CIS Controls v8.1 (10.1, 10.2, 10.7)**
13. Deploy and maintain anti-malware software on all enterprise assets per 10.1.
14. Configure automatic anti-malware signature updates per 10.2.
15. Use behaviour-based anti-malware software per 10.7.

**SCF 2025 (END-04, END-07)**
16. Utilise anti-malware technologies to detect and eradicate malicious code per END-04.
17. Utilise host-based intrusion detection and prevention per END-07.

**NIS2 Article 21 (Article 21(2) (e))**
18. Confirm malware protection controls support security in network and information systems per Article 21(2)(e).

### Web and Email Filtering Review

**Applies to all frameworks**
1. Review web filtering platform configuration and confirm DNS filtering is applied to all users including remote workers.
2. Verify DMARC, SPF, and DKIM are configured and enforced for all organisation-owned domains.
3. Review email gateway anti-malware scan configuration and confirm it covers all inbound attachments.
4. Review filtering event logs; confirm blocked events are being logged and significant incidents investigated.
5. Document configuration status and any gaps identified.

**PCI DSS v4.0.1 (1.2.5, 5.4.1)**
6. Confirm anti-phishing mechanisms are in place and active per 5.4.1.
7. Confirm only approved services and protocols are permitted through network controls per 1.2.5.

**SOC 2 (TSP 2017) (CC6.6, CC6.8)**
8. Confirm logical access security measures protect against external threats per CC6.6.
9. Confirm controls prevent introduction of malicious software per CC6.8.

**ISO 27002:2022 (8.23)**
10. Manage access to external websites to reduce exposure to malicious content per 8.23.

**NIST 800-53 Rev5 (SC-7, SI-8)**
11. Monitor and control communications at external and internal managed interfaces per SC-7.
12. Employ spam protection mechanisms at system entry and exit points per SI-8.

**CIS Controls v8.1 (9.2, 9.3, 9.5, 9.7)**
13. Use DNS filtering services on all endpoint devices per 9.2.
14. Enforce and update network-based URL filters per 9.3.
15. Implement DMARC, SPF, and DKIM per 9.5.
16. Deploy and maintain email server anti-malware protections per 9.7.

**SCF 2025 (NET-18, NET-20, END-08)**
17. Force internet-bound traffic through a proxy for URL and DNS filtering per NET-18.
18. Implement email filtering to detect malicious attachments per NET-20.
19. Utilise anti-phishing and spam protection technologies per END-08.

**NIS2 Article 21 (Article 21(2) (e))**
20. Confirm web and email filtering controls support security in network and information systems per Article 21(2)(e).

### Software Installation Control

**Applies to all frameworks**
1. Review application allowlisting or equivalent control configuration across all endpoints.
2. Pull log of software installation attempts for the period; identify blocked and authorised events.
3. Confirm all authorised installations were approved and logged.
4. Review the authorised software list for currency; remove end-of-life titles.
5. Document compliance rate and any unauthorised software found.

**PCI DSS v4.0.1 (6.3.2)**
6. Confirm only authorised software is installed on CDE systems per 6.3.2.

**SOC 2 (TSP 2017) (CC6.8)**
7. Confirm installation and modification of software is restricted to authorised individuals per CC6.8.

**ISO 27002:2022 (8.19)**
8. Securely manage software installation on operational systems per 8.19.

**NIST 800-53 Rev5 (CM-7, CM-11)**
9. Configure the system to provide only essential capabilities per CM-7.
10. Establish policies governing user-installed software per CM-11.

**CIS Controls v8.1 (2.5, 2.6, 2.7)**
11. Allowlist authorised software using technical controls per 2.5.
12. Allowlist authorised libraries per 2.6.
13. Allowlist authorised scripts using digital signatures and version control per 2.7.

**SCF 2025 (CFG-03, CFG-04, CFG-05, END-03)**
14. Configure systems to provide only essential capabilities per CFG-03.
15. Enforce software usage restrictions per CFG-04.
16. Restrict ability of non-privileged users to install unauthorised software per CFG-05.
17. Prohibit software installations without explicitly assigned privileged status per END-03.
