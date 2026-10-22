# Shared code blocks

> **Installing an automation? You do not need this folder.** Copy the automation's `run.php` into eramba as its README explains. That file is complete: it already contains everything from here that it uses.

## What this folder is

Several automations need the same code: checking that secrets exist, calling a provider API safely, building an Account Review feed. Writing that code separately in every `run.php` lets copies drift apart, so a fix made in one automation never reaches the others.

This folder holds the single original of that code. Each `run.php` contains a copy of the blocks it uses, between two comments:

```php
// ─── shared:http v1 (edit Automation/_shared/http.php, then run Automation/tools/sync.php) ───
...copy of Automation/_shared/http.php...
// ─── end shared:http v1 sha256:… ───
```

The comments do nothing in eramba. They tell readers where the code comes from and let [`tools/sync.php`](../tools/sync.php) keep the copies identical to the originals.

## Blocks

| Block | What it does |
|---|---|
| [`core`](core.php) | Turns PHP warnings into errors, writes the step log (within eramba's 10,000-byte output limit), enforces the time and memory budget, checks secrets |
| [`http`](http.php) | Read-only HTTPS calls to provider APIs: only the endpoints the script declares, TLS verified, no redirects, timeouts, retries when the provider throttles, size limit |
| [`feed`](feed.php) | Account Review feed rows: field limits, unique account keys, publication only after the whole list is collected, the SUMMARY block (counts only) that ends every run, and `TplEmptyFeedException` for an empty list the script refuses to publish |
| [`providers/graph`](providers/graph.php) | Microsoft Graph: application token, complete collections, request batching |
| [`providers/okta`](providers/okta.php) | Okta: org URL validation, complete lists following the `Link` header |
| [`providers/auth0`](providers/auth0.php) | Auth0: Management API token, page-based and checkpoint lists |
| [`providers/aws`](providers/aws.php) | AWS SDK: client options, calls that never expose credentials, `Marker` pagination |
| [`providers/google`](providers/google.php) | Google API client: service-account key, delegated admin, bounded client, `nextPageToken` lists |
| [`providers/github`](providers/github.php) | GitHub REST API: headers, `Link` pagination, stops before the rate limit runs out |
| [`providers/openai`](providers/openai.php) | OpenAI Administration API: complete `after` / `has_more` lists |
| [`providers/anthropic`](providers/anthropic.php) | Anthropic Admin API: complete `after_id` / `has_more` lists |

Blocks only declare functions whose names start with `tpl_`. Each script sets their options with constants in its section 3 (`TPL_LOG_STREAM`, `TPL_RUN_SECONDS`, `TPL_ALLOWED_REQUESTS`). A setting that depends on a secret, such as the allowed host of an Okta org, is set with `define()` in MAIN once the secret is validated.

## If you customise an automation

You may edit the variables of a `run.php` (section 2) as its README explains. Do not edit code between `shared:` comments: the next version of the automation will replace it. If you find a problem in that code, please report it so it is fixed for every automation.

## For contributors

1. Edit the file in this folder and increase its `// version:` number.
2. Run `php Automation/tools/sync.php update <run.php>` for each automation you will validate again. Automations left on an older version are reported as stale, not broken.
3. Increase the version and changelog of each updated automation and validate it on a real eramba and provider.
4. Run `php Automation/tools/sync.php check` before committing. It fails if code was edited between markers in a `run.php`, a block changed without a version bump, a function or constant is declared twice, shared code contains an eramba `%MACRO%`, a script lacks a `TPL_` setting, or PHP syntax is invalid.

To add a block to a new automation, write its two comments with nothing between them and the hash `0000000000000000`, then run `update`.
