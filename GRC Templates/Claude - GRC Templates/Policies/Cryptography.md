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

**SCF 2025 (CRY-01, CRY-02, CRY-03)**

**NIS2 Article 21 (Article 21(2) (h))**

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

**SCF 2025 (CRY-04, CRY-05, CRY-06, CRY-07)**

**NIS2 Article 21 (Article 21(2) (h))**

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

**SCF 2025 (CRY-08, CRY-09)**

**NIS2 Article 21 (Article 21(2) (h))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Key Generation and Distribution

**Applies to all frameworks**
1. Generate keys using approved tools and algorithms.
2. Distribute keys via secure, encrypted channels only.
3. Record key metadata in the key register.
4. Never transmit keys in plaintext.
5. Apply split knowledge and dual control for manual key management operations.
6. Formally designate key custodians and document their responsibilities.
7. Confirm key generation method is consistent with the classification of the data being protected.

**PCI DSS v4.0.1 (3.7.1, 3.7.2, 3.7.6, 3.7.8)**
8. Apply split knowledge and dual control for manual cleartext key operations per 3.7.6.
9. Obtain written acknowledgement from key custodians of their responsibilities per 3.7.8.
10. Confirm key generation method produces strong cryptographic keys per 3.7.1.

**SOC 2 (TSP 2017) (CC6.7)**
11. Confirm encryption keys are managed to protect data transmission and storage per CC6.7.

**ISO 27002:2022 (8.24)**
12. Implement rules for effective use of cryptography and key management per 8.24.

**NIST 800-53 Rev5 (SC-12)**
13. Establish and manage cryptographic keys in accordance with key management requirements per SC-12.

**SCF 2025 (CRY-09)**
14. Manage cryptographic keys to protect confidentiality, integrity, and availability per CRY-09.

**NIS2 Article 21 (Article 21(2) (h))**
15. Confirm cryptographic key management supports the encryption policy per Article 21(2)(h).

**ISO 27701:2025 (A.3.26)**
16. Apply rules for effective use of cryptography related to PII processing per A.3.26.

### Key Rotation and Retirement

**Applies to all frameworks**
1. Identify keys due for rotation per the defined schedule or upon suspected compromise.
2. Generate replacement key and re-encrypt affected data.
3. Revoke old key and update the key register.
4. Securely destroy retired key material.
5. Document the rotation event including date, custodians involved, and systems affected.
6. Confirm retired keys cannot be used to decrypt current data.

**PCI DSS v4.0.1 (3.7.4, 3.7.5, 3.7.7)**
7. Confirm key rotation occurs at cryptoperiod end or upon compromise per 3.7.4.
8. Confirm retired keys are prevented from use and securely destroyed per 3.7.5.
9. Confirm unauthorised key substitution is prevented per 3.7.7.

**SOC 2 (TSP 2017) (CC6.7)**
10. Confirm key retirement prevents access to encrypted data per CC6.7.

**ISO 27002:2022 (8.24)**
11. Implement key management per the cryptography policy including rotation at cryptoperiod end per 8.24.

**NIST 800-53 Rev5 (SC-12)**
12. Manage key lifecycle in accordance with defined key management requirements per SC-12.

**SCF 2025 (CRY-09)**
13. Manage cryptographic key rotation and retirement per CRY-09.

**NIS2 Article 21 (Article 21(2) (h))**
14. Confirm key rotation supports the cryptography and encryption policy per Article 21(2)(h).

**ISO 27701:2025 (A.3.26)**
15. Apply cryptographic key management controls for PII processing per A.3.26.

### Certificate Management

**Applies to all frameworks**
1. Maintain an inventory of all TLS/PKI certificates including expiry dates and responsible owners.
2. Monitor certificates for upcoming expiry; initiate renewal at least 30 days before expiry.
3. Generate a CSR and submit to the approved Certificate Authority.
4. Deploy the renewed certificate and verify correct installation.
5. Revoke and remove any expired or compromised certificates immediately.
6. Confirm only approved algorithms and key lengths are used in new or renewed certificates.
7. Verify that no TLS versions earlier than 1.2 are enabled on renewed endpoints.
8. Record certificate lifecycle events in the key management register.

**PCI DSS v4.0.1 (4.2.1)**
9. Confirm only trusted keys and certificates are accepted per 4.2.1.
10. Confirm TLS 1.2 or higher is enforced; earlier versions are disabled per 4.2.1.
11. Maintain inventory of trusted keys and certificates per 4.2.1.1.

**SOC 2 (TSP 2017) (CC6.7)**
12. Confirm certificates protect data transmission per CC6.7.

**ISO 27002:2022 (8.24)**
13. Define and implement rules for cryptographic key management including certificate lifecycle per 8.24.

**NIST 800-53 Rev5 (SC-17)**
14. Issue public key certificates under an approved certificate policy per SC-17.

**SCF 2025 (CRY-08, CRY-11, CRY-12)**
15. Securely implement PKI or obtain from a reputable provider per CRY-08.
16. Enable organisation-defined certificate authorities per CRY-11.
17. Monitor for new certificates issued for organisation-controlled domains per CRY-12.

**NIS2 Article 21 (Article 21(2) (h))**
18. Confirm certificate management supports the use of cryptography per Article 21(2)(h).

**ISO 27701:2025 (A.3.26)**
19. Apply cryptographic controls including certificate management for PII processing per A.3.26.
