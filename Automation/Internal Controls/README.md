# Internal Control Automations

PHP scripts that read service evidence and record an internal-control audit in eramba: Passed/Failed, conclusion, comment and attachments.

Each script tests the control's **Audit Methodology**. Linked policies provide traceability, not additional checks. Read the coverage section before attaching a script to a broader control.

Only official Composer packages are used: provider SDKs or libraries from their own official publishers. See [dependency and security requirements](docs/writing-automations.md#dependencies-and-security).

## Install

1. Choose an automation below and read its README: scope, prerequisites and permissions.
2. Follow [Installing an automation](docs/installing.md): create secrets, paste `run.php`, set Composer packages and configure your regions/connection settings.
3. Link it to a control and configure the documented audit dates. Use one control per automation/account.
4. Test on a disposable audit. `DRY_RUN=true` prevents writes; the shipped default saves the result.

Install the `run.php` file from your chosen automation. Requires eramba Enterprise; see the installation guide for runner limits.

## AWS coverage

Start with the [AWS coverage guide](AWS/README.md) for supported scopes and the evidence needed before adding other controls. Provider availability does not establish full methodology coverage.

## Catalogue

Automations are grouped by vendor and named after the control they test. Each README identifies the specific services used.

*Recommended audits* is the audit frequency that lets the automation cover the control's methodology with that technology (see each README §1).

Status: `draft` (not yet validated on real eramba and the target system) · `tested` (tested on a real eramba and target system) · `stable` (used in production by several installations).

| Control | Technology and automation | Recommended audits | Version / status |
|---|---|---|---|
| Backup Execution and Restore Test | [AWS Backup](AWS/Backup%20Execution%20and%20Restore%20Test/) | At least weekly | 0.3.2 candidate; current-version validation pending |
| Capacity Planning Review | [AWS Auto Scaling + CloudWatch](AWS/Capacity%20Planning%20Review/) | Days 1 and 15 monthly | 0.1.2 draft |
| Capacity and Performance Monitoring Review | [AWS Auto Scaling + CloudWatch](AWS/Capacity%20and%20Performance%20Monitoring%20Review/) | Days 1 and 15 monthly | 0.2.1 draft; completion pending manual review |
| Remote Access Security Review | [AWS Client VPN + Directory Service](AWS/Remote%20Access%20Security%20Review/) | Monthly | 0.2.1 draft; SAML MFA requires IdP evidence |
| Cryptographic Key and Certificate Lifecycle Review | [AWS KMS + ACM + Load Balancers](AWS/Cryptographic%20Key%20and%20Certificate%20Lifecycle%20Review/) | Quarterly | 0.2.1 draft; manual rotation requires review |
| Multi-Factor Authentication Coverage Review | [Microsoft Entra ID](Microsoft/Multi-Factor%20Authentication%20Coverage%20Review/) | Annually | 0.1.1 draft |
| Multi-Factor Authentication Coverage Review | [Google Workspace](Google/Multi-Factor%20Authentication%20Coverage%20Review/) | Annually | 0.1.0 draft; Google-native scope, exceptions pending |
| Multi-Factor Authentication Coverage Review | [Zoom](Zoom/Multi-Factor%20Authentication%20Coverage%20Review/) | Annually | 0.1.0 limited-scope draft; not an SSO/IdP MFA review |
| Endpoint Encryption Compliance Review | [AWS WorkSpaces Personal + KMS](AWS/Endpoint%20Encryption%20Compliance%20Review/) | Quarterly | 0.1.1 draft; documented exceptions remain pending |
| Endpoint Encryption Compliance Review | [Microsoft Intune + Entra ID](Microsoft/Endpoint%20Encryption%20Compliance%20Review/) | Quarterly | 0.2.0 draft; exceptions remain pending |
| Mobile Device Management Review | [Microsoft Intune + Entra ID](Microsoft/Mobile%20Device%20Management%20Review/) | Quarterly | 0.1.0 draft; Apple bulk enrollment supported, other wipe configurations pending |
| Nonconformity and Corrective Action Tracking | [Linear](Linear/Nonconformity%20and%20Corrective%20Action%20Tracking/) | Monthly | 0.1.0 draft; requires recorded evidence review |
| Vulnerability Remediation Tracking | [Atlassian Jira Cloud](Atlassian/Vulnerability%20Remediation%20Tracking/) | Monthly | 0.1.2 draft; acceptance/escalation may remain pending |
| Vulnerability Remediation Tracking | [Linear](Linear/Vulnerability%20Remediation%20Tracking/) | Monthly | 0.1.1 draft; acceptance/escalation may remain pending |

Each integration covers the technology scope described in its README. A passing result does not cover systems outside that scope. Drafts require functional and installation validation before use for assurance.


## Create your own automation

Use the [PHP and README template](_TEMPLATE/) and [writing guide](docs/writing-automations.md), or give your agent the [eramba-control-automation skill](skills/eramba-control-automation/SKILL.md). Supply your control through eramba MCP, CSV, another file or text. The integrations above are examples; use your own methodology and success criteria to define the checks.


*eramba does not provide support for developing or troubleshooting custom automation code.*
