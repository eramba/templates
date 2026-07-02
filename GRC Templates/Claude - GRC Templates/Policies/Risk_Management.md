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

### Threat Intelligence Review

**PCI DSS v4.0.1 (12.3.1)**
1. Document intelligence review as part of the targeted risk analysis per 12.3.1.

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
