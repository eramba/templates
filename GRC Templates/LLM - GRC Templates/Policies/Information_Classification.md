# Information Classification

## Purpose
To ensure information is classified according to its sensitivity and handled with appropriate protection controls throughout its lifecycle.

## Scope
All information created, received, processed, stored, or transmitted by the organization, regardless of format or medium.

## Responsibilities

- **Information Owners**: Classify information under their ownership and ensure it is handled accordingly.
- **All Staff**: Handle information per its classification label and applicable handling rules.
- **Information Security**: Define and maintain the classification scheme and handling requirements.

## Policy Statements

### Classification Scheme

**Applies to all frameworks**
- A formal information classification scheme must be defined and communicated to all personnel.
- Information must be labelled at creation or receipt where technically feasible.

**PCI DSS v4.0.1 (3.1.1, 3.2.1)**
- Cardholder data and sensitive authentication data must be classified at the highest protection level.
- The scope of cardholder data storage must be minimized.

**ISO 27002:2022 (5.12, 5.13)**
- Classification must take into account legal requirements and the value and sensitivity of the information.

**SOC 2 (TSP 2017) (CC6.1, C1.1)**
- Classification must support the organization's confidentiality commitments to customers.

**NIST 800-53 Rev5 (MP-3, MP-4)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.1, 3.2, 3.3, 3.4, 3.5)**

**SCF 2025 (DCH-01, DCH-02, DCH-03, DCH-04)**

**NIS2 Article 21 (Article 21(2) (a))**

**ISO 27701:2025 (A.3.3, A.3.4)**

### Handling Requirements

**Applies to all frameworks**
- Handling rules must be defined per classification level covering storage, transmission, access, sharing, retention, and disposal.
- Confidential and Restricted information must be encrypted in transit and at rest.

**PCI DSS v4.0.1 (3.3.1, 3.3.2, 3.3.3, 3.4.1, 3.4.2)**
- Sensitive authentication data must never be stored after authorization, even if encrypted.
- PANs must be masked when displayed; full PANs must only be visible to authorized personnel with a business need.

**ISO 27002:2022 (5.13, 5.14)**
- Sharing of sensitive information with third parties requires authorization and appropriate agreements.

**SOC 2 (TSP 2017) (C1.1, C1.2)**
- Access to confidential information must be restricted to authorized individuals with a business need.

**NIST 800-53 Rev5 (MP-3, SI-12)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.6, 3.7, 3.8, 3.9, 3.12, 3.13, 3.14)**

**SCF 2025 (DCH-05, DCH-06, DCH-07, DCH-08, DCH-09, DCH-10)**

**NIS2 Article 21 (Article 21(2) (a))**

**ISO 27701:2025 (A.3.13, A.3.14, A.3.15, A.3.16)**

### Retention and Disposal

**Applies to all frameworks**
- Retention periods must be defined for each information category based on legal, regulatory, and business requirements.
- Information must be securely disposed of at the end of its retention period.

**PCI DSS v4.0.1 (3.2.1, 3.3.1)**
- Cardholder data retention must be minimized; a documented retention policy must define the business justification for any retention.

**ISO 27002:2022 (5.12, 5.14)**
- Disposal must be documented and, for sensitive information, verified.

**NIST 800-53 Rev5 (MP-6, SI-12, SI-19)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.9, 3.14)**

**SCF 2025 (DCH-11, DCH-12, DCH-13)**

**ISO 27701:2025 (A.3.17, A.3.18)**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Classification at Creation

**Applies to all frameworks**
1. Identify the sensitivity of information being created or received.
2. Apply the appropriate classification label.
3. Apply handling controls consistent with the classification.
4. Confirm whether the information constitutes cardholder data or sensitive authentication data; apply highest classification if so.
5. Apply classification consistent with legal requirements and the organisation's classification scheme.

**PCI DSS v4.0.1 (3.1.1, 3.2.1)**
6. Confirm cardholder data and SAD are classified at the highest protection level per 3.1.1.
7. Confirm SAD is never stored after authorisation per 3.3.1.

**SOC 2 (TSP 2017) (C1.1)**
8. Confirm confidential information is identified and designated per C1.1.

**ISO 27002:2022 (5.12, 5.13)**
9. Classify information according to security needs per 5.12.
10. Apply labelling procedures in accordance with the classification scheme per 5.13.

**NIST 800-53 Rev5 (MP-3)**
11. Mark system media indicating distribution limitations and security markings per MP-3.

**CIS Controls v8.1 (3.1, 3.7)**
12. Establish and maintain a documented data management process per 3.1.
13. Establish and maintain an overall data classification scheme per 3.7.

**SCF 2025 (DCH-02, DCH-04, DCH-05)**
14. Ensure data and assets are categorised per applicable requirements per DCH-02.
15. Mark media in accordance with data protection requirements per DCH-04.
16. Bind cybersecurity and data protection attributes to information per DCH-05.

**NIS2 Article 21 (Article 21(2) (a))**
17. Confirm classification supports risk analysis and information system security per Article 21(2)(a).

**ISO 27701:2025 (A.3.5, A.3.6)**
18. Classify information taking PII into account per A.3.5.
19. Apply labelling procedures for PII per A.3.6.

### Reclassification

**Applies to all frameworks**
1. Identify the trigger for reclassification.
2. Obtain approval from the information owner.
3. Update the classification label on all copies of the information.
4. Adjust handling controls to match the new classification.
5. Notify relevant parties of the reclassification.
6. Document the change and retain a record.

**SOC 2 (TSP 2017) (C1.1)**
7. Confirm confidential information is retained no longer than necessary per C1.1.

**ISO 27002:2022 (5.12)**
8. Review and update classification as information needs change per 5.12.

**SCF 2025 (DCH-11)**
9. Reclassify data commensurate with security category or classification level per DCH-11.

### Secure Disposal

**Applies to all frameworks**
1. Identify information due for disposal per the retention schedule.
2. Apply the approved disposal method per classification level.
3. Document the disposal action and retain a record.
4. Confirm cardholder data is rendered unrecoverable; retain destruction evidence.
5. Select disposal method appropriate to the classification and medium of the information.

**PCI DSS v4.0.1 (3.3.1, 3.5.1)**
6. Confirm cardholder data destruction renders data unrecoverable per 3.3.1.
7. Confirm PAN storage is minimised and disposal follows retention policy per 3.5.1.

**SOC 2 (TSP 2017) (C1.2)**
8. Confirm confidential information is destroyed per disposal procedures per C1.2.

**ISO 27002:2022 (8.1)**
9. Delete information stored in systems or devices when no longer required per 8.1.

**NIST 800-53 Rev5 (MP-6, SI-12)**
10. Sanitise system media prior to disposal per MP-6.
11. Manage and retain information in accordance with applicable requirements per SI-12.

**CIS Controls v8.1 (3.5)**
12. Securely dispose of data per the data management process commensurate with sensitivity per 3.5.

**SCF 2025 (DCH-21, DCH-08, DCH-09)**
13. Securely dispose of information per DCH-21.
14. Securely dispose of media per DCH-08.
15. Sanitise system media per DCH-09.

**ISO 27701:2025 (A.1.4.6, A.1.4.9)**
16. Delete PII or render it non-identifiable at end of processing per A.1.4.6.
17. Dispose of PII per documented disposal policies per A.1.4.9.

### Intellectual Property Rights Management

### Intellectual Property Rights Compliance Review Procedure

**Applies to all frameworks**
1. Review software asset inventory against licence entitlements.
2. Identify any unlicensed or over-deployed software.
3. Confirm procedures are in place to prevent use of unlicensed content.
4. Document compliance status and remediation actions.
5. Retain review record with date and approver.

### Cardholder Data Discovery Scan Procedure

**Applies to all frameworks**
1. Schedule and execute cardholder data discovery tool scan across all in-scope systems.
2. Review scan results for any PANs found outside the defined CDE.
3. Investigate each finding: confirm whether data is authorised or must be removed.
4. Remediate unauthorised cardholder data findings immediately.
5. Document scan results and remediation actions; retain as evidence.

### PII Cross-Border Transfer Review Procedure

**Applies to all frameworks**
1. Review data transfer register for completeness and currency.
2. Confirm each cross-border transfer has a documented legal mechanism.
3. Verify mechanisms (adequacy decisions, SCCs, etc.) are current and legally valid.
4. Identify any new transfers not yet registered.
5. Document review and retain evidence.

### Intellectual Property Rights

**Applies to all frameworks**
- The organisation shall implement procedures to protect intellectual property rights including software licences, copyrights, and proprietary content.
- Personnel must not copy, distribute, or modify third-party software or content in violation of licence terms or copyright law.

**ISO 27002:2022 (5.32)**
- Appropriate procedures shall be implemented to protect intellectual property rights in accordance with applicable legislation, regulation, and contractual obligations.

### Protection of Records

**Applies to all frameworks**
- Records shall be protected from loss, destruction, falsification, unauthorised access, and unauthorised release in accordance with their classification and applicable legal, regulatory, and contractual requirements.
- Retention periods for all record categories shall be defined, documented, and enforced.

**ISO 27002:2022 (5.33)**
- Records shall be protected in accordance with the organisation's classification scheme, applicable legal and regulatory requirements, and business need.

### Capacity Management

**Applies to all frameworks**
- The use of resources shall be monitored and adjusted in line with current and expected capacity requirements to ensure system performance and availability.
- Capacity planning shall identify potential bottlenecks before they affect service delivery.

**ISO 27002:2022 (8.6)**
- The use of resources shall be monitored, tuned, and projections made of future capacity requirements to ensure the required system performance.

### Output and Storage Integrity

**SOC 2 (TSP 2017) (PI1.4, PI1.5)**
- The organisation shall implement policies and procedures to make available or deliver output completely, accurately, and timely in accordance with specifications, protecting output from theft, destruction, and unauthorised disclosure.
- The organisation shall implement policies and procedures to store inputs, items in processing, and outputs completely, accurately, and timely, protecting stored items from theft, corruption, destruction, and unauthorised disclosure.

### Intellectual Property Compliance Review Procedure

**Applies to all frameworks**
1. Review the software asset inventory for unlicensed or expired licences.
2. Confirm procedures for acquiring and renewing software licences are operating.
3. Confirm personnel are trained on copyright and intellectual property requirements.
4. Document findings and initiate remediation for any violations.

**ISO 27002:2022 (5.32)**
5. Confirm procedures address all intellectual property categories: software, documents, databases, and proprietary processes.

**NIST 800-53 Rev5 (MP-1, MP-2, MP-5, MP-8)**
- Develop and disseminate media protection policy per MP-1.
- Restrict access to media containing sensitive information per MP-2.
- Control the transport of media outside controlled areas per MP-5.
- Implement media downgrading process when media sensitivity changes per MP-8.

### Records Retention Review Procedure

**Applies to all frameworks**
1. Confirm the records retention schedule is current and covers all record categories.
2. Sample records to confirm they are stored in approved locations with appropriate access controls.
3. Confirm records scheduled for disposal have been disposed of securely.
4. Review legal and regulatory changes that may affect retention requirements.

**ISO 27002:2022 (5.33)**
5. Confirm records are protected against falsification and unauthorised access consistent with their classification.

**SCF 2025 (CRY-10, CRY-13, DCH-14, DCH-15, DCH-16, DCH-17, DCH-18, DCH-19, DCH-20, DCH-22, DCH-23, DCH-24, DCH-25, DCH-26, DCH-27)**
- Implement transmission of cybersecurity and data protection attributes per CRY-10.
- Implement cryptographic hash controls per CRY-13.
- Control information sharing per DCH-14.
- Manage publicly accessible content per DCH-15.
- Implement data mining protection per DCH-16.
- Control ad-hoc data transfers per DCH-17.
- Implement media and data retention controls per DCH-18.
- Control geographic location of data per DCH-19.
- Manage archived data sets per DCH-20.
- Implement data quality operations per DCH-22.
- Implement de-identification and anonymisation per DCH-23.
- Document and maintain information location per DCH-24.
- Control transfer of sensitive and regulated data per DCH-25.
- Address data localisation requirements per DCH-26.
- Implement data rights management per DCH-27.

### Capacity Planning Review Procedure

**Applies to all frameworks**
1. Review resource utilisation metrics for all critical systems.
2. Identify systems approaching capacity thresholds.
3. Produce capacity projections for the next planning period.
4. Initiate procurement or optimisation actions for systems with forecast shortfalls.

**ISO 27002:2022 (8.6)**
5. Confirm capacity planning covers storage, processing, network bandwidth, and cloud service limits.

### Acceptable Use of Information and Assets

**Applies to all frameworks**
- Rules for the acceptable use and procedures for handling information and other associated assets shall be identified, documented, and implemented.
- Personnel shall be made aware of acceptable use rules before being granted access to information or assets.

**ISO 27002:2022 (5.1)**
- Acceptable use rules shall cover requirements for authorised and prohibited use of information processing facilities, information assets, software, and removable media per 5.1.
- Acceptable use rules shall be reviewed at minimum annually and updated to reflect changes in technology, regulation, and business requirements.

### Acceptable Use Review Procedure

**ISO 27002:2022 (5.1)**
1. Confirm an acceptable use policy exists and is current.
2. Confirm the policy covers information processing facilities, information assets, software, and removable media.
3. Confirm personnel have acknowledged the acceptable use policy.
4. Review any violations during the period and confirm disciplinary process was followed.
5. Update the policy to reflect any new technology or regulatory requirements.

**PCI DSS v4.0.1 (12.2.1, 12.6.3.1, 12.6.3.2)**
- Document and implement acceptable use policies for end-user technologies covering explicit approval, acceptable uses, and prohibited activities per 12.2.1.
- Include awareness of threats and vulnerabilities impacting the CDE in security awareness training per 12.6.3.1.
- Include awareness of acceptable use of end-user technologies in security awareness training per 12.6.3.2.
