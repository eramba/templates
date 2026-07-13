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
### Prohibited AI Practices

**Applies to all frameworks**
- The organisation must not develop, deploy, or use AI systems that perform practices prohibited under applicable AI regulations.
- Personnel must be trained to recognise prohibited AI use cases and report concerns without reprisal.

**EU AI Act 2024/1689 (Art.5.1, Art.5.2, Art.5.3, Art.5.4)**
- AI systems that deploy subliminal techniques beyond a person's consciousness to distort behaviour are prohibited.
- AI systems that exploit vulnerabilities of specific groups due to age, disability, or social or economic circumstances are prohibited.
- AI systems used for social scoring by public authorities leading to detrimental treatment are prohibited.
- Real-time remote biometric identification systems in publicly accessible spaces for law enforcement are prohibited except under specific legal authorisations.

**OWASP Top 10 for LLM Applications 2025 (LLM06)**
- LLM-based systems must not be granted autonomous agency beyond their defined, least-privilege function.

### AI Literacy

**EU AI Act 2024/1689 (Art.4)**
- Providers and deployers must ensure sufficient AI literacy among staff and other persons dealing with AI system operation and use.
- AI literacy measures must be proportionate to the context of use, the technical complexity, and the impact of the AI system.

**NIST AI 600-1 2024 (GV-3.2-002, GV-3.2-003)**
- Acceptable use policies for AI interfaces and human-AI configurations must be defined and communicated.
- Roles and responsibilities must be adjusted to account for the oversight of AI systems across their lifecycle.

### LLM-Specific Security Risks

**Applies to all frameworks**
- Organisations deploying large language model applications must identify and manage LLM-specific security risks including prompt injection, sensitive data disclosure, supply chain integrity, output handling failures, and excessive agency.

**OWASP Top 10 for LLM Applications 2025 (LLM01, LLM02, LLM03, LLM04, LLM05, LLM06, LLM07, LLM08, LLM09, LLM10)**
- Prompt injection vulnerabilities must be mitigated through input validation, privilege separation, and adversarial testing.
- Sensitive information disclosure through LLM outputs must be prevented through output scanning, data minimisation, and access controls.
- LLM supply chain risks including compromised models, datasets, and plugins must be managed through vendor assessment and provenance verification.
- Data and model poisoning risks must be mitigated through training data governance and integrity controls.
- Improper output handling that could lead to downstream system exploitation must be addressed through output sanitisation.
- Excessive agency granted to LLM agents must be controlled through least-privilege configuration, human approval checkpoints, and resource limits.
- System prompt leakage must be prevented through secure prompt design and access controls.
- Vector and embedding weaknesses in RAG systems must be addressed through access controls and data validation.
- Misinformation from LLM outputs must be mitigated through content verification, fact-checking, and clear AI disclosure.
- Unbounded resource consumption must be prevented through rate limiting, quotas, and usage monitoring.

**SCF 2025 (AAT-01, AAT-02, AAT-03, AAT-04, AAT-05, AAT-06, AAT-07, AAT-08, AAT-11, AAT-12, AAT-13, AAT-14, AAT-15, AAT-17, AAT-18, AAT-19, AAT-20, AAT-21, AAT-22, AAT-23, AAT-24, AAT-25, AAT-26, AAT-27, AAT-28, AAT-29, AAT-30, AAT-31, AAT-32)**
- Establish AI and autonomous technologies governance per AAT-01.
- Maintain situational awareness of AI and autonomous technologies per AAT-02.
- Define AI and autonomous technologies context per AAT-03.
- Develop AI and autonomous technologies business case per AAT-04.
- Provide AI and autonomous technologies training per AAT-05.
- Address AI and autonomous technologies fairness and bias per AAT-06.
- Make risk management decisions for AI and autonomous technologies per AAT-07.
- Assign responsibilities for AI and autonomous technologies per AAT-08.
- Implement robust stakeholder engagement for AI per AAT-11.
- Implement intellectual property protections for AI per AAT-12.
- Ensure stakeholder diversity in AI per AAT-13.
- Define AI requirements per AAT-14.
- Make viability decisions for AI per AAT-15.
- Implement harm prevention controls for AI per AAT-17.
- Implement risk tracking for AI per AAT-18.
- Ensure AI conformity per AAT-19.
- Implement AI development practices per AAT-20.
- Register AI systems per AAT-21.
- Govern AI deployment per AAT-22.
- Mark AI outputs per AAT-23.
- Conduct real world testing of AI per AAT-24.
- Manage AI system value chain per AAT-25.
- Implement AI testing techniques per AAT-26.
- Implement AI output filtering per AAT-27.
- Implement AI model resilience per AAT-28.
- Implement AI agent governance per AAT-29.
- Implement agentic output traceability per AAT-30.
- Control human-in-the-loop workload and manipulation per AAT-31.
- Govern robotic process automation per AAT-32.

### AI Quality Management System

**EU AI Act 2024/1689 (Art.17.1, Art.17.2)**
- Providers of high-risk AI systems must put a quality management system in place documented in a systematic and orderly manner covering all aspects of compliance with the Regulation.
- Implementation of the quality management system must be proportionate to the size of the provider's organisation.

**NIST AI 600-1 2024 (GV-4.1-001, GV-4.1-002, GV-4.1-003)**
- Policies, procedures, and processes must address continual improvement for AI risk measurement.
- Policies must address risks associated with lack of explainability and transparency in AI systems.
- Oversight functions must be established across the AI lifecycle including procurement, design, and deployment.

### AI Corrective Actions and Duty of Information

**EU AI Act 2024/1689 (Art.20.1, Art.20.2, Art.82.2)**
- Providers who consider or have reason to consider that a high-risk AI system is not in conformity with requirements must take corrective action, withdraw, recall, or disable the system as appropriate.
- Where a high-risk AI system presents a risk, the provider shall immediately investigate the causes and take corrective measures.
- Corrective action shall be applied to all AI systems of the same type that present the same risk.

**NIST AI 600-1 2024 (MG-2.4-001, MG-2.4-002, MG-2.4-003, MG-2.4-004)**
- Specific criteria for deactivation of GAI systems must be established and regularly reviewed in accordance with risk tolerances.
- Procedures for escalating GAI system incidents to the organisational risk management authority must be maintained.
- Communication plans must be in place to inform AI stakeholders as part of the deactivation process.

### Procedures

### AI Prohibited Practices Compliance Review Procedure

**Applies to all frameworks**
1. Review the inventory of AI systems in use or development against the prohibited practices list.
2. Confirm no system performs subliminal manipulation, exploits vulnerability of specific groups, or performs prohibited biometric identification.
3. Obtain legal/compliance sign-off on the review.
4. Document the review outcome and retain as evidence.

**EU AI Act 2024/1689 (Art.5.1, Art.5.2, Art.5.3, Art.5.4)**
5. Confirm review specifically addresses all sub-provisions of Article 5 including real-time remote biometric identification.
6. Document any borderline cases with legal counsel assessment.

### AI Literacy Programme Procedure

**Applies to all frameworks**
1. Identify all personnel who develop, operate, deploy, or use AI systems.
2. Assess current AI literacy levels against role requirements.
3. Deliver training appropriate to role, context, and AI system risk level.
4. Document completion and retain records.

**EU AI Act 2024/1689 (Art.4)**
5. Ensure AI literacy measures are proportionate to the technical complexity and impact of AI systems in scope.

**NIST AI 600-1 2024 (GV-3.2-002, GV-3.2-003)**
5. Include training on acceptable use policies and human-AI configuration responsibilities.

### LLM Security Controls Review Procedure

**Applies to all frameworks**
1. Identify all LLM-based applications and agentic systems in production.
2. Assess each system against the OWASP LLM Top 10 risk categories.
3. Confirm controls are in place for prompt injection, sensitive data disclosure, output handling, supply chain, and resource limits.
4. Document findings and assign remediation owners.
5. Retest after remediation.

**OWASP Top 10 for LLM Applications 2025 (LLM01, LLM02, LLM05, LLM06, LLM07, LLM10)**
6. Include adversarial prompt testing in scope.
7. Confirm agent permission boundaries are enforced technically, not only by policy.

**NIST AI 600-1 2024 (MS-2.7-007, MS-2.6-006)**
6. Include red-team scenarios covering misuse, manipulation, and abuse facilitation.

### AI Corrective Action Procedure

**Applies to all frameworks**
1. Identify the non-conformity or risk presented by the AI system.
2. Investigate the root cause.
3. Take corrective action: update the system, withdraw from service, or apply a temporary mitigation.
4. Communicate to affected deployers or downstream parties as required.
5. Document the corrective action and outcome.

**EU AI Act 2024/1689 (Art.20.1, Art.20.2, Art.82.2)**
6. Confirm corrective action is applied to all AI systems of the same type presenting the same risk.
7. Notify the market surveillance authority where required.

### AI Roles and Responsibilities

**ISO 42001:2023 (A.3.2)**
- Roles and responsibilities for AI shall be defined and allocated according to the needs of the organisation, covering the design, development, deployment, operation, monitoring, and decommissioning of AI systems.
- Role definitions shall include accountability for AI system outcomes, oversight, and escalation.

**ISO 42001:2023 (A.3.3)**
- The organisation shall define and put in place a process to report concerns about the organisation's role with respect to AI, including concerns about AI system behaviour, ethical issues, and safety incidents.
- Personnel shall be able to raise concerns without fear of reprisal.

### AI Objectives

**ISO 42001:2023 (6.2)**
- The organisation shall establish AI objectives at relevant functions and levels consistent with the AI policy.
- AI objectives shall be measurable, monitored, communicated, and updated as appropriate.
- Plans to achieve AI objectives shall include what will be done, resources required, responsibility, timeline, and how results will be evaluated.

### AI Operational Planning

**ISO 42001:2023 (8.1)**
- The organisation shall plan, implement, and control the processes needed to meet requirements and to implement the actions determined in risk treatment.
- Planned changes shall be carried out in a controlled manner and unintended changes reviewed to mitigate adverse effects.
- The organisation shall ensure that externally provided AI-related processes, products, or services are controlled.

### AI Resource Documentation

**ISO 42001:2023 (A.4.2, A.4.3, A.4.4, A.4.5, A.4.6)**
- The organisation shall identify and document relevant resources required for the activities at each AI system lifecycle stage.
- Data resources used in AI system development and operation shall be documented including source, type, ownership, and data governance arrangements.
- Tooling resources used for AI development, testing, and deployment shall be documented.
- System and computing resources required for AI system operation shall be documented including infrastructure, cloud services, and dependencies.
- Human resources and their competencies required for AI system activities shall be documented.

### AI Roles and Concerns Procedure

**ISO 42001:2023 (A.3.2, A.3.3)**
1. Confirm AI roles and responsibilities are documented and assigned to named individuals.
2. Confirm role definitions cover the full AI lifecycle including design, development, deployment, and monitoring.
3. Confirm the concerns reporting process is communicated to all personnel involved in AI activities.
4. Review concerns raised during the period and confirm they were addressed appropriately.
5. Document role changes and update assignments as the AI programme evolves.

### AI Objectives Review Procedure

**ISO 42001:2023 (6.2)**
1. Review AI objectives for the current period and assess achievement.
2. Confirm AI objectives remain aligned with the AI policy and organisational strategy.
3. Set or update AI objectives for the next period with measurable targets.
4. Confirm plans to achieve objectives include assigned responsibilities and timelines.
5. Document and communicate updated objectives to relevant functions.

### AI Resource Documentation Review Procedure

**ISO 42001:2023 (A.4.2, A.4.3, A.4.4, A.4.5, A.4.6)**
1. Review the AI resource inventory covering data, tooling, infrastructure, and human resources.
2. Confirm all resources used in active AI systems are documented.
3. Confirm data resource documentation includes source, ownership, and governance arrangements.
4. Confirm infrastructure and cloud resource documentation is current.
5. Confirm human resource competency records are up to date for all AI roles.
6. Update documentation for any new or changed resources.

### AI System User Information and Responsible Use

**ISO 42001:2023 (A.8.2, A.8.3)**
- The organisation shall determine and provide the necessary information to users of the AI system, including capabilities, limitations, and instructions for safe use per A.8.2.
- The organisation shall provide capabilities for interested parties to report adverse impacts of the AI system per A.8.3.

**ISO 42001:2023 (A.9.2, A.9.3, A.9.4)**
- The organisation shall define and document the processes for the responsible use of AI systems per A.9.2.
- The organisation shall identify and document objectives to guide the responsible use of AI systems per A.9.3.
- The organisation shall ensure that the AI system is used according to its intended uses and accompanying documentation per A.9.4.

### AI User Information and Responsible Use Procedure

**ISO 42001:2023 (A.8.2, A.8.3, A.9.2, A.9.3, A.9.4)**
1. Confirm user documentation exists for all deployed AI systems covering capabilities, limitations, and safe use instructions.
2. Confirm mechanisms exist for users and interested parties to report adverse AI system impacts.
3. Confirm responsible use processes are documented and communicated to relevant personnel.
4. Confirm responsible use objectives are documented and monitored.
5. Review any reports of adverse impacts received during the period and confirm they were addressed.
6. Confirm AI systems are being used within their documented intended use boundaries.
