# Backup & Recovery

## Purpose
To ensure that organizational data and systems are backed up, protected, and recoverable in order to support business continuity, incident response, and legal or regulatory requirements.

## Scope
This document applies to all systems, applications, infrastructure, and data managed by the organization, including on-premise, cloud-based, and third-party hosted services where backups are required.

## Responsibilities
- **System Owners** are responsible for ensuring backups are configured and aligned with this policy for systems they own.
- **Department Managers** support identification of backup requirements based on business needs.
- **The Information Security function** is responsible for defining backup security requirements and supporting their implementation, with the assistance of Department Managers.
- **IT / Technical Teams** are responsible for implementing, maintaining, and testing backup and recovery processes.

## Policy Statements
- Backups must be performed to ensure availability and recoverability of systems and data.
- Backup data must be protected against unauthorized access, loss, or corruption.
- Backup retention must align with data classification and retention requirements.
- Backup and recovery practices must support legal, regulatory, and business requirements.
- Backup effectiveness must be verified through restore testing.

## Standards

### Backup Scope and Frequency
- Backup scope and frequency must be defined based on the system and data criticality.
- Systems processing or storing critical data must be included in backup processes.
- Backup schedules must be documented.

### Backup Retention
- Backup retention periods must be defined and managed in accordance with the **Data Management Policy**.
- Retention periods may vary by system or data type.
- Backups required for investigations or legal purposes must be retained until no longer required.

### Backup Encryption
- Backup data must be encrypted at rest and in transit.
- Encryption requirements and approved mechanisms must follow the **Cryptography Policy**.
- Encryption keys must be protected and managed in accordance with cryptographic requirements.

### Backup Storage and Protection
- Backup data must be stored in secure and approved locations.
- Access to backup systems and data must be restricted based on least privilege.
- Backup data must be protected against unauthorized modification or deletion.

### Backup Restore and Testing
- Restore procedures must be defined and documented.
- Backup restore tests must be performed periodically to verify data recoverability.
- Restore test results must be documented and issues remediated.

## Procedures

### Backup Configuration Procedure
1. Systems and data requiring backup are identified.
2. Backup scope, frequency, and retention are defined.
3. Backup jobs are configured and enabled.
4. Backup status is monitored.

### Backup Restore Procedure
1. Restore request is initiated.
2. Appropriate approvals are obtained where required.
3. Data or systems are restored.
4. Restore results are validated and documented.

### Backup Testing Procedure
1. Restore tests are planned.
2. Test restores are performed.
3. Results are reviewed.
4. Improvements are implemented where required.
