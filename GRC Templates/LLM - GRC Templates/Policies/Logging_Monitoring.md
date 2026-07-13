# Logging Monitoring

## Purpose
To ensure that security-relevant events are logged, retained, and reviewed to support detection, investigation, and compliance.

## Scope
All systems, applications, network devices, and infrastructure processing, storing, or transmitting sensitive information.

## Responsibilities

- **System Owners**: Ensure logging is enabled and configured per standards on their systems.
- **Information Security**: Define logging requirements, monitor alerts, and review logs.
- **IT Operations**: Maintain log infrastructure, integrity, and retention.

## Policy Statements

### Log Generation Requirements

**Applies to all frameworks**
- All systems must generate logs covering authentication events, access to sensitive data, privilege escalation, configuration changes, and security tool alerts.
- System clocks must be synchronized to an authoritative time source.

**PCI DSS v4.0.1 (10.2.1, 10.2.2, 10.3.1, 10.3.2)**
- Log entries must include: timestamp, event type, user identity, source IP, and outcome.
- Logs must capture all individual user access to cardholder data.

**ISO 27002:2022 (8.15, 8.17)**
- Logging requirements must be defined based on the risk profile of the system.

**SOC 2 (TSP 2017) (CC7.1, CC7.2)**
- Logging must be sufficient to support detection of and response to security events.

**NIST 800-53 Rev5 (AU-2, AU-3, AU-8, AU-12)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (8.2, 8.4, 8.5, 8.6, 8.7, 8.8)**

**SCF 2025 (MON-01, MON-02, MON-03, MON-04)**

**NIS2 Article 21 (Article 21(2) (a))**

**CIS Controls v8.1 (13.6)**
- Collect network traffic flow logs and/or network traffic to review and alert upon from network devices per 13.6.

**PCI DSS v4.0.1 (10.2.1.1, 10.2.1.2, 10.2.1.3, 10.2.1.4, 10.2.1.5, 10.2.1.6, 10.2.1.7)**
- Audit logs capture all individual user access to cardholder data per 10.2.1.1.
- Audit logs capture all actions by any individual with administrative access per 10.2.1.2.
- Audit logs capture all access to audit logs per 10.2.1.3.
- Audit logs capture all invalid logical access attempts per 10.2.1.4.
- Audit logs capture all changes to identification and authentication credentials per 10.2.1.5.
- Audit logs capture all initialisation of new audit logs and starting, stopping, or pausing of existing audit logs per 10.2.1.6.
- Audit logs capture all creation and deletion of system-level objects per 10.2.1.7.

**NIST 800-53 Rev5 (AU-1, AU-4, AU-10, AU-13, AU-14, AU-15, AU-16)**
- Develop and disseminate audit and accountability policy per AU-1.
- Allocate sufficient audit log storage capacity per AU-4.
- Provide non-repudiation protection for information from being denied by sender per AU-10.
- Monitor open source systems for evidence of unauthorised exfiltration of information per AU-13.
- Provide and implement capability to capture and log user sessions per AU-14.
- Provide alternate audit logging capability in case of primary failure per AU-15.
- Coordinate audit logging with organisations requiring cross-organisational audit per AU-16.

### Log Protection and Retention

**Applies to all frameworks**
- Logs must be protected from unauthorized modification or deletion.
- Log access must be restricted to authorized personnel.

**PCI DSS v4.0.1 (10.3.3, 10.5.1, 10.6.1, 10.6.2, 10.6.3)**
- Logs must be retained for a minimum of 12 months, with at least 3 months immediately available.

**ISO 27002:2022 (8.15)**
- Log storage capacity must be managed to avoid loss of events due to overflow.

**NIST 800-53 Rev5 (AU-9, AU-11)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (8.1, 8.3, 8.9)**

**SCF 2025 (MON-05, MON-06, MON-07)**

**PCI DSS v4.0.1 (10.3.4)**
- Use file integrity monitoring or change-detection mechanisms on audit logs to ensure existing log data cannot be changed without generating alerts per 10.3.4.

### Log Review and Alerting

**Applies to all frameworks**
- Automated alerting must be configured for critical security events.
- Security monitoring tools must be operational and alerts must not be suppressed without authorization.

**PCI DSS v4.0.1 (10.4.1, 10.4.2, 10.4.3, 10.7.1, 10.7.2, 10.7.3)**
- Logs must be reviewed at least daily for critical systems.
- Failures in security controls must be detected and responded to in a timely manner.

**ISO 27002:2022 (8.16)**
- Anomalous activity must be investigated and escalated per defined procedures.

**SOC 2 (TSP 2017) (CC4.1, CC4.2)**
- Monitoring must support detection of threats from both internal and external sources.

**NIST 800-53 Rev5 (AU-6)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (8.11, 8.12)**

**SCF 2025 (MON-08, MON-09, MON-10, MON-11)**

**NIS2 Article 21 (Article 21(2) (a), Article 21(2) (f))**

**CIS Controls v8.1 (13.11)**
- Tune security event alerting thresholds monthly or more frequently per 13.11.

**PCI DSS v4.0.1 (10.4.1.1, 10.4.2.1)**
- Use automated mechanisms to perform audit log reviews per 10.4.1.1.
- Define the frequency of periodic log reviews for all other system components in the entity's targeted risk analysis per 10.4.2.1.

**NIST 800-53 Rev5 (AU-7)**
- Provide audit record reduction and report generation to support analysis and after-the-fact investigations per AU-7.

**SCF 2025 (MON-12, MON-13, MON-14, MON-15, MON-18)**
- Implement session audit capability per MON-12.
- Provide alternate event logging capability per MON-13.
- Coordinate audit logging across organisations per MON-14.
- Implement covert channel analysis per MON-15.
- Implement file activity monitoring per MON-18.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Log Configuration

**Applies to all frameworks**
1. Identify all in-scope systems requiring logging.
2. Configure event types per minimum logging requirements.
3. Synchronize system clocks to an approved time source.
4. Verify logs are forwarding to centralised storage.
5. Confirm log entries include timestamp, event type, user identity, source IP, and outcome.
6. Verify logging covers all individual access to cardholder data.
7. Document logging configuration per system and retain as a baseline.

**PCI DSS v4.0.1 (10.2.1, 10.2.2, 10.3.1, 10.3.2, 10.6.1, 10.6.2, 10.6.3)**
8. Confirm logs capture all individual user access to cardholder data per 10.2.1.
9. Confirm time synchronisation is configured to at least two time sources per 10.6.1 and 10.6.2.
10. Confirm log entries include all required fields: user ID, event type, date/time, success/fail, source, affected resource per 10.2.2.

**SOC 2 (TSP 2017) (CC7.1, CC7.2)**
11. Confirm logging covers changes to configurations and security-relevant events per CC7.1 and CC7.2.

**ISO 27002:2022 (8.15, 8.17)**
12. Produce, store, protect, and analyse logs recording activities, exceptions, and events per 8.15.
13. Synchronise system clocks to approved time sources per 8.17.

**NIST 800-53 Rev5 (AU-2, AU-3, AU-8, AU-12)**
14. Identify event types to log per AU-2.
15. Ensure records contain event type, time, location, source, outcome, and identity per AU-3.
16. Synchronise clocks per AU-8.
17. Provide audit record generation capability per AU-12.

**CIS Controls v8.1 (8.2, 8.4, 8.5)**
18. Ensure logging is enabled across all enterprise assets per 8.2.
19. Configure at least two synchronised time sources per 8.4.
20. Configure detailed audit logging for assets containing sensitive data per 8.5.

**SCF 2025 (MON-02, MON-03, MON-07)**
21. Utilise a SIEM for centralised log collection per MON-02.
22. Configure event logs with sufficient content per MON-03.
23. Configure systems to use an authoritative time source per MON-07.

**NIS2 Article 21 (Article 21(2) (a))**
24. Confirm logging supports risk analysis and information system security policies per Article 21(2)(a).

**ISO 27701:2025 (A.3.25)**
25. Produce, store, protect, and analyse logs related to PII processing per A.3.25.

**ISO 42001:2023 (A.6.2.8)**
26. Record AI system event logs at minimum when the system is in use per A.6.2.8.

### Log Review

**Applies to all frameworks**
1. Review automated alerts per defined SLA.
2. Investigate and escalate anomalies.
3. Document review activities and findings.
4. Perform daily review of logs for critical and CDE systems.
5. Confirm all alerts were actioned; document disposition of each.
6. Perform periodic manual log reviews per schedule in addition to automated alerting.
7. Confirm monitoring covers both internal and external threat sources.

**PCI DSS v4.0.1 (10.4.1, 10.4.2, 10.4.3)**
8. Confirm daily review is performed for CDE and critical system logs per 10.4.1.
9. Confirm automated mechanisms are used for log reviews per 10.4.1.1.
10. Confirm all anomalies and exceptions identified are addressed per 10.4.3.

**SOC 2 (TSP 2017) (CC4.1, CC7.2)**
11. Confirm anomalous behaviour is detected and investigated per CC7.2.
12. Confirm evaluation process is in place for security event analysis per CC4.1.

**ISO 27002:2022 (8.16)**
13. Monitor for anomalous behaviour and evaluate potential incidents per 8.16.

**NIST 800-53 Rev5 (AU-6, SI-4)**
14. Review and analyse audit records at defined frequencies per AU-6.
15. Monitor the system for attacks and indicators of compromise per SI-4.

**CIS Controls v8.1 (8.11, 13.1)**
16. Conduct audit log reviews to detect anomalies at least weekly per 8.11.
17. Centralise security event alerting for correlation and analysis per 13.1.

**SCF 2025 (MON-16, MON-17)**
18. Detect and respond to anomalous behaviour per MON-16.
19. Ensure log reviews include analysis and triage integrated with incident response per MON-17.

**NIS2 Article 21 (Article 21(2) (f))**
20. Confirm log review supports assessment of cybersecurity risk-management measure effectiveness per Article 21(2)(f).

### Security Control Failure Response

**Applies to all frameworks**
1. Detect failure of security controls via automated alerting.
2. Raise an incident ticket immediately upon detection.
3. Restore the failed control within defined SLA.
4. Review logs for the period of failure to identify any missed events.
5. Document the failure, duration, impact assessment, and remediation actions.

**PCI DSS v4.0.1 (10.7.1, 10.7.2, 10.7.3)**
6. Confirm failure is detected promptly; document start and end time of failure per 10.7.3.
7. Confirm security functions are restored and root cause documented per 10.7.3.
8. Review logs for the failure period to identify missed events per 10.7.2.

**SOC 2 (TSP 2017) (CC7.1)**
9. Confirm control failure triggers detection and monitoring procedures per CC7.1.

**ISO 27002:2022 (8.16)**
10. Take appropriate actions to evaluate potential incidents upon detecting anomalous behaviour per 8.16.

**NIST 800-53 Rev5 (SI-4, AU-5)**
11. Monitor for security control failures per SI-4.
12. Alert personnel and take action upon audit logging process failures per AU-5.

**CIS Controls v8.1 (13.1)**
13. Centralise alerting to detect and respond to control failures per 13.1.

**SCF 2025 (MON-05)**
14. Alert appropriate personnel in the event of a log processing failure and remedy per MON-05.
### AI System Logging Requirements

**EU AI Act 2024/1689 (Art.12.1, Art.12.2, Art.12.3, Art.19.1, Art.19.2)**
- High-risk AI systems must technically allow for the automatic recording of events (logs) over the lifetime of the system.
- Logging capabilities must ensure a level of traceability appropriate to the intended purpose, enabling the detection of risks and facilitating post-market monitoring.
- For AI systems used for biometric identification or categorisation, logs must record the period of use, the reference database used, and the input data that led to identification.
- Providers must retain logs automatically generated by high-risk AI systems for the legally required period.
- Deployers must keep logs under their control for a period appropriate to the intended purpose and applicable law.

**NIST AI 600-1 2024 (GV-1.5-001, GV-1.5-003, MG-4.1-006)**
- Organisational responsibilities for periodic review of content provenance and incident monitoring for GAI systems must be defined.
- A document retention policy must keep history for test, evaluation, validation, and verification methods for GAI systems.
- Dataset modifications must be tracked for provenance by monitoring data deletions, rectification requests, and other changes.

### AI System Log Configuration Procedure

**Applies to all frameworks**
1. Confirm logging is enabled on all in-scope AI systems.
2. Verify logs capture events sufficient for traceability of AI system outputs and decisions.
3. Confirm retention period meets the legally required minimum.
4. Confirm log access is controlled and logs are protected from tampering.
5. Document configuration and retain as evidence.

**EU AI Act 2024/1689 (Art.12.1, Art.12.2, Art.19.1)**
6. Confirm AI system technical architecture supports automatic event logging throughout the system lifetime.
7. Confirm logs include sufficient detail for post-market monitoring and serious incident investigation.

**NIST AI 600-1 2024 (GV-1.5-001, MG-4.1-006)**
6. Confirm dataset modification tracking is in place for AI training data changes.

### Security Operations

**NIST 800-53 Rev5 (AU-1)**
- Develop, document, disseminate, review, and update an audit and accountability policy per AU-1.

**SCF 2025 (OPS-01, OPS-02, OPS-03, OPS-04, OPS-05, OPS-06, OPS-07)**
- Implement operations security controls to protect operational information per OPS-01.
- Define and implement a security concept of operations per OPS-02.
- Ensure security controls support business process delivery per OPS-03.
- Operate a Security Operations Center or equivalent capability per OPS-04.
- Publish and maintain secure practices guidelines per OPS-05.
- Implement security orchestration, automation, and response capabilities per OPS-06.
- Detect and manage shadow IT per OPS-07.

### Security Operations Review Procedure

**SCF 2025 (OPS-01, OPS-02, OPS-03, OPS-04, OPS-05, OPS-06, OPS-07)**
1. Confirm operations security controls are documented and operating.
2. Review SOC or equivalent monitoring coverage for gaps.
3. Confirm automated response playbooks are current and tested.
4. Review shadow IT detection findings and confirm remediation actions taken.
