# Asset Management

## Purpose
To ensure information assets are identified, classified, owned, and managed throughout their lifecycle to protect them from unauthorized access, loss, or misuse.

## Scope
All information assets including hardware, software, data, and services owned or managed by the organization.

## Responsibilities

- **Asset Owners**: Maintain accuracy of asset records and ensure appropriate protection.
- **IT Operations**: Maintain the asset inventory and support asset lifecycle management.
- **Information Security**: Define asset classification requirements and monitor compliance.
- **All Staff**: Report new assets, changes, or disposals to the asset register.

## Policy Statements

### Asset Inventory

**Applies to all frameworks**
- A complete and accurate inventory of information assets must be maintained.
- Each asset must have a designated owner responsible for its protection.
- The inventory must be reviewed and updated at least annually and upon significant changes.

**PCI DSS v4.0.1 (12.3.3, 12.3.4)**
- Hardware and software assets in the cardholder data environment must be tracked including location, version, and lifecycle status.

**ISO 27001:2022 (8.1)**
- The asset inventory must support the scope of the information security management system.

**ISO 27002:2022 (5.9, 5.11)**
- Assets must be returned upon termination of employment or contract.

**NIST 800-53 Rev5 (CM-8)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (1.2, 1.3, 1.4, 1.5, 2.1, 2.2, 2.3)**

**SCF 2025 (AST-01, AST-02, AST-03, AST-04, AST-05)**

**NIS2 Article 21 (Article 21(2) (i))**

**ISO 9001:2015 (7.1.1, 7.1.2, 7.1.3)**

### Acceptable Use

**Applies to all frameworks**
- Acceptable use rules must be defined and communicated for all information assets.
- Personnel must use organizational assets only for authorized purposes.

**ISO 27002:2022**
- Use of personal devices for organizational purposes must be governed by a defined policy.

**NIST 800-53 Rev5 (CM-7, CM-10, CM-11)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (2.4, 2.5, 2.6, 2.7)**

**SCF 2025 (AST-06, AST-07, AST-08)**

**NIS2 Article 21 (Article 21(2) (i))**

### Asset Disposal

**Applies to all frameworks**
- Assets must be securely wiped or destroyed before reuse or disposal.
- Asset disposal must be documented and records retained.

**ISO 27002:2022 (5.11, 5.14)**
- Disposal method must be appropriate to the classification of information the asset contained.

**NIST 800-53 Rev5 (MP-6, MP-7)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (2.7)**

**SCF 2025 (AST-09, AST-10, AST-11)**

**NIS2 Article 21 (Article 21(2) (i))**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Asset Registration

**Applies to all frameworks**
1. Identify new asset (hardware, software, data store, or service).
2. Record asset details: type, owner, location, classification, and lifecycle dates.
3. Assign asset owner and communicate responsibilities.
4. Confirm whether the asset is in scope for the CDE; update CDE asset list if applicable.
5. Confirm asset is reflected in the ISMS asset inventory.

**PCI DSS v4.0.1 (12.3.3, 12.5.1)**
6. Confirm whether asset is in scope for the CDE; update CDE asset list if applicable per 12.5.1.
7. Confirm asset description includes function/use per 12.5.1.

**ISO 27001:2022 (8.1)**
8. Confirm asset is recorded in the ISMS asset inventory per 8.1.

**SOC 2 (TSP 2017) (CC6.1)**
9. Confirm asset is included in the information asset inventory per CC6.1.

### Asset Inventory Review

**Applies to all frameworks**
1. Review the full asset inventory at least annually.
2. Confirm each asset has an assigned owner.
3. Identify and remove or retire assets no longer in use.
4. Update classification, location, or ownership where changes have occurred.
5. Document review completion and retain records.
6. Confirm CDE asset list is accurate and complete; reconcile against network scans.
7. Record the review as part of ISMS management review inputs.

**PCI DSS v4.0.1 (12.3.4, 12.5.1)**
8. Confirm CDE asset list is reconciled against network scans per 12.5.1.
9. Confirm hardware and software technology review includes analysis of ongoing vendor support per 12.3.4.

**ISO 27001:2022 (8.1)**
10. Confirm asset inventory supports ISMS scope and is current per 8.1.

**SOC 2 (TSP 2017) (CC6.1)**
11. Confirm information assets are identified, inventoried, and managed per CC6.1.

### Asset Disposal

**Applies to all frameworks**
1. Identify asset for disposal and confirm sensitive data presence.
2. Apply approved sanitisation method per data classification.
3. Remove asset from inventory and document disposal method and date.
4. Retain disposal certificate where applicable.
5. Confirm all cardholder data has been rendered unrecoverable before disposal.

**SOC 2 (TSP 2017) (CC6.5)**
6. Confirm logical and physical protections over the asset are discontinued appropriately per CC6.5.
