# Cryptography

## Purpose
To ensure cryptographic controls are used consistently and correctly to protect the confidentiality, integrity, and authenticity of information throughout its lifecycle.

## Scope
This document applies to all information systems, applications, infrastructure, data, and communications that use cryptographic mechanisms to protect information managed or processed by the organization.

## Responsibilities
- **System Owners** are responsible for ensuring cryptographic controls are implemented in the systems they own in accordance with this document.
- **The Information Security function** is responsible for defining cryptographic requirements, approved algorithms, and key management principles, with the support of system owners and cryptography specialists where applicable.
- **Administrators and Engineers** are responsible for implementing and maintaining cryptographic controls as defined.
- **Users** are responsible for complying with cryptographic requirements and not bypassing security controls.

## Policy Statements
- Cryptographic controls must be used to protect sensitive and confidential information **in accordance with the organization’s Data Management and Data Classification policy**.
- Cryptography must be implemented using approved algorithms, protocols, and key management practices.
- Cryptographic keys must be protected throughout their lifecycle.
- Weak or deprecated cryptographic mechanisms must not be used.
- Cryptographic implementations must support auditability and traceability.

## Standards

### Data at Rest
- Data at rest must be encrypted where required using organization-approved cryptographic algorithms.
- Encryption for data at rest must be based on **AES** algorithms.
- Strong encryption modes must be used where supported.

### Data in Transit
- Data in transit must be protected using secure cryptographic protocols.
- **TLS** must be used for protecting data in transit.
- Deprecated or insecure protocols must be disabled.

### Virtual Private Networks (VPNs)
- VPN connections must use secure cryptographic protocols.
- VPNs must use **TLS or IPsec** with approved encryption algorithms.

### Cryptographic Keys and Certificates
- Cryptographic keys and certificates must be generated using approved computerized methods where technically feasible.
- Cryptographic keys must be stored in encrypted form.
- Keys, certificates, and secrets must be stored in an organization-approved password or key management system.
- Access to cryptographic keys must be restricted based on least privilege.
- Cryptographic keys and certificates must be rotated at least annually or sooner if compromised.
- Key generation, storage, rotation, and revocation processes must be protected from unauthorized modification.

### Approved and Disallowed Cryptography
- Only strong, industry-accepted cryptographic algorithms and protocols may be used.
- Deprecated or weak algorithms and protocols must be disabled, including but not limited to:
  - SSL and early TLS versions
  - DES, 3DES, RC4
  - MD5 and SHA-1

### Logging
- Cryptographic operations related to key management must be logged.
- Logs must comply with the Logging & Monitoring requirements.

## Procedures

### Cryptographic Key Management Procedure
1. Cryptographic requirements are identified based on data classification and system risk.
2. Keys and certificates are generated using approved methods.
3. Keys are stored securely in approved management systems.
4. Access to keys is granted based on least privilege.
5. Keys and certificates are rotated according to defined schedules.
6. Compromised or obsolete keys are revoked promptly.
7. Key management activities are logged and monitored.

### Cryptographic Configuration Procedure
1. Systems are configured to use approved cryptographic algorithms and protocols.
2. Deprecated or weak cryptographic options are disabled.
3. Cryptographic configurations are reviewed periodically.
4. Changes to cryptographic configurations follow the change management process.
