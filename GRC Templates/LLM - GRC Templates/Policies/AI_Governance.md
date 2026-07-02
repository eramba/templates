# AI Governance

## Purpose
To ensure the organisation develops, deploys, and uses artificial intelligence systems in a responsible, transparent, and accountable manner, managing AI-specific risks throughout the AI system lifecycle.

## Scope
All AI systems developed, procured, or used by the organisation, including machine learning models, automated decision systems, and AI-enabled products or services.

## Responsibilities

- **AI Governance Committee / Senior Management**: Set AI strategy, approve high-risk AI use cases, and oversee organisational AI risk.
- **AI System Owners**: Ensure AI systems under their ownership comply with this policy and are subject to appropriate impact assessments.
- **Development / Data Science Teams**: Implement responsible AI practices throughout the development lifecycle.
- **Information Security**: Assess and address security risks specific to AI systems.
- **Legal / Compliance**: Identify applicable AI regulations and ensure compliance.

## Policy Statements

### AI Policy and Governance Framework

**Applies to all frameworks**
- The organisation must establish and maintain a policy for responsible AI use.
- Roles and responsibilities for AI governance must be defined and assigned.
- AI-specific risks must be identified and assessed as part of the organisation's risk management process.
- Objectives for responsible AI must be established and monitored.

**ISO 42001:2023 (5.1, 5.2, 5.3, 6.1.1, A.2.2, A.2.3, A.2.4)**
- The organisation must determine its role with respect to AI systems (provider, producer, customer, or partner) and apply appropriate controls.
- AI governance must address transparency, fairness, accountability, and human oversight.

### AI Risk and Impact Assessment

**Applies to all frameworks**
- An impact assessment must be conducted for all AI systems prior to deployment, covering potential harms to individuals, groups, and society.
- AI risk assessments must consider bias, fairness, transparency, and unintended consequences.
- High-risk AI systems must be subject to enhanced review and approval before deployment.
- Impact assessments must be reviewed when AI systems are materially updated.

**ISO 42001:2023 (6.1.2, 6.1.3, A.5.2, A.5.3, A.5.4, A.5.5)**
- The organisation must assess societal, ethical, and legal impacts of AI systems in addition to operational risks.

### AI System Lifecycle Management

**Applies to all frameworks**
- AI systems must be managed throughout their lifecycle including design, development, testing, deployment, monitoring, and decommissioning.
- AI system documentation must be maintained including intended purpose, data sources, model architecture, and known limitations.
- AI systems must be monitored in production for performance degradation, drift, and unintended outputs.
- Decommissioned AI systems must be retired in a controlled manner with data handled per classification requirements.

**ISO 42001:2023 (8.1, 8.2, 8.3, 8.4, A.6.1.2, A.6.1.3, A.6.2.2, A.6.2.3, A.6.2.4, A.6.2.5, A.6.2.6, A.6.2.7, A.6.2.8)**
- Data used for AI training, validation, and testing must be managed for quality, representativeness, and bias.
- Human oversight mechanisms must be built into AI systems where decisions have significant impact on individuals.

### Third-Party AI and Data

**Applies to all frameworks**
- Third-party AI systems and components must be assessed for risk prior to procurement or integration.
- Contractual requirements for third-party AI must address transparency, performance standards, and incident notification.
- Data used in AI systems must be governed in accordance with the Information Classification policy.

**ISO 42001:2023 (A.10.2, A.10.3, A.10.4, A.7.2, A.7.3, A.7.4, A.7.5, A.7.6)**
- The provenance, quality, and representativeness of training data from third parties must be documented and assessed.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above.*

## Procedures

### AI Impact Assessment

**Applies to all frameworks**
1. Identify the AI system, its intended purpose, and the population affected.
2. Assess potential harms: bias, privacy, safety, security, and broader societal impact.
3. Classify the AI system by risk level (high, medium, low).
4. For high-risk systems, obtain approval from the AI Governance Committee before deployment.
5. Document the assessment and retain as evidence.
6. Schedule reassessment upon material changes to the system or its use.

**SCF 2025 (AAT-09, AAT-10)**
7. Document AI risks and potential impacts per AAT-09.
8. Implement AI Test, Evaluation, Validation and Verification practices per AAT-10.

**ISO 42001:2023 (6.1.4, 8.4, A.5.2, A.5.3, A.5.4, A.5.5)**
9. Perform AI system impact assessments at planned intervals or when significant changes occur per 6.1.4 and 8.4.
10. Document results of AI impact assessments per A.5.3.
11. Assess and document potential impacts on individuals and groups per A.5.4.
12. Assess and document potential societal impacts per A.5.5.

### AI System Monitoring

**Applies to all frameworks**
1. Define performance metrics and acceptable thresholds for the AI system.
2. Monitor system outputs and performance against defined thresholds in production.
3. Investigate anomalies, unexpected outputs, or performance degradation.
4. Implement corrective actions where issues are identified.
5. Report significant issues to the AI system owner and governance committee.
6. Document monitoring results and retain records.

**SCF 2025 (AAT-16)**
7. Monitor functionality and behaviour of deployed AI systems per AAT-16.

**ISO 42001:2023 (9.1, A.6.2.6, A.8.4, A.8.5)**
8. Monitor and evaluate AI management system performance per 9.1.
9. Define and document necessary elements for ongoing AI system operation including monitoring per A.6.2.6.
10. Provide capabilities for interested parties to report adverse impacts per A.8.3.
11. Document plan for communicating AI incidents to users per A.8.4.
12. Document obligations to report information about the AI system per A.8.5.

### AI System Impact Assessment Review Procedure

**Applies to all frameworks**
1. Inventory all AI systems in production.
2. Confirm each has a current impact assessment (reviewed within last 12 months or after material change).
3. Review assessment for completeness: individual impacts, societal impacts, bias, and privacy.
4. Confirm all high risks have documented treatment plans.
5. Obtain governance committee approval for high-risk systems.
6. Document review and retain as evidence.

### AI System Performance and Monitoring Review Procedure

**Applies to all frameworks**
1. Pull monitoring reports for all production AI systems for the review period.
2. Review metrics against defined performance thresholds.
3. Investigate anomalies or unexpected outputs.
4. Escalate significant issues to the AI governance committee.
5. Document findings and actions taken.
