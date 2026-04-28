# Business Continuity Policy

## Purpose
To ensure that the organization can continue to operate critical business processes and protect information during and after disruptive events, and that information security is maintained or restored within required timeframes.

This policy defines the requirements for business continuity planning, information security during disruption, and recovery capabilities.

## Scope
This policy applies to:
- All critical business processes and supporting information systems.
- All information assets within the scope of the ISMS.
- All personnel involved in business continuity and recovery activities.
- All disruptive events, including system failures, cyber incidents, supplier outages, and external service disruptions.

## Responsibilities
- **Management**: Ensures business continuity objectives are defined, resourced, approved, and reviewed.
- **The Information Security function**: Defines information security requirements for business continuity, with the support of other department managers.
- **Business and Process Owners**: Identify critical activities, support business impact analysis, and ensure continuity requirements are met.
- **IT / Operations Teams**: Support the implementation, testing, and execution of continuity and recovery actions.
- **Suppliers and Service Providers**: Support continuity requirements as defined in agreements.

---

## Policy Statements
- Business continuity requirements must be identified and documented.
- Information security must be maintained at an appropriate level during disruption.
- Compensating controls must be applied where normal controls cannot be maintained.
- Redundancy and alternate solutions must be considered to meet availability needs.
- Business continuity capabilities must be tested, reviewed, and improved.

---

## Standards

### Information Security During Disruption
- Information security requirements must be integrated into business continuity planning.
- Business continuity plans must:
  - Maintain existing information security controls during disruption where possible.
  - Define compensating controls when normal controls cannot be maintained.
- Security of information must be restored to the required level within defined timeframes following disruption.
- Continuity plans must be developed, implemented, tested, reviewed, and updated.

### Business Impact Analysis (BIA)
- A Business Impact Analysis must be performed to:
  - Identify critical business activities.
  - Assess impact types and severity over time.
  - Determine acceptable disruption periods.
- Each prioritized activity must be assigned a **Recovery Time Objective (RTO)**.
- Resources required to support prioritized activities must be identified.
- For information supporting critical activities, a **Recovery Point Objective (RPO)** must be defined where applicable.

### Redundancy of Information Processing Facilities
- Redundancy must be implemented where required to meet availability objectives.
- Redundant components and facilities must provide equivalent security to primary systems.
- Redundancy may include, where applicable:
  - Multiple network or connectivity providers.
  - Redundant networks or routing paths.
  - Geographically separated hosting locations.
  - Redundant power supplies or sources.
  - Multiple parallel software instances with load balancing.
- Procedures must exist to activate redundant components.
- Redundant systems must be monitored for failures.
- Redundant systems should be tested to verify failover operates as intended.

---

## Procedures

### Business Continuity Planning Procedure
1. Identify critical business processes.
2. Perform Business Impact Analysis.
3. Define RTO and RPO requirements.
4. Identify continuity and alternate solutions.
5. Develop and approve continuity plans.
6. Test and review plans.

### Continuity Review and Improvement Procedure
1. Review continuity plans at planned intervals.
2. Review plans after significant disruptions or incidents.
3. Update plans, controls, and documentation.
4. Report results to management.

---

## Continuity Plan Scenarios Example

### Loss of Primary Communication Platform in a Fully Remote Organization

**Scenario**  
The organization operates fully remotely and relies on **Slack** as its primary internal communication platform. Due to external factors outside the organization’s control, Slack becomes unavailable for a full business day.

**Impact**  
- Internal coordination is disrupted.
- Incident response and operational communication are delayed.
- Business activities relying on real-time communication are affected.

**Continuity Response**
- Slack unavailability is confirmed.
- Management and the Information Security function declare a communication service disruption.
- The organization activates its alternate communication channel.

**Alternate Solution**
- **WhatsApp** is used as the temporary communication platform.
- Predefined WhatsApp groups are used for management, operations, and incident coordination.

**Security Considerations**
- Only approved groups and participants are used.
- Sensitive information sharing is minimized.
- File sharing and long-term record keeping are avoided.
- Actions taken during the disruption are documented and later transferred to approved systems.

**Restoration**
- Once Slack is restored:
  - Communication returns to Slack.
  - Temporary WhatsApp groups are closed.
  - A post-incident review is performed.

---

### Loss of Primary Email Delivery Gateway for SaaS Platform

**Scenario**  
The organization operates a SaaS platform that relies on **AWS** as the default outbound email gateway. Due to external factors, the AWS email gateway is banned or blocked, preventing email delivery.

**Impact**  
- Transactional and notification emails are not delivered.
- Customer communications are disrupted.
- Platform workflows relying on email are degraded.

**Continuity Response**
- Email delivery failure is detected and confirmed.
- Management and the Information Security function declare a service degradation.

**Alternate Solution**
- The application is updated to use an **alternative email gateway (e.g. Gmail)**.
- Configuration or code changes redirect outbound email traffic to the alternate provider.

**Security and Operational Considerations**
- Credentials for the alternative gateway are securely managed.
- Access is restricted to required application components.
- Email handling continues to comply with information security and data protection requirements.
- Changes are documented as continuity actions.

**Restoration**
- Once the primary gateway is restored:
  - The application is reverted to the default email gateway.
  - Temporary configurations are removed.
- A post-incident review evaluates response effectiveness and resilience improvements.
