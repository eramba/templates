# Eramba Automation Examples

Small PHP examples for Eramba automations on Online Assessments.

These examples are written for the Online Assessments section. The item IDs, payload fields, and macros shown here should be adapted against Online Assessment records and questionnaires.

## Available examples

- [Internal Controls](Internal%20Controls/) contains ready-to-use automations that audit the internal controls of the eramba GRC templates (e.g. AWS Backup), grouped by technology, plus a `_TEMPLATE` to build new ones.

- [Security Incidents](Security%20Incidents/) contains the automation of the course *Security Incident Management in eramba*: a Jira issue for each urgent incident.

- [Function Examples](Function%20Examples/) contains PHP 8.4 single-execution examples for Online Assessments:
  - edit an Online Assessment
  - add an Online Assessment
  - add a comment to an Online Assessment
  - upload an attachment to an Online Assessment
  - write text to a file

## Usage

1. Open the example directory that matches the function you want to test.
2. Copy the contents of `run.php` into the Eramba automation editor.
3. Replace macros and placeholder values.
4. Test from Eramba against an Online Assessment item.

Scripts that call Eramba macros need Eramba to run. Local PHP is only useful for syntax checks.
