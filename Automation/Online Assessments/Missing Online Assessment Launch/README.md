---
id: oa-missing-assessment-launch
name: Missing Online Assessment Launch
version: 0.1.2
status: tested
vendor: eramba
technology: eramba API v2
eramba_module: Third Parties
trigger: Notification "New Item" (Trigger Automation)
guide: Online Assessments - Advanced Configurations (automation 2 of 3)
secrets:
  - eramba_api_token
variables:
  - QUESTIONNAIRE_NAME
  - ASSESSOR_GROUP
  - SUPPLIER_TYPE_ID
  - REQUIRES_OA_FIELD
  - DURATION_DAYS
  - TITLE_PREFIX
  - MAX_ITEMS
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - DRY_RUN
dependencies: []
timeout_seconds: 60
eramba_version_tested: 3.31.1
last_tested: 2026-10-06
---

# Missing Online Assessment Launch

> **Tutorial templates.** This is one of the three example automations of the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It is built for the scenario of that tutorial (a Finance supplier list, supplier accounts, a supplier questionnaire). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** eramba. **Status:** tested. Validated end to end on eramba 3.31.1 (version 0.1.0): the *New Item* notification ran it for a new supplier and the assessment was created and sent; suppliers without *Requires Online Assessment* = Yes and suppliers with an assessment were skipped.

Makes sure every supplier is assessed. As soon as a supplier is created, the automation checks its *Requires Online Assessment* field (set from the Finance sheet's *Requies OA*). If it is Yes, it creates an Online Assessment and sends it to the supplier's Third Party Contact.

## At a glance

| | |
|---|---|
| **Runs** | Not recurrent. The Third Party notification *New Item* runs it for the supplier that was just created (section *Third Parties*). |
| **Reads** | That Third Party (including *Requires Online Assessment*) and the Online Assessments linked to it. |
| **Creates** | One Online Assessment, started at once and sent to the *Third Party Contact* (magic link), only if *Requires Online Assessment* is Yes, the supplier has a contact and it has no assessment yet. |
| **Updates** | Nothing. |
| **Never does** | Create an assessment for a supplier whose *Requires Online Assessment* is not Yes, re-send one to a supplier that already has one, or create one for a supplier without contact. |

## 1. Guide and scope

This is automation 2 of 3 in [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It runs right after [Finance Supplier Onboarding](../Finance%20Supplier%20Onboarding/) creates a supplier, because creating the Third Party fires the *New Item* notification.

## 2. What it does

eramba runs it with the Third Party that fired the notification (macro `%THIRDPARTY_ID%`):

| Case | Action |
|---|---|
| Not of type `SUPPLIER_TYPE_ID` | `SKIPPED`. |
| *Requires Online Assessment* is not Yes (No, Undefined or empty) | `SKIPPED`. |
| No *Third Party Contact* | `SKIPPED`. |
| Already linked to any Online Assessment | `SKIPPED`. |
| Otherwise | Creates and starts an assessment through the eramba API: the `QUESTIONNAIRE_NAME` questionnaire, the `ASSESSOR_GROUP` group as Assessor, the contact as Recipient, open `DURATION_DAYS` days, magic-link access, no recurrence. |

The assessment is created through the API, not with `addObjectMacro()`, because the automation lives in *Third Parties* and that helper can only add records to its own section.

## 3. Coverage

It only runs when a supplier is created. Suppliers that already existed, suppliers created without contact and suppliers whose *Requires Online Assessment* changes later are not launched automatically: send their assessment by hand, or run the automation on them with *Test* (§6).

## 4. Before you start

- eramba Enterprise, with the questionnaire and the GRC group from the guide.
- The Third Party custom field *Requires Online Assessment* (dropdown: Undefined, Yes, No), filled by [Finance Supplier Onboarding](../Finance%20Supplier%20Onboarding/).
- An eramba user with *Allow APIs* that can read Third Parties and create Online Assessments, plus an API token for it.

## 5. Setup on the target system

No external system is used.

## 6. Setup in eramba

1. Create Secret `eramba_api_token`. You can share it with the other automations.
2. In **Third Parties**, create an automation: PHP 8.4, no Composer packages, timeout 60 s. Paste [run.php](run.php). Leave *Recurrent Automation* off.
3. Set `QUESTIONNAIRE_NAME` to your questionnaire's exact name.
4. In **Third Parties > Notifications**, open *New Item*. Turn on *Trigger Automation*, select this automation in the *Automation* tab and set *Status* to enabled. Email can stay off.
5. Test it with *Test* on a supplier that already has an assessment (it must log `SKIPPED`), and with `DRY_RUN=true` on one without. Then create a supplier with a contact and check that its assessment is created and sent.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `QUESTIONNAIRE_NAME` | `Supplier Security Questionnaire` | Exact name of the questionnaire to send. |
| `ASSESSOR_GROUP` | `GRC` | Group set as Assessor. |
| `SUPPLIER_TYPE_ID` | `2` | Third Party type that is assessed. |
| `REQUIRES_OA_FIELD` | `Requires Online Assessment` | Name of the Third Party custom field. The script finds its ID by name, because custom field IDs differ between installations. |
| `DURATION_DAYS` | `30` | Days the assessment stays open after it starts. |
| `TITLE_PREFIX` | `Supplier Security Assessment – ` | The supplier name is appended. |
| `MAX_ITEMS` | `2000` | The run aborts above this many Third Parties or assessments. |
| `ERAMBA_API_URL` / `ERAMBA_API_VERIFY_TLS` | Empty / `true` | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |
| `DRY_RUN` | `false` | `true` logs what would be created without creating it. |

## 8. Results

The log shows `CREATED` with the assessment ID and its Recipients, or `SKIPPED` with the reason. If creation fails, nothing is created and the run ends with an error.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Questionnaire '…' not found` | Fix `QUESTIONNAIRE_NAME`. |
| `No Third Party in context` | The automation ran without an item: run it from the notification or with *Test* on a Third Party. |
| Never runs | Check that *New Item* is enabled and has *Trigger Automation* with this automation selected. |
| `Custom field '…' not found` | Create the field, or fix its name in `$config`. |
| Supplier skipped | Check the reason in the log: *Requires Online Assessment* must be Yes (from the sheet) and the supplier needs a Third Party Contact. |
| `HTTP 422 POST …/vendor-assessments` | A required assessment field is missing or invalid. The log shows only the status (response bodies are not logged): check the questionnaire, recipients and required custom fields of Online Assessments. |
| eramba `401` / TLS errors | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |

## 10. Customising

Change the payload in `launch()` to add Business Units, start dates or recurrence (field names as in the API documentation of `POST /api/v2/vendor-assessments`).

## 11. Removing

Turn off *Trigger Automation* in the *New Item* notification, then disable or delete the automation. If no other automation uses the API token, revoke it. Assessments already created remain.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.2 | The eramba API URL must be HTTPS, or HTTP only to a private network address (the runner's internal URL). Error messages show only the HTTP status and path, never the response body, so no personal data reaches the Automation Logs. |
| 0.1.1 | Reads only the Third Party that fired the notification (`GET /api/v2/third-parties/{id}`) instead of listing all of them. |
| 0.1.0 | Validated end to end on eramba 3.31.1: *New Item* → assessment created and sent to the supplier contact; skip cases (field not Yes, assessment already exists) checked. |
