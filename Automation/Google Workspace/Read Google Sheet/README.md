## System: Google Workspace (Google Sheets + Google Drive)

### Required Variables
- `GOOGLE_SERVICE_ACCOUNT_JSON`  
  - The full Service Account key JSON (as a string/secret), **or** a file path like `GOOGLE_APPLICATION_CREDENTIALS=/path/key.json`
- `GOOGLE_WORKSPACE_ADMIN_EMAIL`  
  - A Workspace user to impersonate via Domain-Wide Delegation (e.g., `admin@yourcompany.com`)
- `GOOGLE_SHEET_ID`  
  - The Spreadsheet unique ID (the long ID in the URL)
- `GOOGLE_SHEET_RANGE` *(optional; default we’ll use `Sheet1!A:D`)*  
  - Example: `Employees!A:D`
- `GOOGLE_SHEETS_API_SCOPES` *(we’ll hardcode these in the script, but listing for transparency)*  
  - `https://www.googleapis.com/auth/spreadsheets.readonly`
  - `https://www.googleapis.com/auth/drive.readonly`

### Steps to create accounts and roles
- Create / select a Google Cloud project
  - Go to Google Cloud Console → select or create a project
- Enable required APIs
  - APIs & Services → Library → enable:
    - **Google Sheets API**
    - **Google Drive API**
- Create a Service Account
  - IAM & Admin → Service Accounts → **Create Service Account**
  - Name example: `eramba-sheets-reader`
- Create a Service Account key (JSON)
  - Service Account → **Keys** → **Add Key** → **Create new key** → JSON
  - Store it securely (secret manager / eramba secret storage / CI secrets). Do **not** commit it to GitHub.
- Configure Domain-Wide Delegation (Workspace Admin required)
  - In Google Cloud Console:
    - Service Account → **Details** → enable **“Domain-wide delegation”**
    - Copy the **Client ID** shown for that service account
  - In Google Admin Console (Workspace):
    - Security → Access and data control → **API controls**
    - **Domain-wide delegation** → Manage Domain Wide Delegation
    - Add new:
      - Client ID: (paste the service account Client ID)
      - OAuth scopes (comma-separated):
        - `https://www.googleapis.com/auth/spreadsheets.readonly`
        - `https://www.googleapis.com/auth/drive.readonly`
- Ensure the impersonated user can access the spreadsheet
  - Share the spreadsheet in Google Drive with the impersonated user (`GOOGLE_WORKSPACE_ADMIN_EMAIL`)
  - If the sheet is restricted, make sure that user has at least **Viewer** access

---

## Data Contract (what the spreadsheet must contain)
- Column A: employee login (mandatory)
- Column B: one or more organization roles separated by `|` (mandatory)
- Column C: employee vs contractor indicator (mandatory)
- Column D: operating system (optional)

## Output
- The script will print to **STDOUT** a JSON array of objects like:
  - `login` (string)
  - `roles` (array of strings, split by `|` and trimmed)
  - `worker_type` (string from column C)
  - `os` (string or null from column D)
- Any failures (auth, missing sheet, bad range, API errors) will be written to **STDERR** and the script will exit non-zero.