# Access Management

## Purpose
To ensure that access to information systems and data is managed through defined organizational roles and corresponding system roles, enforcing least privilege, segregation of duties, automation, and controlled exceptions.

## Scope
This document applies to all users, accounts, systems, applications, infrastructure, and data managed or used by the organization.

## Responsibilities
- **Department Managers** are responsible for defining organizational (departmental) roles and their required functions, with support from Human Resources where applicable.
- **System Owners** are responsible for defining and maintaining system roles for the systems they own, ensuring system roles align with organizational roles and that segregation of duties is enforced.
- **Human Resources** supports the validation of organizational roles and employment or contractual status where applicable.
- **The Information Security function** is responsible for supporting and monitoring role and access decisions to ensure security principles and segregation of duties are maintained.
- **Users** are responsible for using their access appropriately and protecting their credentials.

## Policy Statements
- Access to systems and data must be based on the user’s organizational role.
- Systems must implement role-based access control aligned with organizational roles.
- Access provisioning must be automated where technically feasible and driven by approved roles.
- Individual access outside predefined roles is not permitted as standard practice.
- Deviations from role-based access must be explicitly approved as exceptions.
- All access must be traceable and auditable.

## Standards

### Identity
- All identities must be uniquely identifiable.
- Human user accounts must be linked to a single individual and must not be shared.
- Account lifecycle events must align with employment or contractual status.
- Organizational (departmental) roles must be defined and documented.
- Each system must define system roles that map directly to organizational roles.
- System roles must be defined or modified collaboratively by the system owner, department manager, Human Resources (where applicable), and the Information Security function.
- Segregation of duties must be enforced through role design.
- Default or built-in accounts (e.g. *root*, *Administrator*) must be disabled or restricted where technically feasible.

### Authentication
- Authentication must be performed using organization-approved mechanisms.
- Authentication must be integrated with a central identity source where technically feasible.
- All authentication secrets, including passwords, keys, tokens, and credentials, must be generated using approved computerized methods where technically feasible.
- Human-generated passwords or secrets must be avoided where technically feasible.
- Authentication secrets must be stored in an organization-approved password or secret management system.
- Credentials must be protected and must not be shared.
- **Where password-based authentication is used, systems must enforce the following password policy where technically feasible:**
  - Minimum length of **12 characters**
  - Passwords must include a mix of letters and at least one number or symbol
  - Common, breached, or easily guessable passwords must be blocked where supported
  - Password reuse must be prevented for at least the **last 10 passwords**
- **Systems must implement brute-force protection where technically feasible**, including one or more of the following:
  - Account lockout after repeated failed attempts
  - Progressive authentication delays
  - Rate limiting
- Multi-factor authentication must be enforced where technically feasible.
- Initial credentials must be temporary and changed at first use where applicable.
- Interactive login using highly privileged or technical accounts must be disabled where supported.
- Authentication events must be logged in accordance with the Logging & Monitoring requirements.

### Authorization
- Authorization must be based on predefined system roles.
- Access must be granted to roles rather than directly to individuals where technically feasible.
- Privileged access must be restricted and separated from standard user access.
- Use of highly privileged accounts (e.g. *root*, *Administrator*) must be avoided where possible and replaced with role-based privileged access.
- Where privileged accounts cannot be disabled, their use must be restricted, monitored, and logged.
- Temporary or non-standard access must be managed as a time-bound exception.
- Exceptions must include justification, approval, and an expiration date.
- Access rights must be removed promptly when no longer required.
- Authorization changes must be logged and auditable.

## Procedures

### System Role Definition Procedure
1. Organizational (departmental) roles and required business functions are identified.
2. Corresponding system roles are designed to support those functions.
3. Proposed system roles are reviewed jointly by the system owner, department manager, Human Resources (where applicable), and Information Security.
4. Segregation of duties and least privilege are validated.
5. System roles are approved in writing by all required parties.
6. Approved roles are created or modified in the system and documented.

### Account Provisioning Procedure
1. A request is submitted to provision access for a user.
2. The user’s organizational role is identified and validated.
3. Access is provisioned automatically by assigning the corresponding system roles.
4. Provisioning actions are documented and logged.

### Exception-Based Access Procedure
1. If access outside predefined system roles is required, an exception request is submitted.
2. The exception request must include justification and a defined expiration date.
3. The exception must be approved by the user’s manager, the system owner, and the Information Security function.
4. Approved exceptions are implemented and tracked.
5. Access is revoked automatically or manually upon exception expiration.

### Account Deprovisioning Procedure
1. User access is revoked on the last day of employment or contractual engagement.
2. Deprovisioning occurs automatically or manually and does not require approval.
3. All system access associated with the user is disabled or removed.
4. Deprovisioning actions are documented and logged.
