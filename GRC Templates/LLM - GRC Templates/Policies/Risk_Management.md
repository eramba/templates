# Risk Management

## Purpose
To establish a systematic approach to identifying, assessing, treating, and monitoring information security risks across the organization.

## Scope
All information assets, processes, systems, and third-party relationships within the organization.

## Responsibilities

- **Risk Owner**: Accept or escalate residual risks within their domain.
- **Information Security**: Coordinate and maintain the risk management process.
- **Senior Management**: Review and approve risk treatment decisions and risk appetite.
- **All Staff**: Report identified risks to Information Security.

## Policy Statements

### Risk Identification and Assessment

**Applies to all frameworks**
- A formal risk assessment must be performed at least annually and upon significant changes.
- Risk assessments must identify threats, vulnerabilities, and potential impacts to information assets.
- Risk assessment results must be documented and retained.

**PCI DSS v4.0.1 (12.3.1, 12.3.2)**
- Risks specific to the cardholder data environment must be assessed separately and tracked to resolution.

**ISO 27001:2022 (6.1.1, 6.1.2)**
- Risks must be evaluated against defined likelihood and impact criteria consistent with the organization's risk acceptance criteria.
- The risk assessment process must be repeatable and produce consistent, valid, and comparable results.

**ISO 27002:2022 (5.7, 5.8)**
- Threat intelligence must be used to inform the risk assessment process.

**SOC 2 (TSP 2017) (CC3.1, CC3.2, CC3.3)**
- Risk assessment must consider fraud risk and risks arising from the use of third parties.

**NIST 800-53 Rev5 (RA-2, RA-3, RA-5)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (RSK-01, RSK-02, RSK-03, RSK-04)**

**NIS2 Article 21 (Article 21(1), Article 21(2) (a))**

**ISO 37001:2025 (37001:2025 - 6.1, 37001:2025 - 6.2)**

**ISO 9001:2015 (6.1.1, 6.1.2)**

### Risk Treatment

**Applies to all frameworks**
- Each identified risk must have a treatment decision: mitigate, accept, transfer, or avoid.
- Risk treatment plans must be documented with owners, timelines, and controls.

**ISO 27001:2022 (6.1.3, 8.3)**
- Residual risk must be formally accepted by an authorized risk owner.
- The Statement of Applicability must be maintained and kept current.

**SOC 2 (TSP 2017) (CC3.4, CC9.1)**
- Risk treatment must consider the impact on the organization's ability to meet its service commitments.

**NIST 800-53 Rev5 (CA-5)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (RSK-05, RSK-06, RSK-07)**

**NIS2 Article 21 (Article 21(2) (a), Article 21(2) (f))**

**ISO 37001:2025 (37001:2025 - 6.3)**

**ISO 9001:2015 (6.2.1, 6.2.2)**

### Continuous Risk Monitoring

**Applies to all frameworks**
- Significant changes to the risk landscape must trigger a reassessment.
- Risk register must be maintained and reviewed by senior management.

**ISO 27001:2022 (8.2, 9.1)**
- Risk treatment effectiveness must be monitored on an ongoing basis.

**SOC 2 (TSP 2017) (CC9.1, CC9.2)**
- Changes to the organization's objectives, operations, or technology must be evaluated for risk impact.

**NIST 800-53 Rev5 (CA-7)**
- Comply with NIST 800-53 Rev5 controls applicable to this area; organisation-defined parameters must be documented in the system security plan.

**SCF 2025 (RSK-08, RSK-09, RSK-10)**

**NIS2 Article 21 (Article 21(2) (f))**

**ISO 9001:2015 (9.1.1, 9.3.1, 9.3.2, 9.3.3)**

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above. Each standard section inherits the framework grouping of its parent policy statement section.*

## Procedures

### Risk Assessment

**Applies to all frameworks**
1. Define scope and objectives of the risk assessment.
2. Identify and catalogue information assets in scope.
3. Identify applicable threats and vulnerabilities per asset.
4. Evaluate likelihood and impact; calculate risk level.
5. Document findings in the risk register.
6. Ensure CDE assets and processes are explicitly included in scope.
7. Apply the organisation's defined risk criteria consistently to produce comparable results.
8. Obtain management review and sign-off on assessment results.
9. Incorporate relevant threat intelligence into the assessment.
10. Include fraud risk and third-party risks in the assessment scope.

**PCI DSS v4.0.1 (12.3.1, 12.3.2)**
11. Confirm CDE assets and processes are explicitly included in scope and documented per 12.3.1.

**ISO 27001:2022 (6.1.1, 6.1.2, 8.2)**
12. Apply the organisation's defined risk criteria to produce consistent, comparable results per 6.1.2.
13. Retain documented evidence of assessment results per 8.2.

**SOC 2 (TSP 2017) (CC3.1, CC3.2, CC3.3)**
14. Include fraud risk and third-party risks in the assessment scope per CC3.3.

**NIST 800-53 Rev5 (RA-3, RA-2)**
15. Categorise the system and document the security categorisation per RA-2.
16. Identify threats, vulnerabilities, likelihood, and magnitude of harm per RA-3.

**SCF 2025 (RSK-03, RSK-04, RSK-05)**
17. Assign a risk ranking to identified risks per RSK-05.
18. Document risks per defined identification and assessment processes per RSK-03 and RSK-04.

**NIS2 Article 21 (Article 21(2) (a))**
19. Confirm risk analysis includes information system security policies proportionate to entity classification per Article 21(2)(a).

**ISO 27701:2025 (6.1.2, 8.2)**
20. Conduct privacy risk assessment covering risks to PII principals per 6.1.2 and 8.2.

**ISO 45001:2018 (6.1.2.1, 6.1.2.2)**
21. Identify hazards and assess OH&S risks and opportunities per 6.1.2.1, 6.1.2.2, and 6.1.2.3.

**ISO 42001:2023 (6.1.2, 8.2)**
22. Conduct AI risk assessment per 6.1.2 and 8.2.

**ISO 37001:2025 (37001:2025 - 4.5.1, 37001:2025 - 4.5.2)**
23. Identify and document bribery risks at planned intervals per 4.5.1 and 4.5.2.

**ISO 9001:2015 (6.1.1, 6.1.2)**
24. Determine risks and opportunities relevant to the QMS per 6.1.1 and 6.1.2.

### Risk Treatment

**Applies to all frameworks**
1. For each identified risk, select a treatment option: mitigate, accept, transfer, or avoid.
2. Define specific controls or actions for mitigation.
3. Assign ownership and target completion dates.
4. Obtain formal acceptance of residual risk from the risk owner.
5. Update the risk register with treatment decisions.
6. Update the Statement of Applicability to reflect treatment decisions.
7. Confirm treatment decisions are consistent with service commitments and system requirements.

**ISO 27001:2022 (6.1.3, 8.3)**
8. Update the Statement of Applicability to reflect treatment decisions per 6.1.3.
9. Retain documented results of the risk treatment process per 8.3.

**SOC 2 (TSP 2017) (CC3.4, CC9.1)**
10. Confirm treatment decisions are consistent with service commitments per CC3.4 and CC9.1.

**NIST 800-53 Rev5 (RA-7)**
11. Respond to findings with actions consistent with organisational risk tolerance per RA-7.

**SCF 2025 (RSK-06)**
12. Implement risk remediation to bring risks to an acceptable level per RSK-06.

**NIS2 Article 21 (Article 21(2) (a))**
13. Confirm treatment measures are proportionate and all-hazards based per Article 21(2)(a).

**ISO 27701:2025 (6.1.3, 8.3)**
14. Implement the privacy risk treatment plan per 6.1.3 and 8.3.

**ISO 45001:2018 (6.1.4)**
15. Plan actions to address OH&S risks and opportunities per 6.1.4.

**ISO 42001:2023 (6.1.3, 8.3)**
16. Implement the AI risk treatment plan per 6.1.3 and 8.3.

**ISO 37001:2025 (37001:2025 - 6.1)**
17. Define and implement actions to manage bribery risks per 6.1.

**ISO 9001:2015 (6.1.2)**
18. Integrate risk treatment actions into QMS processes per 6.1.2.

### Risk Register Review

**Applies to all frameworks**
1. Review the risk register at defined intervals and upon significant changes.
2. Confirm treatment actions are progressing per plan; escalate overdue items.
3. Identify new or changed risks arising from operational or environmental changes.
4. Update risk ratings where circumstances have changed.
5. Present review outcomes to senior management.
6. Record the review as part of management review inputs per clause 9.3.
7. Assess whether changes to the organisation's objectives or technology affect existing risk ratings.

**ISO 27001:2022 (9.1, 9.3.1, 9.3.2, 9.3.3)**
8. Present review results to top management; record as input to management review per 9.3.2.

**SOC 2 (TSP 2017) (CC9.1)**

**NIST 800-53 Rev5 (CA-7, PM-6)**
9. Monitor risk continuously and update per CA-7 and PM-6.

**SCF 2025 (RSK-07, RSK-11)**
10. Routinely update risk assessments upon new vulnerabilities or changes per RSK-07 and RSK-11.

**NIS2 Article 21 (Article 21(2) (f))**
11. Confirm the effectiveness of risk management measures is assessed per Article 21(2)(f).

**ISO 27701:2025 (9.1)**
12. Monitor and evaluate privacy management system performance per 9.1.

**ISO 45001:2018 (9.1.1)**
13. Monitor and measure OH&S performance per 9.1.1.

**ISO 42001:2023 (9.1)**
14. Monitor and evaluate AI management system performance per 9.1.

**ISO 37001:2025 (37001:2025 - 9.1)**
15. Monitor, measure, analyse, and evaluate the ABMS per 9.1.

**ISO 9001:2015 (9.1.1, 9.1.3)**
16. Analyse and evaluate QMS data and performance per 9.1.1 and 9.1.3.

### Threat Intelligence Review

**PCI DSS v4.0.1 (12.3.1)**
1. Document intelligence review as part of the targeted risk analysis per 12.3.1.

**ISO 27002:2022 (5.7)**
2. Collect and analyse threat intelligence to produce actionable output per 5.7.

**NIST 800-53 Rev5 (SI-5, PM-16)**
3. Receive security alerts and advisories on an ongoing basis per SI-5.
4. Implement a threat awareness programme for cross-organisation sharing per PM-16.

**SCF 2025 (THR-01, THR-03)**
5. Maintain situational awareness via threat intelligence feeds per THR-01 and THR-03.

### Threat Intelligence Review Procedure

**Applies to all frameworks**
1. Identify and access approved threat intelligence sources (e.g. government CERT, ISAC, commercial feed).
2. Review intelligence published since last review; identify threats relevant to the organisation's assets and sector.
3. Assess whether any new threats require a risk register update or immediate action.
4. Document findings and share with Information Security leadership.
5. Record review completion date and sources consulted.

### Legal and Regulatory Compliance Review

**ISO 27001:2022 (9.1)**
1. Retain documented information of compliance review results per 9.1.

**ISO 27002:2022 (5.31)**
2. Identify, document, and keep up to date applicable legal and regulatory requirements per 5.31.

**SCF 2025 (CPL-01, CPL-02)**
3. Facilitate identification and implementation of relevant requirements per CPL-01.
4. Provide controls oversight function reporting to leadership per CPL-02.

**ISO 27701:2025 (A.3.13)**
5. Document legal, statutory, regulatory, and contractual requirements related to PII processing per A.3.13.

**ISO 45001:2018 (6.1.3, 9.1.2)**
6. Determine and maintain access to current OH&S legal requirements per 6.1.3.
7. Evaluate compliance with legal requirements at defined intervals per 9.1.2.

**ISO 37001:2025 (37001:2025 - 6.2)**
8. Establish and maintain anti-bribery objectives per 6.2.

### Legal and Regulatory Compliance Review Procedure

**Applies to all frameworks**
1. Review and update the register of applicable legal and regulatory requirements.
2. Identify any new or changed requirements since last review.
3. Assess impact of changes on existing controls and policies.
4. Confirm compliance evidence is documented per requirement.
5. Retain review record with date and approver.

### Independent Information Security Review

**ISO 27001:2022 (9.2, 9.2.1, 9.2.2)**
1. Confirm reviewer is independent of the area being reviewed per 9.2.1.
2. Retain documented audit results per 9.2.1.

**SOC 2 (TSP 2017) (CC4.1)**
3. Confirm evaluation results are communicated to responsible parties per CC4.1.

**ISO 27002:2022 (5.35)**
4. Ensure the review covers people, processes, and technologies at planned intervals per 5.35.

**NIST 800-53 Rev5 (CA-2)**
5. Select appropriate assessor; develop and execute a control assessment plan per CA-2.

**SCF 2025 (IAO-02, CPL-03)**
6. Formally assess controls through Information Assurance Programme activities per IAO-02 and CPL-03.

**ISO 27701:2025 (A.3.15)**
7. Review the approach to managing information security related to PII processing independently per A.3.15.

**ISO 45001:2018 (9.2.1)**
8. Conduct independent OH&S internal audits per 9.2.1.

**ISO 42001:2023 (9.2.1)**
9. Conduct independent AI management system internal audits per 9.2.1.

**ISO 37001:2025 (37001:2025 - 9.2.1, 37001:2025 - 9.2.4)**
10. Ensure audit objectivity and impartiality through independent review per 9.2.4.

**ISO 9001:2015 (9.2.1)**
11. Conduct independent QMS internal audits per 9.2.1.

### Independent Information Security Review Procedure

**Applies to all frameworks**
1. Define scope and appoint an independent reviewer (internal audit, external auditor, or qualified third party).
2. Conduct review covering key controls, policies, and management processes.
3. Document findings with risk rating and management responses.
4. Track remediation of findings to closure.
5. Present results to senior management.

### Internal Information Security Compliance Review

**ISO 27001:2022 (9.1)**
1. Document monitoring and measurement results per 9.1.

**SOC 2 (TSP 2017) (CC4.1, CC4.2)**
2. Confirm deficiencies are communicated and tracked per CC4.1 and CC4.2.

**ISO 27002:2022 (5.36)**
3. Regularly review compliance with the information security policy and standards per 5.36.

**NIST 800-53 Rev5 (CA-7)**
4. Implement continuous monitoring and report results per CA-7.

**SCF 2025 (CPL-02, CPL-03)**
5. Provide cybersecurity controls oversight and review processes for conformity per CPL-02 and CPL-03.

**ISO 27701:2025 (A.3.16)**
6. Review compliance with PIMS policies and standards per A.3.16.

**ISO 45001:2018 (9.1.1)**
7. Monitor, measure, analyse, and evaluate OH&S performance per 9.1.1.

**ISO 42001:2023 (9.1)**
8. Monitor and evaluate AI management system performance per 9.1.

**ISO 37001:2025 (37001:2025 - 9.1)**
9. Monitor, measure, analyse, and evaluate the ABMS per 9.1.

**ISO 9001:2015 (9.1.1, 9.1.3)**
10. Analyse and evaluate QMS performance per 9.1.1 and 9.1.3.

### Internal Information Security Compliance Review Procedure

**Applies to all frameworks**
1. Execute compliance review checklist across major policy areas.
2. Document non-compliances with policy reference, description, and owner.
3. Assign remediation deadlines.
4. Track resolution of findings from prior reviews.
5. Report overall compliance status to management.

### Internal Audit Execution

**ISO 27001:2022 (9.2, 9.2.1, 9.2.2)**
1. Confirm auditors are independent of the area being audited per 9.2.2.
2. Document the audit programme with frequency, methods, and responsibilities per 9.2.2.
3. Retain documented information of audit results as evidence per 9.2.1.

**SOC 2 (TSP 2017) (CC4.1)**
4. Confirm audit results are evaluated and communicated to responsible parties per CC4.1.

**ISO 27002:2022 (5.35)**
5. Confirm the review covers people, processes, and technologies per 5.35.

**NIST 800-53 Rev5 (CA-2)**
6. Conduct control assessments to determine whether controls are implemented correctly per CA-2.

**SCF 2025 (CPL-04, IAO-02)**
7. Conduct assessments to determine conformity and effectiveness per CPL-04 and IAO-02.

**ISO 27701:2025 (9.2.1, 9.2.2)**
8. Conduct PIMS internal audits at planned intervals per 9.2.1 and 9.2.2.

**ISO 45001:2018 (9.2.1, 9.2.2)**
9. Conduct OH&S management system internal audits per 9.2.1 and 9.2.2.

**ISO 42001:2023 (9.2.1, 9.2.2)**
10. Conduct AI management system internal audits per 9.2.1 and 9.2.2.

**ISO 37001:2025 (37001:2025 - 9.2.1, 37001:2025 - 9.2.2, 37001:2025 - 9.2.3, 37001:2025 - 9.2.4)**
11. Conduct ABMS audits using risk-based procedures per 9.2.1, 9.2.2, 9.2.3, and 9.2.4.

**ISO 9001:2015 (9.2.1, 9.2.2)**
12. Conduct QMS internal audits at planned intervals per 9.2.1 and 9.2.2.

### Internal Audit Execution Procedure

**Applies to all frameworks**
1. Plan the internal audit schedule covering all relevant areas within the audit cycle.
2. Appoint auditors who are independent of the area being audited.
3. Execute audits per the plan; collect and evaluate evidence.
4. Document findings, graded by severity; issue audit report.
5. Communicate findings to the relevant manager.
6. Open corrective action records for all non-conformities.

### Management Review Execution

**ISO 27001:2022 (9.3.1, 9.3.2, 9.3.3)**
1. Ensure all required inputs are addressed per 9.3.2: status of prior actions, changes, performance, audit results, risk status, objectives.
2. Retain documented information of review results as evidence per 9.3.3.

**SOC 2 (TSP 2017) (CC4.2, CC5.3)**
3. Confirm control deficiencies are identified and communicated per CC4.2 and CC5.3.

**NIST 800-53 Rev5 (PM-6)**
4. Monitor, report, and document management system performance per PM-6.

**SCF 2025 (GOV-03, GOV-17)**
5. Submit status reporting to applicable authorities as required per GOV-17.
6. Review and update programme documentation per GOV-03.

**ISO 27701:2025 (9.3.1, 9.3.2, 9.3.3)**
7. Conduct PIMS management review covering all required inputs per 9.3.1, 9.3.2, and 9.3.3.

**ISO 45001:2018 (9.3)**
8. Conduct OH&S management review at planned intervals per 9.3.

**ISO 42001:2023 (9.3.1, 9.3.2, 9.3.3)**
9. Conduct AI management system review per 9.3.1, 9.3.2, and 9.3.3.

**ISO 37001:2025 (37001:2025 - 9.3.1, 37001:2025 - 9.3.2, 37001:2025 - 9.3.3, 37001:2025 - 9.4)**
10. Conduct ABMS review per 9.3.1, 9.3.2, and 9.3.3.
11. Anti-bribery function assesses system adequacy continually per 9.4.

**ISO 9001:2015 (9.3.1, 9.3.2, 9.3.3)**
12. Conduct QMS management review per 9.3.1, 9.3.2, and 9.3.3.

### Management Review Execution Procedure

**Applies to all frameworks**
1. Schedule management review at defined intervals (at minimum annually).
2. Prepare review inputs: performance metrics, audit results, risk status, objectives, prior actions.
3. Conduct the review with relevant management.
4. Document decisions and actions with owners and due dates.
5. Retain minutes as evidence.

### Nonconformity and Corrective Action Tracking Procedure

**Applies to all frameworks**
1. Log new nonconformity including source, description, and severity.
2. Assign owner and target resolution date.
3. Conduct root cause analysis for significant items.
4. Implement and document corrective action.
5. Verify effectiveness of action before closing.
6. Review all open items monthly; escalate overdue items.

### System Security Plan Review Procedure

**Applies to all frameworks**
1. Review the system security plan against current system configuration and control implementation.
2. Update sections that no longer reflect the deployed environment.
3. Confirm all controls have documented implementation status.
4. Obtain authorising official sign-off.
5. Retain version history.

### Plan of Action and Milestones (POA&M) Review Procedure

**Applies to all frameworks**
1. Review all open POA&M items against milestones and scheduled completion dates.
2. Identify overdue items; escalate and document reasons for delay.
3. Add newly identified weaknesses from audits, scans, or assessments.
4. Confirm completed items have evidence of resolution.
5. Update and distribute POA&M report monthly.

### Cardholder Data Environment Scoping Review Procedure

**Applies to all frameworks**
1. Review network diagram, data flow diagram, and CDE asset inventory for currency.
2. Run cardholder data discovery scan across all in-scope systems.
3. Investigate any PANs found outside the defined CDE.
4. Update scope documentation to reflect any changes.
5. Obtain sign-off from a qualified assessor or internal reviewer.

### PCI DSS Compliance Assessment Procedure

**Applies to all frameworks**
1. Determine applicable SAQ type or arrange QSA engagement.
2. Complete assessment against all applicable requirements.
3. Document compensating controls where required.
4. Obtain authorised officer signature on AOC.
5. Submit to acquiring bank or payment brand as required.
6. Track open items to resolution.

### Cyber Governance Programme Review Procedure

**Applies to all frameworks**
1. Review cybersecurity programme documentation for currency and completeness.
2. Confirm senior management approval of programme objectives and resources.
3. Review performance metrics against defined targets.
4. Identify and document gaps; assign owners.
5. Present programme status to leadership.
### AI-Specific Risk Management System

**EU AI Act 2024/1689 (Art.9.1, Art.9.2, Art.9.8, Art.9.9, Art.9.10)**
- A risk management system for high-risk AI systems must be established, implemented, documented, and maintained as a continuous iterative process throughout the entire AI system lifecycle.
- The risk management system must identify and analyse known and reasonably foreseeable risks to health, safety, and fundamental rights, estimate and evaluate those risks, and adopt appropriate risk management measures.
- High-risk AI systems must be tested before deployment and the testing must demonstrate that they perform consistently for their intended purpose and do not present risks beyond accepted thresholds.
- Providers must consider whether the AI system is accessible to children or other vulnerable persons.

**NIST AI 600-1 2024 (MP-1.1-001, MP-1.1-002, MP-1.1-004, GV-1.3-001, GV-1.3-002)**
- The intended purpose, context of use, and assumptions and limitations of the GAI system must be documented and evaluated in collaboration with domain experts.
- Foreseeable illegal uses or applications of the GAI system that surpass organisational risk tolerances must be identified and documented.
- Risk tiers for GAI systems must consider information integrity impacts, system dependencies, harm potential, and CBRN information risks.
- Minimum performance thresholds for deployment approval must be established and reviewed.

### Fundamental Rights Impact Assessment

**EU AI Act 2024/1689 (Art.27.1, Art.27.2, Art.27.3, Art.27.4)**
- Deployers of high-risk AI systems must conduct a fundamental rights impact assessment prior to first use.
- The assessment must cover the categories of persons likely to be affected, the foreseeable impacts on fundamental rights, the measures taken to mitigate risks, and an indication of the expected duration.
- Assessment results must be notified to the market surveillance authority and the filled-in form submitted.
- Where a data protection impact assessment has been conducted, it may be combined with the fundamental rights impact assessment.

### AI-Specific Risk Management Procedure

**Applies to all frameworks**
1. Identify the AI system scope and confirm classification (high-risk or not under applicable regulation).
2. Identify and document known and foreseeable risks to health, safety, and fundamental rights.
3. Estimate likelihood and severity of harm.
4. Define and implement risk management measures.
5. Test the system to confirm performance and risk thresholds are met.
6. Document the risk management system and retain records.
7. Review and update continuously throughout the AI system lifecycle.

**EU AI Act 2024/1689 (Art.9.1, Art.9.2, Art.9.6, Art.9.8)**
8. Confirm testing was performed at an appropriate stage of development and prior to market placement.
9. Document that residual risks are acceptable per organisational risk tolerance.

**NIST AI 600-1 2024 (MP-1.1-001, MP-1.1-002, GV-1.3-001)**
8. Document the expected and acceptable context of use in collaboration with domain experts.
9. Update the risk hierarchy as new risks emerge.

### Fundamental Rights Impact Assessment Procedure

**EU AI Act 2024/1689 (Art.27.1, Art.27.2, Art.27.3)**
1. Confirm the AI system is subject to the fundamental rights impact assessment obligation.
2. Identify categories of persons who may be affected.
3. Assess foreseeable impacts on the right to human dignity, privacy, non-discrimination, consumer protection, and other applicable rights.
4. Define and document measures to mitigate identified risks.
5. Document expected duration of use.
6. Notify the market surveillance authority and submit the completed assessment.
7. Update the assessment if the system is substantially modified.
