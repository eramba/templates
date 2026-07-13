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

**CIS Controls v8.1 (5.6)**
- Centralise account management through a directory or identity service per 5.6.

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

**PCI DSS v4.0.1 (8.3.2, 8.3.3, 8.3.4, 8.3.7, 8.3.8, 8.3.11, 8.4.1, 8.4.3, 8.4.4, 8.5.1, 8.6.1, 8.6.2, 8.6.3)**
- Use strong cryptography to render all authentication factors unreadable during transmission and storage per 8.3.2.
- Verify user identity before modifying any authentication factor per 8.3.3.
- Lock out user IDs after no more than 10 invalid authentication attempts for a minimum of 30 minutes per 8.3.4.
- Prevent individuals from submitting a new password that is the same as any of the last four used per 8.3.7.
- Document and communicate authentication policies and procedures to all users per 8.3.8.
- Assign physical and logical security tokens, smart cards, and certificates to an individual user and not shared per 8.3.11.
- Implement MFA for all non-console access into the CDE for personnel with administrative access per 8.4.1.
- Implement MFA for all remote network access originating from outside the entity's network that could access or impact the CDE per 8.4.3.
- Implement MFA for all non-console access into the CDE per 8.4.4.
- Implement MFA systems that are not susceptible to replay attacks and cannot be bypassed per 8.5.1.
- Manage application and system accounts that can be used for interactive login with controls preventing misuse per 8.6.1.
- Ensure passwords for application and system accounts are not hard-coded in scripts or configuration files per 8.6.2.
- Protect passwords for application and system accounts against misuse with periodic changes and access controls per 8.6.3.

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

**PCI DSS v4.0.1 (7.2.5.1)**
- Review all access by application and system accounts and related privileges periodically per 7.2.5.1.

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

**ISO 27002:2022 (5.16, 5.18)**
12. Record the access grant in the access rights register covering the full identity lifecycle per 5.16 and 5.18.

**NIST 800-53 Rev5 (AC-2, IA-4)**
13. Define account type and apply least privilege at point of provisioning per AC-2 and IA-4.

**CIS Controls v8.1 (5.1, 6.1)**
14. Add account to the account inventory immediately upon creation per 5.1.
15. Confirm access granting follows the documented process per 6.1.

**SCF 2025 (IAC-07, IAC-15)**
16. Confirm provisioning follows the formal user registration process per IAC-07 and IAC-15.

**NIS2 Article 21 (Article 21(2) (i))**
17. Confirm provisioning supports human resources security and access control proportionate to NIS2 classification per Article 21(2)(i).

**ISO 27701:2025 (A.3.8, A.3.9)**
18. Confirm identities for PII-processing systems are provisioned per the identity lifecycle process per A.3.8 and A.3.9.

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

**ISO 27002:2022 (5.15, 5.18)**
9. Confirm exception access is time-bounded and subject to the access control policy per 5.15 and 5.18.

**NIST 800-53 Rev5 (AC-2, AC-6)**
10. Confirm exception is consistent with least privilege and documented per AC-2 and AC-6.

**CIS Controls v8.1 (6.8)**
11. Confirm exception is reflected in the RBAC documentation per 6.8.

**SCF 2025 (IAC-20, IAC-21)**
12. Confirm exception is authorised under the access enforcement framework per IAC-20 and IAC-21.

**NIS2 Article 21 (Article 21(2) (i))**
13. Confirm exception access controls remain proportionate per Article 21(2)(i).

**ISO 27701:2025 (A.3.9)**
14. Confirm access exception related to PII processing is approved and time-limited per A.3.9.

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

**ISO 27002:2022 (5.16, 5.18)**
9. Confirm identity is deprovisioned across all systems per the identity lifecycle policy per 5.16 and 5.18.

**NIST 800-53 Rev5 (AC-2, PS-4)**
10. Disable account within the defined time period upon termination per AC-2 and PS-4.

**CIS Controls v8.1 (5.3, 6.2)**
11. Confirm dormant account is disabled within 45 days and access revoking process is followed per 5.3 and 6.2.

**SCF 2025 (IAC-07, HRS-09)**
12. Confirm deprovisioning follows the formal user de-registration process per IAC-07 and HRS-09.

**NIS2 Article 21 (Article 21(2) (i))**
13. Confirm deprovisioning supports access control requirements per Article 21(2)(i).

**ISO 27701:2025 (A.3.8, A.3.9)**
14. Confirm expired user IDs are not reissued for PII-processing systems per A.3.8.

**PCI DSS v4.0.1 (8.2.7, 8.2.8)**
- Manage third-party remote access accounts so they are enabled only during the time period needed per 8.2.7.
- Re-authenticate users after sessions have been idle for more than 15 minutes per 8.2.8.

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

**ISO 27002:2022 (5.15, 5.18, 8.2, 8.3)**
11. Confirm access rights are reviewed and removed per the access control policy per 5.18.
12. Confirm privileged access rights are restricted and reviewed per 8.2.

**NIST 800-53 Rev5 (AC-2, AC-6)**
13. Confirm review covers all account types including service accounts per AC-2 and AC-6.

**CIS Controls v8.1 (5.1, 5.3, 6.8)**
14. Confirm dormant accounts have been identified and disabled per 5.3.
15. Confirm RBAC definitions reflect reviewed access rights per 6.8.

**SCF 2025 (IAC-17, IAC-16)**
16. Confirm periodic review of privileges is completed per IAC-17 and IAC-16.

**NIS2 Article 21 (Article 21(2) (i))**
17. Confirm access review supports human resources security and access control per Article 21(2)(i).

**ISO 27701:2025 (A.3.9)**
18. Confirm access rights to PII and associated assets are reviewed per A.3.9.

### Privileged Utility Programs

**Applies to all frameworks**
- The use of utility programs capable of overriding system and application controls shall be restricted, tightly controlled, and logged.
- Privileged utility programs shall only be available to authorised personnel and used in accordance with an approved request.

**ISO 27002:2022 (8.18)**
- The use of utility programs that can override system and application controls shall be restricted to the minimum number of trusted, authorised users.
- Use of privileged utility programs shall require specific authorisation, shall be time-limited, shall be logged, and shall be reviewed.

### Privileged Utility Program Access Review Procedure

**Applies to all frameworks**
1. Confirm the inventory of privileged utility programs is current.
2. Review who has access to each tool and confirm access is still required.
3. Review logs of privileged utility program use during the period.
4. Confirm each use was authorised and documented.
5. Revoke access where it is no longer required.

**ISO 27002:2022 (8.18)**
6. Confirm privileged utility programs are not accessible to general users and require explicit authorisation for each use.

### Authentication Information Management

**Applies to all frameworks**
- Allocation and management of authentication information shall be controlled through a formal management process.
- Personnel shall be advised on the appropriate handling of authentication information including prohibition on sharing credentials.

**ISO 27002:2022 (5.17)**
- Authentication information including passwords, tokens, and certificates shall be managed through a formal process covering initial allocation, periodic change, reset, revocation, and prohibition on sharing per 5.17.
- Default authentication information shall be changed immediately upon first use.
- Authentication information shall be kept confidential and not recorded in an unprotected form.

**ISO 27701:2025 (A.3.23)**
- Implement secure authentication technologies and procedures for systems processing PII based on information access restrictions per A.3.23.

### Authentication Information Review Procedure

**ISO 27002:2022 (5.17)**
1. Confirm a process exists for allocating, resetting, and revoking authentication information.
2. Confirm default credentials are changed upon first use across all systems.
3. Confirm password and credential policies enforce minimum complexity and rotation requirements.
4. Confirm personnel have been trained on appropriate handling of authentication information.
5. Review any incidents involving credential compromise or sharing during the period.
