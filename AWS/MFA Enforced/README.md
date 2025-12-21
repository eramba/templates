## System: AWS (IAM)

### Required Variables
- `AWS_ACCESS_KEY_ID`
- `AWS_SECRET_ACCESS_KEY`
- `AWS_REGION` (e.g., `eu-central-1`)
- `AWS_SESSION_TOKEN` *(optional; only if using temporary credentials)*

### Steps to create accounts and roles
- Create a dedicated IAM user
  - AWS Console → **IAM** → **Users** → **Create user**
  - User name: `eramba-mfa-audit-bot`
- Grant minimum required permissions (read-only)
  - AWS Console → **IAM** → **Policies** → **Create policy**
  - Choose **JSON** and use a policy that allows:
    - `iam:ListUsers`
    - `iam:ListMFADevices`
    - `iam:GetUser` *(optional but safe)*
    - `sts:GetCallerIdentity` *(optional, for diagnostics)*
- Attach the policy to the user
  - IAM → **Users** → `eramba-mfa-audit-bot` → **Permissions** → **Add permissions**
- Create access keys
  - IAM → **Users** → `eramba-mfa-audit-bot` → **Security credentials**
  - **Access keys** → **Create access key**
  - Securely store:
    - `AWS_ACCESS_KEY_ID`
    - `AWS_SECRET_ACCESS_KEY`
- Security notes
  - Do **not** commit credentials to GitHub
  - Store secrets in a secure secret manager (e.g., GitHub Secrets, Vault)