# Operating System Security Standards

## Purpose
To define minimum security and operational standards for operating systems used by the organization, ensuring they are configured, maintained, and managed securely to reduce risk to systems, data, and services.

## Scope
This document applies to all operating systems used by the organization, including server and workstation operating systems, whether on-premise, cloud-based, virtualized, or hosted by third parties.

## Responsibilities
- **System Owners** are responsible for ensuring operating systems under their ownership comply with these standards.
- **Department Managers** support implementation and ensure business requirements are met.
- **The Information Security function** is responsible for defining operating system security requirements and supporting their implementation, with the assistance of Department Managers.
- **IT / System Administrators** are responsible for implementing, configuring, and maintaining operating systems in accordance with these standards.

## Policy Statements
- Operating systems must be securely configured, maintained, and supported.
- Only supported and approved operating systems may be used.
- Operating system security controls must be enforced consistently.
- Operating systems must support logging, monitoring, backup, and access control requirements.
- Operating systems must be retired when no longer supported or required.

## Standards

### Approved and Supported Operating Systems
- Only operating systems approved by the organization may be deployed.
- Operating systems must be vendor-supported and within their supported lifecycle.
- Unsupported or end-of-life operating systems must not be used.

### Secure Configuration
- Operating systems must be configured using approved secure baseline configurations.
- Unnecessary services, ports, and components must be disabled or removed.
- Default accounts, credentials, and settings must be secured or disabled where possible.
- Configuration changes must follow standard organizational processes.

### Access Control
- Access to operating systems must be controlled in accordance with the **Access Management Policy**.
- Operating systems must integrate with central authentication where technically feasible.
- Administrative privileges must be restricted and assigned based on least privilege.
- Use of shared or generic administrative accounts must be avoided where possible.

### Patching and Vulnerability Management
- Operating system patching and vulnerability remediation must follow the **Patching & Vulnerability Management Policy**.
- Security updates must be applied within defined timeframes based on risk.
- Patch status must be monitored and reported where required.

### Malware Protection
- Operating systems must have organization-approved anti-malware or endpoint protection installed where applicable.
- Protection mechanisms must be kept up to date and enabled.
- Users must not disable malware protection controls.

### Logging and Monitoring
- Operating systems must generate security and operational logs.
- Logging must be implemented in accordance with the **Logging & Monitoring Policy**.
- Logs must include authentication events, privilege use, and configuration changes where technically feasible.

### Backup and Recovery
- Operating systems must be backed up at least **weekly** to support system recovery.
- Operating system backups must be retained for up to **one (1) month**.
- Backup encryption, storage, access, and protection must follow the **Backup & Recovery Policy** and **Cryptography Policy**.
- Backup restore capabilities must be tested periodically in accordance with the Backup & Recovery Policy.

### Network Exposure
- Operating systems must not be exposed directly to untrusted networks unless explicitly required and approved.
- Where external access is required, systems must be protected using appropriate network controls (e.g. firewalls, reverse proxies).

### Decommissioning
- Operating systems must be decommissioned when no longer required or when support ends.
- Access must be revoked prior to decommissioning.
- System data must be handled in accordance with the **Data Management Policy**.
- Decommissioning activities must be documented.

## Procedures

### Operating System Deployment Procedure
1. Operating system requirements are identified and approved.
2. Approved operating system versions are installed.
3. Secure baseline configurations are applied.
4. Required security controls are enabled.
5. System is approved for use.

### Operating System Decommissioning Procedure
1. Decommissioning is approved.
2. Access is revoked.
3. System backups are handled according to retention requirements.
4. Operating system is retired and documented.
