# Calendly

## Required Variables
- `{$CALENDLY_API_BASE_URL}`
  - Set to `https://api.calendly.com`
- `{$CALENDLY_ACCESS_TOKEN}`
  - Calendly Personal Access Token (PAT) used for API v2 authentication (Bearer token)
- `{$CALENDLY_PAGE_SIZE}` *(optional)*
  - Default: `100` (used when fetching large orgs with pagination; Calendly uses cursor-based pagination)

## Steps to create accounts and roles
- Create (or pick) a dedicated Calendly account for automation.
- Grant it a role that can list the users you want to audit (typically **Owner** or **Admin**; otherwise you may only see a subset).
- Generate a Personal Access Token (PAT) for that account:
  - In Calendly, go to the **API / Personal Access Tokens** area and create a new token.
  - Copy it once and store it in Eramba as `{$CALENDLY_ACCESS_TOKEN}` (Calendly does not allow retrieving it again later).
- Confirm the token works and can “see” your organization:
  - Call **Get Current User** to confirm authentication and retrieve:
    - `uri` (the user URI)
    - `current_organization` (the organization URI)
  - Use the **organization memberships** endpoint to list members of the organization (this is the cleanest way to retrieve all users in a Calendly org using API v2).

---

# Employees JSON (environment variable input)

## Required Variables
- `{$EMPLOYEES_JSON}`
  - A JSON string containing an array of active employees (the script will parse it from the environment variable, not from a file).

## Steps to create accounts and roles
- No external accounts needed.
- Ensure the JSON contains **only active employees** (the script will treat this input as the authoritative active employee list).
- Minimum required fields per employee object:
  - `login` (string; mandatory)
  - `roles` (array of strings; mandatory)
  - `worker_type` (string; mandatory)
  - `os` (string; optional)

## Example (your format)
```json
[
  {
    "login": "esteban.ribicic",
    "roles": ["director", "consultant"],
    "worker_type": "employee",
    "os": "mac"
  }
]