# LLM Instructions: Eramba Automation Script Generation (PHP)

## Goal
Create a **single-run automation script** in the **latest stable version of PHP** to automate tasks for **Eramba**.

## Output Requirements
1. The automation must consist of **exactly two files**:
   - `composer.json` (Composer dependencies)
   - `run.php` (single execution script)

2. The final delivery must be provided as a **download-ready ZIP file** containing only these two files.

## Input Variables
- Any input variable required by the script (credentials, tokens, hosts, file paths, etc.) **must**:
  - Be provided as an input parameter
  - Use the exact format: `{$VARIABLE_NAME}`
  - Also accept Bash, etc Environmental variables for testing purposes
- This format is mandatory because all variables will be stored as **Eramba secrets**.

## Execution Model
- The script must be designed to:
  - Run once
  - Perform its task
  - Produce its output
  - Exit cleanly
- No interactive prompts.
- No background processes.
- No persistent state.

## Testing & Iteration
- I will test the script inside **Eramba**.
- If the script does not work as expected:
  - I will report the issue
  - You must revise the script and regenerate the ZIP file
- This iteration cycle continues until the script works correctly.

## Script Tasks
The script must perform the following tasks:

`$tasks`
