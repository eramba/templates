---
author: "Esteban"
version: "1.2"
modules:
  - "Access Management"
use_cases:
  - compliance
  - risk
  - incident
  - online assessments
  - awareness programs
  - data privacy
---

# Set Up Basic Groups and Accounts

## Description

Use when setting up the initial GRC team and department groups and administrator accounts in a new Eramba instance that has no user-created groups or accounts.

## Skill

```markdown
# Set Up Basic Groups and Accounts

## Mandatory sequence

Complete these four phases in order. Do not skip a phase or begin the next until the current phase is complete. If a required Eramba action fails or cannot be verified, stay in the current phase, explain what is blocking progress, and ask only for the information or action needed to continue. Never claim that a group or account exists unless Eramba confirms it.

Do not request confirmation before creating groups or accounts after the user has provided and confirmed the required details.

## Phase 1: Check whether initial setup is appropriate

Inspect Eramba for user-created groups and user accounts. Exclude built-in/system groups and the initial system account when identifying prior setup. If any user-created group or any additional user account exists, tell the user this skill is for a fresh setup and cannot be used in this instance. Stop without making changes.

Proceed only when no user-created groups or additional user accounts exist.

## Phase 2: Create the GRC team group

Ask the user for the name of the team responsible for GRC administration. Accept the name they use, such as GRC or Cybersecurity; do not impose a standard label.

After the user provides the name, create one group with that name. Set its description to clearly state that this group is used by the administrators. Verify the group exists in Eramba before proceeding. If the name is already taken or creation fails, resolve the issue with the user and remain in this phase.

## Phase 3: Create department groups

Ask which organizational departments are most likely to own Eramba items, such as risks, internal controls, or policies. Require one or more department names. Accept departments such as Finance or IT; do not accept job titles or roles such as CISO or CEO as departments. If the user provides roles, ask for the corresponding department names.

After the user confirms the department names, create one group for each department. Set each description to clearly state that the group is used by the administrators. Verify that every requested group exists in Eramba before proceeding. If any group cannot be created or verified, remain in this phase and resolve it before continuing.

## Phase 4: Create administrator accounts

Ask for each administrator's name, surname, email address, and login. Allow multiple administrators. Check Eramba to confirm every email address is unused before creating any accounts. If an email is already in use, ask for a different address for that person. Also resolve any login conflict Eramba reports. Do not create accounts with duplicate emails.

For every administrator with complete, validated details, create an account using these settings:

| Setting | Value |
|---|---|
| Name | As provided by the user |
| Surname | As provided by the user |
| Email | As provided by the user |
| Login | As provided by the user |
| Active | Yes |
| Local account | Yes |
| Password | Generate a strong, random temporary password |
| Groups | Admin and the GRC team group created in Phase 2 |
| Portals | Enable all available portals |
| API | Inactive |

Verify that each account was created and that its settings match the table. If creation or verification fails for any account, remain in this phase and resolve it. Do not report completion for accounts that Eramba did not confirm.

After all accounts are verified, give the user each administrator's login and temporary password. State that Eramba requires the administrator to change the password at first login. Do not send credentials to anyone else or through another channel.
```
