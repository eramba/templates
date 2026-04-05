# Software Development Security

## Purpose
To ensure that software developed, maintained, or integrated by the organization is designed, built, tested, and deployed securely, reducing the risk of vulnerabilities, data breaches, and operational disruption.

## Scope
This document applies to all software development activities, including in-house development, outsourced development, use of third-party libraries, and deployment of applications and services.

## Responsibilities
- **Development Teams** are responsible for implementing secure development practices.
- **Department Managers / Product Owners** are responsible for ensuring development activities follow this policy.
- **The Information Security function** is responsible for defining secure development requirements and supporting their implementation, with the assistance of Department Managers.
- **Third Parties** involved in development must comply with applicable security requirements.

## Policy Statements
- Security must be considered throughout the software development lifecycle.
- Software must be developed, tested, and deployed in a controlled and secure manner.
- Third-party components and outsourced development must be managed to reduce security risk.

## Standards

### Security by Design
- Security requirements must be considered during design and planning phases.
- Applications must follow secure architecture principles, including least privilege and separation of environments.
- Data protection and privacy requirements must be incorporated into design decisions.

### Secure Coding Practices
- Developers must follow secure coding guidelines appropriate to the technologies used.
- Common vulnerabilities (e.g. injection, authentication flaws, insecure deserialization) must be addressed during development.
- Secrets, credentials, and keys must not be hard-coded in source code.

### Third-Party Libraries
- Third-party libraries, frameworks, and dependencies must be approved before use.
- Dependencies must be obtained from trusted sources.
- Known vulnerable or unsupported libraries must not be used.
- Third-party components must be kept up to date where feasible.

### Security Testing
- Security testing must be performed as part of the development lifecycle.
- Testing may include static analysis, dependency scanning, and other automated or manual techniques.
- Identified security issues must be tracked and remediated based on risk.

### Code Repository Management
- Source code must be stored in organization-approved code repositories.
- Access to code repositories must be restricted based on role and least privilege.
- Changes to code must be traceable and auditable.
- Code repositories must be protected against unauthorized access and modification.

### Deployment Controls
- Deployment to production must follow defined and controlled processes.
- Separation must exist between development, testing, and production environments.
- Only authorized personnel or automated processes may deploy code to production.
- Deployment activities must be logged where technically feasible.

### Outsourcing
- Outsourced development activities must be approved by management.
- Security requirements must be included in contracts with development vendors.
- Outsourced code must be subject to the same security controls and reviews as internally developed code.
- The organization retains responsibility for the security of outsourced software.

## Procedures

### Secure Development Lifecycle Procedure
1. Security requirements are identified during planning.
2. Secure design principles are applied.
3. Code is developed following secure coding practices.
4. Security testing is performed.
5. Issues are remediated prior to deployment.
6. Software is deployed using controlled deployment processes.

### Third-Party and Outsourcing Review Procedure
1. Third-party libraries or vendors are identified.
2. Security risks are assessed.
3. Approval is obtained where required.
4. Ongoing usage is monitored and reviewed.
