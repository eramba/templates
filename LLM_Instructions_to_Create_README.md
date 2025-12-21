# LLM Instructions: Eramba Automation Script (PHP)

## Goal
Create a **single-run automation script** in the **latest stable PHP version** to automate tasks for **Eramba**.

## Output Requirements
1. The automation must be delivered as **two files only**:
   - `composer.json` (Composer dependencies)
   - `run.php` (the single execution script)

2. Before generating the code, you must produce a **Markdown setup document** that explains:
   - What input variables you need from me (credentials, hostnames, tokens, file paths, etc.)
   - How to create the required accounts, roles, and permissions in the target system(s)

## Setup Document Format
Your Markdown document must use the following structure **for each target system**:

### System: <system name>
#### Required Variables
- `VARIABLE_NAME`: description
- `VARIABLE_NAME`: description

#### Steps to create accounts and roles
- Step 1 ...
- Step 2 ...
- Step 3 ...

## Workflow
1. First, output only the **Markdown setup document** described above.
2. Do **not** generate any PHP code yet.
3. After I complete the account/permission setup on the target system(s), I will ask you (later) to generate the actual script using the variables defined in the setup document.

## Script Tasks
The script must perform the following tasks:

`$tasks`
