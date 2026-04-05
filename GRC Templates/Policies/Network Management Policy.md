# Network Management Policy

## Purpose
To ensure that network infrastructure is securely designed, managed, and operated to protect organizational systems and data, while supporting availability, performance, and controlled connectivity.

## Scope
This document applies to all network devices and services owned, managed, or used by the organization, including switches, routers, wireless networks, firewalls, load balancers, and related network security devices, whether on-premise or cloud-based.

## Responsibilities
- **System Owners** are responsible for ensuring network components under their ownership comply with this policy.
- **Department Managers** support implementation and ensure business requirements are met.
- **The Information Security function** is responsible for defining network security requirements and supporting their implementation, with the assistance of Department Managers.
- **Network Administrators / IT Teams** are responsible for configuring, operating, and maintaining network devices in accordance with this policy.

## Policy Statements
- Network infrastructure must be securely configured, monitored, and maintained.
- Only approved and supported network devices may be deployed.
- Networks must be segmented to reduce risk and limit the impact of security incidents.
- Network access must be controlled, logged, and auditable.
- Changes to network configurations must follow standard organizational processes.
- Network security controls must prevent unauthorized access and unintended data flows.

## Standards

### Network Segmentation
- Networks must be segregated to differentiate at a minimum:
  - Production environments
  - Development and testing environments
  - Office and employee networks
- Network segmentation must be enforced using appropriate network controls (e.g. VLANs, firewalls).

### Production Networks
- Production services must not be directly exposed to the internet.
- All inbound and outbound production traffic must pass through approved firewalls and security inspection points.
- Public-facing production services must only be exposed through approved load balancers and firewall controls.
- Access to production networks must be strictly restricted and logged.

### Development Networks
- Development and testing networks must be logically separated from production networks.
- Development networks must not publish services to the internet by default.
- Any external exposure of development services must be explicitly approved and documented.
- Access to development environments must be restricted to authorized users.

### Office and Employee Networks
- Office networks must be segregated from production and development environments.
- Office network traffic must be outbound-only by default.
- Outbound internet traffic from office networks must be inspected and filtered using approved security controls.
- Inbound traffic from external networks into office networks is not permitted.
- Direct access from office networks to production systems must be restricted and controlled.

### General Network Device Standards
- Network devices must be organization-approved and centrally managed where technically feasible.
- Administrative access must authenticate against a central identity source where technically feasible.
- Administrative privileges must be restricted based on least privilege.
- Default accounts, credentials, and insecure services must be disabled or secured.
- Network configuration changes must follow standard organizational processes.
- Network device activity and administrative actions must be logged in accordance with the **Logging & Monitoring Policy**.
- Network devices must use synchronized time sources.

### Switches
- Switches must be securely configured to limit unnecessary network access.
- Management interfaces must be restricted to authorized network segments.
- Physical access to switches and their cabling must be protected.
- Redundant power supplies should be used where availability requirements exist.

### Routers
- Routers must be configured to restrict traffic based on defined routing and security rules.
- Unnecessary services and interfaces must be disabled.
- Router configurations must be backed up regularly.

### Wireless (Wi-Fi) Networks
- Open or unsecured wireless networks are not permitted.
- Wireless networks must use strong encryption and authentication.
- Wireless access must be centrally managed where technically feasible.
- Guest wireless networks must be logically separated from internal networks.
- Wireless access activity must be logged where technically feasible.

### Firewalls and Load Balancers
- Firewalls must enforce least-privilege network rules.
- Firewall and load balancer rules must be documented.
- Changes must follow standard organizational processes.
- Allowed and blocked traffic must be logged.
- Load balancers must be used to control and inspect traffic to production services.

### Network Monitoring and Availability
- Network performance and capacity must be monitored.
- Alerts must be defined for failures, anomalies, or capacity thresholds.
- Critical network components should be designed with redundancy where feasible.

### Network Backup and Recovery
- Network device configurations must be backed up regularly.
- Backup scope, retention, and protection must follow the **Backup & Recovery Policy**.
- Restore procedures must be documented and tested where appropriate.

## Procedures

### Network Change Procedure
1. Network change request is submitted and documented.
2. Impact and security implications are reviewed.
3. Change is approved in accordance with standard organizational processes.
4. Change is implemented and validated.
5. Configuration and documentation are updated.

### Network Decommissioning Procedure
1. Network device decommissioning is approved.
2. Access and connectivity are removed.
3. Configurations and data are securely removed.
4. Device is retired or disposed of according to organizational procedures.
