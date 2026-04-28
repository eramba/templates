# Data Management

## Purpose
To ensure that organizational data is managed, protected, and handled appropriately throughout its lifecycle based on its value, sensitivity, and risk to the organization.

## Scope
This document applies to all data created, processed, stored, transmitted, or managed by the organization, regardless of format, system, or location.

## Responsibilities
- **Department Managers** are responsible for identifying and classifying data used within their areas.
- **System Owners** are responsible for ensuring data within their systems is managed in accordance with this document.
- **The Information Security function** is responsible for defining data management requirements and supporting departments in their application, in collaboration with Legal, Compliance, Department Owners, and System Administrators.
- **Users** are responsible for handling data in accordance with its classification and organizational policies.

## Policy Statements
- All organizational data must be classified according to its sensitivity and business impact.
- Data must be handled, stored, transmitted, and disposed of based on its classification.
- Data ownership must be clearly defined.
- Data must be protected against unauthorized access, disclosure, modification, or loss.
- Data management practices must support legal, regulatory, and contractual obligations.
- Personal data must be handled in accordance with applicable privacy and data protection laws.
- **All organizational data must be backed up to ensure availability and recoverability.**

## Standards

### Data Classification
- Data must be classified using the classification table defined below.
- Data classification must be determined by the data owner or responsible department.
- Classification must be reviewed and updated when data usage or sensitivity changes.
- Where technically feasible, data classification must be reflected using labels or metadata.

| Classification | Classification Criteria | Examples |
|---------------|-------------------------|----------|
| **Public** | Information intended to be accessible by anyone. Disclosure causes no harm to the organization or individuals. | Public website content, marketing materials, press releases |
| **Internal** | Information whose disclosure could cause limited operational or reputational impact but does not involve regulated or sensitive data. | Internal policies, procedures, internal communications, project documentation |
| **Confidential** | Information whose disclosure, modification, or loss could cause significant harm. Includes personally identifiable information (PII), regulated data, or sensitive business information. | Employee PII, customer PII, financial records, contracts |
| **Restricted** | Information whose disclosure, modification, or loss could cause severe or critical harm to the organization or individuals. Includes highly sensitive PII and security-critical data. | Authentication secrets, cryptographic keys, government identifiers, security configurations, M&A data |

### Data Inventory
- An organization-wide data inventory must exist.
- The data inventory must be maintained and kept up to date.
- The data inventory must identify, at a minimum:
  - Name or description of the data set
  - Data owner
  - Data classification
  - Type of data (e.g. business data, personal data, PII)
  - Applicable legal, regulatory, or contractual obligations
- The data inventory must be reviewed periodically and updated when significant changes occur.

### Data Storage
- Data must be stored only in locations appropriate to its classification.
- Encryption at rest is required for all data classifications except Public.
- Internal, Confidential, and Restricted data must only be stored in company-controlled systems or approved and controlled third-party systems.
- Restricted data must only be stored in systems with enhanced security controls.

### Data Transmission
- Data transmitted over networks must be protected based on its classification.
- PII and other sensitive data must be encrypted during transmission in accordance with the Cryptography policy.

### Data Retention and Disposal
- Data retention periods must be defined based on legal, regulatory, and business requirements.
- PII must not be retained longer than necessary.
- Data must be disposed of securely when no longer required.

### Data Access
- Access to data must be granted based on business need and least privilege.
- Access to PII must be restricted and reviewed when roles or responsibilities change.

### Data Backup and Recovery
- **All organizational data must be backed up at least daily.**
- **Backups must be retained for up to one (1) year**, unless shorter or longer retention is required by legal, regulatory, or contractual obligations.
- Backup scope, storage, encryption, and protection must follow the **Backup & Recovery Policy** and **Cryptography Policy**.
- Backup data containing PII must be protected in accordance with its classification.
- Backup restore capabilities must be tested in accordance with the Backup & Recovery Policy.

## Procedures

### Data Inventory Procedure
1. Systems and repositories that store or process organizational data are identified.
2. Data sets within each system are identified and documented.
3. For each data set, the required inventory attributes are recorded.
4. The data inventory is reviewed and updated periodically or when significant changes occur.

### Data Classification Procedure
1. Data owners identify the data they are responsible for.
2. Data is classified using the classification table.
3. Classification decisions are documented.
4. Classifications are reviewed periodically or when data usage changes.

### Data Retention and Disposal Procedure
1. Retention requirements are identified for each data type.
2. Data is retained for the defined period.
3. Data is securely disposed of when retention periods expire.
4. Disposal actions are documented where required.
