# Writing automations

Use this guide to automate your own internal controls, by hand or with an agent. Start from the [PHP template](../_TEMPLATE/run.php) and [README outline](../_TEMPLATE/README.md). The [catalogue](../README.md) contains complete integration examples with documented coverage and validation status.

## Use an agent

The public [eramba-control-automation skill](../skills/eramba-control-automation/SKILL.md) accepts definitions from eramba MCP, CSV, other files or text. MCP is the default when you have not selected another source. An authorized API connection is also usable if MCP is unavailable.

Give your agent the repository and the skill path. If your agent supports installed skills, copy the entire `eramba-control-automation` folder into its skill directory. The skill includes its runner notes; keep this repository available for the optional template and examples. You do not need to install a skill to ask an agent to read its instructions.

Example requests:

- “Use the eramba-control-automation skill to assess controls 12, 18 and 24 in my connected eramba instance. Identify evidence and coverage gaps.”
- “Use that skill to create an automation for the control in this CSV using Microsoft Graph. Use the supplied methodology and success criteria. Deliver PHP and setup instructions; do not deploy it.”
- “Use the control definitions in this repository's CSV to implement Capacity Planning Review for my selected AWS scope. Follow the library's structure and document any uncovered requirements.”

Supply the title, complete testing methodology and any success criteria, scope or period that exists. A CSV does not need a fixed schema. Real credentials belong in eramba Secrets, not in the prompt or generated files.

## Define the test

Map every methodology requirement to evidence and an evaluation. Preserve thresholds, exceptions, sampling and cadence. Policies provide traceability, not extra checks. If required judgment cannot be automated, retain it as pending work rather than completing the full audit automatically.

Discover the population before filtering for successful evidence. Empty scope cannot silently pass. Distinguish a completed test with failing evidence from failed collection or persistence. Document technology limits: a passing AWS scope does not cover unrelated systems.

Provide usable reference defaults where the definition permits them. Identify which defaults are implementation choices. Keep credentials, connection details and deployment scope configurable; do not expose switches that disable mandatory checks. Retain full failure evidence.

## Structure and naming

Save a library integration as `<Vendor>/<Exact Control Title>/run.php` with a sibling `README.md`. Use `AWS` or `Microsoft` for the vendor; identify services such as Entra ID or Intune in the README. If several implementations share the same vendor and control, add a service subfolder and update relative links.

Keep a stable machine identifier for logs and evidence filenames; it need not equal the folder name. Keep code and README versions synchronized. New integrations remain `draft` until real installation validation. Add the integration to the catalogue with its actual status.

## Dependencies and security

Use only official Composer packages: a provider's own SDK or a library published by its official project. For example, [AWS's SDK](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html) and [Guzzle](https://docs.guzzlephp.org/en/stable/overview.html#installation) qualify. Unofficial provider clients and forks do not. Check provenance from official documentation, use compatible release constraints, and audit the resolved dependencies before production installation. If no official PHP SDK fits, use the official API through Guzzle or PHP built-ins.

Use dedicated read-only identities, narrow scopes, eramba Secrets and verified HTTPS endpoints. Keep tokens and private data out of diagnostics and attachments. Disable unexpected redirects; bound pagination, response sizes and execution time. Never disable TLS verification to work around an error. Treat provider responses as untrusted data and reject partial collections. Official packages still need vulnerability review and real installation validation.

## Implement

The template illustrates collection, evaluation, evidence and reporting. It deliberately aborts until implemented. Replace placeholders and both guards only after implementing the test. Adapt all relevant sections, including validation and aggregation; the sample all-items-must-pass evaluation is not suitable for every methodology.

Read the skill's [PHP execution notes](../skills/eramba-control-automation/references/php-execution.md) and confirm helper signatures, macros and fields in the destination automation Tools interface. Keep `declare(strict_types=1);` on line 2 for the runner used by these examples. Use injected dependencies and helpers, not a second eramba login.

- Retrieve provider evidence with read-only permissions and bounded pagination, request timeouts and evidence sizes.
- Validate the resolved audit context, variable types/ranges and result option IDs. Keep provider secrets out of logs and evidence.
- Make `DRY_RUN` suppress every write and announce simulation. Finished library scripts default to live execution.
- Upload required evidence before saving the result, then add the comment. Check each helper response. Uploads may remain after a later failure, and a comment failure can leave a saved result; report uncertainty accurately before retrying.
- Keep console output brief and put item details in CSV/TXT attachments. Escape spreadsheet formulas in externally supplied CSV fields.

## Document

Use the template's 12 sections so installation links and troubleshooting references remain consistent. Include precise permissions, all variables and defaults, coverage, cadence and release status. Write directly to the person installing the automation. Keep internal planning, development transcripts, real identifiers and private data out of the published files.

Examples to consult:

| Example | Useful patterns |
|---|---|
| [Backup Execution and Restore Test](../AWS/Backup%20Execution%20and%20Restore%20Test/) | AWS SDK, regional evidence, recovery objectives and report attachments |
| [Multi-Factor Authentication Coverage Review](../Microsoft/Multi-Factor%20Authentication%20Coverage%20Review/) | Microsoft Graph authentication, pagination and conservative policy evaluation |
| [Endpoint Encryption Compliance Review](../Microsoft/Endpoint%20Encryption%20Compliance%20Review/) | Joining independent inventories and reporting missing evidence |

These examples have specific scopes and may still be drafts. Reuse their mechanics only after checking they fit your methodology; their provider-specific acceptance rules are not universal requirements.

The [Jira vulnerability review](../Atlassian/Vulnerability%20Remediation%20Tracking/) demonstrates a pending outcome: it records evidence without completing the audit when risk acceptance or escalation needs manual review. It also distinguishes an empty register from a populated register with no open items. Verify provider-field meanings and visibility before using such evidence as proof.

## Verify

Run PHP syntax checks and focused checks of the decisions that can change audit outcomes, including incomplete evidence and dry-run behavior. Avoid redundant test scaffolding. Validate against the provider and a disposable eramba audit before relying on results; verify the persisted outcome, attachments and schedule.

Document the version actually verified. Syntax checks, simulated execution and a real persisted audit are different levels of verification. Follow [Installing an automation](installing.md) for installation and cleanup.

Choose integrations that naturally hold the methodology’s evidence. Account availability or a similar check in another product is not enough: document the actual scope and sign-in paths, and defer candidates that require an artificial workflow.

The [Zoom MFA review](../Zoom/Multi-Factor%20Authentication%20Coverage%20Review/) shows how to separate native enforcement from alternative authentication paths. Unsupported secure configurations stay pending; do not instruct someone to weaken their setup to obtain an automatic pass.

For a platform-wide implementation request, assess the complete control list before adding scripts. Prefer full methodology coverage over provider count; check existing examples for the same coverage limitations. See the [AWS coverage guide](../AWS/README.md).
