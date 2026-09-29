---
id: linear-corrective-actions
name: Nonconformity and Corrective Action Tracking
version: 0.1.0
status: draft
vendor: Linear
technology: Linear GraphQL API
eramba_module: Internal Controls
trigger: Audit planned date
controls:
  - Nonconformity and Corrective Action Tracking
secrets:
  - linear_api_key_b64
variables:
  - TEAM_ID
  - REGISTER_LABEL
  - REVIEWER_IDS
  - DEADLINE_TIMEZONE
  - MAX_ISSUES
  - DRY_RUN
  - RESULT_PASSED_ID
  - RESULT_FAILED_ID
  - MAX_LOG_ITEMS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Nonconformity and Corrective Action Tracking

**Technology:** Linear. **Draft:** real Linear/eramba validation pending.

Review a corrective-action register already maintained in Linear: owners, target dates, overdue work and recorded human verification of root cause and effectiveness. A completed issue alone is not proof that its corrective action worked.

## 1. Controls and policies

Source: **Nonconformity and Corrective Action Tracking**, identified by title in [internal_controls.csv](../../../../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/internal_controls.csv). Policy mapping: Risk Management > Risk Register Review; it adds no checks.

The methodology requires a register with description, root cause, action taken, owner and closure status; owners/target dates for open items; root-cause analysis for significant nonconformities; effectiveness verification for closed items; documentation of overdue items. Review monthly.

Schedule the first of each month. Include all current open/unresolved items and closures since the first day of the previous calendar month, through execution time (UTC). Earlier valid completions are counted separately. This is a current register review, not a reconstruction of historical assignments or deleted issues.

## 2. What it checks

| Check | Evaluation |
|---|---|
| Register | Complete visible team/label collection, including archived records; description and creation date present. |
| Open actions | Assigned owner and valid target date after creation. |
| Overdue actions | Listed when the local target day has ended. Overdue status is documented, not an invented failure threshold. |
| Closure | Valid completion date and explicit verification of effectiveness. |
| Evidence review | Authorized review comment bound to the current issue content and relevant fields. |

Passed/Failed is saved only when required reviews are available. Missing reviews, ambiguous dispositions and empty registers remain pending. Collection errors abort without deciding the control result.

## 3. Coverage

This integration is appropriate when Linear is your actual nonconformity or corrective-action register. It does not turn a generic backlog into one. Configure the authoritative team and label, including all applicable records. The identity must see private in-scope issues. Unlabelled, deleted or inaccessible records cannot be inferred.

The script verifies recorded human review; it does not decide whether a root-cause analysis is technically sound. For every evaluated record, an authorized reviewer must confirm the description and action record, assess whether the nonconformity is significant and verify its root-cause analysis where required. For completed records, that review must also confirm evidence of effectiveness. Canceled, duplicate, archived unresolved and unknown workflow states remain pending even with a review comment.

The content fingerprint covers issue ID, description, creation/completion dates, due date, priority, archive status, workflow, assignee and team. Changing those fields invalidates the recorded review. It does not cover changes to externally linked documents: put the evidence's immutable version/reference in the issue description and renew the review when it changes. Live collection is not a transactional snapshot.

## 4. Before you start

You need a complete Linear register, designated reviewers, retained evidence of actions/root cause/effectiveness, and eramba Enterprise. If these reviews do not already form part of your process, use pending evidence for manual completion rather than treating a marker as a substitute for a review.

## 5. Setup on Linear

### 5.1 Identity

Create a dedicated API key with **Read** access restricted to the relevant team. Store its single-line base64 value as `linear_api_key_b64` in eramba Secrets. Base64 protects substitution syntax; it is not encryption. The script decodes the key and uses Linear's API-key Authorization format. No OAuth token or write permission is needed. Allow HTTPS to `api.linear.app`.

### 5.2 Register and review evidence

Set the team UUID, exact register label and authorized reviewer user UUIDs. Use the description to record the nonconformity, action taken/planned and versioned supporting evidence. Assign owners and target dates using native fields.

An initial run produces a review fingerprint for each item needing review. After reviewing that exact revision, an authorized reviewer posts this **entire comment** on the issue, replacing the placeholder with its 64-character fingerprint:

```text
eramba-corrective-action-reviewed: <fingerprint from evidence>
```

This statement records the review described in §3. The script verifies the comment's author, timestamp and fingerprint. It never creates the statement itself. Other comments, unauthorized authors and old fingerprints cannot approve the record. Run again to evaluate the completed review, or finish the pending audit manually. Changes to the fields in §3 require a new review.

The [official Linear SDK](https://linear.app/developers/sdk) is TypeScript. PHP uses the official [Guzzle library](https://docs.guzzlephp.org/en/stable/overview.html#installation) with fixed, read-only GraphQL queries and JSON variables, following the [Linear schema](https://github.com/linear/linear/blob/master/packages/sdk/src/schema.graphql). GraphQL errors reject partial data, including HTTP 200 responses.

## 6. Setup in eramba

Follow [Installing an automation](../../docs/installing.md). Paste [run.php](run.php), configure `guzzlehttp/guzzle:^7.9`, a 240-second timeout, the Secret and monthly audit dates.

Set `TEAM_ID`, verify `REGISTER_LABEL` and `REVIEWER_IDS`, then inspect a dry-run and validate a disposable audit. No reviewer configured means pending, never an automatic approval. The shipped `DRY_RUN=false` writes evidence/results to eramba, not to Linear.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `TEAM_ID` | Empty | Required authoritative register team UUID. |
| `REGISTER_LABEL` | `nonconformity` | Exact register label; no status filter hides unresolved records. |
| `REVIEWER_IDS` | `[]` | Authorized Linear user UUIDs; empty leaves required reviews pending. |
| `DEADLINE_TIMEZONE` | `UTC` | IANA timezone for target dates; a day ends at the next local midnight. |
| `MAX_ISSUES` | `200` | 1–2000 total register records, including historical completions. |
| `DRY_RUN` | `false` | True suppresses every eramba write. |
| `RESULT_PASSED_ID` | `2` | Verify the Passed option ID. |
| `RESULT_FAILED_ID` | `1` | Verify the Failed option ID; must differ from Passed. |
| `MAX_LOG_ITEMS` | `5` | 1–10 summary observations; full observations are attached. |

Collection is bounded by 170 seconds plus the active request, 2 MB per response, 100 pages per connection, 1000 comments per issue and 600 KB of evidence. Reporting shares the 240-second runner budget. Limits abort rather than truncate into a pass.

## 8. Results

Uploads CSV observations and TXT configuration. Evidence includes issue, owner and reviewer IDs, review fingerprints and dates. Issue descriptions and comment bodies are read for verification but not logged or attached. No API keys are exported.

Pending saves evidence and a comment without editing result/dates or clearing an old result. Use an audit without a prior result. A definitive result also saves conclusion and UTC execution dates. Dry-run makes no writes. Pending execution does not schedule a retry; reruns can duplicate attachments/comments. Inspect partial persistence before retrying after errors.

## 9. Troubleshooting

- Missing or inaccessible team: check UUID, key permissions and private-team visibility.
- Pending evidence review: inspect the fingerprint and reviewer IDs; verify the actual evidence before adding the review statement.
- Canceled/duplicate/archived unresolved issue: verify disposition and complete manually or correct the register.
- Missing owner/target/description: correct the underlying register. Editing covered fields requires a new review.
- Collection limit or throttling: retry appropriately or use a suitable scoped integration; partial collections never save a result.
- Persistence error: inspect the audit for existing evidence/result before retrying.

## 10. Customising

Use your existing register and authorized reviewers. Do not map arbitrary priority values to significance: the reviewer makes that determination from the nonconformity. Do not issue automatic review markers from a bot or use them without reviewing the evidence.

## 11. Removing

Unlink the automation, retain historical audit evidence and revoke its dedicated API key when unused. No Linear records are modified by the automation.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Owner/date and overdue review with authorized, revision-bound evidence verification; missing reviews and ambiguous dispositions pending. |
