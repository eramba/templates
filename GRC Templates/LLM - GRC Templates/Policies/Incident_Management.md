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

**NIST 800-53 Rev5 (IR-6, IR-8, SI-17)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (17.2, 17.3, 17.6)**

**SCF 2025 (IRO-01, IRO-02, IRO-03, IRO-04)**

**NIS2 Article 21 (Article 21(2) (b))**

**CIS Controls v8.1 (17.1)**
- Designate one key person and at least one backup to manage incident handling, coordination, and documentation per 17.1.

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

**SCF 2025 (IRO-05, IRO-06, IRO-07, IRO-08, IRO-09)**

**NIS2 Article 21 (Article 21(2) (b))**

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

**SCF 2025 (IRO-10, IRO-11, IRO-12)**

**NIS2 Article 21 (Article 21(2) (b))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Incident Reporting

**Applies to all frameworks**
1. Identify a suspected or confirmed security incident.
2. Report via the designated channel.
3. Information Security logs the report and assigns a severity classification.
4. Open an incident record and begin tracking.
5. Confirm whether cardholder data may be involved; if so, escalate immediately per the CDE incident response plan.

**PCI DSS v4.0.1 (12.10.1, 12.10.3)**
6. Confirm 24/7 response capability is in place and designated personnel are available per 12.10.3.
7. Confirm whether CDE or cardholder data is involved; if so, initiate CDE incident response plan per 12.10.1.

**SOC 2 (TSP 2017) (CC7.3)**
8. Confirm security events are evaluated and communicated to determine incident classification per CC7.3.

**ISO 27002:2022 (5.24, 5.25, 6.8)**
9. Confirm incident management processes, roles, and responsibilities are defined per 5.24.
10. Confirm security events are assessed and categorised per 5.25.
11. Confirm reporting mechanism is accessible per 6.8.

**NIST 800-53 Rev5 (IR-6, IR-8)**
12. Require personnel to report suspected incidents within the defined time period per IR-6.
13. Confirm the incident response plan includes roles and communication per IR-8.

**CIS Controls v8.1 (17.3, 17.4)**
14. Confirm incident reporting process is documented and accessible per 17.3.
15. Confirm incident response process addresses roles and responsibilities per 17.4.

**SCF 2025 (IRO-01, IRO-04, IRO-09)**
16. Confirm incident response operations capability is in place per IRO-01.
17. Confirm incident response plan is maintained and accessible per IRO-04.
18. Document and monitor incident status through to resolution per IRO-09.

**NIS2 Article 21 (Article 21(2) (b))**
19. Confirm incident handling procedures are in place proportionate to entity classification per Article 21(2)(b).

**ISO 27701:2025 (A.3.11)**
20. Confirm incident management processes cover PII processing incidents per A.3.11.

**ISO 42001:2023 (A.8.4)**
21. Confirm a plan for communicating AI incidents to users is in place per A.8.4.

**ISO 27002:2022 (5.5)**
- Establish and maintain contact with relevant authorities so that obligations to notify can be fulfilled promptly per 5.5.

### Incident Response

**Applies to all frameworks**
1. Assemble incident response team based on severity.
2. Contain the incident to prevent further damage.
3. Eradicate the threat and restore affected systems.
4. Close incident record with full documentation.
5. Collect and preserve evidence in a forensically sound manner.
6. Notify payment brand and acquirer if cardholder data is confirmed compromised.
7. Document all actions taken throughout the response lifecycle.
8. Confirm recovery actions restore service in line with availability and processing integrity commitments.

**PCI DSS v4.0.1 (12.10.2, 12.10.4, 12.10.5)**
9. Collect and preserve evidence in a forensically sound manner per 12.10.4.
10. Notify payment brand or acquirer if cardholder data is confirmed compromised per 12.10.5.

**SOC 2 (TSP 2017) (CC7.4)**
11. Confirm response procedures address containment and recovery per CC7.4.
12. Document roles and responsibilities applied during response per CC7.4.

**ISO 27002:2022 (5.26, 5.28)**
13. Respond to incidents in accordance with documented procedures per 5.26.
14. Identify, collect, and preserve evidence in a forensically sound manner per 5.28.

**NIST 800-53 Rev5 (IR-4, IR-5)**
15. Execute incident handling covering preparation, detection, containment, eradication, and recovery per IR-4.
16. Track and document all incidents per IR-5.

**CIS Controls v8.1 (17.5, 17.6)**
17. Confirm roles and responsibilities were assigned and followed per 17.5.
18. Confirm communication mechanisms were used as planned per 17.6.

**SCF 2025 (IRO-02, IRO-08, IRO-12)**
19. Execute incident handling covering detection, analysis, containment, eradication, and recovery per IRO-02.
20. Maintain chain of custody and digital forensics procedures per IRO-08.

**NIS2 Article 21 (Article 21(2) (b))**
21. Execute incident response measures proportionate to entity classification per Article 21(2)(b).

**ISO 27701:2025 (A.3.12)**
22. Respond to PII-related incidents per documented procedures; include breach notification actions where required per A.3.12.

**PCI DSS v4.0.1 (12.10.4.1)**
- Define the frequency of periodic training for incident response personnel in the entity's targeted risk analysis per 12.10.4.1.

**NIST 800-53 Rev5 (IR-1, IR-2, IR-3, IR-7, IR-9, IR-10)**
- Develop and disseminate incident response policy per IR-1.
- Provide incident response training to personnel per IR-2.
- Test incident response capability using exercises per IR-3.
- Provide incident response assistance resources per IR-7.
- Implement information spillage response procedures per IR-9.
- Establish an integrated information security analysis team per IR-10.

**SCF 2025 (IRO-14, IRO-15, IRO-16, OPS-04, OPS-06, TPM-11)**
- Maintain contacts with regulatory and law enforcement agencies per IRO-14.
- Implement detonation chambers and sandboxes per IRO-15.
- Implement public relations and reputation repair processes per IRO-16.
- Operate security operations centre capability per OPS-04.
- Implement security orchestration, automation, and response per OPS-06.
- Ensure third-party incident response and recovery capabilities per TPM-11.

### Post-Incident Review

**Applies to all frameworks**
1. Conduct review within a defined timeframe after incident closure.
2. Identify root cause, timeline, and control gaps.
3. Document lessons learned.
4. Update incident response plan and controls as needed.
5. Incorporate lessons learned into the next annual incident response plan test.
6. Retain collected evidence consistent with legal and regulatory requirements.

**PCI DSS v4.0.1 (12.10.6, 12.10.7)**
7. Incorporate lessons learned into the incident response plan per 12.10.6.
8. If stored PAN was found outside CDE, confirm retrieval or deletion actions were taken per 12.10.7.

**SOC 2 (TSP 2017) (CC7.5)**
9. Confirm recovery activities restored the affected environment per CC7.5.

**ISO 27002:2022 (5.27)**
10. Use knowledge gained from incidents to strengthen controls per 5.27.

**NIST 800-53 Rev5 (IR-4)**
11. Incorporate lessons learned into incident response capabilities per IR-4.

**CIS Controls v8.1 (17.8)**
12. Conduct post-incident review to identify lessons learned and follow-up actions per 17.8.

**SCF 2025 (IRO-13)**
13. Conduct root cause analysis and incorporate lessons learned per IRO-13.

**NIS2 Article 21 (Article 21(2) (b))**
14. Confirm response and recovery activities were proportionate per Article 21(2)(b).

**ISO 45001:2018 (10.2, 8.2)**
15. Investigate incidents and implement corrective actions per 10.2.

### NIS2 Incident Notification Review Procedure

**Applies to all frameworks**
1. For each significant incident in the review period, confirm whether NIS2 reporting obligation was triggered.
2. Verify initial notification to the competent authority was made within 24 hours.
3. Verify detailed report was submitted within 72 hours.
4. Confirm notification content met regulatory requirements.
5. Document incidents where notification obligations were or were not triggered, with rationale.
