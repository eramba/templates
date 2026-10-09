# Online Assessment Automations – Advanced Configurations tutorial

> **These are tutorial templates.** The three automations in this folder are the examples built in the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). They follow that tutorial's scenario step by step and are meant to be followed alongside it. They are not a general-purpose product feature: review and adapt them to your own process before using them in production.

PHP scripts that automate the supplier review cycle with Online Assessments, as built in that course.

## How it works

```
Finance sheet ──(1, daily)──▶ supplier user + Third Party
                                   │  notification "New Item"
                  (2, on create)▼  only if Requires Online Assessment = Yes
                     Online Assessment sent to the supplier
                                   │  supplier answers and submits
       notification "OA has been submitted"
                          (3, on submit)▼
     risk level + conclusion on the assessment, risk level + date on the supplier
                                   │
                                   ▼
                     the assessor reviews the assessment in eramba
```

| # | Automation | Runs | Creates or updates | Leaves to people |
|---|---|---|---|---|
| 1 | [Finance Supplier Onboarding](Finance%20Supplier%20Onboarding/) | Daily (Third Parties) | Supplier user and Third Party for each new row of the Finance sheet, with *Requires Online Assessment* from its *Requies OA*. | Completing missing contact details in the sheet. |
| 2 | [Missing Online Assessment Launch](Missing%20Online%20Assessment%20Launch/) | When a supplier is created (Third Parties) | One assessment, started and sent, if *Requires Online Assessment* is Yes, the supplier has a contact and it has none yet. | Answering it (the supplier). |
| 3 | [Submitted Assessment Risk Review](Submitted%20Assessment%20Risk%20Review/) | When an assessment is submitted | Proposed risk level and conclusion on the assessment; risk level and review date on the supplier. | The formal review of the assessment (the assessor). |

## Requirements

The configuration from the course's *Implementation* chapter, plus the fields these automations write:

- Third Party type *Suppliers*, a supplier questionnaire and the *GRC* group.
- Third Party custom fields *Finance Supplier ID* (text), *Requires Online Assessment* (Undefined, Yes, No), *Risk Profile* (Undefined, Low, Medium, High) and *Last Reviewed* (date).
- Online Assessment custom fields *Post Assessment Risk Level* (Undefined, Low, Medium, High) and *Automated Review Conclusion* (paragraph).
- An eramba user with *Allow APIs* and its API token in Secret `eramba_api_token`, and the Google service account key in Secret `google_service_account` (#1 reads the Finance sheet). Optionally `openai_api_key` or `anthropic_api_key` for the AI review of #3.

## Install

1. Read the README of each automation below: scope, prerequisites and permissions.
2. Create the Secrets, paste `run.php` into an automation of the indicated section, and set the variables of README §7.
3. Run once with `DRY_RUN=true` and read the log. Then run live and check the result.
4. Enable *Recurrent Automation* for #1 only after the log is clean, as the guide recommends. #2 and #3 are not recurrent: notifications run them (#2 from the Third Party notification *New Item*, #3 from the Online Assessment notification *OA has been submitted*).

Requires eramba Enterprise. None of the automations needs Composer packages.

## Catalogue

Status: `draft` (not yet validated on real eramba and the target system) · `tested` (tested on a real eramba and target system) · `stable` (used in production by several installations).

| # | Automation | Section | Technology | Recommended schedule | Version / status |
|---|---|---|---|---|---|
| 1 | [Finance Supplier Onboarding](Finance%20Supplier%20Onboarding/) | Third Parties | Google Sheets + eramba API | Daily | 0.1.1 tested |
| 2 | [Missing Online Assessment Launch](Missing%20Online%20Assessment%20Launch/) | Third Parties | eramba API | On create (notification *New Item*) | 0.1.2 tested |
| 3 | [Submitted Assessment Risk Review](Submitted%20Assessment%20Risk%20Review/) | Online Assessments | OpenAI or Anthropic (optional) + eramba API | On submit (notification *OA has been submitted*) | 0.4.0 tested (score rule and OpenAI review); Anthropic review pending |

## Shared design

- **eramba API.** #1 works on the whole section, #2 creates assessments from the Third Parties section, and #3 also updates suppliers. Automation helpers can only add or edit records of their own section, and they cannot list records. So reads, and writes to other sections, use the eramba REST API v2 with a dedicated token (Secret `eramba_api_token`).
- **Custom fields by name.** The scripts find custom fields by their name, not by `CustomField_N`, because IDs differ between installations. If you rename a field, change its name in `$config`, or put its key (`CustomField_6`) there instead.
- **Idempotent.** Every run starts from what is already in eramba. Re-running never duplicates accounts, Third Parties or assessments, and never processes a submitted assessment twice.
- **People keep the decisions.** The automations prepare work; the formal review of each assessment stays with the assessor.
- **Missing details are not invented.** A supplier without a contact email is created without a Third Party Contact. A Third Party Dynamic Status flags it until Finance completes the row.

## Create your own automation

Follow the structure of these scripts: header, `$secrets`, `$config`, helpers, then collect → evaluate → apply → main. Each script has a sibling README with the 12 standard sections.

*eramba does not provide support for developing or troubleshooting custom automation code.*
