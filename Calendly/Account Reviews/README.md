# Calendly User vs Employees Audit – Prerequisites

This document defines **all required inputs, variables, and external system setup** needed before implementing the PHP automation script.

---

## System: Calendly

### Required Variables
- `{$CALENDLY_API_BASE_URL}`
  - Value: `https://api.calendly.com`
- `{$CALENDLY_ACCESS_TOKEN}`
  - Calendly **Personal Access Token (PAT)** used for API v2 authentication (Bearer token)
- `{$CALENDLY_PAGE_SIZE}` *(optional)*
  - Default: `100`
  - Used to control pagination when retrieving users

### Steps to create accounts and roles
- Create or designate a **dedicated Calendly account** for automation.
- Assign this account a role with visibility over **all users in the organization**:
  - Recommended: **Owner** or **Admin**
- Generate a **Personal Access Token (PAT)**:
  - Log in to Calendly with the automation account.
  - Navigate to the API / Personal Access Tokens section.
  - Create a new token.
  - Copy it once and store it securely in Eramba as `{$CALENDLY_ACCESS_TOKEN}`.
- Validate access:
  - Call the **Get Current User** endpoint to confirm authentication.
  - Extract the `current_organization` identifier.
  - Use the **Organization Memberships** endpoint to confirm all users are visible.

---

## System: Employees Input (Environment Variable)

### Required Variables
- `{$EMPLOYEES_JSON}`
  - A JSON string stored as an environment variable.
  - Contains **only active employees**.
  - The script will not read from a file.
  - We use a [Google Drive Spreadsheet](https://github.com/eramba/automation/tree/master/Google%20Workspace/Read%20Google%20Sheet) to store employees and some attributes, this automation script reads that file and stores it in eramba so all other account reviews scripts can compare users.

### Required JSON format
The environment variable must contain a JSON **array** of employee objects in the following format:

```json
[
  {
    "name": "Name",
    "surname": "Surname",
    "login": "email@domain.com",
    "roles": ["role"],
    "worker_type": "employee|contractor",
    "os": "mac|windows|linux"
  }
]
