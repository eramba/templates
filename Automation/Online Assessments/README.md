# Online Assessment Automations – Advanced Configurations tutorial

> **These are tutorial templates.** The three automations in this folder are the examples built in the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). They follow that tutorial's scenario step by step and are meant to be followed alongside it. They are not a general-purpose product feature: review and adapt them to your own process before using them in production.

PHP scripts that automate the supplier review cycle with Online Assessments. Suppliers flow from the Finance list into eramba. Each new supplier gets an assessment, and every submitted assessment is concluded with a supplier risk level.

These are the three example automations of the eramba guide **[Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81)**. They expect the configuration built in that guide's *Implementation* chapter:

- Third Party type *Suppliers*.
- A supplier questionnaire.
- The *GRC* group.
- Third Party custom fields *Supplier Risk Level*, *Last Review Date* and *Finance Supplier ID*.
- The Online Assessment custom field *Post Assessment Risk Level*.

## Install

1. Read the README of each automation below: scope, prerequisites and permissions.
2. Create the Secrets, paste `run.php` into an automation of the indicated section, and set the variables of README §7.
3. Run once with `DRY_RUN=true` and read the log. Then run live and check the result.
4. Enable *Recurrent Automation* for #1 and #2 only after the log is clean, as the guide recommends (#1 before #2). #3 is not recurrent: it runs from the *OA has been submitted* notification.

Requires eramba Enterprise. None of the automations needs Composer packages.

## Catalogue

Status: `draft` (not yet validated on real eramba and the target system) · `tested` (tested on a real eramba and target system) · `stable` (used in production by several installations).

| # | Automation | Section | Technology | Recommended schedule | Version / status |
|---|---|---|---|---|---|
| 1 | [Finance Supplier Onboarding](Finance%20Supplier%20Onboarding/) | Third Parties | Google Sheets + eramba API | Daily | 0.1.0 draft; logic tested on eramba 3.31.1 |
| 2 | [Missing Online Assessment Launch](Missing%20Online%20Assessment%20Launch/) | Online Assessments | eramba API | Daily, after #1 | 0.1.0 draft; logic tested on eramba 3.31.1 |
| 3 | [Submitted Assessment Risk Review](Submitted%20Assessment%20Risk%20Review/) | Online Assessments | OpenAI (optional) + eramba API | On submit (notification *OA has been submitted*) | 0.1.0 draft; score rule tested, AI review pending |

## Shared design

- **Section-level, not item-level.** The scripts read and update many records per run. Automation helpers can only add or edit records of their own section, and they cannot list records. So reads, and writes to other sections, use the eramba REST API v2 with a dedicated token (Secret `eramba_api_token`).
- **Idempotent.** Every run starts from what is already in eramba. Re-running never duplicates accounts, Third Parties or assessments, and never reviews an assessment twice.
- **Missing details are not invented.** A supplier without a contact email is created without a Third Party Contact. A Third Party Dynamic Status flags it until Finance completes the row.

## Create your own automation

Follow the structure of these scripts: header, `$secrets`, `$config`, helpers, then collect → evaluate → apply → main. Each script has a sibling README with the 12 standard sections.

*eramba does not provide support for developing or troubleshooting custom automation code.*
