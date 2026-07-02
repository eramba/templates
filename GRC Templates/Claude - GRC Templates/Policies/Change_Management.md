# Change Management

## Purpose
To ensure changes to information systems are planned, authorized, tested, and documented to minimize security and operational risk.

## Scope
All changes to production systems, applications, infrastructure, and configurations.

## Responsibilities

- **Change Requester**: Submit and justify change requests.
- **Change Approver**: Review and authorize changes before implementation.
- **IT Operations / Development**: Implement approved changes.
- **Information Security**: Review security implications of significant changes.

## Policy Statements

### Change Request and Authorization

**Applies to all frameworks**
- All changes to production systems must be submitted via a formal change request.
- Changes must be authorized by an appropriate approver before implementation.

**PCI DSS v4.0.1 (6.5.1, 6.5.2, 6.5.3)**
- Emergency changes must follow an expedited approval process and be reviewed post-implementation.
- Roles and responsibilities for change management must be defined and documented.

**ISO 27002:2022 (8.32)**
- The potential security impact of proposed changes must be assessed prior to approval.

**SOC 2 (TSP 2017) (CC8.1)**
- Changes must be tracked and status must be visible to relevant stakeholders.

**NIST 800-53 Rev5 (CM-3, CM-5)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (4.1, 4.2, 4.3, 16.1, 16.2)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 4.1, 4.2, 4.3, 16.1, 16.2 for specific safeguard requirements.

**SCF 2025 (CHG-01, CHG-02, CHG-03)**
- Implement SCF 2025 controls applicable to this area; refer to controls CHG-01, CHG-02, CHG-03 for specific requirements.

**NIS2 Article 21 (Article 21(2) (e))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (e)); measures must be proportionate to the organisation's risk exposure and size.

**ISO 9001:2015 (6.3, 8.5.6)**
- Comply with ISO 9001:2015 requirements applicable to this area (6.3, 8.5.6).

### Testing and Validation

**Applies to all frameworks**
- Changes must be tested in a non-production environment before deployment where feasible.
- Test results must be documented and approved prior to production deployment.

**PCI DSS v4.0.1 (6.5.4, 6.5.5)**
- Security testing must be performed for changes that affect security controls or cardholder data flows.

**ISO 27002:2022 (8.25, 8.32)**
- Testing must include validation that security requirements are met.

**NIST 800-53 Rev5 (CM-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (4.4, 4.5, 4.6)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 4.4, 4.5, 4.6 for specific safeguard requirements.

**SCF 2025 (CHG-04, CHG-05)**
- Implement SCF 2025 controls applicable to this area; refer to controls CHG-04, CHG-05 for specific requirements.

**NIS2 Article 21 (Article 21(2) (e))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (e)); measures must be proportionate to the organisation's risk exposure and size.

**ISO 9001:2015 (8.5.1, 8.5.2)**
- Comply with ISO 9001:2015 requirements applicable to this area (8.5.1, 8.5.2).

### Rollback and Documentation

**Applies to all frameworks**
- A rollback plan must be defined for all significant changes.
- All changes must be documented including description, approvals, test results, and outcome.
- Change records must be retained per the organization's records retention requirements.

**NIST 800-53 Rev5 (CM-2, CM-6, CM-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (4.1)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 4.1 for specific safeguard requirements.

**SCF 2025 (CHG-06, CHG-07)**
- Implement SCF 2025 controls applicable to this area; refer to controls CHG-06, CHG-07 for specific requirements.

**ISO 9001:2015 (7.5.1, 7.5.2, 7.5.3.1, 7.5.3.2)**
- Comply with ISO 9001:2015 requirements applicable to this area (7.5.1, 7.5.2, 7.5.3.1, 7.5.3.2).

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Standard Change

**Applies to all frameworks**
1. Submit change request with justification, scope, and rollback plan.
2. Obtain required approvals.
3. Test in non-production environment.
4. Deploy during approved maintenance window.
5. Verify success and document outcome.

**PCI DSS v4.0.1 (6.5.1, 6.5.2, 6.5.3, 6.5.4, 6.5.5, 6.5.6)**
6. Perform security testing for changes affecting CDE systems or cardholder data flows.
7. Confirm rollback plan is validated before deployment.

**ISO 27002:2022 (8.32)**
6. Assess security impact of the change prior to approval.

**SOC 2 (TSP 2017) (CC8.1)**
6. Confirm change is tracked and status is visible to relevant stakeholders.

**NIST 800-53 Rev5 (CM-3, CM-4, CM-5, CM-6)**
10. Document compliance with applicable NIST 800-53 Rev5 controls (CM-3, CM-4, CM-5, CM-6) in the system security plan.

**CIS Controls v8.1 (4.1, 4.2, 4.3, 4.4, 4.5, 4.6)**
11. Document compliance with applicable CIS Controls v8.1 controls (4.1, 4.2, 4.3, 4.4, 4.5, 4.6) per organisational policy.

**SCF 2025 (CHG-01, CHG-02, CHG-03, CHG-04, CHG-05)**
12. Document compliance with applicable SCF 2025 controls (CHG-01, CHG-02, CHG-03, CHG-04, CHG-05) per organisational policy.

**NIS2 Article 21 (Article 21(2) (e))**
13. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (e)) as applicable to the organisation's classification under NIS2.

**ISO 9001:2015 (6.3, 8.5.6)**
14. Ensure compliance with applicable ISO 9001:2015 requirements (6.3, 8.5.6).

### Emergency Change

**Applies to all frameworks**
1. Implement with available approvals to address a critical issue.
2. Notify required stakeholders.
3. Complete full documentation and post-implementation review within 48 hours.

**PCI DSS v4.0.1 (6.5.3)**
4. Confirm post-implementation security review covers any CDE-impacting changes.

**NIST 800-53 Rev5 (CM-3)**
5. Document compliance with applicable NIST 800-53 Rev5 controls (CM-3) in the system security plan.

**CIS Controls v8.1 (4.1)**
6. Document compliance with applicable CIS Controls v8.1 controls (4.1) per organisational policy.

**SCF 2025 (CHG-02, CHG-07)**
7. Document compliance with applicable SCF 2025 controls (CHG-02, CHG-07) per organisational policy.

**NIS2 Article 21 (Article 21(2) (e))**
8. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (e)) as applicable to the organisation's classification under NIS2.

**ISO 9001:2015 (6.3)**
9. Ensure compliance with applicable ISO 9001:2015 requirements (6.3).
