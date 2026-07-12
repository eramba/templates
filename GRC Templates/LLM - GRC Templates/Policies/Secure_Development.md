# Secure Development

## Purpose
To ensure that information security is embedded throughout the software development lifecycle and that applications are designed, built, and tested to resist attack.

## Scope
All software developed, customised, or procured by the organisation, including internal applications, customer-facing services, APIs, and third-party components.

## Responsibilities

- **Development Teams**: Follow secure coding standards, use approved tools, and complete required security testing.
- **Information Security**: Define security requirements, review designs, and approve security testing processes.
- **Product / Project Management**: Ensure security activities are included in project plans and not bypassed under schedule pressure.
- **IT Operations**: Maintain separation between development, test, and production environments.

## Policy Statements

### Secure Development Lifecycle

**Applies to all frameworks**
- Security must be integrated into every phase of the development lifecycle: requirements, design, implementation, testing, and deployment.
- Secure development standards must be documented, maintained, and accessible to all developers.
- Security design principles including least privilege, defence in depth, and secure defaults must be applied.
- All developers with production code access must complete role-specific security training at least annually.

**ISO 27002:2022 (8.25, 8.27)**
- System architecture and engineering must follow documented secure design principles.

### Application Security Requirements

**Applies to all frameworks**
- Security requirements must be defined and approved by Information Security before development begins on any new or significantly changed application.
- Requirements must address authentication, authorisation, input validation, data protection, logging, and error handling.
- A process must exist for receiving and handling reports of security vulnerabilities in developed software.

### Secure Coding

**Applies to all frameworks**
- Developers must follow documented secure coding standards appropriate to their development language and environment.
- Code reviews must include a security component; significant changes must be reviewed by a developer with security awareness.
- Static analysis security testing must be integrated into the build or CI/CD pipeline.

### Security Testing in Development

**Applies to all frameworks**
- Security testing must be performed before any significant application release to production.
- Dynamic application security testing must be performed on externally accessible applications at least annually.
- Application penetration testing must be conducted for critical applications by qualified testers.
- All critical and high findings from security testing must be remediated before deployment or have a documented risk acceptance.

### Third-Party Software Components

**Applies to all frameworks**
- All third-party libraries and open-source components used in development must be inventoried.
- Components must be obtained from trusted sources and assessed for known vulnerabilities before use.
- The component inventory must be kept current and reviewed at least monthly for newly disclosed vulnerabilities.
- End-of-life or unsupported components must have a documented migration plan.

### Environment Separation

**Applies to all frameworks**
- Development, test, and production environments must be logically or physically separated.
- Developers must not have direct access to production systems without a documented approval process.
- Production data must not be used in non-production environments unless masked or anonymised.

### Source Code Access Control

**Applies to all frameworks**
- Read and write access to source code repositories must be restricted to authorised personnel.
- Access must be reviewed periodically and revoked when no longer required.
- Changes to production code must require approval through the change management process.

## Standards

*Standards define the specific technical and operational requirements that implement the policy statements above.*

## Procedures

### Secure Development Lifecycle Review

**Applies to all frameworks**
1. Sample development projects from the review period.
2. Confirm security requirements were documented and approved before development began.
3. Confirm a security design review or threat model was completed for significant projects.
4. Confirm all developers on sampled projects completed role-specific security training in the last 12 months.
5. Document projects with missing security activities and escalate.

**PCI DSS v4.0.1 (6.2.1, 6.2.2, 6.2.3)**
6. Confirm bespoke and custom software is developed based on industry standards and in accordance with PCI DSS per 6.2.1.
7. Confirm developers receive annual security training in their development language per 6.2.2.
8. Confirm code reviews include security checks per 6.2.3.

**SOC 2 (TSP 2017) (CC8.1)**
9. Confirm changes are authorised, developed, configured, documented, tested, and approved per CC8.1.

**ISO 27002:2022 (8.25, 8.27)**
10. Establish and apply rules for secure development of software and systems per 8.25.
11. Establish, document, and maintain secure system architecture and engineering principles per 8.27.

**NIST 800-53 Rev5 (SA-3, SA-8, SA-15)**
12. Acquire, develop, and manage the system using an SDL incorporating security and privacy per SA-3.
13. Apply security and privacy engineering principles in specification, design, development per SA-8.
14. Require the developer to follow a documented development process explicitly addressing security per SA-15.

**CIS Controls v8.1 (16.1, 16.9)**
15. Establish and maintain a secure application development process per 16.1.
16. Train developers in application security concepts and secure coding at least annually per 16.9.

**SCF 2025 (TDA-06, TDA-07, PRM-07)**
17. Develop applications based on Secure Software Development Practices per TDA-06.
18. Maintain a segmented development network for secure development environments per TDA-07.
19. Manage SDL changes through formal change control per PRM-07.

**NIS2 Article 21 (Article 21(2) (e))**
20. Confirm SDL controls support security in network and information system development per Article 21(2)(e).

**ISO 27701:2025 (A.3.27, A.3.29)**
21. Establish and apply rules for secure development related to PII processing per A.3.27.
22. Apply secure system architecture and engineering principles for PII processing systems per A.3.29.

**ISO 42001:2023 (A.6.1.3)**
23. Define and document processes for responsible AI system design and development per A.6.1.3.

### Application Security Requirements Review

**Applies to all frameworks**
1. Identify new or significantly changed applications deployed in the review period.
2. Confirm each has a documented security requirements record approved by Information Security.
3. Confirm requirements addressed authentication, authorisation, data protection, and logging.
4. Document any applications deployed without security requirements sign-off.

**PCI DSS v4.0.1 (6.2.1, 6.2.2)**
5. Confirm security requirements are defined before development begins per 6.2.1.
6. Confirm requirements address PCI DSS-relevant controls (authentication, logging, encryption) per 6.2.1.

**SOC 2 (TSP 2017) (CC8.1)**
7. Confirm security requirements are part of the system change lifecycle per CC8.1.

**ISO 27002:2022 (8.26)**
8. Identify, specify, and approve information security requirements when developing or acquiring applications per 8.26.

**NIST 800-53 Rev5 (SA-4, SA-17)**
9. Include security and privacy requirements in acquisition documentation per SA-4.
10. Require developer to produce a security and privacy architecture per SA-17.

**CIS Controls v8.1 (16.2)**
11. Establish and maintain a process to accept and address software vulnerabilities per 16.2.

**SCF 2025 (TDA-02, TDA-05, PRM-05)**
12. Ensure Minimum Viable Product security requirements are defined per TDA-02.
13. Require developers to produce a design specification and security architecture per TDA-05.
14. Identify and document critical system components and requirements per PRM-05.

**ISO 27701:2025 (A.3.28, A.3.29)**
15. Identify, specify, and approve information security requirements related to PII processing per A.3.28 and A.3.29.

**ISO 42001:2023 (A.6.2.2)**
16. Specify and document requirements for new or materially enhanced AI systems per A.6.2.2.

### Secure Coding Review

**Applies to all frameworks**
1. Confirm SAST tool is integrated into the CI/CD pipeline and scan results are reviewed before merge.
2. Sample code review records; confirm security checklist or security-aware reviewer was included.
3. Pull developer security training completion report; identify untrained developers with production code access.
4. Document gaps and initiate remediation.

**PCI DSS v4.0.1 (6.2.3, 6.2.4)**
5. Confirm code reviews are performed by individuals other than the originating author per 6.2.3.1.
6. Confirm software engineering techniques address injection attacks, authentication failures, and other OWASP categories per 6.2.4.

**SOC 2 (TSP 2017) (CC8.1)**
7. Confirm code changes are tested and approved per CC8.1.

**ISO 27002:2022 (8.28)**
8. Apply secure coding principles to software development per 8.28.

**NIST 800-53 Rev5 (SA-15, SA-8)**
9. Require developer to follow documented development process explicitly addressing security per SA-15.
10. Apply security and privacy engineering principles per SA-8.

**CIS Controls v8.1 (16.9, 16.12)**
11. Train developers in application security concepts and secure coding per 16.9.
12. Implement code-level security checks using static and dynamic analysis per 16.12.

**SCF 2025 (TDA-06, TDA-18, TDA-19)**
13. Develop applications based on Secure Software Development Practices per TDA-06.
14. Check validity of information inputs per TDA-18.
15. Handle error conditions and generate minimal error messages per TDA-19.

**ISO 27701:2025 (A.3.27)**
16. Apply secure coding principles to software development related to PII processing per A.3.27.

### Security Testing in Development

**Applies to all frameworks**
1. Confirm DAST scan was performed on all externally accessible applications within the last 12 months.
2. Review findings tracker: confirm all critical and high findings have remediation owners.
3. Confirm no critical findings were deployed to production without documented risk acceptance.
4. For critical applications, confirm penetration test was conducted within the last 12 months.
5. Document applications without security testing coverage.

**PCI DSS v4.0.1 (6.3.1, 6.4.1, 6.4.2)**
6. Confirm application security testing is performed on all public-facing web applications per 6.4.1.
7. Confirm automated WAF or equivalent is deployed on public-facing applications per 6.4.2.

**SOC 2 (TSP 2017) (CC8.1)**
8. Confirm security testing is included in the development lifecycle per CC8.1.

**ISO 27002:2022 (8.29)**
9. Define and implement security testing processes in the development lifecycle per 8.29.

**NIST 800-53 Rev5 (SA-11, CA-2)**
10. Require developer to develop and implement an ongoing security testing plan per SA-11.
11. Conduct control assessments on development environment per CA-2.

**CIS Controls v8.1 (16.12, 16.13)**
12. Apply static and dynamic analysis tools to verify secure coding practices per 16.12.
13. Conduct application penetration testing for critical applications per 16.13.

**SCF 2025 (TDA-09, VPM-07)**
14. Require developers to consult with security personnel and conduct security testing per TDA-09.
15. Conduct penetration testing per VPM-07.

**NIS2 Article 21 (Article 21(2) (e))**
16. Confirm security testing supports security in network and information system development per Article 21(2)(e).

**ISO 42001:2023 (A.6.2.4)**
17. Define and document verification and validation measures for the AI system per A.6.2.4.

### Source Code Access Control

**Applies to all frameworks**
1. Export access list from source code repository showing users and permission levels.
2. Cross-reference against current team members; confirm no former employees retain access.
3. Confirm write access is restricted to active developers; confirm production branch requires approval.
4. Document and remediate excess access.

**PCI DSS v4.0.1 (6.5.3, 6.5.4)**
5. Confirm production and pre-production environments have separated roles and functions per 6.5.3 and 6.5.4.

**SOC 2 (TSP 2017) (CC8.1)**
6. Confirm access to source code is restricted and authorised per CC8.1.

**ISO 27002:2022 (8.4)**
7. Appropriately manage read and write access to source code, development tools, and libraries per 8.4.

**NIST 800-53 Rev5 (CM-5)**
8. Define, document, approve, and enforce access restrictions associated with changes per CM-5.

**SCF 2025 (TDA-20)**
9. Limit privileges to change software resident within software libraries per TDA-20.
### LLM Application Security

**Applies to all frameworks**
- LLM-based applications must be assessed for LLM-specific vulnerabilities as part of the secure development lifecycle.
- Security testing must include adversarial prompt testing, output validation, and supply chain integrity checks.

**OWASP Top 10 for LLM Applications 2025 (LLM01, LLM02, LLM03, LLM04, LLM05, LLM06, LLM07, LLM08, LLM09, LLM10)**
- Prompt injection mitigations must be designed into LLM application architecture before deployment.
- Sensitive information disclosure through model outputs must be addressed in the threat model.
- LLM supply chain components (base models, datasets, plugins) must be vetted as part of the SDL.
- Output handling must include sanitisation before passing LLM output to downstream systems or execution environments.
- Agentic LLM systems must implement least-privilege, resource limits, and human approval checkpoints in the design phase.

**NIST AI 600-1 2024 (MS-2.7-007, MS-2.7-008, MS-2.7-009)**
- AI red-teaming must assess resilience against abuse, prompt injection, extraction attacks, and misuse facilitation.
- Fine-tuning must be verified not to compromise safety and security controls.
- Security measures must be regularly assessed and verified to remain effective.

**EU AI Act 2024/1689 (Art.15.1, Art.15.5)**
- High-risk AI systems must be designed to achieve appropriate accuracy, robustness, and cybersecurity.
- AI systems must be resilient against attempts by unauthorised third parties to alter their use, outputs, or performance by exploiting vulnerabilities.

### LLM Application Security Testing Procedure

**Applies to all frameworks**
1. Include LLM-specific test cases in the security testing plan for all LLM applications.
2. Test for prompt injection (direct and indirect), sensitive data leakage via outputs, and excessive agency.
3. Conduct adversarial red-team testing against jailbreaking and manipulation scenarios.
4. Test output handling: confirm LLM-generated code or commands are not executed without sanitisation.
5. Review supply chain components for provenance and known vulnerabilities.
6. Document findings and track to remediation.

**OWASP Top 10 for LLM Applications 2025 (LLM01, LLM02, LLM05, LLM06, LLM10)**
7. Include resource exhaustion and denial-of-service via unbounded inference in the test scope.

**NIST AI 600-1 2024 (MS-2.7-007)**
7. Include scenarios covering abuse to facilitate attacks on other systems via malicious code generation.

**EU AI Act 2024/1689 (Art.15.5)**
7. Include testing of resilience against adversarial inputs designed to alter AI system outputs.
