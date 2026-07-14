# System Maintenance

## Purpose
To ensure that maintenance of information systems is planned, controlled, and performed by authorised personnel using approved tools and methods to protect system integrity and availability.

## Scope
All information systems, system components, and supporting infrastructure owned or operated by the organisation.

## Responsibilities

- **IT Operations**: Schedule, execute, and document all system maintenance activities.
- **System Owners**: Approve maintenance activities and verify system integrity post-maintenance.
- **Information Security**: Review security implications of maintenance activities and tools.
- **Facilities / Procurement**: Manage maintenance contracts and spare parts availability.

## Policy Statements

### Controlled Maintenance

**Applies to all frameworks**
- All maintenance, repair, and replacement of system components must be scheduled, documented, and reviewed.
- Maintenance activities must be approved and monitored whether performed on-site or remotely.
- Systems or components removed for off-site maintenance must be sanitised of sensitive data before removal where technically feasible.
- Maintenance records must be retained and reviewed periodically.

**NIST 800-53 Rev5 (MA-2, MA-6, SI-13)**
- Spare parts and maintenance support for critical system components must be obtained within organisation-defined timeframes following failure.

**CIS Controls v8.1 (4.1, 4.2, 4.3)**

**SCF 2025 (MNT-01, MNT-02, MNT-03)**

**NIS2 Article 21 (Article 21(2) (e))**

**ISO 27002:2022 (7.13)**
- Define and enforce equipment maintenance programme including authorised personnel and maintenance records per 7.13.

**NIST 800-53 Rev5 (MA-1, MA-3, MA-7)**
- Develop and disseminate system maintenance policy per MA-1.
- Approve, control, and monitor system maintenance tools per MA-3.
- Perform field maintenance on systems per MA-7.

**SCF 2025 (MNT-10, MNT-11)**
- Implement maintenance validation processes per MNT-10.
- Monitor maintenance activities per MNT-11.

### Maintenance Tools

**Applies to all frameworks**
- All system maintenance tools must be approved, controlled, and monitored.
- Maintenance tools must be reviewed at defined intervals to confirm they remain appropriate and have not been compromised.
- Unauthorised tools must not be used for system maintenance.

**CIS Controls v8.1 (4.4, 4.5, 4.6)**

**SCF 2025 (MNT-04, MNT-05)**

### Remote Maintenance

**Applies to all frameworks**
- Remote maintenance and diagnostic activities must be approved and monitored.
- Strong authentication must be used when establishing remote maintenance sessions.
- Remote maintenance sessions must be terminated and network connections closed upon completion.
- Records of all remote maintenance activities must be maintained.

**NIST 800-53 Rev5 (MA-4)**
- Remote maintenance tools must be consistent with organisational policy and documented in the system security plan.

**CIS Controls v8.1 (4.2)**

**SCF 2025 (MNT-06, MNT-07)**

**NIS2 Article 21 (Article 21(2) (e))**

### Maintenance Personnel

**Applies to all frameworks**
- A process for authorising maintenance personnel must be established and a list of authorised maintenance organisations or personnel maintained.
- Non-escorted personnel performing maintenance must possess the required access authorisations.
- Maintenance personnel without required access authorisations must be supervised by authorised organisational personnel.

**SCF 2025 (MNT-08, MNT-09)**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Scheduled Maintenance

**Applies to all frameworks**
1. Schedule maintenance activity and notify system owner and affected users.
2. Verify maintenance personnel are on the authorised list.
3. Execute maintenance during an approved window.
4. Verify system integrity and functionality post-maintenance.
5. Document the maintenance activity including date, personnel, components affected, and outcome.
6. Confirm spare parts availability meets defined timely maintenance requirements for critical components.

**NIST 800-53 Rev5 (MA-2, MA-6)**
7. Schedule, document, and review maintenance records per manufacturer specifications per MA-2.
8. Obtain maintenance support within the defined time period of failure per MA-6.

**SCF 2025 (MNT-02, MNT-03)**
9. Conduct controlled maintenance activities throughout the asset lifecycle per MNT-02.
10. Obtain maintenance support within the defined RTO per MNT-03.

### Remote Maintenance Session

**Applies to all frameworks**
1. Obtain approval for remote maintenance session from system owner.
2. Establish session using approved encrypted channel with strong authentication.
3. Monitor the session throughout its duration.
4. Terminate session and close network connection upon completion.
5. Document session details: date, duration, personnel, actions taken.

**NIST 800-53 Rev5 (MA-4)**
6. Approve, monitor, and control nonlocal maintenance and diagnostic activities per MA-4.

**SCF 2025 (MNT-05)**
7. Authorise, monitor, and control remote maintenance and diagnostic activities per MNT-05.

### Component Removal for Off-site Maintenance

**Applies to all frameworks**
1. Identify component requiring off-site maintenance.
2. Sanitise or remove sensitive data from the component before removal where feasible.
3. Log component removal including serial number, destination, and responsible party.
4. Upon return, inspect component for tampering before reinstallation.
5. Verify system integrity after reinstallation and document.

**NIST 800-53 Rev5 (MA-5)**
6. Establish a process for maintenance personnel authorisation per MA-5.

**SCF 2025 (MNT-09)**
7. Ensure off-site maintenance activities are conducted securely and assets are secured during transfer per MNT-09.
