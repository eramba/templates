---
id: <source>-current-employees
name: <Source> Current Employees
version: 0.1.0
status: draft
technology: <Source>
vendor: <Vendor>
eramba_module: Account Review Feeds
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

## 1. At a glance

| | |
|---|---|
| **Family** | Employee list (for Exit reviews) |
| **Each row is** | <One current (or former) employee, and what identifies them> |
| **Connects to** | <Source> <API or service>, read-only |
| **Permission needed** | <Exact read-only permission>. Approved by <who grants it in Source>. |
| **Included** | <Who counts as an employee> |
| **Not included** | <Who is left out, for example contractors or people on leave> |
| **Size limit** | Up to 10000 people by default (`MAX_USERS`, maximum 50000). A larger list stops the run instead of being cut short. |
| **Roles listed** | None: employee lists carry no roles |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| None | Employee lists carry no roles; the list only says who is an employee. | Only the person's name and identifier are shown. |

```
Account: <Name> <person@example.com>
```

If an identifier in square brackets appears, reviewers can ignore it.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| <Review the accounts of an application against this list> | [<Product> Accounts](../../<Provider>/<Accounts automation>/README.md) |
| <Use another source of employees> | [<Other Source> Current Employees](../../<Provider>/<Employees automation>/README.md) |

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

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this list: an empty list is never published.

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
