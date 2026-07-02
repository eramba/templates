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

**SCF 2025 (IAC-01, IAC-02, IAC-04, IAC-07, IAC-08)**

**NIS2 Article 21 (Article 21(2) (i), Article 21(2) (j))**

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

**SCF 2025 (IAC-09, IAC-10, IAC-11, IAC-12, IAC-13, IAC-14, IAC-15)**

**NIS2 Article 21 (Article 21(2) (j))**

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

**SCF 2025 (IAC-03, IAC-05, IAC-06, IAC-16, IAC-17, IAC-18)**

**NIS2 Article 21 (Article 21(2) (i))**

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

**SCF 2025 (IAC-02, IAC-07, IAC-20)**

**NIS2 Article 21 (Article 21(2) (i))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Account Provisioning

**Applies to all frameworks**
1. Submit access request identifying the user and their organisational role.
2. Validate the role and required system access against defined role profiles.
3. Provision access by assigning the corresponding system roles.
4. Document and log provisioning actions.
5. Confirm the request includes explicit approval from the user's manager.
6. Verify least privilege is applied; no access beyond what the role requires.
7. Record the access grant in the access rights register.
8. Confirm provisioning is consistent with the documented provisioning process.

**PCI DSS v4.0.1 (7.2.1, 7.2.2, 7.2.3, 7.2.5, 8.2.1, 8.2.4)**
9. Confirm request includes explicit written approval from the user's manager per 7.2.3.
10. Verify least-privilege assignment — access must match the documented role profile with no excess permissions.

**SOC 2 (TSP 2017) (CC6.1, CC6.2)**
11. Confirm the provisioned credentials are registered in the identity inventory per CC6.1.

### Exception-Based Access

**Applies to all frameworks**
1. Submit exception request with business justification and defined expiration date.
2. Obtain approval from user's manager, system owner, and Information Security.
3. Implement and track approved exceptions.
4. Revoke access upon expiration.
5. Log the exception in the privileged access exception register.

**PCI DSS v4.0.1 (7.2.6, 8.2.2)**
6. Confirm exception is time-bounded; document the specific exceptional circumstance per 8.2.2.
7. Log the exception in the privileged-access exception register.

**SOC 2 (TSP 2017) (CC6.3)**
8. Confirm the exception is recorded in the access management system per CC6.3.

### Account Deprovisioning

**Applies to all frameworks**
1. Revoke all system access on the last day of employment or contractual engagement.
2. Disable or remove all associated accounts.
3. Document and log deprovisioning actions.
4. Confirm removal of all CDE system access within defined timeframe.
5. Confirm deprovisioning is recorded in the access management system.

**PCI DSS v4.0.1 (8.2.5, 8.2.6)**
6. Confirm all CDE system access is revoked on or before the last working day per 8.2.5.
7. Verify inactive accounts from prior periods have been disabled within 90 days per 8.2.6.

**SOC 2 (TSP 2017) (CC6.2, CC6.3)**
8. Confirm deprovisioning is logged in the access management system per CC6.2 and CC6.3.

### Access Review

**Applies to all frameworks**
1. Schedule periodic reviews per defined frequency.
2. Review all active accounts and access rights against current role requirements.
3. Revoke or adjust access where discrepancies are found.
4. Document review outcomes and retain records.
5. Review privileged and service accounts separately; confirm each has a documented business justification.
6. Confirm inactive accounts have been disabled per the 90-day inactivity requirement.

**PCI DSS v4.0.1 (7.2.4, 8.2.6)**
7. Review privileged and service accounts separately; confirm each has documented business justification per 7.2.4.
8. Confirm all accounts inactive for more than 90 days have been disabled per 8.2.6.

**SOC 2 (TSP 2017) (CC6.1, CC6.2, CC6.3)**
9. Confirm access rights reviewed align with current job function per CC6.3.
10. Document management acknowledgement of review results per CC6.1.
