# Incident Management

## Purpose
To ensure information security incidents are detected, reported, assessed, contained, and resolved in a timely and consistent manner.

## Scope
All information systems, users, and processes. Includes third parties who must report incidents under contractual obligation.

## Responsibilities

- **All Staff**: Report suspected or confirmed security incidents immediately.
- **Information Security**: Triage, coordinate response, and maintain incident records.
- **Incident Response Team**: Execute containment, eradication, and recovery actions.
- **Senior Management**: Authorize escalation decisions and communicate with external parties where required.

## Policy Statements

### Incident Detection and Reporting

**Applies to all frameworks**
- All suspected or confirmed security incidents must be reported to Information Security without delay.
- Reporting channels must be documented and communicated to all personnel.
- An incident response plan must be maintained and available to response staff.

**PCI DSS v4.0.1 (12.10.1, 12.10.3)**
- The incident response plan must be tested at least annually.
- Roles and responsibilities for incident response must be defined and assigned.

**ISO 27002:2022 (5.24, 5.25)**
- Incidents must be classified by type and severity upon receipt.

**SOC 2 (TSP 2017) (CC7.3, CC7.4)**
- Mechanisms must exist to detect incidents from both internal and external sources.

**NIST 800-53 Rev5 (IR-6, IR-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (17.2, 17.3, 17.6)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 17.2, 17.3, 17.6 for specific safeguard requirements.

**SCF 2025 (IRO-01, IRO-02, IRO-03, IRO-04)**
- Implement SCF 2025 controls applicable to this area; refer to controls IRO-01, IRO-02, IRO-03, IRO-04 for specific requirements.

**NIS2 Article 21 (Article 21(2) (b))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (b)); measures must be proportionate to the organisation's risk exposure and size.

### Incident Response and Containment

**Applies to all frameworks**
- Containment actions must be taken immediately upon confirmation of an incident.
- Affected systems must be eradicated of the threat prior to restoration.

**PCI DSS v4.0.1 (12.10.2, 12.10.4, 12.10.5)**
- Evidence must be collected and preserved in a forensically sound manner.
- Payment brand and acquirer must be notified for incidents involving cardholder data per contractual requirements.

**ISO 27002:2022 (5.26, 5.27)**
- Incidents must be documented throughout the response lifecycle.

**SOC 2 (TSP 2017) (CC7.4, CC7.5)**
- Incident response procedures must address recovery of affected systems to restore service commitments.

**NIST 800-53 Rev5 (IR-4, IR-5)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (17.4, 17.5, 17.7)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 17.4, 17.5, 17.7 for specific safeguard requirements.

**SCF 2025 (IRO-05, IRO-06, IRO-07, IRO-08, IRO-09)**
- Implement SCF 2025 controls applicable to this area; refer to controls IRO-05, IRO-06, IRO-07, IRO-08, IRO-09 for specific requirements.

**NIS2 Article 21 (Article 21(2) (b))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (b)); measures must be proportionate to the organisation's risk exposure and size.

### Post-Incident Review and Notification

**Applies to all frameworks**
- Post-incident reviews must be conducted to identify lessons learned and control improvements.
- Regulatory, legal, and contractual notification obligations must be fulfilled within required timeframes.

**PCI DSS v4.0.1 (12.10.6, 12.10.7)**
- Lessons learned must be incorporated into the incident response plan.

**ISO 27002:2022 (5.28, 5.29)**
- Collected evidence must be retained in a manner consistent with legal and regulatory requirements.

**NIST 800-53 Rev5 (IR-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (17.8, 17.9)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 17.8, 17.9 for specific safeguard requirements.

**SCF 2025 (IRO-10, IRO-11, IRO-12)**
- Implement SCF 2025 controls applicable to this area; refer to controls IRO-10, IRO-11, IRO-12 for specific requirements.

**NIS2 Article 21 (Article 21(2) (b))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (b)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Incident Reporting

**Applies to all frameworks**
1. Identify a suspected or confirmed security incident.
2. Report via the designated channel.
3. Information Security logs the report and assigns a severity classification.
4. Open an incident record and begin tracking.

**PCI DSS v4.0.1 (12.10.1, 12.10.3)**
5. Confirm whether cardholder data may be involved; if so, escalate immediately per the CDE incident response plan.

**NIST 800-53 Rev5 (IR-6)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (IR-6) in the system security plan.

**CIS Controls v8.1 (17.3, 17.6)**
7. Document compliance with applicable CIS Controls v8.1 controls (17.3, 17.6) per organisational policy.

**SCF 2025 (IRO-02, IRO-03)**
8. Document compliance with applicable SCF 2025 controls (IRO-02, IRO-03) per organisational policy.

**NIS2 Article 21 (Article 21(2) (b))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (b)) as applicable to the organisation's classification under NIS2.

### Incident Response

**Applies to all frameworks**
1. Assemble incident response team based on severity.
2. Contain the incident to prevent further damage.
3. Eradicate the threat and restore affected systems.
4. Close incident record with full documentation.

**PCI DSS v4.0.1 (12.10.2, 12.10.4, 12.10.5)**
5. Collect and preserve evidence in a forensically sound manner.
6. Notify payment brand and acquirer if cardholder data is confirmed compromised.

**ISO 27002:2022 (5.26, 5.27)**
5. Document all actions taken throughout the response lifecycle.

**SOC 2 (TSP 2017) (CC7.4, CC7.5)**
5. Confirm recovery actions restore service in line with availability and processing integrity commitments.

**NIST 800-53 Rev5 (IR-4, IR-5)**
9. Document compliance with applicable NIST 800-53 Rev5 controls (IR-4, IR-5) in the system security plan.

**CIS Controls v8.1 (17.4, 17.5, 17.7)**
10. Document compliance with applicable CIS Controls v8.1 controls (17.4, 17.5, 17.7) per organisational policy.

**SCF 2025 (IRO-05, IRO-06, IRO-07, IRO-08)**
11. Document compliance with applicable SCF 2025 controls (IRO-05, IRO-06, IRO-07, IRO-08) per organisational policy.

**NIS2 Article 21 (Article 21(2) (b))**
12. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (b)) as applicable to the organisation's classification under NIS2.

### Post-Incident Review

**Applies to all frameworks**
1. Conduct review within a defined timeframe after incident closure.
2. Identify root cause, timeline, and control gaps.
3. Document lessons learned.
4. Update incident response plan and controls as needed.

**PCI DSS v4.0.1 (12.10.6)**
5. Incorporate lessons learned into the next annual incident response plan test.

**ISO 27002:2022 (5.28, 5.29)**
5. Retain collected evidence consistent with legal and regulatory requirements.

**NIST 800-53 Rev5 (IR-4)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (IR-4) in the system security plan.

**CIS Controls v8.1 (17.8, 17.9)**
8. Document compliance with applicable CIS Controls v8.1 controls (17.8, 17.9) per organisational policy.

**SCF 2025 (IRO-10, IRO-11)**
9. Document compliance with applicable SCF 2025 controls (IRO-10, IRO-11) per organisational policy.

**NIS2 Article 21 (Article 21(2) (b))**
10. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (b)) as applicable to the organisation's classification under NIS2.
### NIS2 Incident Notification Review Procedure

**NIS2 Article 21 (Article 21(2) (b), Article 21(4), Article 21(5))**
1. For each significant incident in the review period, confirm whether NIS2 reporting obligation was triggered.
2. Verify initial notification to the competent authority was made within 24 hours.
3. Verify detailed report was submitted within 72 hours.
4. Confirm notification content met regulatory requirements.
5. Document incidents where notification obligations were or were not triggered, with rationale.
