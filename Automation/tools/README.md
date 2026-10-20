# Maintenance tools

> **Installing an automation? You do not need this folder.** These tools are for people who change the automations in this repository; nothing here runs in eramba.

## sync.php

Keeps the [shared code blocks](../_shared/) inside every `run.php` identical to their originals. Requires PHP 8.4 or later on your computer; run it from the repository root.

| Command | What it does |
|---|---|
| `php Automation/tools/sync.php check` | Checks every `run.php`. Exits with 1 and lists the problems if any copy was edited, is inconsistent or has invalid PHP. Changes nothing. |
| `php Automation/tools/sync.php update <run.php> …` | Replaces the shared blocks of the given files with the current originals. Refuses to overwrite a block edited by hand. |

See [Shared code blocks](../_shared/README.md#for-contributors) for the full workflow.
