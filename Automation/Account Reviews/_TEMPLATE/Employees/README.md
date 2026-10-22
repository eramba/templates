---
id: <source>-current-employees
name: <Source> Current Employees
version: 0.1.0
status: draft
technology: <Source>
vendor: <Vendor>
eramba_module: Account Review Feeds
trigger: Account Review pull
feed_type: List of current employees
secrets:
  - <secret_name>
variables:
  - USER_KEY
  - MAX_USERS
dependencies:
  - guzzlehttp/guzzle:^7.9
timeout_seconds: 240
eramba_version_tested: null
last_tested: null
---

# <Source> Current Employees

**Technology:** <Source>.

Send the people who should have access today to an eramba feed of type **List of current employees** (or **List of former employees**). **Draft: functional and installation validation pending.**

## 1. Purpose

The comparison side of an Exit review: who counts as a current (or former) employee, and why this source is authoritative.

## 2. What it gathers

| Feed field | Source |
|---|---|
| `user` | The identifier the compared application accounts also hold (usually the work email) |
| `description` | What identifies the person for a reviewer |

Employee feeds have no roles.

## 3. Coverage

Who is included and left out, how keys are matched with application accounts, and that an empty list or any incomplete collection aborts without publishing.

## 4. Before you start

Product edition or licence, network reach from eramba, a dedicated read-only identity.

## 5. Setup on <Source>

Numbered steps to create the identity and the exact read-only permissions, with why each is needed.

## 6. Setup in eramba

1. Create the secrets in **Settings → Application Configuration → Automation Secrets**: `<secret_name>`.
2. **Security Operations → Account Reviews → Feeds → Add Item**: Source **Automation**, Type **List of current employees**, then **Add** an automation:

| Setting | Value |
|---|---|
| Language | PHP 8.4 |
| Composer packages | `guzzlehttp/guzzle:^7.9` |
| Timeout | 240 seconds |
| Code | [run.php](run.php) |

3. Run **Test** in the automation editor: exit code `0`, stdout only shows the `account-review` artifact declaration, step logs appear on stderr.
4. Use it as the **Exit Feed** of an Account Review of type **Exits**.

## 7. Variables

| Variable | Default | Meaning |
|---|---|---|
| `USER_KEY` | `email` | Identifier compared with the application accounts. |
| `MAX_USERS` | `10000` | 1–50000 people; exceeding it aborts instead of truncating. |

## 8. Results

stdout only contains the artifact declaration; the step log goes to stderr with counts only. The list is published once, after it is complete. Exit 1 means nothing was published.

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
