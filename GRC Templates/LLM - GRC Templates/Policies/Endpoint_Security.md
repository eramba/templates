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

**CIS Controls v8.1 (4.7, 4.8, 4.9)**
- Manage default accounts on enterprise assets including disabling or renaming them per 4.7.
- Uninstall or disable unnecessary services on enterprise assets per 4.8.
- Configure trusted DNS servers on enterprise network infrastructure per 4.9.

**CIS Controls v8.1 (4.11, 4.12)**
- Enforce remote wipe capability on portable end-user devices per 4.11.
- Ensure separate enterprise workspaces on mobile end-user devices per 4.12.

**PCI DSS v4.0.1 (1.4.1, 1.4.2, 2.2.1, 2.2.2, 2.2.3, 2.2.4, 2.2.5, 2.2.6, 2.2.7, 2.3.1, 2.3.2)**
- Develop, implement, and maintain configuration standards covering all system components addressing known security vulnerabilities per 2.2.1.
- Manage vendor default accounts by disabling unused ones or changing default passwords per 2.2.2.
- Manage system components so that primary functions requiring different security levels are separated per 2.2.3.
- Enable only necessary services, protocols, daemons, and functions; remove or disable all unnecessary functionality per 2.2.4.
- Document and implement additional security features for any insecure services, protocols, or daemons that are present per 2.2.5.
- Configure system security parameters to prevent misuse per 2.2.6.
- Encrypt all non-console administrative access using strong cryptography per 2.2.7.
- Manage wireless vendor defaults: change wireless encryption keys and passwords, enable only necessary wireless services per 2.3.1.
- Change wireless encryption keys when personnel with knowledge of the key leave the organisation per 2.3.2.

**NIST 800-53 Rev5 (CM-1, CM-14, CM-9, SA-7, SI-16)**
- Develop and disseminate configuration management policy per CM-1.
- Develop, document, and implement configuration management plan per CM-9.
- Document and maintain information location for system components per CM-12.
- Document and maintain data action mapping for system components per CM-13.
- Prohibit the use or installation of unsigned software components per CM-14.

**SCF 2025 (CFG-01, CFG-06, CFG-07, CFG-08, AST-12, AST-13, AST-14, AST-15, AST-16, AST-17, AST-18, AST-19, AST-20, AST-21, AST-22, AST-23, AST-24, AST-25, AST-26, AST-27, AST-28, AST-29, AST-31)**
- Implement configuration management program per CFG-01.
- Enforce configuration baselines per CFG-06.
- Implement zero-touch provisioning per CFG-07.
- Enforce sensitive and regulated data access restrictions per CFG-08.
- Govern use of personal devices per AST-12.
- Govern use of third-party devices per AST-13.
- Define usage parameters for assets per AST-14.
- Implement logical tampering protection per AST-15.
- Govern BYOD usage per AST-16.
- Identify and restrict prohibited equipment and services per AST-17.
- Protect roots of trust per AST-18.
- Secure telecommunications equipment per AST-19.
- Implement video teleconference security per AST-20.
- Implement VoIP security per AST-21.
- Control microphones and web cameras per AST-22.
- Secure multi-function devices per AST-23.
- Manage travel-only devices per AST-24.
- Re-image devices after travel per AST-25.
- Control system administrative processes per AST-26.
- Implement jump server controls per AST-27.
- Control database administrative processes per AST-28.
- Implement RFID security per AST-29.
- Implement asset categorisation per AST-31.

### Malware Protection

**Applies to all frameworks**
- Anti-malware software must be deployed and active on all endpoint devices.
- Malware signatures and definitions must be updated automatically and within 24 hours of release.
- Behaviour-based and heuristic detection must be enabled where supported.
- Anti-malware management must be centralised and provide visibility across all endpoints.

**CIS Controls v8.1 (10.1, 10.2, 10.6, 10.7)**
- Anti-exploitation features must be enabled on all endpoints where supported.

**CIS Controls v8.1 (10.3, 10.4, 10.5)**
- Disable autorun and autoplay for removable media per 10.3.
- Configure anti-malware to automatically scan removable media per 10.4.
- Enable anti-exploitation features on enterprise assets where supported per 10.5.

**PCI DSS v4.0.1 (5.2.3, 5.2.3.1, 5.3.2.1)**
- Periodically evaluate system components not at risk for malware and document the evaluation per 5.2.3.
- Define the frequency of periodic evaluations of components not at risk for malware in the entity's targeted risk analysis per 5.2.3.1.
- Define the frequency of periodic malware scans in the entity's targeted risk analysis per 5.3.2.1.

### Web and Email Filtering

**Applies to all frameworks**
- DNS filtering must be applied to all endpoint devices to block access to known malicious domains.
- Web content filtering must restrict access to categories posing security risk.
- Email security controls must include DMARC, SPF, and DKIM enforcement to prevent spoofing.
- Email gateway anti-malware scanning must be enabled for all inbound attachments.
- Unnecessary file types must be blocked at the email gateway.

**CIS Controls v8.1 (9.1, 9.4)**
- Ensure only fully supported browsers and email clients are allowed to execute in the enterprise per 9.1.
- Restrict unauthorised or unnecessary browser and email client extensions and plugins per 9.4.

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

**SOC 2 (TSP 2017) (CC6.8)**
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

### Remote Working Security

**Applies to all frameworks**
- Security measures shall be implemented for personnel working remotely to protect information accessed, processed, or stored outside the organisation's premises.
- Remote working arrangements shall be authorised and subject to appropriate security controls proportionate to the risk.

**ISO 27002:2022 (6.7)**
- Security measures for remote working shall cover: physical protection of the remote working location, technical controls on the device and network connection, access controls, data handling requirements, and protection against interception per 6.7.
- Remote access shall use encrypted connections and multi-factor authentication.

**SCF 2025 (END-05, END-06, END-09, END-10, END-11, END-12, END-13, END-14, END-15, END-16, MDM-01, MDM-02, MDM-03, MDM-04, MDM-05, MDM-06, MDM-07, MDM-08, MDM-09, MDM-10, MDM-11)**
- Implement software firewall on endpoints per END-05.
- Implement endpoint file integrity monitoring per END-06.
- Implement trusted path for security functions per END-09.
- Control mobile code per END-10.
- Implement thin node controls per END-11.
- Control port and I/O device access per END-12.
- Control sensor capability and data per END-13.
- Control collaborative computing devices per END-14.
- Control hypervisor access per END-15.
- Restrict access to security functions per END-16.
- Centralise management of mobile devices per MDM-01.
- Control access for mobile devices per MDM-02.
- Implement full device and container-based encryption per MDM-03.
- Protect against mobile device tampering per MDM-04.
- Implement remote purging capability per MDM-05.
- Govern use of personally-owned mobile devices per MDM-06.
- Govern use of organisation-owned mobile devices per MDM-07.
- Implement mobile device data retention limitations per MDM-08.
- Implement mobile device geofencing per MDM-09.
- Separate mobile device profiles per MDM-10.
- Restrict access to authorised technology assets from mobile devices per MDM-11.

### Remote Working Security Review Procedure

**ISO 27002:2022 (6.7)**
1. Confirm remote working security requirements are documented and communicated to all remote workers.
2. Confirm remote access uses encrypted connections (VPN or equivalent) and multi-factor authentication.
3. Confirm remote working devices meet the same endpoint security standards as office devices.
4. Review any incidents involving remote working security during the period.
5. Confirm remote working authorisations are reviewed and approved at minimum annually.
