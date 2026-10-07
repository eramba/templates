---
id: incident-urgent-jira-issue
name: Urgent Incident Jira Issue
version: 0.1.0
status: draft
vendor: Atlassian
technology: Jira Cloud REST API v3 + eramba API v2
eramba_module: Security Incidents
trigger: Notification "New Item Created" (Trigger Automation)
guide: Security Incident Management in eramba
secrets:
  - jira_site_url
  - jira_email
  - jira_api_token
  - eramba_api_token
variables:
  - PROJECT_KEY
  - ISSUE_TYPE
  - PRIORITY_FIELD
  - JIRA_KEY_FIELD
  - URGENT_VALUE
  - MAX_ITEMS
  - ERAMBA_API_URL
  - ERAMBA_API_VERIFY_TLS
  - ERAMBA_UI_URL
  - DRY_RUN
dependencies: []
timeout_seconds: 60
eramba_version_tested: null
last_tested: null
---

# Urgent Incident Jira Issue

> **Tutorial template.** This is the example automation of the eramba course [Security Incident Management in eramba](https://www.eramba.org/learning/courses/94), chapter *Automate Jira Issue Creation*. It is built for the scenario of that tutorial (an *Incident Priority* field and a Jira project for the response team). Use it as a starting point: review and adapt it to your own process before using it in production.

**Technology:** Atlassian Jira Cloud. **Status:** draft. Not yet validated end to end with this version of the script.

Opens a Jira issue for every urgent incident. As soon as an incident is created with *Incident Priority* = Urgent, the automation creates an issue in your Jira project and saves its key in the incident's *Jira Issue Key*.

## At a glance

| | |
|---|---|
| **Runs** | Not recurrent. The incident notification *New Item Created* runs it for the incident that was just created (section *Security Incidents*). |
| **Reads** | That incident (title, description, *Incident Priority*, *Jira Issue Key*), and the Jira issues of `PROJECT_KEY` labelled for it. |
| **Creates** | One Jira issue with the incident title, description and a link to the incident, labelled `eramba-incident-<id>`. Only if *Incident Priority* is Urgent and *Jira Issue Key* is empty. |
| **Updates** | Only the incident's *Jira Issue Key*. |
| **Never does** | Create an issue for a Normal incident, create a second issue for the same incident, or change the issue after creating it. |

## 1. Guide and scope

This is the automation of the chapter *Automate Jira Issue Creation* in [Security Incident Management in eramba](https://www.eramba.org/learning/courses/94). Complete the chapters *Prepare Incident Fields* and *Configure Dynamic Status* first: the automation reads the same *Incident Priority* field that the *Urgent Incident* status uses.

## 2. What it does

eramba runs it with the incident that fired the notification (macro `%SECURITYINCIDENT_ID%`):

| Case | Action |
|---|---|
| *Incident Priority* is not `URGENT_VALUE` (Normal or empty) | `SKIPPED`. |
| *Jira Issue Key* already has a value | `SKIPPED`. |
| A Jira issue labelled `eramba-incident-<id>` already exists (an earlier run failed before saving) | `FOUND`: saves its key, does not create another. |
| Otherwise | `CREATED`: creates an issue of type `ISSUE_TYPE` in `PROJECT_KEY`, then saves its key in *Jira Issue Key*. |

The key is saved with `editObjectMacro()`, because the automation lives in *Security Incidents*. The incident is read through the eramba API, because automations have no read helpers and the custom fields are found by name (see §7).

## 3. Coverage

It only runs when an incident is created. An existing incident changed to Urgent later is not sent to Jira: add the same automation to an update notification, or run it on that incident with *Test* (§6).

## 4. Before you start

- eramba Enterprise.
- The incident custom fields from the guide: *Incident Priority* (dropdown: Normal, Urgent) and *Jira Issue Key* (short text, left empty).
- An eramba user with *Allow APIs* that can read and edit Security Incidents, plus an API token for it.
- A Jira Cloud project where the response team works, and an Atlassian account that can create issues in it.

## 5. Setup on the target system

1. In Jira, note the project key (for example `KAN`) and check that the issue type `ISSUE_TYPE` (default `Task`) exists in it.
2. Use an Atlassian account that can browse and create issues in that project. A dedicated service account is best.
3. Create an API token for that account at [id.atlassian.com/manage-profile/security/api-tokens](https://id.atlassian.com/manage-profile/security/api-tokens).

## 6. Setup in eramba

1. Create the Secrets `jira_site_url` (your Jira Cloud site, for example `https://example.atlassian.net`), `jira_email` (the Atlassian account email), `jira_api_token` and `eramba_api_token`. Share them with your other automations: if your Jira site changes, you update `jira_site_url` once.
2. In **Security Incidents**, create an automation: PHP 8.4, no Composer packages, timeout 60 s. Paste [run.php](run.php). Leave *Recurrent Automation* off.
3. Set `PROJECT_KEY` (§7).
4. In **Security Incidents > Notifications**, open *New Item Created*. Turn on *Trigger Automation*, select this automation in the *Automation* tab and set *Status* to enabled. Email can stay off.
5. Test it with fictional incidents, as the guide describes: with *Test* on a Normal incident (it must log `SKIPPED`), then with `DRY_RUN=true` on an Urgent one. Then create an Urgent incident and check that one issue is created, its key is saved, and the link in the issue opens the incident. Run *Test* on it again: it must log `SKIPPED`.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `PROJECT_KEY` | `KAN` | Jira project where issues are created. |
| `ISSUE_TYPE` | `Task` | Issue type name in that project. |
| `PRIORITY_FIELD` | `Incident Priority` | Name of the incident custom field with the priority. The script finds its ID by name, because custom field IDs differ between installations. You can also put its key (`CustomField_8`). |
| `JIRA_KEY_FIELD` | `Jira Issue Key` | Name (or key) of the incident custom field where the issue key is saved. |
| `URGENT_VALUE` | `Urgent` | Priority value that creates an issue. |
| `MAX_ITEMS` | `5000` | The run aborts above this many incidents. |
| `ERAMBA_API_URL` | Empty | eramba address for API calls. Empty uses the address the automation runner provides. |
| `ERAMBA_API_VERIFY_TLS` | `true` | See §9. |
| `ERAMBA_UI_URL` | Empty | eramba address used in the link inside the Jira issue. Set it if your users open eramba at a different address from `ERAMBA_API_URL`. |
| `DRY_RUN` | `false` | `true` logs what would be created without creating or saving anything. |

## 8. Results

The log shows `CREATED` or `FOUND` with the issue key and then `SAVED`, or `SKIPPED` with the reason.

If the run fails after the issue is created, the incident stays without key. Run it again (with *Test*): it finds the issue by its label `eramba-incident-<id>` and saves the key without creating a second issue.

## 9. Troubleshooting

| Problem | Action |
|---|---|
| `Secret '…' is missing` | Create it with exactly that name. |
| `Custom field '…' not found` | Create the field, or fix its name in `$config`. |
| `No incident in context` | The automation ran without an item: run it from the notification or with *Test* on an incident. |
| Never runs | Check that *New Item Created* is enabled and has *Trigger Automation* with this automation selected. |
| Incident skipped | Check the reason in the log: *Incident Priority* must be Urgent and *Jira Issue Key* empty. |
| Jira `401` / `403` / `404` | Check `jira_site_url`, `jira_email`, `jira_api_token` and that the account can create issues in `PROJECT_KEY`. |
| Jira `400` on creation | Usually a wrong `PROJECT_KEY` or `ISSUE_TYPE`, or a field that the project requires; read the message in the log. |
| eramba `401` | The eramba token is wrong or its user lacks *Allow APIs*. |
| TLS errors | Fix the certificate of your eramba. Set `ERAMBA_API_VERIFY_TLS=false` only on a test instance with a self-signed certificate. |

## 10. Customising

Change the payload in `createIssue()` to set a Jira priority, assignee or components (field names as in the Jira documentation of `POST /rest/api/3/issue`).

## 11. Removing

Turn off *Trigger Automation* in the *New Item Created* notification, then disable or delete the automation. Revoke the Jira and eramba tokens, and delete the Secrets, if nothing else uses them. Issues already created remain in Jira.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | First version, from the course example: custom fields found by name, issue reused by label on retry. |
