---
id: oa-submitted-risk-review
name: Submitted Assessment Risk Review
version: 0.1.0
status: draft
vendor: OpenAI
technology: OpenAI Chat Completions (optional) + eramba API v2
eramba_module: Online Assessments
trigger: Notification "OA has been submitted" (Trigger Automation)
guide: Online Assessments - Advanced Configurations (automation 3 of 3)
secrets:
  - eramba_api_token
  - openai_api_key (optional)
variables:
  - OA_RISK_FIELD
  - TP_RISK_FIELD
  - TP_REVIEW_DATE
  - UNREVIEWED_VALUES
  - HIGH_BELOW_PCT
  - MEDIUM_BELOW_PCT
  - OPENAI_MODEL
  - OPENAI_REASONING
  - FORCE_REVIEW
  - MAX_ITEMS
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - DRY_RUN
dependencies: []
timeout_seconds: 120
eramba_version_tested: 3.31.1
last_tested: null
---

# Submitted Assessment Risk Review

> **Tutorial templates.** This is one of the three example automations of the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It is built for the scenario of that tutorial (a Finance supplier list, supplier accounts, a supplier questionnaire). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** OpenAI (optional). **Status:** draft. The score rule was tested on eramba 3.31.1. The AI review is pending real validation.

Concludes every submitted supplier assessment. It sets a risk level and a written conclusion on the assessment, and copies the risk level and the review date to the supplier.

## 1. Guide and scope

This is automation 3 of 3 in [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It closes the cycle started by [Missing Online Assessment Launch](../Missing%20Online%20Assessment%20Launch/).

## 2. What it does

The automation is not recurrent. The Online Assessments notification *OA has been submitted* runs it once for the assessment that was just submitted (macro `%VENDORASSESSMENT_ID%`). It only reviews that assessment if it is submitted and its *Post Assessment Risk Level* is empty or *Undefined*; otherwise it logs `SKIPPED`. The script reads the score, open findings and answers. It then decides a level with one of two methods:

| Mode | Rule |
|---|---|
| AI (Secret `openai_api_key` exists) | The answers, score and open findings go to `OPENAI_MODEL`. The model returns a level (Low, Medium or High) and a conclusion of at most 600 characters. Any other answer is an error, and the assessment stays unreviewed. |
| Score rule (no Secret) | **High** if there are open findings or the score is below `HIGH_BELOW_PCT`. **Medium** if the score is below `MEDIUM_BELOW_PCT`. **Low** otherwise. |

It then saves:

- On the assessment: *Post Assessment Risk Level* and the conclusion in *Review Notes*.
- On each linked Third Party: *Supplier Risk Level* and *Last Review Date* (today, UTC).

## 3. Coverage

The AI conclusion is a proposal for the GRC team. Review it before you rely on it as evidence. Questionnaire answers are sent to OpenAI, so confirm that your data-processing terms allow this before you create the Secret. Attachments uploaded by the supplier are not read.

## 4. Before you start

- eramba Enterprise, with the custom fields from the guide. *Post Assessment Risk Level* must include the options Low, Medium and High.
- An eramba user with *Allow APIs* that can edit Online Assessments and Third Parties, plus an API token for it.
- Optional: an OpenAI API key restricted to this use.

## 5. Setup on OpenAI

Create a project API key and give it access only to the configured model. Store the key as Secret `openai_api_key`. Without it, the score rule is used.

## 6. Setup in eramba

1. Create Secret `eramba_api_token`. Optionally create `openai_api_key`.
2. In **Online Assessments**, create an automation: PHP 8.4, no Composer packages, timeout 120 s. Paste [run.php](run.php).
3. Check the custom field IDs in §7.
4. Leave *Recurrent Automation* off: this automation runs per assessment, not on a schedule.
5. In **Online Assessments > Notifications**, add the notification *OA has been submitted*. Turn on *Trigger Automation* and select this automation in its *Automation* tab. Email can stay off.
6. Test it from the automation editor (*Test*) on a submitted assessment with `DRY_RUN=true` and `FORCE_REVIEW=true`. Then set both back to `false`, submit an assessment from the portal and check the log, the assessment and its supplier.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `OA_RISK_FIELD` | `CustomField_2` | Assessment field *Post Assessment Risk Level*. |
| `TP_RISK_FIELD` / `TP_REVIEW_DATE` | `CustomField_4` / `CustomField_5` | Third Party fields *Supplier Risk Level* and *Last Review Date*. |
| `UNREVIEWED_VALUES` | `''`, `Undefined` | Risk level values that mean the assessment is still pending review. |
| `HIGH_BELOW_PCT` / `MEDIUM_BELOW_PCT` | `50` / `80` | Score thresholds (%) of the rule. These are reference defaults, not part of the guide. |
| `OPENAI_MODEL` / `OPENAI_REASONING` | `gpt-6.1-luna` / `low` | Model and reasoning effort for the AI review. |
| `FORCE_REVIEW` | `false` | `true` reviews the assessment even if it already has a level. Testing only. |
| `MAX_ITEMS` | `2000` | The run aborts above this many records. |
| `ERAMBA_API_URL` / `ERAMBA_API_VERIFY_TLS` | Empty / `true` | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |
| `DRY_RUN` | `false` | `true` reviews and logs without saving. AI calls still run. |

## 8. Results

The log shows each assessment's level, suppliers and conclusion. The assessment is saved first and its suppliers afterwards. If a supplier update fails, the assessment is already reviewed and is not retried. Set the supplier fields by hand, or test the automation on that assessment with `FORCE_REVIEW=true`.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Unexpected AI answer` | The model did not return valid JSON. Retry, or change `OPENAI_MODEL`. |
| OpenAI `401` / `404` | Check the key, and the model name or its access. |
| eramba `422` on the risk level | Add the missing option (Low, Medium or High) to the custom field. |
| `SKIPPED` in the log | The assessment is not submitted or already has a level. Use `FORCE_REVIEW` to test. |
| `No Online Assessment in context` | The automation ran without an item: run it from the notification or with *Test* on an assessment. |
| Never runs | Check that the *OA has been submitted* notification is enabled and has *Trigger Automation* with this automation selected. |
| eramba `401` / TLS errors | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |

## 10. Customising

Adapt the prompt in `reviewByAi()` to your risk methodology, or change the thresholds of the rule. Keep the result limited to the field's options.

## 11. Removing

Disable or delete the automation, delete `openai_api_key` and revoke the OpenAI key. Saved levels and conclusions remain.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | First packaged version. The score rule was validated on eramba 3.31.1: an assessment at 50% with one open finding became High, and its supplier was updated. The OpenAI review is pending validation. |
