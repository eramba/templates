# Supplier Management

## Purpose
To ensure that information security risks arising from third-party suppliers and service providers are identified, managed, and monitored.

## Scope
All third-party suppliers, vendors, and service providers that access, process, store, or transmit organizational information.

## Responsibilities

- **Procurement / Contract Management**: Ensure security requirements are included in supplier contracts.
- **Information Security**: Define supplier security requirements, assess risk, and monitor compliance.
- **Business Owners**: Identify supplier relationships and their associated data flows.

## Policy Statements

### Supplier Risk Assessment

**Applies to all frameworks**
- All suppliers with access to sensitive information must be subject to a security risk assessment prior to engagement.
- A register of suppliers and their associated services and data access must be maintained.
- Supplier risk must be reassessed periodically and upon material changes to the relationship.

**PCI DSS v4.0.1 (12.8.1, 12.8.2)**
- A list of all third-party service providers (TPSPs) with access to cardholder data must be maintained.

**ISO 27002:2022 (5.19, 5.21)**
- Supplier risk assessment must consider information security risks introduced through the supply chain.

**NIST 800-53 Rev5 (SR-2, SR-3, SA-12)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (15.1, 15.2, 15.3)**

**SCF 2025 (TPM-01, TPM-02, TPM-03)**

**NIS2 Article 21 (Article 21(2) (d), Article 21(3))**

**ISO 37001:2025 (37001:2025 - 8.6, 37001:2025 - 8.7)**

**ISO 9001:2015 (8.4.1)**

### Supplier Contracts

**Applies to all frameworks**
- Contracts must include information security requirements and data protection obligations.
- Suppliers must notify the organization of security incidents affecting organizational data without undue delay.

**PCI DSS v4.0.1 (12.8.3, 12.8.4)**
- Contracts must specify each party's responsibilities for protecting cardholder data.
- Right-to-audit clauses must be included in contracts with suppliers that handle cardholder data.

**ISO 27002:2022 (5.22)**
- Contracts must specify data handling, retention, and disposal requirements.

**NIST 800-53 Rev5 (SR-5, SA-9)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (15.4, 15.5)**

**SCF 2025 (TPM-04, TPM-05, TPM-06)**

**NIS2 Article 21 (Article 21(2) (d))**

**ISO 27701:2025 (A.1.4.2, A.1.4.3, A.2.4.2, A.2.4.3)**

**ISO 37001:2025 (37001:2025 - 8.7, 37001:2025 - 8.8)**

**ISO 9001:2015 (8.4.2, 8.4.3)**

### Supplier Monitoring

**Applies to all frameworks**
- Supplier compliance with security requirements must be monitored on an ongoing basis.
- Suppliers must provide evidence of security controls at defined intervals.

**PCI DSS v4.0.1 (12.8.5)**
- The organization must maintain a program to monitor TPSP PCI DSS compliance status at least annually.

**ISO 27002:2022 (5.22, 5.23)**
- Significant changes to supplier services or subcontractor arrangements must be reviewed for security impact.

**NIST 800-53 Rev5 (SR-6, SA-9)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**CIS Controls v8.1 (15.6, 15.7)**

**SCF 2025 (TPM-07, TPM-08, TPM-09)**

**NIS2 Article 21 (Article 21(2) (d))**

**ISO 27701:2025 (A.1.4.4, A.1.4.5, A.2.4.4)**

**ISO 37001:2025 (37001:2025 - 8.8)**

**ISO 9001:2015 (8.4.1, 8.4.2)**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Supplier Onboarding

**Applies to all frameworks**
1. Conduct a security risk assessment based on data access and service criticality.
2. Include required security clauses in the contract.
3. Obtain evidence of supplier security posture.
4. Register supplier in the supplier inventory.
5. Confirm the contract specifies each party's responsibilities for protecting cardholder data.
6. Include a right-to-audit clause for suppliers handling cardholder data.
7. Assess supply chain risks including subcontractors used by the supplier.

**PCI DSS v4.0.1 (12.8.1, 12.8.2, 12.8.3)**
8. Confirm contract specifies responsibilities for protecting cardholder data per 12.8.2.
9. Confirm due diligence was performed before engagement per 12.8.3.
10. Confirm supplier is added to the TPSP list per 12.8.1.

**SOC 2 (TSP 2017) (CC9.2)**
11. Confirm specific security requirements are established for the engagement per CC9.2.

### Supplier Review

**Applies to all frameworks**
1. Schedule periodic review per risk tier.
2. Request updated security evidence or conduct an assessment.
3. Document findings and track remediation of gaps.
4. Update the supplier risk register.
5. Confirm the supplier's PCI DSS compliance status is reviewed at least annually.
6. Review whether any changes to the supplier's services or subcontractors affect the risk assessment.

**PCI DSS v4.0.1 (12.8.4, 12.8.5)**
7. Confirm TPSP PCI DSS compliance status is reviewed at least annually per 12.8.4.
8. Confirm information about shared vs TPSP-managed requirements is maintained per 12.8.5.

**SOC 2 (TSP 2017) (CC9.2)**
9. Confirm supplier compliance with commitments is assessed and documented per CC9.2.

### Supplier Offboarding

**Applies to all frameworks**
1. Notify the supplier of contract termination and effective date.
2. Revoke all supplier access to systems, networks, and data.
3. Confirm the supplier has returned or securely destroyed all organisational data.
4. Obtain written confirmation of data destruction where applicable.
5. Remove the supplier from the active supplier register and retain a closed record.
6. Confirm removal of all CDE access; verify via access logs.
7. Confirm any surviving confidentiality obligations are communicated to the supplier.

**PCI DSS v4.0.1 (12.8.1)**
8. Confirm TPSP is removed from the active TPSP list and records are retained per 12.8.1.

**SOC 2 (TSP 2017) (CC9.2)**
9. Confirm vendor access and data are terminated per CC9.2.
