# Business Continuity

## Purpose
To ensure the organization can maintain or rapidly restore critical operations following a disruptive event affecting information systems or services.

## Scope
All critical information systems, services, and processes including disaster recovery arrangements for IT infrastructure.

## Responsibilities

- **Senior Management**: Approve business continuity plans and recovery time objectives.
- **IT Operations**: Maintain and test disaster recovery capabilities.
- **Business Owners**: Define recovery priorities and participate in continuity planning.
- **Information Security**: Ensure continuity plans address information security requirements.

## Policy Statements

### Business Impact Analysis and Recovery Objectives

**Applies to all frameworks**
- A business impact analysis must be performed to identify critical processes and supporting systems.
- Recovery Time Objectives (RTO) and Recovery Point Objectives (RPO) must be defined and approved for critical systems.

**ISO 27002:2022 (5.29)**
- Business continuity plans must address information security requirements during and after recovery.

**SOC 2 (TSP 2017) (A1.1, A1.2)**
- Environmental protections must be in place to support availability commitments.

**NIST 800-53 Rev5 (CP-2)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (BCD-01, BCD-02, BCD-03)**

**NIS2 Article 21 (Article 21(2) (c))**

### Backup and Recovery

**Applies to all frameworks**
- Backups of critical data and systems must be performed at defined intervals consistent with RPO.
- Backup integrity must be verified periodically through test restores.

**PCI DSS v4.0.1 (12.3.3)**
- Backup media must be protected from unauthorized access and stored securely.

**ISO 27002:2022 (8.13, 8.14)**
- At least one copy of backups must be retained off-site or in a geographically separate location.
- Redundant systems must be in place for critical services where RTO requirements demand it.

**SOC 2 (TSP 2017) (A1.2, A1.3)**
- Backup and recovery capabilities must support the organization's service availability commitments.

**NIST 800-53 Rev5 (CP-6, CP-7, CP-9, CP-10)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (11.1, 11.2, 11.3, 11.4, 11.5)**

**SCF 2025 (BCD-04, BCD-05, BCD-06, BCD-07, BCD-08)**

**NIS2 Article 21 (Article 21(2) (c))**

### Testing and Maintenance

**Applies to all frameworks**
- Business continuity and disaster recovery plans must be tested at least annually.
- Plans must be reviewed and updated following tests, significant changes, or actual incidents.

**ISO 27002:2022**
- Test results must be documented and gaps addressed through remediation plans.

**NIST 800-53 Rev5 (CP-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (11.3, 11.4)**

**SCF 2025 (BCD-09, BCD-10, BCD-11)**

**NIS2 Article 21 (Article 21(2) (c))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Backup Execution and Verification

**Applies to all frameworks**
1. Execute backups per the defined schedule.
2. Verify backup job completion and log results.
3. Perform a test restore on a defined periodic basis.
4. Document restore test results and retain records.
5. Confirm backup media is stored securely and access is restricted.
6. Confirm at least one backup copy is stored off-site or in a geographically separate location.
7. Verify restore capability is consistent with defined RTO and RPO commitments.

**PCI DSS v4.0.1 (12.3.3)**
8. Confirm backup media containing cardholder data is physically secured per 12.3.3.

**SOC 2 (TSP 2017) (A1.2, A1.3)**
9. Confirm backup and recovery capabilities support availability commitments per A1.2.
10. Confirm restore testing verifies recovery meets defined RTO/RPO per A1.3.

**ISO 27002:2022 (8.13)**
11. Maintain and regularly test backup copies in accordance with the backup policy per 8.13.

**NIST 800-53 Rev5 (CP-9)**
12. Conduct backups at defined frequencies consistent with recovery objectives per CP-9.

**CIS Controls v8.1 (11.2, 11.3, 11.4, 11.5)**
13. Perform automated backups weekly or more frequently per 11.2.
14. Protect recovery data with equivalent controls per 11.3.
15. Maintain isolated instance of recovery data per 11.4.
16. Test backup recovery quarterly per 11.5.

**SCF 2025 (BCD-11, BCD-13)**
17. Create recurring backups and verify integrity to satisfy RTO and RPO per BCD-11.
18. Protect backup and restoration hardware per BCD-13.

**NIS2 Article 21 (Article 21(2) (c))**
19. Confirm backups support business continuity and backup management per Article 21(2)(c).

**ISO 27701:2025 (A.3.24)**
20. Maintain and regularly test backups of PII and related systems per A.3.24.

### Disaster Recovery Invocation

**Applies to all frameworks**
1. Declare a DR event with authorised management approval.
2. Notify the recovery team and activate the DR plan.
3. Execute recovery steps per system-specific runbooks.
4. Restore services and validate integrity before returning to production.
5. Document the incident timeline and conduct a post-recovery review.
6. Confirm information security controls are re-established as part of recovery.
7. Confirm service restoration meets defined availability and processing integrity commitments.

**SOC 2 (TSP 2017) (A1.2, CC7.5)**
8. Confirm recovery activities restore the environment to functional operation per CC7.5.
9. Confirm availability commitments are met following recovery per A1.2.

**ISO 27002:2022 (5.3)**
10. Maintain ICT readiness planned and tested based on business continuity objectives per 5.3.

**NIST 800-53 Rev5 (CP-10)**
11. Provide for system recovery and reconstitution to a known state within defined timeframes per CP-10.

**SCF 2025 (BCD-12)**
12. Ensure secure recovery and reconstitution to a known state after disruption per BCD-12.

**NIS2 Article 21 (Article 21(2) (c))**
13. Confirm disaster recovery supports business continuity and crisis management per Article 21(2)(c).

**ISO 45001:2018 (8.2)**
14. Establish, implement, and maintain emergency preparedness and response processes per 8.2.

### BCP and DR Plan Review

**Applies to all frameworks**
1. Review BCP and DR plans at least annually and following any significant change or actual incident.
2. Validate that RTO and RPO targets remain consistent with current business requirements.
3. Update contact lists, system inventories, and recovery steps where changes have occurred.
4. Obtain management sign-off on updated plans.
5. Document review completion and retain records.
6. Confirm updates reflect lessons learned from tests and real incidents.

**SOC 2 (TSP 2017) (A1.1, A1.3, CC9.1)**
7. Confirm recovery plan procedures are tested and validated per A1.3.
8. Confirm plan addresses risk mitigation for business disruption per CC9.1 and A1.1.

**ISO 27002:2022 (5.29, 5.3)**
9. Plan how to maintain information security during disruption per 5.29.
10. Maintain ICT readiness tested based on continuity objectives per 5.3.

**NIST 800-53 Rev5 (CP-2, CP-4)**
11. Develop and maintain a contingency plan addressing essential functions and recovery objectives per CP-2.
12. Test contingency plan at defined frequencies per CP-4.

**CIS Controls v8.1 (11.1)**
13. Establish and maintain a documented data recovery process per 11.1.

**SCF 2025 (BCD-04, BCD-05, BCD-06)**
14. Conduct tests and exercises to evaluate contingency plan effectiveness per BCD-04.
15. Conduct root cause analysis and lessons learned after each activation per BCD-05.
16. Update contingency plans due to changes per BCD-06.

**NIS2 Article 21 (Article 21(2) (c))**
17. Confirm BCP and DR planning supports business continuity and crisis management per Article 21(2)(c).

**ISO 45001:2018 (8.2)**
18. Establish, implement, and maintain emergency preparedness and response processes per 8.2.

### Availability and Processing Integrity Monitoring Procedure

**Applies to all frameworks**
1. Review availability monitoring report for the period; confirm uptime met SLA.
2. Investigate and document any availability incidents.
3. Review processing integrity check results; confirm errors were detected and flagged.
4. Confirm incidents triggered the incident response process.
5. Document SLA performance and retain as evidence.
