I have uploaded one or more compliance-framework packages.

Your task is to create a complete, non-redundant policy library that covers all requirements across all uploaded frameworks.

## Input handling

1. Inspect each uploaded file.
2. Identify the columns that represent:
   - framework name, if available;
   - requirement ID;
   - requirement title or name;
   - requirement statement;
   - implementation guidance;
   - points of focus;
   - testing procedures;
   - applicability notes;
   - any other fields that clarify the intent of the requirement.
3. Tell me which columns you used from each file and which columns you ignored.
4. Use requirement IDs as the primary compliance mapping key.
5. Do not rely only on requirement titles. Use the full available requirement text, guidance, points of focus, and testing procedures when deciding policy coverage.

## Goal

Create the smallest practical set of policy documents that covers all requirements from all uploaded frameworks without unnecessary duplication.

The policy documents must be understandable to normal business users, not named after framework IDs.

For example, use names like:

- Information Security Policy
- Access Control Policy
- Cryptography and Key Management Policy
- Incident Response Policy
- Supplier Security Policy

Do not use names like:

- ISO 5.1 Policy
- PCI Requirement 3 Policy
- SOC 2 CC6 Policy

## Required policy format

For every policy document you create, use this exact structure:

# {{POLICY_NAME}}

<!--
LLM CUSTOMIZATION INSTRUCTIONS

When adapting this policy for a customer:

1. Preserve the following chapters:
   - Purpose
   - Scope
   - Policy Statements
   - Standard Statements

2. Replace all placeholders such as {{COMPANY_NAME}}, {{POLICY_OWNER}}, {{APPROVAL_AUTHORITY}}, {{REVIEW_FREQUENCY}}, and technology-specific placeholders.

3. Keep statements tagged with frameworks that are in scope.

4. Remove statements tagged only with frameworks that are not in scope.

5. Remove optional blocks that are not applicable to the customer’s environment.

6. Do not weaken mandatory requirements unless the customer explicitly requests a documented exception.

7. Keep Policy Statements broad, outcome-based, and understandable.

8. Keep Standard Statements specific, measurable, and auditable.

9. Preserve framework references for retained statements.

10. After customization, produce:
    - the final customized policy;
    - a list of removed statements;
    - a list of assumptions made;
    - a short framework coverage summary.
-->

## 1. Purpose

Explain why the policy exists and what compliance/security objective it supports.

## 2. Scope

Define who, what systems, data, business processes, third parties, and environments are covered.

## 3. Policy Statements

Create generic, outcome-based statements.

Each Policy Statement must use this format:

### PS-{{SHORT_POLICY_CODE}}-{{NUMBER}} — {{STATEMENT_NAME}}

**Applies to:** {{FRAMEWORK_LIST}}  
**Framework references:** {{REQUIREMENT_IDS}}

{{Generic policy statement}}

Policy Statements should be broad and easy to understand.

Examples:
- We must encrypt sensitive information transmitted over public or untrusted networks.
- We must restrict access to information systems based on business need and least privilege.
- We must identify, assess, and treat information security risks.

## 4. Standard Statements

Create specific, testable, auditable statements.

Each Standard Statement must use this format:

### SS-{{SHORT_POLICY_CODE}}-{{NUMBER}} — {{STATEMENT_NAME}}

**Applies to:** {{FRAMEWORK_LIST}}  
**Framework references:** {{REQUIREMENT_IDS}}

{{Specific standard statement}}

Standard Statements should be detailed enough to satisfy specific requirements from frameworks like PCI DSS, while still fitting into the broader policy.

Examples:
- TLS 1.2 or higher must be used for internet-facing services that transmit sensitive information.
- Stored PAN must be rendered unreadable wherever it is stored using strong cryptography, truncation, index tokens, or keyed cryptographic hashes.
- Administrative access over networks must use encrypted protocols such as SSH, HTTPS, VPN, or another approved secure method.

## Statement design rules

1. Policy Statements must describe what must be achieved.
2. Standard Statements must describe specific minimum requirements.
3. Do not create one policy per requirement.
4. Do not duplicate the same requirement across multiple policies unless genuinely necessary.
5. If one requirement logically belongs in more than one policy, map it to all relevant policies but avoid repeating the same statement text unnecessarily.
6. Use plain-language policy names.
7. Include framework references at statement level.
8. Use placeholders where customer-specific values are required.

Use placeholders such as:
- {{COMPANY_NAME}}
- {{POLICY_OWNER}}
- {{APPROVAL_AUTHORITY}}
- {{REVIEW_FREQUENCY}}
- {{IN_SCOPE_ENVIRONMENTS}}
- {{IN_SCOPE_DATA_TYPES}}
- {{CLOUD_PROVIDER}}
- {{KEY_MANAGEMENT_SYSTEM}}
- {{SECRETS_MANAGEMENT_SYSTEM}}
- {{SIEM_OR_LOGGING_PLATFORM}}
- {{TICKET_SYSTEM}}
- {{RISK_REGISTER}}
- {{ASSET_INVENTORY}}
- {{VULNERABILITY_SCANNER}}
- {{BACKUP_PLATFORM}}
- {{HR_SYSTEM}}

## Optional blocks

Where requirements apply only to certain customers, include optional blocks.

Use this format:

<!--
OPTIONAL BLOCK: {{BLOCK_NAME}}

Keep this block if:
- {{CONDITION_1}}
- {{CONDITION_2}}

Remove this block if:
- {{CONDITION_3}}
-->

Examples:
- OPTIONAL BLOCK: PCI DSS - Cardholder Data Environment
- OPTIONAL BLOCK: Cloud Services
- OPTIONAL BLOCK: Software Development
- OPTIONAL BLOCK: Remote Work
- OPTIONAL BLOCK: Physical Offices
- OPTIONAL BLOCK: Sensitive Personal Data

## Output requirements

Produce the following outputs.

### Output 1 — Policy library plan

Create a markdown table with:

| Policy name | Purpose | Main requirement themes covered | Frameworks covered |

### Output 2 — Full policy documents

Create the full content for each policy using the required structure above.

### Output 3 — Requirement-to-policy mapping CSV

Create a CSV file with one column per framework and one final column called `policy documents`.

Rules for the CSV:
1. The framework columns must contain requirement IDs only.
2. Do not include requirement titles in the ID columns.
3. If a row maps multiple framework requirements to the same policy or policies, include all relevant IDs.
4. The `policy documents` column must contain one or more plain-language policy names.
5. Do not use internal policy IDs as policy document names.
6. Include every requirement ID from every uploaded framework at least once.
7. If a requirement maps to more than one policy, separate policy names with semicolons.
8. If a requirement has no equivalent in another framework, leave that framework cell blank.
9. Deduplicate exact duplicate requirement IDs but tell me which duplicates were removed.

Example CSV columns for ISO, SOC 2, and PCI:

```csv
iso requirement,soc2 requirement,pci requirement,policy documents
8.24,,3.6.1,Cryptography and Key Management Policy
,CC6.1,,Access Control Policy
5.19,CC9.2,12.8,Supplier Security Policy