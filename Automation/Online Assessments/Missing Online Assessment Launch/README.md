---
id: oa-missing-assessment-launch
name: Missing Online Assessment Launch
version: 0.1.0
status: draft
vendor: eramba
technology: eramba API v2 + automation helpers
eramba_module: Online Assessments
trigger: Recurrent (daily)
guide: Online Assessments - Advanced Configurations (automation 2 of 3)
secrets:
  - eramba_api_token
variables:
  - QUESTIONNAIRE_NAME
  - ASSESSOR_GROUP
  - SUPPLIER_TYPE_ID
  - DURATION_DAYS
  - TITLE_PREFIX
  - MAX_ITEMS
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - DRY_RUN
dependencies: []
timeout_seconds: 60
eramba_version_tested: 3.31.1
last_tested: null
---

# Missing Online Assessment Launch

> **Tutorial templates.** This is one of the three example automations of the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It is built for the scenario of that tutorial (a Finance supplier list, supplier accounts, a supplier questionnaire). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** eramba. **Status:** draft. Its logic was tested on eramba 3.31.1; this packaged version has not been re-validated.

Makes sure every supplier is assessed. When a supplier has no Online Assessment, the automation creates one and sends it to the supplier's Third Party Contact.

## 1. Guide and scope

This is automation 2 of 3 in [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It runs after [Finance Supplier Onboarding](../Finance%20Supplier%20Onboarding/), which creates the suppliers and their contacts.

## 2. What it does

| Case | Action |
|---|---|
| A supplier already linked to any Online Assessment | Nothing. |
| A supplier with a Third Party Contact and no assessment | Creates and starts an assessment. It uses the `QUESTIONNAIRE_NAME` questionnaire, the `ASSESSOR_GROUP` group as Assessor and the contact as Recipient. It is open `DURATION_DAYS` days, with magic-link access and no recurrence. |
| A supplier without a Third Party Contact | Skipped and logged. Its Dynamic Status flags it. |

## 3. Coverage

Only Third Parties of type `SUPPLIER_TYPE_ID` are considered. A supplier counts as assessed if any Online Assessment, in any state, is linked to it. Periodic re-assessment belongs to the assessment's own recurrence settings, not to this script.

## 4. Before you start

- eramba Enterprise, with the questionnaire and the GRC group from the guide.
- An eramba user with *Allow APIs* that can read Third Parties and Online Assessments, plus an API token for it.

## 5. Setup on the target system

No external system is used.

## 6. Setup in eramba

1. Create Secret `eramba_api_token`. You can share it with automation 1.
2. In **Online Assessments**, create an automation: PHP 8.4, no Composer packages, timeout 60 s. Paste [run.php](run.php).
3. Set `QUESTIONNAIRE_NAME` to your questionnaire's exact name.
4. Run with `DRY_RUN=true` and check the list of suppliers. Then run live and confirm that the assessment emails reach the contacts.
5. Enable *Recurrent Automation* (daily, after automation 1).

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `QUESTIONNAIRE_NAME` | `Supplier Security Questionnaire` | Exact name of the questionnaire to send. |
| `ASSESSOR_GROUP` | `GRC` | Group set as Assessor. |
| `SUPPLIER_TYPE_ID` | `2` | Third Party type that is assessed. |
| `DURATION_DAYS` | `30` | Days the assessment stays open after it starts. |
| `TITLE_PREFIX` | `Supplier Security Assessment – ` | The supplier name is appended. |
| `MAX_ITEMS` | `2000` | The run aborts above this many Third Parties or assessments. |
| `ERAMBA_API_URL` / `ERAMBA_API_VERIFY_TLS` | Empty / `true` | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |
| `DRY_RUN` | `false` | `true` logs the suppliers that would be assessed without creating anything. |

## 8. Results

The log lists each assessment created, its Recipients and the skipped suppliers. Creation uses `addObjectMacro()` and checks its response. An error on one supplier does not stop the others, but the run ends with an error.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Questionnaire '…' not found` | Fix `QUESTIONNAIRE_NAME`. |
| `Call to a member function getMaxScore() on null` | The questionnaire does not exist in this instance. |
| Supplier always skipped | Set its Third Party Contact, or complete its row in the Finance sheet. |
| eramba `401` / TLS errors | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |

## 10. Customising

Change the payload in `launch()` to add Business Units, start dates or recurrence. Keep the payload complete, as the [Add Item example](../../Function%20Examples/Add%20Item/) recommends.

## 11. Removing

Disable or delete the automation. If no other automation uses the API token, revoke it. Assessments already created remain.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | First packaged version. Logic validated on eramba 3.31.1: created one assessment, and a second run created none. Packaged version pending re-validation. |
