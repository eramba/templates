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

**PCI DSS v4.0.1 (9.2.1.1, 9.3.1.1)**
- Monitor individual physical access to sensitive areas within the CDE with video cameras or physical access control mechanisms per 9.2.1.1.
- Control physical access for personnel to sensitive areas within the CDE based on individual job function with access reviewed at least once every three months per 9.3.1.1.

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

**ISO 27002:2022 (7.2)**
9. Protect secure areas with appropriate entry controls per 7.2.

**NIST 800-53 Rev5 (PE-8)**
10. Maintain visitor access records for the defined time period per PE-8.

**SCF 2025 (PES-06)**
11. Identify, authorise, and monitor visitors before allowing access per PES-06.

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

**ISO 27002:2022 (7.2, 7.3, 7.4)**
9. Define and use security perimeters to protect areas containing information assets per 7.1.
10. Protect secure areas with appropriate entry controls per 7.2.
11. Monitor premises for unauthorised physical access per 7.4.

**NIST 800-53 Rev5 (PE-2, PE-3, PE-6)**
12. Maintain authorised access list; review at defined intervals per PE-2.
13. Enforce physical access authorisations at entry and exit points per PE-3.
14. Monitor physical access and review logs at defined frequencies per PE-6.

**SCF 2025 (PES-02, PES-03, PES-05)**
15. Maintain current list of personnel with authorised physical access per PES-02.
16. Enforce physical access authorisations at all physical access points per PES-03.
17. Monitor for, detect, and respond to physical security incidents per PES-05.

**ISO 45001:2018 (8.1.1, 8.1.2)**
18. Manage physical environment controls to eliminate or reduce OH&S hazards per 8.1.1 and 8.1.2.

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

**ISO 27002:2022 (7.1, 7.14)**
11. Manage storage media through its lifecycle including secure disposal per 7.1.
12. Verify equipment is securely overwritten or destroyed prior to disposal per 7.14.

**NIST 800-53 Rev5 (MP-6)**
13. Sanitise system media using techniques commensurate with information sensitivity per MP-6.

**SCF 2025 (AST-09, DCH-08, DCH-09)**
14. Securely dispose of or repurpose system components per AST-09.
15. Securely dispose of media using formal procedures per DCH-08.
16. Sanitise system media per DCH-09.

**ISO 27701:2025 (A.3.20, A.3.21)**
17. Manage storage media with PII through its lifecycle per A.3.20.
18. Verify equipment containing PII is securely overwritten prior to disposal per A.3.21.

**PCI DSS v4.0.1 (9.4.1.1, 9.4.1.2, 9.4.5.1)**
- Store offline media backups with cardholder data in a secure location per 9.4.1.1.
- Review security of offline media backup locations at least once every 12 months per 9.4.1.2.
- Conduct inventories of electronic media with cardholder data at least once every 12 months per 9.4.5.1.

### Clear Desk and Clear Screen Controls


**ISO 27002:2022 (7.7)**
- Define and enforce clear desk rules for papers and removable storage media and clear screen rules for information processing facilities per 7.7.

**ISO 27701:2025 (A.3.19)**
- Define and enforce clear desk and clear screen rules for PII-processing environments per A.3.19.

### Clear Desk and Screen Spot Check Procedure

**Applies to all frameworks**
1. Conduct unannounced walkthrough of work areas, checking for unattended sensitive documents, unlocked screens, and exposed credentials.
2. Record findings by area and workstation.
3. Report findings to line managers of affected areas.
4. Confirm corrective actions are communicated to affected personnel.
5. Document walkthrough date, areas covered, and findings.

### Protection Against Physical and Environmental Threats

**Applies to all frameworks**
- The organisation shall design and implement protection against physical and environmental threats including natural disasters, fire, flood, earthquake, and intentional or unintentional physical threats to infrastructure.
- Physical threat assessments shall be conducted for all facilities housing critical information processing infrastructure.

**ISO 27002:2022 (7.5)**
- Protection against physical and environmental threats such as natural disasters, malicious attack, accidents, and other intentional or unintentional physical threats to infrastructure shall be designed and implemented per 7.5.

### Working in Secure Areas

**Applies to all frameworks**
- Security measures for working in secure areas shall be defined and communicated to all personnel with access to those areas.
- Access to secure areas shall be on a need-to-know and need-to-access basis.

**ISO 27002:2022 (7.6)**
- Security measures for working in secure areas shall be designed and implemented covering: need-to-know access, prohibition of unsupervised working, locked unoccupied areas, prohibition of recording devices unless authorised, and appropriate supervision per 7.6.

### Physical Threat Assessment Procedure

**ISO 27002:2022 (7.5)**
1. Identify all facilities housing critical information processing infrastructure.
2. Assess physical and environmental threats applicable to each facility including natural disasters, power, temperature, and physical attack.
3. Confirm protective controls are in place and proportionate to identified threats.
4. Document residual risks and initiate remediation for gaps.
5. Review annually and following any significant physical incident.

### Secure Area Controls Review Procedure

**ISO 27002:2022 (7.6)**
1. Confirm secure area boundaries are physically enforced and access-controlled.
2. Review the list of personnel with access to each secure area — confirm access is still required.
3. Confirm security measures for working in secure areas are communicated and followed.
4. Confirm unoccupied secure areas are locked and alarmed.
5. Review any incidents involving secure area breaches during the period.

### Point-of-Interaction Device Security

**PCI DSS v4.0.1 (9.5.1, 9.5.1.1, 9.5.1.2, 9.5.1.2.1, 9.5.1.3)**
- POI devices that capture payment card data via direct physical interaction shall be protected from tampering and unauthorised substitution.
- An up-to-date list of POI devices shall be maintained including make, model, location, and serial number per 9.5.1.1.
- POI device surfaces shall be periodically inspected to detect tampering and unauthorised substitution per 9.5.1.2.
- The frequency of periodic POI device inspections shall be defined in the entity's targeted risk analysis per 9.5.1.2.1.
- Training shall be provided to personnel in POI environments on verifying device identity, awareness of tampering attempts, and reporting suspicious behaviour per 9.5.1.3.

### POI Device Inspection Procedure

**PCI DSS v4.0.1 (9.5.1, 9.5.1.1, 9.5.1.2, 9.5.1.2.1, 9.5.1.3)**
1. Confirm the POI device inventory is current including make, model, location, and serial number for each device.
2. Inspect POI device surfaces for evidence of tampering, skimming devices, or substitution.
3. Confirm inspection frequency is defined in the targeted risk analysis.
4. Confirm personnel received training on POI security awareness.
5. Document inspection results and escalate any anomalies immediately.
