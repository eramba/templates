---
id: oa-finance-supplier-onboarding
name: Finance Supplier Onboarding
version: 0.1.0
status: draft
vendor: Google
technology: Google Sheets API + eramba API v2
eramba_module: Third Parties
trigger: Recurrent (daily)
guide: Online Assessments - Advanced Configurations (automation 1 of 3)
secrets:
  - google_service_account
  - eramba_api_token
variables:
  - SPREADSHEET_ID
  - SHEET_RANGE
  - MAX_ROWS
  - COL_NAME
  - COL_TYPE
  - COL_CONTACT_NAME
  - COL_CONTACT_SURNAME
  - COL_CONTACT_EMAIL
  - COL_FINANCE_ID
  - FINANCE_ID_FIELD
  - SUPPLIER_GROUPS
  - GRC_GROUP
  - TYPE_MAP
  - DEFAULT_TYPE_ID
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - DRY_RUN
dependencies: []
timeout_seconds: 60
eramba_version_tested: 3.31.1
last_tested: null
---

# Finance Supplier Onboarding

> **Tutorial templates.** This is one of the three example automations of the eramba course [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It is built for the scenario of that tutorial (a Finance supplier list, supplier accounts, a supplier questionnaire). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** Google Sheets. **Status:** draft. Its logic was tested on eramba 3.31.1; this packaged version has not been re-validated.

Keeps eramba's supplier list in sync with the list Finance maintains in Google Sheets. Each new supplier gets a supplier user account and a Third Party whose Third Party Contact is that account.

## At a glance

| | |
|---|---|
| **Runs** | Recurrent, daily (section *Third Parties*). |
| **Reads** | The Finance supplier sheet in Google Sheets (read-only), and existing users and Third Parties in eramba. |
| **Creates** | For each new supplier: a supplier user (Online Assessment portal only, magic-link access) and a Third Party with that user as *Third Party Contact* and the GRC group as *GRC Contact*. |
| **Updates** | Only the *Finance Supplier ID* of an existing Third Party with the same name and no ID yet. |
| **Never does** | Delete or disable anything, change existing contacts, or send Online Assessments. |

## 1. Guide and scope

This is automation 1 of 3 in [Online Assessments – Advanced Configurations](https://www.eramba.org/learning/courses/81). It replaces the manual step of creating the supplier test account and the Third Party. It does not create Online Assessments; [Missing Online Assessment Launch](../Missing%20Online%20Assessment%20Launch/) does.

## 2. What it does

| Case | Action |
|---|---|
| A Third Party already has the row's Finance Supplier ID | Nothing. |
| A Third Party has the same name but no Finance Supplier ID | Stores the ID on it (`LINKED`). Suppliers created before the ID existed are not duplicated. |
| New supplier with contact email | Reuses the user with that email, or creates one. Then it creates the Third Party with that user as Third Party Contact and the GRC group as GRC Contact (`CREATED`). |
| New supplier without contact email | Creates the Third Party without contact. The Dynamic Status *Missing Supplier Contact* flags it until the details exist. |

New supplier accounts are active, use magic-link access (no local password) and only have the Online Assessment portal. Their groups are `SUPPLIER_GROUPS`.

## 3. Coverage

Only rows with a supplier name are read. Rows removed from the sheet are not deleted or disabled in eramba. If a contact email changes in the sheet, the existing Third Party is not updated, because the script never edits existing records except to store a missing Finance Supplier ID.

## 4. Before you start

- eramba Enterprise, with the configuration from the guide's *Implementation* chapter.
- The Third Party custom field *Finance Supplier ID* (text) is in the view, and the Finance sheet has a matching *Supplier ID* column.
- Groups *No Allowed Permissions*, *Suppliers* and *GRC* exist.
- An eramba user with *Allow APIs* that can create users and edit Third Parties, plus an API token for it.

## 5. Setup on Google

1. In a Google Cloud project, enable the Google Sheets API. Create a dedicated service account and a JSON key for it.
2. Share the Finance sheet with the service account's `client_email` as **Viewer**. The script only requests `spreadsheets.readonly`.
3. Paste the whole JSON key, as downloaded, into Secret `google_service_account`. The script reads it inside a nowdoc, so its quotes and line breaks need no escaping.

## 6. Setup in eramba

1. Create Secrets `google_service_account` and `eramba_api_token` in Settings / Application Configuration / Automation Secrets.
2. In **Third Parties**, create an automation: PHP 8.4, no Composer packages, timeout 60 s. Paste [run.php](run.php).
3. Set `SPREADSHEET_ID` and review the column names and IDs in §7.
4. Run with `DRY_RUN=true`, check the log, then run live. Run it again to confirm that nothing is duplicated.
5. Enable *Recurrent Automation* (daily).

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `SPREADSHEET_ID` | Empty | Required. The ID from the sheet URL. |
| `SHEET_RANGE` | `Sheet1!A:Z` | Tab and columns to read. The first row is the header. |
| `MAX_ROWS` | `500` | The run aborts above this, with no partial sync. |
| `COL_*` | Guide column names | Header names, case-insensitive. The name, email and Supplier ID columns are mandatory. |
| `FINANCE_ID_FIELD` | `CustomField_3` | Field ID of *Finance Supplier ID*. Check it in the API documentation of your instance. |
| `SUPPLIER_GROUPS` | `No Allowed Permissions`, `Suppliers` | Groups of new supplier accounts. |
| `GRC_GROUP` | `GRC` | Group set as GRC Contact. |
| `TYPE_MAP` / `DEFAULT_TYPE_ID` | Customer 1, Supplier 2, Regulator 3 / `2` | Maps the sheet *Type* column to Third Party type IDs. |
| `ERAMBA_API_URL` | Empty | eramba URL as seen from the runner. Empty uses the runner's `ERAMBA_BASE_URL`. |
| `ERAMBA_API_VERIFY_TLS` | `true` | See §9. |
| `DRY_RUN` | `false` | `true` reads everything and logs the planned actions without writing. |

## 8. Results

The log lists every created and linked supplier, plus a summary line. Row errors are logged and the run continues; at the end the run exits with an error so that eramba marks it as failed. Writes are not transactional. If the account was created but the Third Party failed, the next run reuses the account.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create the Secret with exactly that name. |
| Google `403 PERMISSION_DENIED` | Share the sheet with the service account email. |
| `Column '…' not found` | Fix the `COL_*` names or the sheet header. |
| eramba `401` | Enable *Allow APIs* on the token's user, or regenerate the token. |
| eramba `422` | A required field is missing or has the wrong type. Check custom fields marked as required. |
| TLS error to the eramba API | The runner reaches eramba through an internal URL with a self-signed certificate. Prefer a trusted certificate or a URL with one in `ERAMBA_API_URL`. Set `ERAMBA_API_VERIFY_TLS=false` only for that internal URL, never for an Internet host. |

## 10. Customising

Add fixed values to `$data` in `syncRow()` for custom fields your Third Parties require. To map other sheet columns, add a `COL_*` variable and read it with `cell()`.

## 11. Removing

Disable or delete the automation, delete the two Secrets, revoke the API token, and stop sharing the sheet with the service account. Users and Third Parties already created remain.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | First packaged version. Logic validated on eramba 3.31.1: creation, Finance ID linking and re-runs without duplicates. Packaged version pending re-validation. |
