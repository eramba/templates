# Automation template

Copy this folder to `<Vendor>/<Exact Control Title>/` and replace this outline with your integration's details. Follow [Writing automations](../docs/writing-automations.md), or give that guide and the [public skill](../skills/eramba-control-automation/SKILL.md) to your agent.

The accompanying [run.php](run.php) is an authoring scaffold, not an executable audit. It refuses to write until its guards are deliberately removed after implementation. Supply configuration/context validation, complete evidence collection and the evaluation required by your control. Do not publish unfinished placeholders as a working integration.

**Security:** use only official Composer packages from the provider or the library’s own project. No unofficial provider wrappers or forks. Verify provenance and resolved dependencies; use read-only credentials, eramba Secrets, verified HTTPS, bounded collection and redacted diagnostics. See the [security guidance](../docs/writing-automations.md#dependencies-and-security).

Use this metadata at the top of the completed README:

```yaml
---
id: provider-control-test
name: Exact Control Title
version: 0.1.0
status: draft
vendor: Vendor
technology: Specific services
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Exact Control Title
secrets: []
variables: []
dependencies: []
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---
```

Set the timeout to your implementation's requirement within the destination runner's limit. Populate secrets, variables and dependencies to match the code. Add policy mappings only if supplied. Use the exact control title as the document heading and identify technology separately.

## 1. Controls and policies

Identify the source control, methodology and any success criteria. Record provenance without private data. State the audit schedule and evidence window, explaining provider retention constraints. Keep policy mappings separate from acceptance criteria.

## 2. What it checks

List each check and its passing/failing conditions. Distinguish a completed failed audit from technical errors. Do not imply partial checks complete the whole methodology.

## 3. Coverage

Map every methodology requirement to implementation or an explicit gap. Define population, sampling, exceptions and unsupported cases. Describe how mandatory manual work remains pending.

## 4. Before you start

List prerequisites, licensing, source systems, scope and required connectivity.

## 5. Setup on the target system

### 5.1 Identity

Explain how to create the dedicated read-only identity and store credentials in eramba Secrets.

### 5.2 Permissions

List the precise permissions and where to apply them.

## 6. Setup in eramba

Link to the installation guide using the path appropriate to the destination folder. List Secret names, Composer packages, timeout, code and audit dates. Explain the first dry-run and disposable-audit validation.

## 7. Variables

Document every configuration key, shipped default, meaning and valid range. Identify reference defaults not prescribed by the methodology. Keep mandatory checks unconditional.

## 8. Results

Describe conclusion, evidence and comments, including partial-write behavior. Implement and document pending/manual review when required: evidence and comment only, without final result or completion dates. Do not present invented output as a real execution.

## 9. Troubleshooting

Cover configuration, permissions, incomplete/unsupported evidence, limits and persistence failures with practical actions.

## 10. Customising

Describe supported scope and objective changes without weakening required coverage.

## 11. Removing

Describe unlinking the automation and removing unused credentials/permissions while retaining historical evidence.

## 12. Changelog

Record actual changes by version and distinguish local verification from real installation validation.
