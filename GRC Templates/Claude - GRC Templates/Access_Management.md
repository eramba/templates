# Access Management

## Purpose
To ensure access to information systems and data is managed through defined organizational roles, enforcing least privilege, segregation of duties, and controlled exceptions.

## Scope
All users, accounts, systems, applications, infrastructure, and data managed or used by the organization.

## Responsibilities

- **Department Managers**: Define organizational roles and required access functions.
- **System Owners**: Define and maintain system roles, ensure alignment with organizational roles and segregation of duties.
- **Human Resources**: Validate organizational roles and employment/contractual status.
- **Information Security**: Support and monitor role and access decisions to ensure security principles are maintained.
- **Users**: Use access appropriately and protect credentials.

## Policy Statements

### Identity Management

**Applies to all frameworks**
- All identities must be uniquely identifiable.
- Human user accounts must be linked to a single individual and must not be shared.

**PCI DSS v4.0.1 (7.2.1, 7.2.2, 7.2.3)**
- Default or built-in accounts must be disabled or restricted where technically feasible.

**ISO 27002:2022 (5.15, 5.16)**
- Account lifecycle events must align with employment or contractual status.
- Organizational roles must be defined, documented, and mapped to system roles.

**SOC 2 (TSP 2017) (CC6.1, CC6.2)**
- Segregation of duties must be enforced through role design.

**NIST 800-53 Rev5 (AC-2, IA-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (5.1, 5.2, 5.3)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 5.1, 5.2, 5.3 for specific safeguard requirements.

**SCF 2025 (IAC-01, IAC-02, IAC-04, IAC-07, IAC-08)**
- Implement SCF 2025 controls applicable to this area; refer to controls IAC-01, IAC-02, IAC-04, IAC-07, IAC-08 for specific requirements.

**NIS2 Article 21 (Article 21(2) (i), Article 21(2) (j))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (i), Article 21(2) (j)); measures must be proportionate to the organisation's risk exposure and size.

### Authentication

**Applies to all frameworks**
- Authentication must use organization-approved mechanisms integrated with a central identity source where feasible.
- Multi-factor authentication must be enforced where technically feasible.

**PCI DSS v4.0.1 (8.2.1, 8.2.2, 8.3.1, 8.3.5, 8.3.6, 8.3.9)**
- Authentication secrets must be generated using approved computerized methods and stored in an approved secrets management system.
- Where password-based authentication is used: minimum 12 characters, mix of letters and at least one number or symbol, last 10 passwords must not be reused, common or breached passwords must be blocked.
- Brute-force protection must be implemented: account lockout, progressive delays, or rate limiting.
- Initial credentials must be temporary and changed at first use.

**ISO 27002:2022 (8.5)**
- Authentication mechanisms must be aligned with the information security risk of the system being accessed.

**NIST 800-53 Rev5 (IA-2, IA-5, IA-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (5.2, 6.3, 6.4, 6.5)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 5.2, 6.3, 6.4, 6.5 for specific safeguard requirements.

**SCF 2025 (IAC-09, IAC-10, IAC-11, IAC-12, IAC-13, IAC-14, IAC-15)**
- Implement SCF 2025 controls applicable to this area; refer to controls IAC-09, IAC-10, IAC-11, IAC-12, IAC-13, IAC-14, IAC-15 for specific requirements.

**NIS2 Article 21 (Article 21(2) (j))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (j)); measures must be proportionate to the organisation's risk exposure and size.

### Authorization and Access Rights

**Applies to all frameworks**
- Access must be granted based on the principle of least privilege.
- Privileged access must be restricted and separated from standard user access.
- Access rights must be removed promptly when no longer required.

**PCI DSS v4.0.1 (7.2.4, 7.2.5, 7.2.6, 7.3.1, 7.3.2, 7.3.3)**
- Access must be granted to roles rather than individuals where technically feasible.
- All authorization changes must be logged and auditable.

**ISO 27002:2022 (5.18, 8.2, 8.3)**
- Temporary or non-standard access must be managed as a time-bound exception with justification, approval, and expiration.

**SOC 2 (TSP 2017) (CC6.2, CC6.3)**
- Access provisioning and deprovisioning must follow a documented and consistently applied process.

**NIST 800-53 Rev5 (AC-3, AC-5, AC-6)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (5.4, 5.5, 6.1, 6.2, 6.6, 6.7, 6.8)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 5.4, 5.5, 6.1, 6.2, 6.6, 6.7, 6.8 for specific safeguard requirements.

**SCF 2025 (IAC-03, IAC-05, IAC-06, IAC-16, IAC-17, IAC-18)**
- Implement SCF 2025 controls applicable to this area; refer to controls IAC-03, IAC-05, IAC-06, IAC-16, IAC-17, IAC-18 for specific requirements.

**NIS2 Article 21 (Article 21(2) (i))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (i)); measures must be proportionate to the organisation's risk exposure and size.

### Periodic Access Review

**Applies to all frameworks**
- Access rights must be reviewed at defined intervals and upon changes in role or employment status.
- All unnecessary, excessive, or unused accounts must be disabled or removed following review.
- Review results must be documented and retained.

**PCI DSS v4.0.1 (8.2.4, 8.2.5, 8.2.6)**
- Inactive accounts must be removed or disabled after a maximum of 90 days of inactivity.

**NIST 800-53 Rev5 (AC-2)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (5.1, 5.3)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 5.1, 5.3 for specific safeguard requirements.

**SCF 2025 (IAC-02, IAC-07, IAC-20)**
- Implement SCF 2025 controls applicable to this area; refer to controls IAC-02, IAC-07, IAC-20 for specific requirements.

**NIS2 Article 21 (Article 21(2) (i))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (i)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Account Provisioning

**Applies to all frameworks**
1. Submit access request identifying the user and their organisational role.
2. Validate the role and required system access against defined role profiles.
3. Provision access by assigning the corresponding system roles.
4. Document and log provisioning actions.

**PCI DSS v4.0.1 (7.2.1, 7.2.2, 7.2.5)**
5. Confirm the request includes explicit approval from the user's manager.
6. Verify least privilege is applied; no access beyond what the role requires.

**ISO 27002:2022 (5.18)**
5. Record the access grant in the access rights register.

**SOC 2 (TSP 2017) (CC6.2)**
5. Confirm provisioning is consistent with the documented provisioning process.

**NIST 800-53 Rev5 (AC-2, IA-4)**
9. Document compliance with applicable NIST 800-53 Rev5 controls (AC-2, IA-4) in the system security plan.

**CIS Controls v8.1 (5.1, 5.4, 6.1)**
10. Document compliance with applicable CIS Controls v8.1 controls (5.1, 5.4, 6.1) per organisational policy.

**SCF 2025 (IAC-01, IAC-02, IAC-04)**
11. Document compliance with applicable SCF 2025 controls (IAC-01, IAC-02, IAC-04) per organisational policy.

**NIS2 Article 21 (Article 21(2) (i))**
12. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (i)) as applicable to the organisation's classification under NIS2.

### Exception-Based Access

**Applies to all frameworks**
1. Submit exception request with business justification and defined expiration date.
2. Obtain approval from user's manager, system owner, and Information Security.
3. Implement and track approved exceptions.
4. Revoke access upon expiration.

**PCI DSS v4.0.1 (7.2.6, 7.3.2)**
5. Log the exception in the privileged access exception register.

**NIST 800-53 Rev5 (AC-6)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (AC-6) in the system security plan.

**CIS Controls v8.1 (6.8)**
7. Document compliance with applicable CIS Controls v8.1 controls (6.8) per organisational policy.

**SCF 2025 (IAC-06, IAC-18)**
8. Document compliance with applicable SCF 2025 controls (IAC-06, IAC-18) per organisational policy.

**NIS2 Article 21 (Article 21(2) (i))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (i)) as applicable to the organisation's classification under NIS2.

### Account Deprovisioning

**Applies to all frameworks**
1. Revoke all system access on the last day of employment or contractual engagement.
2. Disable or remove all associated accounts.
3. Document and log deprovisioning actions.

**PCI DSS v4.0.1 (8.2.5, 8.2.6)**
4. Confirm removal of all CDE system access within defined timeframe.

**SOC 2 (TSP 2017) (CC6.2, CC6.3)**
4. Confirm deprovisioning is recorded in the access management system.

**NIST 800-53 Rev5 (AC-2, PS-4)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (AC-2, PS-4) in the system security plan.

**CIS Controls v8.1 (5.3, 6.2)**
7. Document compliance with applicable CIS Controls v8.1 controls (5.3, 6.2) per organisational policy.

**SCF 2025 (IAC-07, IAC-08)**
8. Document compliance with applicable SCF 2025 controls (IAC-07, IAC-08) per organisational policy.

**NIS2 Article 21 (Article 21(2) (i))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (i)) as applicable to the organisation's classification under NIS2.

### Access Review

**Applies to all frameworks**
1. Schedule periodic reviews per defined frequency.
2. Review all active accounts and access rights against current role requirements.
3. Revoke or adjust access where discrepancies are found.
4. Document review outcomes and retain records.

**PCI DSS v4.0.1 (8.2.4)**
5. Review privileged and service accounts separately; confirm each has a documented business justification.
6. Confirm inactive accounts have been disabled per the 90-day inactivity requirement.

**NIST 800-53 Rev5 (AC-2)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (AC-2) in the system security plan.

**CIS Controls v8.1 (5.1, 5.3)**
8. Document compliance with applicable CIS Controls v8.1 controls (5.1, 5.3) per organisational policy.

**SCF 2025 (IAC-02, IAC-20)**
9. Document compliance with applicable SCF 2025 controls (IAC-02, IAC-20) per organisational policy.

**NIS2 Article 21 (Article 21(2) (i))**
10. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (i)) as applicable to the organisation's classification under NIS2.
