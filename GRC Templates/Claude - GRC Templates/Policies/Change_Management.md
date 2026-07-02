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

**SCF 2025 (CHG-01, CHG-02, CHG-03)**

**NIS2 Article 21 (Article 21(2) (e))**

**ISO 9001:2015 (6.3, 8.5.6)**

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

**SCF 2025 (CHG-04, CHG-05)**

**NIS2 Article 21 (Article 21(2) (e))**

**ISO 9001:2015 (8.5.1, 8.5.2)**

### Rollback and Documentation

**Applies to all frameworks**
- A rollback plan must be defined for all significant changes.
- All changes must be documented including description, approvals, test results, and outcome.
- Change records must be retained per the organization's records retention requirements.

**NIST 800-53 Rev5 (CM-2, CM-6, CM-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (4.1)**

**SCF 2025 (CHG-06, CHG-07)**

**ISO 9001:2015 (7.5.1, 7.5.2, 7.5.3.1, 7.5.3.2)**

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
6. Perform security testing for changes affecting CDE systems or cardholder data flows.
7. Confirm rollback plan is validated before deployment.
8. Assess security impact of the change prior to approval.
9. Confirm change is tracked and status is visible to relevant stakeholders.

**PCI DSS v4.0.1 (6.5.1, 6.5.2, 6.5.3, 6.5.4, 6.5.5, 6.5.6)**
10. Confirm PCI DSS requirements are re-validated on new or changed systems upon completion of significant changes per 6.5.2.
11. Confirm pre-production environment is separated from production per 6.5.3.
12. Confirm live PANs are not used in pre-production per 6.5.5.
13. Confirm test data and test accounts are removed before production deployment per 6.5.6.

**ISO 27001:2022 (6.3)**
14. Confirm changes to the ISMS are carried out in a planned manner per 6.3.

**SOC 2 (TSP 2017) (CC8.1)**
15. Confirm change is authorised, documented, tested, and approved before deployment per CC8.1.

**ISO 27002:2022 (8.32)**
16. Subject all changes to information processing facilities to change management procedures per 8.32.

**NIST 800-53 Rev5 (CM-3, CM-4, CM-5)**
17. Review and approve configuration-controlled changes per CM-3.
18. Analyse changes for security impacts before implementation per CM-4.
19. Enforce access restrictions associated with changes per CM-5.

**CIS Controls v8.1 (4.1, 4.2)**
20. Maintain a documented secure configuration process per 4.1.
21. Maintain a documented secure configuration process for network infrastructure per 4.2.

**SCF 2025 (CHG-01, CHG-02, CHG-03)**
22. Govern technical configuration change control per CHG-02.
23. Analyse proposed changes for security impacts per CHG-03.
24. Verify control functionality following changes per CHG-06.

**NIS2 Article 21 (Article 21(2) (e))**
25. Confirm change management supports security in network and information system maintenance per Article 21(2)(e).

**ISO 45001:2018 (8.1.3)**
26. Control planned changes that impact OH&S performance per 8.1.3.

**ISO 42001:2023 (6.3)**
27. Carry out changes to the AI management system in a planned manner per 6.3.

**ISO 37001:2025 (37001:2025 - 6.3)**
28. Carry out changes to the ABMS in a planned manner per 6.3.

**ISO 9001:2015 (6.3, 8.5.6)**
29. Plan and control changes to the QMS and production processes per 6.3 and 8.5.6.

### Emergency Change

**Applies to all frameworks**
1. Implement with available approvals to address a critical issue.
2. Notify required stakeholders.
3. Complete full documentation and post-implementation review within 48 hours.
4. Confirm post-implementation security review covers any CDE-impacting changes.

**PCI DSS v4.0.1 (6.5.1, 6.5.3)**
5. Confirm post-implementation review covers CDE-impacting changes for PCI DSS compliance per 6.5.1.
6. Confirm documentation is completed within 48 hours of implementation per 6.5.1.

**ISO 27001:2022 (6.3)**
7. Confirm emergency change is documented and the ISMS is updated accordingly per 6.3.

**SOC 2 (TSP 2017) (CC8.1)**
8. Confirm emergency change is authorised and documented per CC8.1.

**ISO 27002:2022 (8.32)**
9. Ensure emergency changes are subject to change management procedures per 8.32.

**NIST 800-53 Rev5 (CM-3)**
10. Apply change control procedures including emergency change protocols per CM-3.

**SCF 2025 (CHG-07)**
11. Govern emergency change procedures per CHG-07.
