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
