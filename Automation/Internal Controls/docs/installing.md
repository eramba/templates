# Installing an automation

Generic steps to install, test, upgrade and remove any automation of this library.
Each automation's README gives the exact values (secrets, permissions, variables, timeout);
this page explains where they go in eramba.

Every automation README has the same numbered sections. Before you start, read **§3 Coverage**
(what it checks and what stays manual) and **§4 Before you start** (what you need).

---

## 1. Prepare the target system

Follow the automation's README **§5 (Setup on <technology>)**. In every case you:

- create a **dedicated account** for eramba (never a personal or admin account);
- give it **read-only** permissions, limited to what the README lists;
- create its credentials (API key, access key, client secret…) and keep them for step 2.

## 2. Create the secrets in eramba

1. Go to **Settings › Application Configuration › Automation Secrets**.
2. Create one secret per row of the *Secrets* table in the README **§6**, with **exactly** that name.

eramba inserts each secret in the script where `%SECRET_<name>%` appears, with the name in
lowercase and spaces or dashes turned into `_`. A secret called `aws_access_key_id` is used in the
code as `%SECRET_aws_access_key_id%`. The value is never shown in the code or in the logs.

> Secret values are inserted as text inside single quotes: a value containing `'` breaks the script.

## 3. Create the automation

1. In the left menu go to **Control Catalog › Internal Controls**. At the top of the page, open the **Audits** tab (the list of audits, not a control). Click the **⋮** button at the top right and choose **Automation**. Add a new automation. You come back here to edit or test it.
2. Fill in:

| Field | Value |
|---|---|
| **Name** | The automation's name from its README (e.g. *AWS Backup – Backup Execution and Restore Test*) |
| **Recurrent Automation** | Off. The automation runs on the audit dates of the control |
| **Language** | PHP |
| **Timeout** | `timeout_seconds` from the README's metadata (also in README §6). eramba's default is too short for most automations |
| **Composer packages** | The `dependencies` from the README, one per line, e.g. `aws/aws-sdk-php:^3.300`. Empty if none |
| **Code** | The full content of `run.php` |

3. In the code, edit only the **VARIABLES** block (section 2 of the script) as described in README **§7**. Variables marked ⚠️ must be reviewed. Do not change the rest.

## 4. Link the automation to the control

1. In **Control Catalog › Internal Controls** (tab *Internal Controls*), open the control (e.g. *Backup Execution and Restore Test*) and edit it.
2. In the edit window, tab **Audits**:
   - **Audits required**.
   - **Audit dates**: one date per audit, following the *Recommended audits* of the README (§1). eramba repeats the dates every year and creates the audits from the next occurrence onwards (a date earlier than today goes to next year).
   - **Audit execution: Automated**, and select the automation. **Select only one**: every automation writes the result, so two would overwrite each other. To use two technologies or accounts, create one control for each.
   - **Audit owner** and **Audit evidence owner**: the people who receive notifications and review the results.
3. Keep the control's **Audit Methodology** in the control description (tab *General*). With automated audits, eramba writes "Automation in use: …" as the methodology of each audit.
4. Optional, for traceability: in the tab *General*, field **Policies**, link the policy listed in README §1.

## 5. Test

> ⚠️ **A test is a real run.** The audit you select gets its result, conclusion, comment and evidence, and is completed with today's date. If you do not want to complete the next planned audit early, test first on a separate control (e.g. *TEST – Backup Execution and Restore Test* with one audit date) and delete it afterwards.

1. Open the automation (Internal Controls › Audits › ⋮ › Automation), click **Test**, and select an audit.
2. Read **STDOUT**: every step is reported (configuration, connection, what was found, result).
3. **STDERR must be empty.** If it is not, the run failed: the audit was not changed and STDERR explains why (README **§9 Troubleshooting**).

## 6. Normal operation

- On every audit date, eramba runs the automation on the control's audit whose *Planned Start* is that day and has no result yet.
- Results appear on the audit: **Audit Result**, **Audit Conclusion**, a comment with the evidence attached.
- Every run (tests included) is logged in **Settings › Application Configuration › Automation Logs**.
- If a run fails (exit code ≠ 0 or any STDERR output), eramba emails the administrators and the audit stays open. Fix the cause and run the automation again, or complete the audit manually.

Things to review periodically:

| When | What |
|---|---|
| When credentials expire or are rotated | Update the secrets in eramba (same names) |
| After changes on the target system (new regions, accounts, resources) | Review the scope variables |
| When a new version of the automation is published | See [Upgrading](#upgrading) |

## Upgrading

1. Read the new version's **Changelog** (README §12): new variables, permissions or secrets.
2. Note your current values of the **VARIABLES** block.
3. Replace the code of the automation with the new `run.php` and re-apply your variable values.
4. Update composer packages, timeout, permissions or secrets if the changelog says so.
5. Run **Test** on an audit.

The version in use is visible in the first line of every run's output and in every comment the automation writes (`[<id> vX.Y.Z]`).

## Removing an automation

1. Edit the control: set **Audit execution** back to **Manual** (fill in methodology and success criteria), or remove the automation from it.
2. Delete the automation.
3. Delete its secrets in **Automation Secrets**.
4. On the target system, delete the account and its credentials (README **§11**).
