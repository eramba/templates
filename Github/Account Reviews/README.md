### System: GitHub (Org accounts vs active employees JSON)

#### Required Variables
- `EMPLOYEES_JSON_PATH`: Absolute path to the JSON file containing **active** employees (array of employee objects).
- `EMPLOYEE_EMAIL_FIELD`: Which field in each employee record is treated as the “employee email” for matching.  
  - Default suggestion: `login` (because your example only shows `login`, not `email`).
- `GITHUB_ORG`: GitHub organization name to audit (example: `eramba`).
- `GITHUB_TOKEN`: GitHub Personal Access Token (PAT) used to call GitHub APIs.
- `GITHUB_API_BASE_URL`: Optional. Default: `https://api.github.com`
- `GITHUB_USE_SCIM`: Optional boolean (`true`/`false`). If `true`, the script will use SCIM to retrieve org users (best chance to get emails).
- `GITHUB_SCIM_BASE_URL`: Required only if `GITHUB_USE_SCIM=true`.  
  - Default for GitHub Enterprise Cloud org SCIM: `https://api.github.com/scim/v2/organizations/{$GITHUB_ORG}`
- `GITHUB_SCIM_TOKEN`: Required only if `GITHUB_USE_SCIM=true`. Token with permission to read SCIM users.
- `NAME_MATCH_NORMALIZATION`: Optional. Default: `true`. If enabled, the script will normalize names for matching (case-folding, trimming, collapsing spaces, stripping accents).

#### Steps to create accounts and roles

- **Step 1 — Decide how you will retrieve org user emails (important)**
  - GitHub’s normal org membership APIs often **do not provide a member’s email** (many users keep email private).
  - If you need reliable “Email” matching, you typically need **SCIM provisioning** (GitHub Enterprise) or you must accept that many GitHub users will have `email = null`, forcing fallback to Name/Surname matching.

- **Step 2 — Create a GitHub token**
  - Create a token for the automation identity (a dedicated user is best).
  - Minimum permissions depend on what you audit:
    - If you only need org members list + user profiles: permissions that allow reading org membership.
    - If you want to use SCIM: permissions to read SCIM users (Enterprise/org SCIM access).
  - Store the token value in Eramba as `{$GITHUB_TOKEN}` (and `{$GITHUB_SCIM_TOKEN}` if SCIM is enabled).

- **Step 3 — Ensure the automation identity can read the org membership**
  - Add the automation user to the GitHub org.
  - Make sure the org allows the token to read members (some org settings restrict member visibility to non-owners).
  - If your org hides members publicly, the automation identity may need to be an org **owner** (or have sufficient org read access) to list all members.

- **Step 4 — (Optional but recommended) Enable SCIM for accurate emails**
  - If you have GitHub Enterprise Cloud and use an IdP (Okta/Azure AD/etc.), enable **SCIM provisioning** for the org.
  - Confirm SCIM endpoint access works and returns users with email attributes.
  - Store SCIM token separately as `{$GITHUB_SCIM_TOKEN}` and set `{$GITHUB_USE_SCIM}` to `true`.

- **Step 5 — Prepare the employees JSON file**
  - The JSON must contain **only active employees** (since matching is “against active employees”).
  - Confirm the field you want to treat as employee email exists (set `EMPLOYEE_EMAIL_FIELD` accordingly).
    - If the employee JSON contains a true email field later (e.g., `email`), set `EMPLOYEE_EMAIL_FIELD=email`.
    - If it does not, and `login` is *not* an email, the “Email” match will not be meaningful.

- **Step 6 — Define expected matching behavior**
  - Matching order:
    1) **Email**: employee email (from `EMPLOYEE_EMAIL_FIELD`) vs GitHub account email
    2) **Name/Surname**: only if email did not match; compare employee `name` + `surname` to GitHub profile name split into name/surname (with normalization if enabled)

---