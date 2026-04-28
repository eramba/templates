# Logging & Monitoring

## Purpose
To ensure that logs are consistently generated, protected, retained, and monitored across all organizational systems in order to detect security events, support incident response, and meet legal, regulatory, and operational requirements.

## Scope
This document applies to all systems, applications, infrastructure, network devices, security controls, and services (on-premise or cloud-based) that process, store, or transmit organizational data.

## Responsibilities
- **System Owners** are responsible for ensuring logging is enabled and aligned with this policy for systems they own.
- **Department Managers** support implementation and ensure operational compliance within their areas.
- **The Information Security function** is responsible for defining logging requirements and monitoring expectations, with the assistance of Department Managers.
- **IT / Technical Teams** are responsible for implementing, maintaining, and supporting logging and monitoring controls.

## Policy Statements
- Logging must be enabled to support security monitoring, incident detection, and investigation.
- Logs must be protected against unauthorized access, modification, or deletion.
- Access to logs must be restricted and controlled.
- Logs must be reviewed to detect suspicious or anomalous activity.
- Log retention must meet legal, regulatory, and business requirements.
- Logging and monitoring practices must be applied consistently across the organization.

## Standards

### Log Sources
- Logs must be generated, where technically feasible, by:
  - Operating systems
  - Applications
  - Databases
  - Network devices (e.g. firewalls, routers, switches)
  - Security controls (e.g. authentication systems, endpoint protection)
  - Cloud platforms and managed services
- Security-relevant events must be logged, including authentication, authorization, configuration changes, and system activity.

### Log Format and Time Synchronization
- Logs must include, where technically feasible:
  - Date and time of the event
  - Source system or device
  - User, service, or account identity
  - Action performed and outcome
- Systems must use synchronized time sources to ensure log correlation.
- Log formats must support centralized collection and analysis where possible.

### Monitoring and Detection Scenarios
- Logs must be monitored manually or automatically to detect security-relevant activity.
- Monitoring must include predefined scenarios, such as:
  - Failed or suspicious authentication attempts
  - Use of privileged accounts
  - Unauthorized access attempts
  - Changes to security configurations
  - Malware or intrusion alerts
- Alerts must be generated and routed to appropriate teams based on defined scenarios.

### Log Retention
- Log retention periods must be defined based on legal, regulatory, contractual, and business requirements.
- Retention periods may vary by system or log type.
- Logs required for investigations or legal purposes must be retained until no longer needed.

### Log Integrity and Access Control
- Logs must be protected against unauthorized modification, deletion, or overwriting.
- Users, including privileged users, must not be able to delete or alter logs of their own activities.
- Access to logs must be limited to:
  - The Information Security function
  - Relevant System Owners
- Log access must be granted and managed in accordance with the **Access Management Policy**.
- Where technically feasible, logs must be:
  - Stored centrally
  - Append-only or write-once
  - Protected using integrity controls (e.g. hashing)
- Access to logs must follow least privilege principles.

## Procedures

### Log Review Procedure
1. Logs are collected from defined sources.
2. Automated or manual reviews are performed based on risk.
3. Alerts or anomalies are investigated.
4. Findings are escalated or recorded where required.

### Log Retention and Disposal Procedure
1. Retention requirements are defined per log type.
2. Logs are retained for the required period.
3. Logs are securely deleted or archived when retention periods expire.
