---
id: oa-submitted-risk-review
name: Submitted Assessment Risk Review
version: 0.2.0
status: tested
vendor: OpenAI or Anthropic
technology: OpenAI Chat Completions or Anthropic Messages API (optional) + eramba API v2
eramba_module: Online Assessments
trigger: Notification "OA has been submitted" (Trigger Automation)
guide: Online Assessments - Advanced Configurations (automation 3 of 3)
secrets:
  - eramba_api_token
  - openai_api_key or anthropic_api_key (optional, see AI_PROVIDER)
variables:
  - OA_RISK_FIELD
  - OA_CONCLUSION_FIELD
  - TP_RISK_FIELD
  - TP_REVIEW_DATE
  - UNREVIEWED_VALUES
  - HIGH_BELOW_PCT
  - MEDIUM_BELOW_PCT
  - AI_PROVIDER
  - OPENAI_MODEL
  - ANTHROPIC_MODEL
  - AI_REASONING
  - FORCE_REVIEW
  - MAX_ITEMS
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - DRY_RUN
dependencies: []
timeout_seconds: 120
eramba_version_tested: 3.31.1
last_tested: 2026-10-06
---

# Submitted Assessment Risk Review

> **Tutorial templates.** This is one of the three example automations of the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It is built for the scenario of that tutorial (a Finance supplier list, supplier accounts, a supplier questionnaire). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** OpenAI or Anthropic (optional). **Status:** tested with the score rule. Validated end to end on eramba 3.31.1: submitting an assessment from the portal ran it through the *OA has been submitted* notification and filled the assessment and supplier fields. The AI review (OpenAI or Anthropic) is pending real validation.

Prepares the review of each supplier assessment as soon as it is submitted. It proposes a risk level and a written conclusion on the assessment, and copies the risk level and the review date to the supplier. The assessor then does the formal review in eramba.

## At a glance

| | |
|---|---|
| **Runs** | Not recurrent. The notification *OA has been submitted* runs it for the assessment that was just submitted (section *Online Assessments*). |
| **Reads** | That assessment's score, open findings and answers. |
| **Writes on the assessment** | *Post Assessment Risk Level* (Low, Medium or High) and *Automated Review Conclusion* (text). |
| **Writes on its suppliers** | *Supplier Risk Level* (same level) and *Last Review Date* (today). |
| **Never does** | The formal *Review* of the assessment: the assessor still reviews and closes it, with the automated conclusion in front of them. It also skips assessments that already have a risk level. |

## 1. Guide and scope

This is automation 3 of 3 in [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It closes the cycle started by [Missing Online Assessment Launch](../Missing%20Online%20Assessment%20Launch/).

## 2. What it does

The automation is not recurrent. The Online Assessments notification *OA has been submitted* runs it once for the assessment that was just submitted (macro `%ONLINE_ASSESSMENT_ID%`). It only processes that assessment if it is submitted and its *Post Assessment Risk Level* is empty or *Undefined*; otherwise it logs `SKIPPED`. The script reads the score, open findings and answers. It then decides a level with one of two methods:

| Mode | Rule |
|---|---|
| AI (the API key Secret of `AI_PROVIDER` exists) | The answers, score and open findings go to the model of `AI_PROVIDER` (`OPENAI_MODEL` or `ANTHROPIC_MODEL`). The model returns a level (Low, Medium or High) and a conclusion of at most 600 characters. Any other answer is an error, and nothing is saved. |
| Score rule (no Secret) | **High** if there are open findings or the score is below `HIGH_BELOW_PCT`. **Medium** if the score is below `MEDIUM_BELOW_PCT`. **Low** otherwise. |

It then saves:

- On the assessment: the custom fields *Post Assessment Risk Level* and *Automated Review Conclusion*. The script does not run eramba's *Review* action: the assessor reviews the assessment with the conclusion in front of them.
- On each linked Third Party: *Supplier Risk Level* and *Last Review Date* (today, UTC).

## 3. Coverage

The level and conclusion, from AI or from the rule, are a proposal for the assessor, not the review itself. The assessor confirms or changes them when reviewing the assessment. Questionnaire answers are sent to the AI provider (OpenAI or Anthropic), so confirm that your data-processing terms allow this before you create the Secret. Attachments uploaded by the supplier are not read.

## 4. Before you start

- eramba Enterprise, with the custom fields from the guide. *Post Assessment Risk Level* and *Supplier Risk Level* must include the options Undefined, Low, Medium and High.
- An Online Assessment custom field *Automated Review Conclusion* of type *Paragraph*, which holds the conclusion.
- An eramba user with *Allow APIs* that can edit Online Assessments and Third Parties, plus an API token for it.
- Optional: an OpenAI or Anthropic API key restricted to this use.

## 5. Setup on the AI provider (optional)

The AI review uses **OpenAI by default**. You only need the key of the provider you use; without it, the score rule is used.

| Provider | `AI_PROVIDER` | Key | Secret | Default model |
|---|---|---|---|---|
| OpenAI (default) | `openai` | A project API key at [platform.openai.com](https://platform.openai.com/api-keys), with access only to the configured model. | `openai_api_key` | `gpt-6.1-sol` |
| Anthropic | `anthropic` | An API key in the [Claude Console](https://platform.claude.com/), in a workspace used only for this. | `anthropic_api_key` | `claude-sonnet-5-5` |

**To switch to Anthropic:** create Secret `anthropic_api_key` and change one line in `$config`: `'AI_PROVIDER' => 'anthropic'`. Nothing else changes: the prompt, the result and the fields saved are the same.

The default models are the balanced, lower-cost model of each provider's latest generation. To use another one, change `OPENAI_MODEL` or `ANTHROPIC_MODEL`.

## 6. Setup in eramba

1. Create Secret `eramba_api_token`. Optionally create the AI key of §5 (`openai_api_key`, or `anthropic_api_key` with `AI_PROVIDER` = `anthropic`).
2. In **Online Assessments**, create an automation: PHP 8.4, no Composer packages, timeout 120 s. Paste [run.php](run.php).
3. Check the custom field names in §7. The script finds their IDs by name, because custom field IDs differ between installations.
4. Leave *Recurrent Automation* off: this automation runs per assessment, not on a schedule.
5. In **Online Assessments > Notifications**, add the notification *OA has been submitted*. Turn on *Trigger Automation* and select this automation in its *Automation* tab. Email can stay off.
6. Test it from the automation editor (*Test*) on a submitted assessment with `DRY_RUN=true` and `FORCE_REVIEW=true`. Then set both back to `false`, submit an assessment from the portal and check the log, the assessment and its supplier.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `OA_RISK_FIELD` / `OA_CONCLUSION_FIELD` | `Post Assessment Risk Level` / `Automated Review Conclusion` | Names of the assessment custom fields. |
| `TP_RISK_FIELD` / `TP_REVIEW_DATE` | `Supplier Risk Level` / `Last Review Date` | Names of the Third Party custom fields. |
| `UNREVIEWED_VALUES` | `''`, `Undefined` | Risk level values that mean the assessment is still pending review. |
| `HIGH_BELOW_PCT` / `MEDIUM_BELOW_PCT` | `50` / `80` | Score thresholds (%) of the rule. These are reference defaults, not part of the guide. |
| `AI_PROVIDER` | `openai` | AI used for the review: `openai` or `anthropic` (§5). |
| `OPENAI_MODEL` | `gpt-6.1-sol` | OpenAI model. It must support Chat Completions and reasoning effort. |
| `ANTHROPIC_MODEL` | `claude-sonnet-5-5` | Anthropic model. It must support effort and structured outputs. |
| `AI_REASONING` | `low` | Reasoning effort, for both providers: `low`, `medium` or `high`. |
| `FORCE_REVIEW` | `false` | `true` processes the assessment even if it already has a level. Testing only. |
| `MAX_ITEMS` | `2000` | The run aborts above this many records. |
| `ERAMBA_API_URL` / `ERAMBA_API_VERIFY_TLS` | Empty / `true` | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |
| `DRY_RUN` | `false` | `true` calculates and logs without saving. AI calls still run. |

## 8. Results

The log shows the assessment's level, suppliers and conclusion. The assessment fields are saved first and its suppliers afterwards. If a supplier update fails, the assessment already has a level and the automation will not retry it. Set the supplier fields by hand, or test the automation on that assessment with `FORCE_REVIEW=true`.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Unexpected AI answer` | The model did not return valid JSON. Retry, or change the model. |
| `The model declined the review` | Anthropic's safety checks declined the request. Retry; if it repeats, use the score rule for that assessment. |
| OpenAI `404` *model does not exist* | `OPENAI_MODEL` is not available to your API key. Use a model listed in your OpenAI account. |
| OpenAI or Anthropic `401` / `404` | Check the key and that it matches `AI_PROVIDER`, and the model name or its access. |
| Log says *score/findings rule* but you expected AI | The Secret of the selected provider is missing: `openai_api_key` for `openai`, `anthropic_api_key` for `anthropic`. |
| `Custom field '…' not found` | Create the field, or fix its name in `$config`. |
| eramba `422` on the risk level | Add the missing option (Low, Medium or High) to the custom field. |
| `SKIPPED` in the log | The assessment is not submitted or already has a level. Use `FORCE_REVIEW` to test. |
| `No Online Assessment in context` | The automation ran without an item: run it from the notification or with *Test* on an assessment. |
| Never runs | Check that the *OA has been submitted* notification is enabled and has *Trigger Automation* with this automation selected. |
| eramba `401` / TLS errors | See [Finance Supplier Onboarding §9](../Finance%20Supplier%20Onboarding/README.md#9-troubleshooting). |

## 10. Customising

Adapt the prompt in `reviewByAi()` to your risk methodology, or change the thresholds of the rule. Keep the result limited to the field's options.

## 11. Removing

Disable or delete the automation, delete the AI Secret (`openai_api_key` or `anthropic_api_key`) and revoke that key. Saved levels and conclusions remain on the assessments and suppliers.

## 12. Changelog

| Version | Change |
|---|---|
| 0.2.0 | Anthropic as an alternative AI provider (`AI_PROVIDER`, `ANTHROPIC_MODEL`). Default OpenAI model `gpt-6.1-sol`. `OPENAI_REASONING` renamed `AI_REASONING`. Reads only the submitted assessment (`GET /api/v2/vendor-assessments/{id}`) instead of listing all of them. |
| 0.1.1 | Default `OPENAI_MODEL` fixed to `gpt-5.6-luna` (`gpt-6.1-luna` does not exist). |
| 0.1.0 | Validated end to end on eramba 3.31.1 with the score rule: portal submission → notification → risk level and conclusion on the assessment, risk level and date on the supplier; re-run skipped. OpenAI review pending validation. |
