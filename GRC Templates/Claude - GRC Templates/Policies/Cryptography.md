# Cryptography

## Purpose
To ensure cryptographic controls are applied consistently to protect the confidentiality, integrity, and authenticity of sensitive information.

## Scope
All systems, applications, and data stores that process, store, or transmit sensitive or regulated information.

## Responsibilities

- **Information Security**: Define approved cryptographic algorithms, key lengths, and key management requirements.
- **System Owners / Development Teams**: Implement cryptographic controls per approved standards.
- **IT Operations**: Manage cryptographic keys and certificates throughout their lifecycle.

## Policy Statements

### Cryptographic Standards

**Applies to all frameworks**
- Only organization-approved cryptographic algorithms and key lengths must be used.
- Weak or deprecated algorithms (e.g. DES, RC4, MD5, SHA-1 for signing) must not be used.
- Cryptographic controls must be applied to sensitive data in transit and at rest.

**PCI DSS v4.0.1 (4.2.1, 4.2.2)**
- TLS 1.2 or higher must be used for all data transmitted over public networks; earlier versions must be disabled.
- Strong cryptography must be used to safeguard PANs wherever stored.

**ISO 27002:2022 (8.24)**
- Cryptographic requirements must be defined based on the classification of the information being protected.

**SOC 2 (TSP 2017) (CC6.7)**
- Encryption must be used to protect data transmitted over public or untrusted networks.

**NIST 800-53 Rev5 (SC-8, SC-13)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.1)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.1 for specific safeguard requirements.

**SCF 2025 (CRY-01, CRY-02, CRY-03)**
- Implement SCF 2025 controls applicable to this area; refer to controls CRY-01, CRY-02, CRY-03 for specific requirements.

**NIS2 Article 21 (Article 21(2) (h))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (h)); measures must be proportionate to the organisation's risk exposure and size.

### Key Management

**Applies to all frameworks**
- Cryptographic keys must be generated, stored, distributed, and retired using approved procedures.
- Keys must be stored separately from the data they protect.
- Key rotation must occur at defined intervals or upon suspected compromise.
- Retired or compromised keys must be revoked and securely destroyed.

**PCI DSS v4.0.1 (3.6.1, 3.7.1, 3.7.2, 3.7.3, 3.7.4, 3.7.5, 3.7.6, 3.7.7, 3.7.8)**
- Key custodians must be formally designated and their responsibilities documented.
- Split knowledge and dual control must be used for manual key management operations.
- Key management procedures must be documented and reviewed annually.

**ISO 27002:2022 (8.24)**
- Key management must address the full key lifecycle including generation, storage, archival, retrieval, distribution, retirement, and destruction.

**NIST 800-53 Rev5 (SC-12, SC-17)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.1)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.1 for specific safeguard requirements.

**SCF 2025 (CRY-04, CRY-05, CRY-06, CRY-07)**
- Implement SCF 2025 controls applicable to this area; refer to controls CRY-04, CRY-05, CRY-06, CRY-07 for specific requirements.

**NIS2 Article 21 (Article 21(2) (h))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (h)); measures must be proportionate to the organisation's risk exposure and size.

### Data Encryption at Rest

**Applies to all frameworks**
- Sensitive data at rest must be encrypted using approved algorithms with appropriate key lengths.

**PCI DSS v4.0.1 (3.5.1)**
- Primary account numbers (PANs) must be rendered unreadable wherever stored.
- Encryption keys used for data at rest must be protected by a separate key-encrypting key.

**ISO 27002:2022 (8.24)**
- Encryption of data at rest must be applied based on the information classification level.

**SOC 2 (TSP 2017) (CC6.7)**
- Encryption keys must be managed to prevent unauthorized access to encrypted data.

**NIST 800-53 Rev5 (SC-28)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (3.11)**
- Implement CIS Controls v8.1 controls applicable to this area; refer to controls 3.11 for specific safeguard requirements.

**SCF 2025 (CRY-08, CRY-09)**
- Implement SCF 2025 controls applicable to this area; refer to controls CRY-08, CRY-09 for specific requirements.

**NIS2 Article 21 (Article 21(2) (h))**
- This area falls within the scope of NIS2 Article 21 obligations (Article 21(2) (h)); measures must be proportionate to the organisation's risk exposure and size.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Key Generation and Distribution

**Applies to all frameworks**
1. Generate keys using approved tools and algorithms.
2. Distribute keys via secure, encrypted channels only.
3. Record key metadata in the key register.
4. Never transmit keys in plaintext.

**PCI DSS v4.0.1 (3.7.1, 3.7.2, 3.6.1)**
5. Apply split knowledge and dual control for manual key management operations.
6. Formally designate key custodians and document their responsibilities.

**ISO 27002:2022 (8.24)**
5. Confirm key generation method is consistent with the classification of the data being protected.

**NIST 800-53 Rev5 (SC-12, SC-17)**
8. Document compliance with applicable NIST 800-53 Rev5 controls (SC-12, SC-17) in the system security plan.

**CIS Controls v8.1 (3.1)**
9. Document compliance with applicable CIS Controls v8.1 controls (3.1) per organisational policy.

**SCF 2025 (CRY-04, CRY-05, CRY-06)**
10. Document compliance with applicable SCF 2025 controls (CRY-04, CRY-05, CRY-06) per organisational policy.

**NIS2 Article 21 (Article 21(2) (h))**
11. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (h)) as applicable to the organisation's classification under NIS2.

### Key Rotation and Retirement

**Applies to all frameworks**
1. Identify keys due for rotation per the defined schedule or upon suspected compromise.
2. Generate replacement key and re-encrypt affected data.
3. Revoke old key and update the key register.
4. Securely destroy retired key material.

**PCI DSS v4.0.1 (3.7.4, 3.7.5, 3.7.6, 3.7.7, 3.7.8)**
5. Document the rotation event including date, custodians involved, and systems affected.
6. Confirm retired keys cannot be used to decrypt current data.

**NIST 800-53 Rev5 (SC-12)**
7. Document compliance with applicable NIST 800-53 Rev5 controls (SC-12) in the system security plan.

**CIS Controls v8.1 (3.1)**
8. Document compliance with applicable CIS Controls v8.1 controls (3.1) per organisational policy.

**SCF 2025 (CRY-06, CRY-07)**
9. Document compliance with applicable SCF 2025 controls (CRY-06, CRY-07) per organisational policy.

**NIS2 Article 21 (Article 21(2) (h))**
10. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (h)) as applicable to the organisation's classification under NIS2.

### Certificate Management

**Applies to all frameworks**
1. Maintain an inventory of all TLS/PKI certificates including expiry dates and responsible owners.
2. Monitor certificates for upcoming expiry; initiate renewal at least 30 days before expiry.
3. Generate a CSR and submit to the approved Certificate Authority.
4. Deploy the renewed certificate and verify correct installation.
5. Revoke and remove any expired or compromised certificates immediately.

**PCI DSS v4.0.1 (4.2.1)**
6. Confirm only approved algorithms and key lengths are used in new or renewed certificates.
7. Verify that no TLS versions earlier than 1.2 are enabled on renewed endpoints.

**ISO 27002:2022 (8.24)**
6. Record certificate lifecycle events in the key management register.

**NIST 800-53 Rev5 (SC-17, SC-8)**
9. Document compliance with applicable NIST 800-53 Rev5 controls (SC-17, SC-8) in the system security plan.

**CIS Controls v8.1 (3.1)**
10. Document compliance with applicable CIS Controls v8.1 controls (3.1) per organisational policy.

**SCF 2025 (CRY-05, CRY-06)**
11. Document compliance with applicable SCF 2025 controls (CRY-05, CRY-06) per organisational policy.

**NIS2 Article 21 (Article 21(2) (h))**
12. Ensure compliance with NIS2 Article 21 obligations (Article 21(2) (h)) as applicable to the organisation's classification under NIS2.
