---
author: "AI Assistant"
version: "1.1"
modules:
  - "Policy Management"
use_cases:
  - compliance
  - risk
  - data privacy
---

# Title

```text
Draft Policy Content
```

## Description

```text
Use when a user wants to draft a policy from compliance requirements or a general topic. Develop and review the policy content, confirm its title, description, type, and version, then pass the approved draft to Create Drafted Policy in eramba.
```

## Skill

```markdown
# Draft Policy Content

## Purpose and boundaries

Help the user draft a policy’s title, description, and content. This skill drafts and refines the material; it does not create or edit a policy in Eramba.

When the policy draft and its attributes are confirmed, pass them to the skill **Create Drafted Policy in eramba**. Do not claim the policy has been created.

Follow the phases below in order. Do not skip a phase or consider it complete until its exit condition is met. Keep the required document structure as internal drafting guidance: do not explain it to the user or display it as a template or checklist.

## Phase 1: Define the policy topic and draft its content

### 1. Identify the source path

Determine which of these two paths the user intends:

1. **Compliance package or compliance item(s):** The user or another skill supplies one or more packages or items as the basis for the policy.
2. **General topic:** The user supplies a broad topic, such as data privacy.

If the source path is unclear, ask one concise question to establish it before drafting.

### 2. Gather the source information

#### Compliance package or item path

For every relevant compliance item, obtain and review all of the following:

- Package name
- Requirement title
- Description
- Additional details

Use those details as the sole substantive basis for the policy. Do not use external sources to add policy content on this path. Do not fill gaps with assumptions or general knowledge.

If any required detail is unavailable, ask the user or the supplying skill for it. If it cannot be obtained, explain that the source information is incomplete and stop this skill without drafting or moving to Phase 2.

#### General topic path

Use available relevant Eramba information and external sources to develop a useful, accurate draft. Prefer authoritative and current sources where available. Distinguish sourced facts from proposed policy language, and cite or identify sources when the tools and interaction support it.

If the topic is too broad to draft responsibly, ask a focused question about its scope. Do not invent organization-specific facts, named owners, approval arrangements, systems, deadlines, or commitments. Use neutral language or clear placeholders where organizational details are needed.

### 3. Draft the policy content

Draft the content using this internal hierarchy and heading levels:

- Introduction — level-one heading
- Policy Statements — level-one heading
  - One or more relevant statement topics — level-two headings
- Standards — level-one heading
  - One or more relevant standard topics — level-two headings
- Procedures — level-one heading
  - One or more relevant procedure titles — level-two headings

Write meaningful content under each applicable heading. Do not expose this hierarchy as instructions, a checklist, or commentary to the user. In particular, do not tell the user that the structure is hidden or prescribed.

Keep the distinctions clear:

- **Policy statements** state the organization’s intended direction or required outcomes.
- **Standards** state specific, measurable or otherwise assessable requirements that support those outcomes.
- **Procedures** describe actions or steps for implementing the requirements.

Do not invent organizational practices. If a procedure depends on unknown local arrangements, draft a general, adaptable procedure or mark the specific missing detail as a placeholder.

### 4. Show the draft and refine it

Once an initial draft is ready, show it using the **display_policy** skill. Then ask the user for feedback, focusing on material gaps, accuracy, scope, and whether the draft reflects their intent.

Revise the draft in response to feedback and display the updated version using **display_policy**. Continue this review cycle as needed. Do not move to Phase 2 until the user says the content is ready or you determine that the draft is sufficiently clear, complete, coherent, and supported by the available source information.

If the required display_policy skill is unavailable, show the draft in the conversation in a clear, readable format and continue the same feedback cycle.

**Phase 1 exit condition:** The user or you has determined that the policy content is ready to proceed.

## Phase 2: Confirm policy attributes and hand off for creation

After Phase 1 is complete, confirm all four attributes below with the user:

- **Title**
- **Description**
- **Policy Type:** Policy, Standard, or Procedure
- **Version:** For example, `1`

Suggest values based on the approved content, but do not treat a suggestion as confirmation. Ask the user to confirm or correct any values not already explicitly confirmed. Do not infer confirmation from silence.

Once all four attributes are confirmed, pass the following to **Create Drafted Policy in eramba**:

- Confirmed title
- Confirmed description
- Confirmed policy type
- Confirmed version
- Complete approved policy content

Do not invoke the creation skill before all four attributes are confirmed. Do not create the policy yourself.

**Phase 2 exit condition:** All four attributes and the complete approved content have been passed to **Create Drafted Policy in eramba**. The drafting skill’s work is then complete.
```
