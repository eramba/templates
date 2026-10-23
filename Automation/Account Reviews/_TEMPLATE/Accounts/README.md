---
id: <product>-account-review
name: <Provider> – Accounts
version: 0.1.0
status: draft
technology: <Product>
vendor: <Vendor>
eramba_module: Account Review Feeds
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

# <Provider> – Accounts

**Technology:** <Product>.

Send <Product> accounts and their assigned access to an eramba Account Review feed.

## 1. At a glance

| | |
|---|---|
| **Family** | <Account review or Permission review> |
| **Each row is** | <One <Product> account, and what identifies it> |
| **Connects to** | <Product> <API or service>, read-only |
| **Permission needed** | <Exact read-only permission>. Approved by <who grants it in Product>. |
| **Included** | <Account types and access types listed> |
| **Not included** | <Account types, access types or environments left out> |
| **Size limit** | Up to 2000 accounts by default (`MAX_USERS`, maximum 5000). A larger population stops the run instead of being cut short. |
| **Roles listed** | <Access types listed, for example groups, roles, apps> |

How the review families, review types and results work is explained once in the [Account Reviews overview](../../README.md).

## 2. What one row looks like

| Kind | What it means for the person | How reviewers see it |
|---|---|---|
| `<kind>` | <What this access lets the person do> | `<kind>: <name> [<id>]` |

```
Account:     person@example.com
Description: <Name>; <account type, for example Member or Guest>; <enabled or disabled>
Roles:       <Kind>: <name> [<id>]
             <Kind>: <name> [<id>]
```

The codes in square brackets are <Provider>'s internal identifiers. They keep two items with the same name apart; reviewers can ignore them. The description gives <what the description shows>.

## 3. Looking to review something else?

| I want to... | Use |
|---|---|
| <Review another part of this product> | [<Other automation>](../<Other automation>/README.md) |
| <Compare with the people who work here today> | [<Provider> – Current employees](../../<Provider>/<Employees automation>/README.md) |

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
| `MAX_USERS` | `2000` | 1–5000 accounts; exceeding it aborts instead of truncating. This version has no scope filter to split a larger population. |

## 8. Results

How results and failures are shown is explained in the [Account Reviews overview](../../README.md). Specific to this feed: <any extra counts in the summary, for example enabled and disabled accounts>; an empty feed is only accepted when `ALLOW_EMPTY_FEED` is on, which is right only if zero accounts is a legitimate result.

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
