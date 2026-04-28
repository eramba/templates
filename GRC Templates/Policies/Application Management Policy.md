# Application Technical Standards

## Purpose
To define minimum technical security and operational standards for applications used or managed by the organization, ensuring they are licensed, maintained, accessed, monitored, backed up, and retired in a controlled and secure manner.

## Scope
This document applies to all applications used, hosted, developed, or managed by the organization, including on-premise, cloud-based, and third-party (SaaS) applications.

## Responsibilities
- **System Owners** are responsible for ensuring applications comply with these standards.
- **Department Managers** are responsible for approving business use of applications.
- **The Information Security function** is responsible for defining application security requirements and supporting their implementation, with the assistance of Department Managers.
- **IT / Application Administrators** are responsible for implementing and maintaining technical controls.

## Policy Statements
- Applications must be approved, owned, and managed in a controlled manner.
- Applications must comply with licensing, security, and operational requirements.
- Application access must be managed in accordance with the **Access Management Policy**.
- Application patching and vulnerability handling must follow the **Patching & Vulnerability Management Policy**.
- Application logging and monitoring must follow the **Logging & Monitoring Policy**.
- Applications must be backed up to support system recovery.
- Applications must be formally decommissioned when no longer required.
- Application management practices must support legal, regulatory, and contractual obligations.

## Standards

### Licensing
- Applications must be properly licensed in accordance with vendor and contractual requirements.
- Use of unlicensed or unauthorized software is not permitted.
- License compliance must be reviewed periodically where applicable.

### Deployment and Configuration
- Applications must be deployed using controlled and repeatable processes.
- Secure baseline configurations must be applied.
- Separation must exist between development, testing, and production environments where applicable.
- Changes to applications must follow standard organizational processes.

### Access Management
- Application access must be implemented and enforced in accordance with the **Access Management Policy**.
- Applications must support central authentication and role-based access control where technically feasible.

### Patching and Vulnerability Management
- Application patching, updates, and vulnerability remediation must be performed in accordance with the **Patching & Vulnerability Management Policy**.
- Exceptions or delays must follow the processes defined in that policy.

### Logging and Monitoring
- Application logging and monitoring must be implemented in accordance with the **Logging & Monitoring Policy**.
- Applications must generate security-relevant and operational logs where technically feasible.

### Application Backup (System-Level)
- Applications must be backed up at least **weekly** to support recovery of the application system.
- Application backups must include configuration, binaries, and system components required to restore the application.
- Application backups must be retained for a minimum of **30 days**.
- Application backup encryption and protection must follow the **Backup & Recovery Policy** and **Cryptography Policy**.
- These requirements apply to the **application system itself** and do not replace data backup requirements defined elsewhere.

### Monitoring and Capacity
- Applications must be monitored to ensure availability and performance.
- Capacity and resource usage must be monitored to prevent service degradation.
- Alerts must be defined for abnormal behavior or resource exhaustion where supported.

### Decommissioning
- Applications must be formally decommissioned when no longer required.
- Access must be revoked prior to decommissioning.
- Application-related data must be handled in accordance with the **Data Management Policy**.
- Decommissioning activities must be documented.

## Procedures

### Application Onboarding Procedure
1. Business need and ownership are identified.
2. Licensing and policy requirements are validated.
3. Access, backup, logging, and configuration standards are applied.
4. Application is approved for use.

### Application Decommissioning Procedure
1. Decommissioning is approved.
2. Access is revoked.
3. Application system backups are handled according to retention requirements.
4. Application is retired and documented.
