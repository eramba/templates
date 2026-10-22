---
id: <product>-account-review
name: <Product> Accounts and Assigned Access
version: 0.1.0
status: draft
technology: <Product>
vendor: <Vendor>
eramba_module: Account Review Feeds
trigger: Account Review pull
secrets:
  - <secret_name>
variables:
  - ROLES
  - ALLOW_EMPTY_FEED
  - MAX_USERS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# <Product> Accounts and Assigned Access

**Technology:** <Product>.

Send <Product> accounts and their assigned access to an eramba Account Review feed. **Draft: functional and installation validation pending.**

## 1. Purpose

What population this feed supplies and which reviews it supports (snapshot, differential, leaver).

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | Account key and why it is unique and stable |
| `description` | What identifies the account for a reviewer |
| `roles` | Every access type, with its exact `<kind>: <name> [<id>]` format |

## 3. Coverage

What is included, what is not (account types, access types, environments), and that any incomplete collection aborts without publishing.

## 4. Before you start

Product edition or licence, network reach from eramba, a dedicated read-only identity.

## 5. Setup on <Product>

Numbered steps to create the identity and the exact read-only permissions, with why each is needed.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `<secret_name>`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of accounts to be reviewed**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Save, test the feed and compare the accounts and roles with <Product>.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `ROLES` | `['groups']` | Access listed as roles; `[]` lists accounts only. |
| `ALLOW_EMPTY_FEED` | `false` | True only if zero accounts is a legitimate result. |
| `MAX_USERS` | `2000` | 1–10000 accounts; exceeding it aborts instead of truncating. |

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only. Accounts are published once, after the whole population is collected. Exit 1 means nothing was published.

When testing, read the **SUMMARY** block at the end of stderr: `RESULT: OK` with the number of accounts eramba received and the roles listed per kind, or `RESULT: FAILED` with the reason.

## 9. Troubleshooting

| Problem | Action |
|---|---|

## 10. Customising

Which variables can change and what needs new collection code.

## 11. Removing

Stop the dependent Account Reviews, then remove the feed, the identity and the secrets.

## 12. Changelog

| Version | Change |
|---|---|
| 0.1.0 | Initial draft; functional and installation validation pending |
