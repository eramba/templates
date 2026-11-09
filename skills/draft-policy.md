---
author: "AI Assistant"
version: "1.6"
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

Build the content as a connected chain of requirements. Each layer must stay within the scope established by the selected topic path. A policy statement may lead to one or more supporting standards; a standard may lead to further policy statements, standards, or procedures when needed. Keep the hierarchy and headings below as internal drafting guidance:

- Introduction — level-one heading, when useful to introduce the policy
- Policy Statements — level-one heading, with one or more relevant statement topics as level-two headings
- Standards — level-one heading, with one or more relevant standard topics as level-two headings
- Procedures — level-one heading, with one or more relevant procedure titles as level-two headings

Include a section only when the topic and available information call for meaningful content in that section. Omit an entire section when it has no relevant content; for example, omit Procedures if no procedure is needed. Do not add empty sections or headings just to complete the hierarchy. Add level-two headings only for relevant topics. Under every level-two heading, present the content as bullet points. For each procedure, write clear, actionable steps in sequence as numbered bullet items, using the format `- 1. ...`, `- 2. ...`, `- 3. ...`. Do not expose this hierarchy as instructions, a checklist, or commentary to the user. In particular, do not tell the user that the structure is hidden or prescribed.

Keep the distinctions clear:

- **Policy statements** express broad principles or intended outcomes for the topic. Keep them generic and high-level; for example, “Encryption is used for all data in transit.” Do not put detailed technical settings in a policy statement.
- **Standards** derive from and make policy statements specific, measurable, and assessable. State the technologies, configurations, scope, ownership, or other details needed to implement the related policy statement, when supported by the topic and source information. For example, an encryption policy statement may lead to a standard requiring TLS 1.1 for data in transit. A standard about TLS may also require a supporting policy statement on secure certificate lifecycle management; its related standard may specify an issuing platform and validity period, such as AWS and two years, when those details are established by the source or confirmed by the user.
- **Procedures** provide operational steps for carrying out a standard when a process is needed. For example, a certificate lifecycle standard may require a “Certificate Issue” procedure with clear, sequential steps. Include only steps supported by the available information; use placeholders or ask the user about unknown organizational responsibilities, tools, and actions.

Trace each standard and procedure to the policy statement or requirement it supports, and add supporting statements or standards when an implementation detail creates a further relevant requirement. Keep every link in this chain within the topic scope. Do not assume organization-specific technology choices, responsible teams, platforms, validity periods, or operating practices; distinguish technical implications from facts about the organization and use placeholders or ask the user when details are unknown.

Do not invent organizational practices. If a procedure depends on unknown local arrangements, draft a general, adaptable procedure or mark the specific missing detail as a placeholder.

### 4. Show the draft and refine it

Once an initial draft is ready, show it using the **display_policy** skill. Ask targeted questions to check whether the draft reflects the user’s organization and its actual context, including relevant data, systems, technologies, teams, and practices. Where a statement describes a desired future requirement rather than a current practice, make that distinction clear and ask whether it reflects the user’s intent. Ask about material gaps, inaccurate assumptions, and missing details needed to make the draft fit the organization.

After every user response, revise the policy content and show the updated draft using **display_policy**. Recheck the full draft against the defined topic and the selected source path’s rules. Keep feedback that accurately reflects the organization and supports the policy topic. If feedback would take the draft outside the defined topic, explain why it is out of scope and leave it out unless the user explicitly confirms they want to broaden the policy scope to include it. If the scope changes, update the topic definition and ensure the applicable source-path requirements are still met before adding content. Do not add unsupported organizational facts; ask follow-up questions or use clear placeholders where needed. Continue this review cycle until the user says the content is ready or you determine that it is sufficiently clear, complete, coherent, grounded in the organization’s context, and supported by the available source information.

If the required display_policy skill is unavailable, show the draft in the conversation in a clear, readable format and continue the same feedback cycle.

**Phase 1 exit condition:** The user or you has determined that the policy content is ready to proceed, after checking that it reflects the organization’s context and remains within the defined topic and source-path rules.

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
