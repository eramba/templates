---
author: "me"
version: "1.1"
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
Use when creating one or more policies in Eramba. Determine the policy fields, ask the user only about unclear fields, then create the policies, in bulk when appropriate.
```

## Skill

```markdown
# Create Eramba Policies

## Workflow

Complete each step in order. Do not begin a step until the previous step’s exit condition is met. Keep messages concise and minimize questions to the user.

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

Prepare the proposed values for all fields. Do not ask the user to review fields you can determine confidently.

**Exit condition:** Every field has a proposed value, and any uncertain fields are identified for brief user validation in Step 3.

### Step 2: Prepare policies for creation

If the user provided multiple policy contents or requested multiple policies, prepare each policy using the same field rules. Keep each policy’s fields and content associated with its name.

**Exit condition:** All requested policies are prepared, and any uncertain fields are ready to be listed by policy name in Step 3.

### Step 3: Resolve unclear fields and create policies

Briefly show the policy name or names and ask the user to confirm or supply only fields you could not determine confidently. These may include the GRC Contact, Reviewer, or next review date. Keep the question brief and list only the uncertain fields. Do not ask the user to revalidate clear fields.

If no fields are uncertain, skip the question and create the policies. After the user resolves any listed fields, create the policies in Eramba using the prepared values. Create multiple policies in bulk when the available Eramba workflow supports it. Do not request another confirmation before creating them. Set each policy’s portal visibility to private.

If a required field remains unresolved, wait for the user’s answer before creating the affected policy. Create other policies whose fields are complete when possible.

If Eramba rejects a value or the policy cannot be created, report the issue and ask only for the information needed to resolve it. Do not claim success unless Eramba confirms creation.

**Exit condition:** Eramba confirms which policies were created. Tell the user which policies were created and report any that could not be created.
```
