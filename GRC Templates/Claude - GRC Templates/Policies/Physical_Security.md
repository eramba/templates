# Physical Security

## Purpose
To protect organizational facilities, equipment, and physical assets from unauthorized access, theft, damage, and environmental threats.

## Scope
All physical locations where organizational information systems or sensitive information are processed or stored.

## Responsibilities

- **Facilities Management**: Maintain physical access controls, environmental controls, and visitor management.
- **Information Security**: Define physical security requirements and review compliance.
- **All Staff**: Challenge unescorted visitors and report physical security incidents.

## Policy Statements

### Physical Access Controls

**Applies to all frameworks**
- Physical access to sensitive areas must be restricted using appropriate controls.
- Access must be granted based on business need and reviewed periodically.
- Physical access rights must be revoked immediately upon termination.

**PCI DSS v4.0.1 (9.1.1, 9.1.2, 9.2.1, 9.2.2, 9.2.3, 9.2.4)**
- Visitors must be authorized, logged, and escorted in sensitive areas.
- Physical access logs must be retained and reviewed.

**ISO 27002:2022 (7.2, 7.3, 7.4)**
- Physical security perimeters must be defined and enforced with appropriate entry controls.

**SOC 2 (TSP 2017) (CC6.4, CC6.5)**
- Physical access controls must prevent unauthorized individuals from accessing systems that process sensitive data.

**NIST 800-53 Rev5 (PE-2, PE-3, PE-6, PE-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (PES-01, PES-02, PES-03, PES-04, PES-05)**

### Equipment and Asset Protection

**Applies to all frameworks**
- Equipment must be sited and protected to reduce risks from environmental threats and unauthorized access.
- Media containing sensitive data must be stored securely and disposed of using approved methods.

**PCI DSS v4.0.1 (9.3.1, 9.3.2, 9.3.3, 9.3.4, 9.4.1, 9.4.2)**
- Unattended equipment must be secured; screen locks must activate after a defined inactivity period.
- Equipment must not be taken off-site without authorization.

**ISO 27002:2022 (7.8, 7.9, 7.11, 7.12)**
- Supporting utilities (power, cooling) must be adequate and monitored for failures.
- Cabling must be protected from interception or damage.

**NIST 800-53 Rev5 (PE-3)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (PES-06, PES-07, PES-08, PES-09, PES-10)**

### Physical Security of Devices

**Applies to all frameworks**
- A list of devices must be maintained including location, serial number, and inspection history.

**PCI DSS v4.0.1 (9.4.3, 9.4.4, 9.4.5, 9.4.6, 9.4.7)**
- Point-of-interaction devices must be inspected periodically for tampering or substitution.
- Personnel must be trained to identify and report suspected device tampering.

**ISO 27002:2022 (7.13, 7.14)**
- Equipment must be maintained in accordance with manufacturer recommendations to ensure availability and integrity.

**NIST 800-53 Rev5 (PE-3)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (PES-11, PES-12, PES-13)**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Visitor Access

**Applies to all frameworks**
1. Log visitor name, organisation, and purpose at entry.
2. Issue a temporary access badge and assign an escort.
3. Escort the visitor throughout their time in restricted areas.
4. Collect badge and log departure time.
5. Retain visitor logs for at least three months.

**PCI DSS v4.0.1 (9.3.2, 9.3.3, 9.3.4)**
6. Confirm visitor logs are maintained with name, organisation, date/time, and authorising personnel per 9.3.4.
7. Confirm visitor badges expire and are surrendered before visitors leave per 9.3.3.

**SOC 2 (TSP 2017) (CC6.4)**
8. Confirm physical access to facilities is restricted and managed per CC6.4.

### Physical Access Review

**Applies to all frameworks**
1. Review access rights list at defined intervals.
2. Remove access for personnel no longer requiring it.
3. Verify no terminated employee access remains active.
4. Document and retain review results.
5. Confirm review frequency is at least quarterly for CDE areas.

**PCI DSS v4.0.1 (9.2.1, 9.3.1)**
6. Confirm physical access to CDE is reviewed at least every three months per 9.3.1.
7. Confirm all physical access mechanisms are returned or disabled upon termination per 9.3.1.1.

**SOC 2 (TSP 2017) (CC6.4, CC6.5)**
8. Confirm physical access controls are reviewed and updated per CC6.4 and CC6.5.

### Media Disposal

**Applies to all frameworks**
1. Identify media containing sensitive data for disposal.
2. Apply approved destruction method.
3. Obtain and retain a certificate of destruction where applicable.
4. Confirm destruction method renders cardholder data unrecoverable.
5. Log disposal in the media destruction register.
6. Select destruction method based on the classification level of data the media contained.

**PCI DSS v4.0.1 (9.4.6, 9.4.7)**
7. Confirm hard-copy materials are cross-cut shredded, incinerated, or pulped per 9.4.6.
8. Confirm electronic media is destroyed or rendered unrecoverable per 9.4.7.
9. Log disposal in media destruction register.

**SOC 2 (TSP 2017) (CC6.5)**
10. Confirm ability to read or recover data is eliminated before disposal per CC6.5.

### Clear Desk and Clear Screen Controls

### Clear Desk and Screen Spot Check Procedure

**Applies to all frameworks**
1. Conduct unannounced walkthrough of work areas, checking for unattended sensitive documents, unlocked screens, and exposed credentials.
2. Record findings by area and workstation.
3. Report findings to line managers of affected areas.
4. Confirm corrective actions are communicated to affected personnel.
5. Document walkthrough date, areas covered, and findings.
