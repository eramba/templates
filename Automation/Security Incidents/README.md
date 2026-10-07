# Security Incident Automations – Security Incident Management tutorial

> **These are tutorial templates.** The automation in this folder is the example built in the eramba course [Security Incident Management in eramba](https://www.eramba.org/learning/courses/94). It follows that tutorial's scenario and is meant to be followed alongside it. Review and adapt it to your own process before using it in production.

## How it works

```
incident created ──notification "New Item Created"──▶ Urgent Incident Jira Issue
                                                         │ only if Incident Priority = Urgent
                                                         ▼ and Jira Issue Key is empty
                                   Jira issue created, its key saved in Jira Issue Key
```

## Requirements

- Incident custom fields *Incident Priority* (dropdown: Normal, Urgent) and *Jira Issue Key* (short text).
- An eramba user with *Allow APIs* and its API token in Secret `eramba_api_token`.
- Your Jira Cloud site, account email and API token in Secrets `jira_site_url`, `jira_email` and `jira_api_token`. The site is a Secret so every Jira automation reads it from one place. Add `jira_cloud_id` only if you use an API token with scopes.

## Catalogue

Status: `draft` (not yet validated on real eramba and the target system) · `tested` (tested on a real eramba and target system) · `stable` (used in production by several installations).

| Automation | Section | Technology | Recommended schedule | Version / status |
|---|---|---|---|---|
| [Urgent Incident Jira Issue](Urgent%20Incident%20Jira%20Issue/) | Security Incidents | Jira Cloud + eramba API | On create (notification *New Item Created*) | 0.1.0 draft |

## Shared design

Same as the [Online Assessment automations](../Online%20Assessments/README.md#shared-design): custom fields by name (or `CustomField_N`), idempotent runs, and `DRY_RUN` before going live.

*eramba does not provide support for developing or troubleshooting custom automation code.*
