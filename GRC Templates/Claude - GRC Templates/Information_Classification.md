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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.1, 3.2, 3.3, 3.4, 3.5 for specific safeguard requirements.

**SCF 2025 (DCH-01, DCH-02, DCH-03, DCH-04)**
- Implement SCF 2025 controls applicable to this area; refer to controls DCH-01, DCH-02, DCH-03, DCH-04 for specific requirements.

**NIS2 Article 21 (Article 21(2) (a))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (a)); measures must be proportionate to the organisation's risk exposure and size.

**ISO 27701:2025 (A.3.3, A.3.4)**
- Comply with ISO 27701:2025 requirements applicable to this area (A.3.3, A.3.4).

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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.6, 3.7, 3.8, 3.9, 3.12, 3.13, 3.14 for specific safeguard requirements.

**SCF 2025 (DCH-05, DCH-06, DCH-07, DCH-08, DCH-09, DCH-10)**
- Implement SCF 2025 controls applicable to this area; refer to controls DCH-05, DCH-06, DCH-07, DCH-08, DCH-09, DCH-10 for specific requirements.

**NIS2 Article 21 (Article 21(2) (a))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (a)); measures must be proportionate to the organisation's risk exposure and size.

**ISO 27701:2025 (A.3.13, A.3.14, A.3.15, A.3.16)**
- Comply with ISO 27701:2025 requirements applicable to this area (A.3.13, A.3.14, A.3.15, A.3.16).

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
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.9, 3.14 for specific safeguard requirements.

**SCF 2025 (DCH-11, DCH-12, DCH-13)**
- Implement SCF 2025 controls applicable to this area; refer to controls DCH-11, DCH-12, DCH-13 for specific requirements.

**ISO 27701:2025 (A.3.17, A.3.18)**
- Comply with ISO 27701:2025 requirements applicable to this area (A.3.17, A.3.18).

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Classification at Creation

**Applies to all frameworks**
1. Identify the sensitivity of information being created or received.
2. Apply the appropriate classification label.
3. Apply handling controls consistent with the classification.

**PCI DSS v4.0.1 (3.1.1, 3.2.1)**
4. Confirm whether the information constitutes cardholder data or sensitive authentication data; apply highest classification if so.

**ISO 27002:2022 (5.12, 5.13)**
4. Apply classification consistent with legal requirements and the organisation's classification scheme.

**NIST 800-53 Rev5 (MP-3, MP-4)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (MP-3, MP-4) in the system security plan.

**CIS Controls v8.1 (3.1, 3.2, 3.3, 3.4, 3.5)**
7. Document compliance with applicable CIS Controls v8.1 controls (3.1, 3.2, 3.3, 3.4, 3.5) per organisational policy.

**SCF 2025 (DCH-01, DCH-02, DCH-03, DCH-04)**
8. Document compliance with applicable SCF 2025 controls (DCH-01, DCH-02, DCH-03, DCH-04) per organisational policy.

**NIS2 Article 21 (Article 21(2) (a))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (a)) as applicable to the organisation's classification under NIS2.

### Reclassification

**Applies to all frameworks**
1. Identify the trigger for reclassification.
2. Obtain approval from the information owner.
3. Update the classification label on all copies of the information.
4. Adjust handling controls to match the new classification.
5. Notify relevant parties of the reclassification.
6. Document the change and retain a record.

**NIST 800-53 Rev5 (MP-3, SI-12)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (MP-3, SI-12) in the system security plan.

**CIS Controls v8.1 (3.5)**
8. Document compliance with applicable CIS Controls v8.1 controls (3.5) per organisational policy.

**SCF 2025 (DCH-04, DCH-05)**
9. Document compliance with applicable SCF 2025 controls (DCH-04, DCH-05) per organisational policy.

### Secure Disposal

**Applies to all frameworks**
1. Identify information due for disposal per the retention schedule.
2. Apply the approved disposal method per classification level.
3. Document the disposal action and retain a record.

**PCI DSS v4.0.1 (3.2.1, 3.3.1)**
4. Confirm cardholder data is rendered unrecoverable; retain destruction evidence.

**ISO 27002:2022 (5.12, 5.14)**
4. Select disposal method appropriate to the classification and medium of the information.

**NIST 800-53 Rev5 (MP-6, SI-19)**
6. Document compliance with applicable NIST 800-53 Rev5 controls (MP-6, SI-19) in the system security plan.

**CIS Controls v8.1 (3.9, 3.14)**
7. Document compliance with applicable CIS Controls v8.1 controls (3.9, 3.14) per organisational policy.

**SCF 2025 (DCH-11, DCH-12, DCH-13)**
8. Document compliance with applicable SCF 2025 controls (DCH-11, DCH-12, DCH-13) per organisational policy.

**NIS2 Article 21 (Article 21(2) (a))**
9. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (a)) as applicable to the organisation's classification under NIS2.
