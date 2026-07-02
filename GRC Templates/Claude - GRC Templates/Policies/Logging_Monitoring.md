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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 8.2, 8.4, 8.5, 8.6, 8.7, 8.8 for specific safeguard requirements.

**SCF 2025 (MON-01, MON-02, MON-03, MON-04)**
- Implement SCF 2025 controls applicable to this area; refer to controls MON-01, MON-02, MON-03, MON-04 for specific requirements.

**NIS2 Article 21 (Article 21(2) (a))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (a)); measures must be proportionate to the organisation's risk exposure and size.

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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 8.1, 8.3, 8.9 for specific safeguard requirements.

**SCF 2025 (MON-05, MON-06, MON-07)**
- Implement SCF 2025 controls applicable to this area; refer to controls MON-05, MON-06, MON-07 for specific requirements.

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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 8.11, 8.12 for specific safeguard requirements.

**SCF 2025 (MON-08, MON-09, MON-10, MON-11)**
- Implement SCF 2025 controls applicable to this area; refer to controls MON-08, MON-09, MON-10, MON-11 for specific requirements.

**NIS2 Article 21 (Article 21(2) (a), Article 21(2) (f))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (a), Article 21(2) (f)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Log Configuration

**Applies to all frameworks**
1. Identify all in-scope systems requiring logging.
2. Configure event types per minimum logging requirements.
3. Synchronize system clocks to an approved time source.
4. Verify logs are forwarding to centralised storage.

**PCI DSS v4.0.1 (10.2.1, 10.3.1, 10.3.2)**
5. Confirm log entries include timestamp, event type, user identity, source IP, and outcome.
6. Verify logging covers all individual access to cardholder data.

**ISO 27002:2022 (8.15, 8.17)**
5. Document logging configuration per system and retain as a baseline.

**NIST 800-53 Rev5 (AU-2, AU-3, AU-8, AU-12)**
8. Document compliance with applicable NIST 800-53 Rev5 controls (AU-2, AU-3, AU-8, AU-12) in the system security plan.

**CIS Controls v8.1 (8.2, 8.4, 8.5, 8.9)**
9. Document compliance with applicable CIS Controls v8.1 controls (8.2, 8.4, 8.5, 8.9) per organisational policy.

**SCF 2025 (MON-01, MON-02, MON-03, MON-04)**
10. Document compliance with applicable SCF 2025 controls (MON-01, MON-02, MON-03, MON-04) per organisational policy.

**NIS2 Article 21 (Article 21(2) (a))**
11. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (a)) as applicable to the organisation's classification under NIS2.

### Log Review

**Applies to all frameworks**
1. Review automated alerts per defined SLA.
2. Investigate and escalate anomalies.
3. Document review activities and findings.

**PCI DSS v4.0.1 (10.4.1, 10.4.2, 10.4.3)**
4. Perform daily review of logs for critical and CDE systems.
5. Confirm all alerts were actioned; document disposition of each.

**ISO 27002:2022 (8.16)**
4. Perform periodic manual log reviews per schedule in addition to automated alerting.

**SOC 2 (TSP 2017) (CC4.1, CC4.2)**
4. Confirm monitoring covers both internal and external threat sources.

**NIST 800-53 Rev5 (AU-6)**
8. Document compliance with applicable NIST 800-53 Rev5 controls (AU-6) in the system security plan.

**CIS Controls v8.1 (8.11, 8.12)**
9. Document compliance with applicable CIS Controls v8.1 controls (8.11, 8.12) per organisational policy.

**SCF 2025 (MON-08, MON-09, MON-10)**
10. Document compliance with applicable SCF 2025 controls (MON-08, MON-09, MON-10) per organisational policy.

**NIS2 Article 21 (Article 21(2) (f))**
11. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (f)) as applicable to the organisation's classification under NIS2.

### Security Control Failure Response

**PCI DSS v4.0.1 (10.7.1, 10.7.2, 10.7.3)**
1. Detect failure of security controls via automated alerting.
2. Raise an incident ticket immediately upon detection.
3. Restore the failed control within defined SLA.
4. Review logs for the period of failure to identify any missed events.
5. Document the failure, duration, impact assessment, and remediation actions.

**NIST 800-53 Rev5 (SI-5)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (SI-5) in the system security plan.

**CIS Controls v8.1 (17.3)**
7. Document compliance with applicable CIS Controls v8.1 controls (17.3) per organisational policy.

**SCF 2025 (MON-11, IRO-02)**
8. Document compliance with applicable SCF 2025 controls (MON-11, IRO-02) per organisational policy.
