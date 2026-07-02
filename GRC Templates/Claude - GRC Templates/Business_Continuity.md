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
- Implement SCF 2025 controls applicable to this area; refer to controls BCD-01, BCD-02, BCD-03 for specific requirements.

**NIS2 Article 21 (Article 21(2) (c))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (c)); measures must be proportionate to the organisation's risk exposure and size.

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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 11.1, 11.2, 11.3, 11.4, 11.5 for specific safeguard requirements.

**SCF 2025 (BCD-04, BCD-05, BCD-06, BCD-07, BCD-08)**
- Implement SCF 2025 controls applicable to this area; refer to controls BCD-04, BCD-05, BCD-06, BCD-07, BCD-08 for specific requirements.

**NIS2 Article 21 (Article 21(2) (c))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (c)); measures must be proportionate to the organisation's risk exposure and size.

### Testing and Maintenance

**Applies to all frameworks**
- Business continuity and disaster recovery plans must be tested at least annually.
- Plans must be reviewed and updated following tests, significant changes, or actual incidents.

**ISO 27002:2022**
- Test results must be documented and gaps addressed through remediation plans.

**NIST 800-53 Rev5 (CP-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (11.3, 11.4)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 11.3, 11.4 for specific safeguard requirements.

**SCF 2025 (BCD-09, BCD-10, BCD-11)**
- Implement SCF 2025 controls applicable to this area; refer to controls BCD-09, BCD-10, BCD-11 for specific requirements.

**NIS2 Article 21 (Article 21(2) (c))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (c)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Backup Execution and Verification

**Applies to all frameworks**
1. Execute backups per the defined schedule.
2. Verify backup job completion and log results.
3. Perform a test restore on a defined periodic basis.
4. Document restore test results and retain records.

**PCI DSS v4.0.1 (12.3.3)**
5. Confirm backup media is stored securely and access is restricted.

**ISO 27002:2022 (8.13)**
5. Confirm at least one backup copy is stored off-site or in a geographically separate location.

**SOC 2 (TSP 2017) (A1.2, A1.3)**
5. Verify restore capability is consistent with defined RTO and RPO commitments.

**NIST 800-53 Rev5 (CP-9)**
8. Document compliance with applicable NIST 800-53 Rev5 controls (CP-9) in the system security plan.

**CIS Controls v8.1 (11.1, 11.2, 11.3)**
9. Document compliance with applicable CIS Controls v8.1 controls (11.1, 11.2, 11.3) per organisational policy.

**SCF 2025 (BCD-04, BCD-05, BCD-06)**
10. Document compliance with applicable SCF 2025 controls (BCD-04, BCD-05, BCD-06) per organisational policy.

**NIS2 Article 21 (Article 21(2) (c))**
11. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (c)) as applicable to the organisation's classification under NIS2.

### Disaster Recovery Invocation

**Applies to all frameworks**
1. Declare a DR event with authorised management approval.
2. Notify the recovery team and activate the DR plan.
3. Execute recovery steps per system-specific runbooks.
4. Restore services and validate integrity before returning to production.
5. Document the incident timeline and conduct a post-recovery review.

**ISO 27002:2022 (5.29)**
6. Confirm information security controls are re-established as part of recovery.

**SOC 2 (TSP 2017) (A1.2, A1.3, CC7.5)**
6. Confirm service restoration meets defined availability and processing integrity commitments.

**NIST 800-53 Rev5 (CP-10)**
8. Document compliance with applicable NIST 800-53 Rev5 controls (CP-10) in the system security plan.

**CIS Controls v8.1 (11.4, 11.5)**
9. Document compliance with applicable CIS Controls v8.1 controls (11.4, 11.5) per organisational policy.

**SCF 2025 (BCD-07, BCD-08)**
10. Document compliance with applicable SCF 2025 controls (BCD-07, BCD-08) per organisational policy.

**NIS2 Article 21 (Article 21(2) (c))**
11. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (c)) as applicable to the organisation's classification under NIS2.

### BCP and DR Plan Review

**Applies to all frameworks**
1. Review BCP and DR plans at least annually and following any significant change or actual incident.
2. Validate that RTO and RPO targets remain consistent with current business requirements.
3. Update contact lists, system inventories, and recovery steps where changes have occurred.
4. Obtain management sign-off on updated plans.
5. Document review completion and retain records.

**ISO 27002:2022**
6. Confirm updates reflect lessons learned from tests and real incidents.

**NIST 800-53 Rev5 (CP-2, CP-4)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (CP-2, CP-4) in the system security plan.

**CIS Controls v8.1 (11.3, 11.4)**
8. Document compliance with applicable CIS Controls v8.1 controls (11.3, 11.4) per organisational policy.

**SCF 2025 (BCD-09, BCD-10, BCD-11)**
9. Document compliance with applicable SCF 2025 controls (BCD-09, BCD-10, BCD-11) per organisational policy.

**NIS2 Article 21 (Article 21(2) (c))**
10. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (c)) as applicable to the organisation's classification under NIS2.
