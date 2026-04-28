# Eramba Automation Scripts

This repository contains automation scripts designed to integrate with and support **Eramba**. These scripts are intentionally simple, reusable, and easy to adapt to different environments and vendors.

---

## Purpose

The goal of this repository is to provide **practical automation examples** that can be reused, adjusted, or extended to fit specific Eramba use cases such as evidence collection, account validation, integrations with third-party systems, and control testing.

There is no magic here: these are straightforward scripts meant to run, produce evidence, and stop.

---

## Repository Structure

Scripts are organized using the following structure:

```
Vendor/
└── Automation_Goal/
    ├── composer.json
    ├── run.php
    └── README.md
```

### Key principles:

- **Vendor**  
  Represents the external system being automated (e.g. AWS, Google, Calendly).

- **Automation_Goal**  
  Describes what the automation does (e.g. user reconciliation, log collection, access review).

---

## What Each Automation Directory Contains

Every automation directory must include:

1. **Composer configuration**  
   A `composer.json` file defining any required dependencies.

2. **Single execution script (Example of Audits)**  
   One PHP script that:
   - Connects to the target system
   - Collects data (audit evidence)
   - Performs validation or comparison (analyses the evidence)
   - Updates audit (conclusion, result, etc) record and includs the analysis from the previous step as comments & attachments
   - Ends execution
  
NOTE: The example above is related purely to Control Catalogue / Internal Controls / Audits, but maintenances can perform many different actions in almost any module in Eramba. For that reason, you might find automations that do other things; for example, they may analyse the results of an Online Assessment and update a third party.

3. **Local README.md**  
   A README that clearly explains:
   - Required environment variables
   - Optional: How to create accounts, roles, or permissions on the target system (vendor)
   - Any assumptions or limitations of the script (For example, evidence that collects, criteria to pass or fail an audit, etc)

NOTE: The example above is related purely to Control Catalogue / Internal Controls / Audits, but maintenances can perform many different actions in almost any module in Eramba. For that reason, you might find automations that do other things; for example, they may analyse the results of an Online Assessment and update a third party.

---

## Running the Scripts Locally

The fastest way to test any automation is to clone the repository and run the script locally.

Make sure you first download all necesary libraries by running:

```composer install```

You will need to define **environment variables** so the script can authenticate and run properly.

Examples:

```bash
export SCRIPT_TOKEN_KEY='keyhere'
```

or

```bash
export SCRIPT_TOKEN_KEY="$HOME/key.file"
```

Once variables are set, you can execute the script directly:

```bash
php run.php
```

This mirrors how the script will behave when executed inside Eramba.

---

## LLM-Generated Automations

All scripts in this repository were created using **Codex**, following a strict two-step approach:

1. **Account & permission instructions**  
   An [LLM prompt](https://github.com/eramba/automation/blob/master/LLM_Instructions_to_Create_README.md) is used to generate clear, actionable instructions explaining how to:
   - Create service accounts
   - Assign roles and permissions
   - Prepare the target system securely

2. **Script generation**  
   A [second LLM prompt](https://github.com/eramba/automation/blob/master/LLM_Instructions_to_Create_PHPScript.md) is used to generate:
   - A single-run PHP script
   - A Composer configuration
   - A ZIP-ready structure suitable for Eramba automations

The exact LLM instructions used for this process are included in this repository for transparency and reuse.

---

## Final Notes

- Scripts are intentionally minimal and opinionated.
- They are meant to be adjusted, not treated as black boxes.
- If something breaks, fix the script — don’t over-engineer it.

If you are looking for polished SaaS integrations, this repo is not that.  
If you want **clear, auditable, and reproducible automations**, you are in the right place.
