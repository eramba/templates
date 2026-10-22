---
id: workday-current-employees
name: Workday Current Employees
version: 0.1.0
status: draft
technology: Workday HCM
vendor: Workday
eramba_module: Account Review Feeds
trigger: Account Review pull
feed_type: List of current employees
secrets:
  - workday_report_url
  - workday_isu_username
  - workday_isu_password_b64
variables:
  - USER_KEY
  - FIELD_EMAIL
  - FIELD_EMPLOYEE_ID
  - FIELD_NAME
  - FIELD_ACTIVE
  - FEED_MODE
  - MAX_USERS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# Workday Current Employees

**Technology:** Workday HCM.

Send the people who work for you today, as Workday knows them, to an eramba feed of type **List of current employees**. **Draft: functional and installation validation pending.**

## 1. Purpose

The comparison side of an **Exit** review. When someone leaves, HR terminates them in Workday, but their accounts in other applications (GitHub, Zoom, Zendesk, AI tools…) can stay active. An Exit review compares an application's account feed with this list and flags every application account that matches no current employee.

**Review types.** Employee feeds are only used by Exit reviews; Snapshot and Differential reviews use account feeds. See [review types](../../README.md#review-types). If your identity provider is a better source of truth for who has access, use [Entra ID Current Employees](../../Microsoft/Entra%20ID%20Current%20Employees/) instead; Workday is the better source when HR terminations are what should end access.

With `FEED_MODE` set to `former`, the same script feeds a **List of former employees** from a report of terminated workers (see §7).

## 2. What it gathers

The script reads one Workday custom report that you build and expose as a web service (Report-as-a-Service, RaaS), in JSON, with an Integration System User (ISU). The report decides who is a current employee; the script reads it whole, validates it and publishes it.

| Feed field | Source |
|---|---|
| `user` | Primary work email (`FIELD_EMAIL`) by default; Employee ID (`FIELD_EMPLOYEE_ID`) with `USER_KEY = 'employee_id'` |
| `description` | Worker name (`FIELD_NAME`) and Employee ID, e.g. `Ana Ruiz; employee 21001` |

Employee feeds have no roles.

## 3. Coverage

- Included: every row of the report. Who appears is decided by the report's data source and filters, so build it as described in §5.
- Optional safety check: with `FIELD_ACTIVE` set, rows whose Active status does not match `FEED_MODE` (inactive rows in a current list, active rows in a former list) are left out and counted in the log.
- Workers without the key field (no work email, for example) are left out and counted in the log. Leaving someone out of a current-employees list can only add findings, never hide one.
- The key must hold the same value as the application accounts it is compared with, usually the work email. An application account with a different email (a personal address, an alias, a service account) will be flagged; that is expected and reviewers decide on it.
- Not covered: report prompts (set defaults in the report instead), multi-instance fields (each field must be a single text value), OAuth 2.0 API clients, and the Workday REST API.
- Limits: RaaS returns the whole report in one response (no pages); responses over 4 MB, more rows than `MAX_USERS`, duplicate keys (ignoring case), an unexpected format or an empty list abort the run and nothing is published. Keep the report to the 3–4 fields below; 4 MB is roughly 25,000–30,000 rows.

## 4. Before you start

- eramba Enterprise with the Account Review Feeds automation helper `writeAccountReviewArtifact()`.
- A Workday administrator who can create an Integration System User, an integration system security group, domain security policies and an Advanced custom report.
- Network access from eramba to your Workday web-services host (for example `wd2-impl-services1.workday.com` or `wd5-services1.myworkday.com`).

## 5. Setup on Workday

Task names are Workday's standard tasks; search for them in the Workday search bar. Validate the exact data source and domain names on your tenant, as they can vary with your configuration.

1. **Create Integration System User**: user name such as `ISU_eramba`, a strong password, **Do Not Allow UI Sessions** checked, **Require New Password at Next Sign In** unchecked. In **Maintain Password Rules**, add the ISU to **System Users exempt from password expiration**, or plan to rotate the secret when the password expires.
2. **Create Security Group**: type **Integration System Security Group (Unconstrained)**, for example `ISSG eramba Employees`, with the ISU as its only member.
3. **Maintain Permissions for Security Group** (or edit the domain security policies): give the group **Get (View)** access, and nothing else, to the domains that secure the report fields, typically **Worker Data: Public Worker Reports** (worker, Employee ID, active status) and **Worker Data: Work Contact Information** (work email). Then run **Activate Pending Security Policy Changes**. View-only access to these domains is all the feed needs: it reads one report and never writes.
4. **Create Custom Report**: type **Advanced**, data source of active workers, such as **All Active Employees** (or one that also includes contingent workers if they get application accounts). Add these columns and set each **Column Heading XML Alias** (the JSON key the script reads):

   | Field | XML alias (default variable value) |
   |---|---|
   | Worker | `Worker` (`FIELD_NAME`) |
   | Employee ID | `Employee_ID` (`FIELD_EMPLOYEE_ID`) |
   | Email - Primary Work (or your work-email field) | `Work_Email` (`FIELD_EMAIL`) |
   | Active Status (optional) | `Active_Status` (`FIELD_ACTIVE`) |

   No prompts. On the **Advanced** tab, check **Enable As Web Service**. On the **Share** tab, share the report with the ISU (or its security group).
5. On the report: **Actions → Web Service → View URLs**, choose **JSON** and copy the URL. It looks like `https://<host>/ccx/service/customreport2/<tenant>/<owner>/<report>?format=json`.
6. Optional test: open the URL in a browser signed in as a report administrator; the result is `{"Report_Entry":[{…}]}` with your aliases as keys.

For a **former employees** feed, build a second report from terminated workers (for example a data source of all workers filtered on Active = No, or on termination date within the period you review) and use a second automation with `FEED_MODE = 'former'`.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**:
   - `workday_report_url`: the JSON URL from step 5 (any `?format=…` is replaced by `format=json`; other query parameters are rejected).
   - `workday_isu_username`: the ISU user name (some tenants expect `ISU_eramba@<tenant>`; use the form that signs in on your tenant).
   - `workday_isu_password_b64`: the ISU password, base64-encoded on one line (`printf %s 'password' | base64`).
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of current employees** (or **List of former employees**), then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test**: exit code `0`, stdout only shows the `account-review` artifact declaration.
4. Create an Account Review of type **Exits**: the application's account feed as the feed to review, this feed as the **Exit Feed**. eramba matches both feeds by `user`, ignoring case.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | `email` (uses `FIELD_EMAIL`) or `employee_id` (uses `FIELD_EMPLOYEE_ID`). Must hold the same value as the compared application accounts. |
| `FIELD_EMAIL` | `Work_Email` | XML alias of the work-email column. |
| `FIELD_EMPLOYEE_ID` | `Employee_ID` | XML alias of the Employee ID column; shown in the description. |
| `FIELD_NAME` | `Worker` | XML alias of the worker-name column; shown in the description. |
| `FIELD_ACTIVE` | `''` | Empty: trust the report filter. Otherwise the XML alias of a boolean Active status column (`1`/`0`); rows that do not match `FEED_MODE` are left out and counted. |
| `FEED_MODE` | `current` | `current` for a List of current employees; `former` for a List of former employees (only changes the `FIELD_ACTIVE` check). |
| `MAX_USERS` | `10000` | 1–50000 rows; exceeding it aborts instead of truncating. |

Fixed limits: 200 seconds of collection, one response of at most 4 MB, 8 MiB of feed. An empty list always aborts: an empty current list would flag every account, an empty former list would hide every leaver.

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only (report rows, employees staged, and how many were left out and why). No names, emails or IDs are logged. Exit 1 means nothing was published.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` / invalid base64 | Check the secret names and the base64 value |
| `Secret 'workday_report_url' must be …` | Paste the JSON URL from **View URLs**, without prompts |
| `HTTP 401` | Check the ISU user name form, the password and its expiry |
| `HTTP 403` / `HTTP 500` with a valid user | Share the report with the ISU and grant the group View on the field domains, then activate the security changes |
| `returned invalid JSON` | The URL is not the RaaS URL, or Workday returned a sign-in page |
| `not a single text value` | The field is multi-instance; use a single-instance field such as Email - Primary Work |
| `No employees found` | Check the report data source and filter, the XML aliases and `USER_KEY` |
| `Two accounts share the same key` | Two report rows have the same email; fix the data or the report |
| Every application account flagged | The keys differ: compare a few values of both feeds |

## 10. Customising

Adjust the documented variables. Other filters (company, location, worker type) belong in the Workday report, not in the script. Section 4 is a copy of the [shared code blocks](../../../_shared/): do not edit it here.

Future work: OAuth 2.0 (API Client for Integrations with a refresh token) instead of Basic authentication, and the Workday REST API (`workers`) as an alternative source.

## 11. Removing

Stop the Exit reviews that use the feed, then remove the feed and, if nothing else uses them, the secrets, the report's web-service sharing, the security group and the ISU (inactivate it in Workday).

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |

References: Workday Community / Workday Administrator Guide (sign-in required): *Reporting as a Service (RaaS)*, *Create Custom Report*, *Create Integration System User*, *Integration System Security Groups*, *Activate Pending Security Policy Changes*. Public: [Reports as a Service (RaaS)](https://doc.workday.com/admin-guide/en-us/reporting-and-analytics/custom-reports-and-analytics/reports-as-a-service-raas-/dan1370796320263.html), [Workday integrations overview](https://doc.workday.com/workday-education/en-us/course-manuals/creating-and-securing-integrations/workday-integrations-overview.html).
