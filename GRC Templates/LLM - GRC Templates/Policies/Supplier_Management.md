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

**ISO 27002:2022 (5.19, 5.2, 5.23)**
12. Define and implement processes to manage information security risks from supplier products per 5.19.
13. Establish and agree information security requirements with each supplier per 5.2.
14. Establish processes for cloud services acquisition, use, and exit per 5.23.

**NIST 800-53 Rev5 (SR-5, SA-9)**
15. Employ acquisition strategies and contract tools to protect against supply chain risks per SR-5.
16. Require external service providers to comply with security requirements per SA-9.

**CIS Controls v8.1 (15.1, 15.2, 15.4)**
17. Maintain an inventory of service providers per 15.1.
18. Establish a service provider management policy per 15.2.
19. Ensure service provider contracts include security requirements per 15.4.

**SCF 2025 (TPM-01, TPM-04, TPM-05)**
20. Facilitate implementation of third-party management controls per TPM-01.
21. Mitigate risks associated with third-party access per TPM-04.
22. Require contractual security requirements from third parties per TPM-05.

**NIS2 Article 21 (Article 21(2) (d))**
23. Confirm supplier security requirements address supply chain security per Article 21(2)(d).

**ISO 27701:2025 (A.3.10)**
24. Ensure supplier agreements address PII processing security requirements per A.3.10.

**ISO 45001:2018 (8.1.4.1, 8.1.4.2)**
25. Control procurement for OH&S conformity per 8.1.4.1.
26. Coordinate with contractors to identify and control OH&S risks per 8.1.4.2.

**ISO 42001:2023 (A.10.3)**
27. Establish a process for supplier usage to align with responsible AI approach per A.10.3.

**ISO 37001:2025 (37001:2025 - 8.6)**
28. Require business associates to commit to anti-bribery compliance per 8.6.

**ISO 9001:2015 (8.4.1, 8.4.2)**
29. Control externally provided processes, products, and services per 8.4.1.
30. Communicate requirements to external providers per 8.4.3.

**ISO 45001:2018 (8.1.4.3)**
- Ensure outsourced functions and processes are controlled and that outsourcing arrangements are consistent with legal requirements and OH&S objectives per 8.1.4.3.

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

**ISO 27002:2022 (5.21, 5.22)**
10. Manage information security in the ICT supply chain per 5.21.
11. Monitor, review, and manage change in supplier security practices per 5.22.

**NIST 800-53 Rev5 (SR-6)**
12. Assess and review supply chain-related risks at defined frequencies per SR-6.

**CIS Controls v8.1 (15.3, 15.5, 15.6)**
13. Classify service providers per defined criteria per 15.3.
14. Assess service providers consistent with the management policy per 15.5.
15. Monitor service providers per the management policy per 15.6.

**SCF 2025 (TPM-08, TPM-09)**
16. Regularly review and assess external service providers for compliance per TPM-08.
17. Address weaknesses identified in supplier assessments per TPM-09.

**NIS2 Article 21 (Article 21(2) (d), Article 21(3))**
18. Confirm supplier review addresses supply chain security and supplier-specific vulnerabilities per Article 21(2)(d) and Article 21(3).

**ISO 9001:2015 (8.4.1)**
19. Evaluate and monitor external providers against requirements per 8.4.1.

**PCI DSS v4.0.1 (12.9.1, 12.9.2)**
- Obtain written agreements from TPSPs acknowledging they are responsible for the security of account data they possess or otherwise store, process, or transmit per 12.9.1.
- Support customers' requests for information to meet PCI DSS requirements 12.8.4 and 12.8.5 per 12.9.2.

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

**ISO 27002:2022 (5.22)**
10. Manage change in supplier services and exit processes per 5.22.

**NIST 800-53 Rev5 (SR-3)**
11. Establish supply chain controls and processes for disposal and transition per SR-3.

**CIS Controls v8.1 (15.7)**
12. Securely decommission service providers including account deactivation, data flows termination, and data disposal per 15.7.

**SCF 2025 (TPM-10)**
13. Control changes to third-party services including termination per TPM-10.

**ISO 27701:2025 (A.2.4.3)**
14. Ensure PII is returned, transferred, or disposed of securely at contract end per A.2.4.3.
### Third-Party GAI System Governance

**NIST AI 600-1 2024 (GV-6.1-001, GV-6.1-004, GV-6.1-005, GV-6.1-006, GV-6.1-007, GV-6.2-001, GV-6.2-003, GV-6.2-004, GV-6.2-006, GV-6.2-007)**
- All third-party entities with access to organisational AI content must be inventoried and an approved GAI technology and service provider list maintained.
- Contracts with third-party GAI providers must specify content ownership, usage rights, security requirements, and content provenance standards.
- A use-case-based supplier risk assessment framework must evaluate and monitor third-party GAI performance and adherence to standards.
- Continuous monitoring processes for third-party GAI systems in deployment must be established.
- Incident response plans for third-party GAI failures must be aligned with the organisation's incident response process.
- Vendor contracts must avoid arbitrary termination of critical GAI services and non-standard terms that amplify liability.

**EU AI Act 2024/1689 (Art.25.4, Art.53.1)**
- Contracts with third-party AI system and component providers must allocate responsibilities for compliance with applicable requirements.
- Organisations using GPAI models must ensure appropriate obligations are placed on model providers through contractual arrangements.

**OWASP Top 10 for LLM Applications 2025 (LLM03)**
- Third-party LLM supply chain components including base models, fine-tuning datasets, and plugins must be assessed for integrity and provenance before use.

### Third-Party GAI Governance Procedure

**Applies to all frameworks**
1. Inventory all third-party GAI systems, models, and services in use.
2. Conduct security assessment including review of model cards, SOC 2 reports, or equivalent.
3. Execute contract with required security, data, and content provenance clauses.
4. Establish continuous monitoring of third-party GAI system performance and safety.
5. Maintain incident response plan for third-party GAI failures.
6. Document review outcomes and retain records.

**NIST AI 600-1 2024 (GV-6.1-004, GV-6.1-005, GV-6.2-004)**
7. Confirm SLAs specify content ownership, usage rights, and content provenance standards.
8. Confirm monitoring processes detect deviations from agreed standards.

**EU AI Act 2024/1689 (Art.25.4)**
7. Confirm contractual responsibilities for compliance with AI Act requirements are allocated between parties.

**OWASP Top 10 for LLM Applications 2025 (LLM03)**
7. Confirm provenance and integrity verification is performed for all LLM supply chain components.
