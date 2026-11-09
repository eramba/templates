---
author: "me"
version: "1.0"
modules:
  - "Policy Management"
use_cases:
  - risk
  - compliance
  - data privacy
  - awareness programs
---

# Title

```text
Create Eramba Policies
```

## Description

```text
Use when creating a policy in Eramba. Determine the policy fields, get the user’s validation, then create the policy only after all fields are confirmed.
```

## Skill

```markdown
# Create Eramba Policies

## Workflow

Complete each step in order. Do not begin a step until the previous step’s exit condition is met. Keep messages concise.

### Step 1: Determine and propose policy fields

Review the policy content provided by the user and the relevant Eramba configuration. Suggest every field below:

- **Title:** A short name for the policy.
- **Description:** A concise summary of your understanding of the policy content.
- **GRC Contact:** The Security or GRC group configured in Eramba. If no such group is defined or you cannot identify it, mark this field as needing the user’s input.
- **Reviewer:** An Eramba group with a clear connection to the policy’s subject. Deduce the most suitable group from the content and available groups. If no group has an obvious connection, mark this field as needing the user’s input.
- **Next review date:** Follow the review period configured in the Policies module, including any enforced period. If no period is configured, suggest one year from today.
- **Document type:** Choose the Eramba document type that best fits the policy content.
- **Version:** Set to `1`.
- **Content:** Use the content provided by the user, formatted clearly. Do not add unsupported requirements or organizational facts.
- **Policy portal visibility:** Set the policy to private so it is not public on the policy portal.

Present all fields together. Clearly identify any field you could not determine and why. Do not create the policy in this step.

**Exit condition:** Every field has a proposed value or is explicitly identified as requiring user input. Present the proposal to the user for validation.

### Step 2: Get user validation

Ask the user whether any proposed fields need changes. Call out unresolved fields, including a missing GRC Contact group or a Reviewer with no obvious connection, and ask the user to provide the needed value.

Apply the user’s feedback to the proposal. If the user requests changes, update the affected fields and present the revised values for validation. Do not proceed while a required field is unresolved or the user has not validated the complete proposal.

**Exit condition:** The user has validated the complete set of fields, and every required field has a confirmed value.

### Step 3: Create the policy

Create the policy in Eramba using the validated field values. Set portal visibility to private.

If Eramba rejects a value or the policy cannot be created, report the issue and ask only for the information needed to resolve it. Do not claim success unless Eramba confirms creation.

**Exit condition:** Eramba confirms that the policy was created. Tell the user it has been created.
```
