# PHP execution notes

Read when generating or troubleshooting PHP for eramba Automations. Use the functions, item macros and Secrets documented in the destination automation Tools interface. Agent-side access to definitions may use MCP, an authorized API connection or supplied files; it is separate from script execution.

## Use the automation runtime

The generated script runs inside eramba Automations. Use supplied helpers for eramba operations; do not implement raw eramba API requests, custom session handling or a separate eramba API login. External evidence providers may still require their own HTTP/SDK calls and credentials stored in automation Secrets.

Confirm helper signatures and editable fields in the destination's automation documentation. Reference signatures include:

- `editObjectMacro(array|string $fields, string|int|null $routeOrId = null): string` updates an object using the execution context or a supported explicit target ID. Supply the documented field values; let the helper handle transport and authentication.
- `addCommentMacro(string|int|null $routeOrId, string $message, array $attachments = []): string` adds an audit comment when required.
- `uploadAttachmentMacro(string|int|null $routeOrId, string $attachmentInput, ?string $filename = null): string` retains evidence when supported. Confirm accepted input and how its result is used by the destination.

Use the current audit context or its documented ID macro. Validate that it resolves to the intended audit, not the parent control. A test without item context can return a warning instead of updating a record. Do not invent routes or substitute a direct API call when context is missing.

Map the saved audit requirements to the runner's documented editable fields and result options. Preserve methodology, success criteria, planned dates and owners. Set execution dates in the agreed timezone where required. Do not assume a result option ID is universal.

## Macros, Secrets and dependencies

Use the actual item and Secret macro syntax exposed by the automation Tools interface. Do not invent macro names or secret-loading functions. List required Secret names and their purpose in the delivery instructions, without requesting or embedding real values in chat.

If the runner substitutes Secrets into PHP source before parsing, quotes and backslashes can break the script. Follow its documented safe substitution mechanism. Where raw substitution is the only option, a base64-encoded Secret with strict decoding can avoid those syntax hazards; base64 is not encryption. Never print resolved Secrets or expanded source in diagnostics.

Use only official Composer packages, verified from the provider or library project documentation; unofficial provider wrappers and forks are not allowed. Use injected Composer autoloading and helpers. Supply dependencies in the target editor's accepted format; some editors accept `vendor/package:version` lines. Confirm the runner's PHP version separately from Composer version constraints. Do not bootstrap the eramba application, scan the host for dependencies or redefine supplied helpers.

Fit evidence collection, pagination and persistence within the configured execution, memory, storage and output limits. Verify external evidence-source connectivity from the runner.

## Evaluate and persist reliably

Collect the complete population or sample required by the methodology. Apply its explicit rules for missing evidence; otherwise leave the audit result unchanged on incomplete collection. Empty scope must not silently mean all resources or PASS. Preserve mandatory human evaluation.

Provide an explicit dry-run that suppresses every mutation helper, including audit edits, comments and attachment uploads. The Test button executes code and may write. Check existing planned-audit execution before adding a duplicate schedule.

Inspect helper return values according to the documented contract. A returned string may be an error or warning; a nonempty value or normal PHP exit does not establish success. Confirm the intended persisted result and required evidence through MCP or the authorized instance connection after execution. Resolve an uncertain write before retrying, as repeated updates may create duplicate comments, attachments or notifications.

Distinguish a confirmed saved FAIL (successful process execution) from collection, configuration or persistence errors (nonzero exit). If a required helper or evidence-retention capability is unavailable, report that dependency and leave completion pending.

## Pending review and evidence meaning

Use a separate outcome for unresolved required judgment, risk acceptance or external follow-up. Record evidence and a clear pending comment when supported, without calling the audit edit helper or setting execution dates. Dry-run must suppress these writes too. Report that a successful collection can leave the audit incomplete; pending execution must not claim completion. Do not clear an existing result unless explicitly authorized, and do not assume the scheduler will retry a pending audit.

Test the actual pending reporting branch, not only its evaluator flag. Check both the lack of completion writes and the intended evidence/comment calls. Distinguish a verified control failure from a case the integration cannot decide.

A nonempty register with no open items is different from an empty or inaccessible register. Define the applicable denominator and show “not applicable” where there is no rate to calculate; do not fabricate 100% compliance. Confirm register visibility and field semantics in setup. APIs can omit restricted records without returning an error, and search indexing can lag recent edits.

## Library runner baseline

The repository examples use the following contract. Confirm it in the destination Tools interface before deployment; these are version-dependent details, not universal API guarantees.

- PHP 8.4-compatible code, with `<?php` on line 1 and `declare(strict_types=1);` on line 2. The runner injects includes after the declaration.
- Audit context `%SECURITYSERVICEAUDIT_ID%`; Secret placeholders `%SECRET_secret_name%`.
- Audit edits include `start_date`, `end_date`, `security_service_audit_result_option_id` and `result_description`. Library dates use UTC; result defaults are Failed=1 and Passed=2, configurable and verified per installation.
- Check helper responses for `ERROR:` and `WARNING:`. Evidence uses CSV/plain text and is uploaded before saving the audit, then linked in a comment. These operations are not transactional: failed edits may leave uploads; failed comments may leave a saved result.
- The documented baseline has a 240-second maximum, 64 MB memory and 10 KB log truncation. Confirm storage, network and actual configured timeout limits. Budget collection and persistence together; a bounded request alone does not bound a paginated run.
- Audit dates repeat annually. Automated execution may replace the audit's displayed methodology with an automation note; preserve the business definition in the control description or another durable source before activation.

## Evidence decisions and focused verification

Apply these checks where relevant to the methodology rather than copying a fixed test suite:

- Before excluding closed records, distinguish completion from cancellation, duplication and archival. Do not let provider storage/workflow state silently erase unresolved obligations.
- Validate pagination against provider totals when supplied, reject duplicate identities, and keep authenticated pagination on the intended collection endpoint. If configuration is read separately from the population, check for changes where practical; do not claim a transactional snapshot.
- Keep every required check and population member accounted for. Unknown statuses, missing identities and malformed records must not disappear or become success; report record-level failures where meaningful. Abort on an incomplete source collection rather than treating it as a complete population.
- Keep in-progress work distinct from completion. Retain failed attempts even after successful retries; let the methodology determine their effect on the final result.
- Distinguish unavailable values from zero. Validate missing, reversed and future dates and equality at threshold boundaries.
- Aggregate across the defined population. An empty region alone must not introduce a per-region obligation that the methodology does not contain.
- Verify shipped defaults with both sufficient and missing evidence, substituting only connection/context details. Check dry-run makes zero mutation calls and that a claimed successful write was actually accepted. Negative checks should fail for the intended reason, not an unrelated connectivity error.
- Check schedule gaps across month/year boundaries and leap years against cadence and provider retention. A retrospective monthly review does not perform a required weekly review. Explain the resulting audit, attachment and notification frequency; validate scheduler acceptance separately from calendar arithmetic.

## Product documentation

Use eramba's [Internal Controls course](https://www.eramba.org/learning/courses/82) for audit concepts and [Automation course](https://www.eramba.org/learning/courses/77) for runner setup. The destination automation Tools interface supplies the applicable function, macro and Secret contract.
