---
author: "Esteban and Codex"
version: "1.3"
modules:
  - "internal controls"
  - "policies"
use_cases:
  - compliance
---

# Title

```text
Analyse Compliance Requirements
```

## Description

```text
Use when the user needs to identify and map the policies and/or internal controls required to address compliance requirements in an Eramba Compliance Package.
```

## Skill

```markdown
# Analyse Compliance Requirements

## Mandatory sequence

Complete the following stages in order. Do not skip, combine, merge, or start a later stage before finishing the current one. Use each stage’s output as the input to the next stage. If a stage cannot be completed, stop and tell the user what is missing.

## Verbosity

Be quiet and provide only the outcomes requested by this skill, unless you or the user cannot determine how to proceed.

## 1. Find the requirements

Get the Compliance Package name and item names or IDs from the user. In Eramba, locate each selected item and review its **Additional Information** first. Use this field as the primary source for the mandatory policy and internal control definitions. If Additional Information is absent or empty, use the compliance package item’s title and description instead. Do not use external sources, particularly internet-based sources.

If the package or items cannot be found, tell the user. If the available details are insufficient to assess an item, explain what is missing and that you cannot continue. Do not guess or proceed with incomplete requirements.

**Stage output:** A complete set of selected requirements, with the available details retrieved from Eramba.

## 2. Check existing policy and control associations

After Stage 1, check each selected compliance requirement in Eramba for associated policies and internal controls. Record the associations, but do not treat an association as evidence that the item addresses the requirement.

Tell the user which associated items you found. Offer to assess associated policies using Assess Policy Coverage of Compliance Requirements and associated internal controls using Assess Internal Control Coverage of Compliance Requirements, where those skills are available. If both types are associated, let the user choose policy assessment, control assessment, both, or proceeding directly to a proposed mapping. Do not assume that choosing one assessment includes the other.

If the user chooses an assessment, perform only the assessment or assessments they selected. Pass the selected item names and relevant requirement details to the corresponding skill. Present its coverage findings and gaps, then stop this workflow for this response. Do not proceed to proposing a policy/control mapping, recommending new records, or preparing changes. Resume those stages only after a subsequent, explicit user request.

If a selected assessment skill is unavailable, say so. Do not substitute a mapping proposal for the unavailable assessment; ask whether the user wants to proceed without it.

**Stage output:** A record of the existing policy and control associations for each requirement, plus any relevant coverage-review suggestions.

## 3. Group requirements into policies and controls

**Start only after Stage 2 is complete. Review all requirements before proposing policies or controls. Aim for the smallest practical set while keeping each policy coherent and each internal control independently testable. Do not default to one policy or control per requirement; create a separate item when its objective, scope, or testing approach warrants one.**

- **Policies:**
  - **First identify the policy-level rule or decision each requirement calls for. Distinguish that rule from the activities, tests, and evidence needed to demonstrate it. Start with a small set of coherent policy objectives and assign requirements to them. Group requirements when they can be addressed by related rules within one policy for a compatible scope and audience. Do not split them merely because they describe different activities or need separately testable controls. For example, measurement, internal audit, management review, and corrective action may fit a single *Performance Evaluation and Improvement* policy, while retaining separate controls and evidence.**
  - **Challenge each grouping in both directions: could two proposed policies be combined without making their objective or rules unclear? Does a proposed policy contain requirements with genuinely different objectives, materially different scopes or owners, or rules that would make it difficult to understand, approve, or maintain? A shared broad domain or keyword alone does not justify combining requirements. Split where necessary, and flag uncertain groupings rather than treating them as settled.**
  - **For each proposed policy, state its objective and scope, identify its grouped requirements, and briefly justify the grouping using those requirements’ titles and details. If a lean grouping is a judgment call, explain the trade-off and show a more separated option. Keep the requirement-to-policy mapping explicitly proposed; do not describe requirements as linked in Eramba unless those associations have been saved and verified.**
- **Internal controls:**
  - **Aim for the smallest practical set of controls by identifying the overarching process or activity first, rather than proposing a separate control for every requirement or process step. Group activities when they belong to one coherent process and can be tested efficiently using the same population, sample, evidence request, and testing approach. A single control can have several distinct, testable activities; list each one explicitly so consolidation does not hide a requirement.**
  - **Challenge each grouping: would testing these activities together save duplicate sampling or evidence requests while still allowing each activity to be checked and failures to be identified? Split controls when activities have materially different scopes, owners, populations, evidence, or testing methods, or when a combined control would be too broad to assess reliably. Different activities alone are not a reason to split controls; a shared topic alone is not a reason to combine them**
  - **For example, Background Checks and Employee Contract Signature may be activities within one Employee Hiring control. An assessor could select a sample of hires and check both the applicable background-check evidence and signed contracts for each person. State both checks as separate activities within the control, including any differences in when or to whom they apply. By contrast, CCTV operation and coverage would normally require different evidence and testing from a walk-around inspection of Clear Desk, Physical Security, and Cabling Protection, so those would remain separate controls.**

**Give each proposed policy and internal control a simple, clear title that anyone can understand. Use the same topic wording as the requirements where appropriate. For example, if a requirement refers to “Physical Security,” use “Physical Security” rather than replacing it with “Facilities.” Do not use acronyms, abbreviations, codes, or requirement identifiers in these titles.**

**Provide a bullet-point description of what each policy must cover or each control must achieve. Keep a clear mapping from every requirement to the policies and controls addressing it. If requirements cannot be grouped without making an item too broad or difficult to test, create separate items. If a requirement cannot genuinely be addressed by a proposed policy or control, mark that table cell “Not proposed” and briefly explain the gap; do not force a misleading mapping.**

**Whenever you need to display a table, use the `display_table` skill.**

**At the end of your analysis, provide a table with one row per identified requirement and its associated policies and internal controls:**

| Requirement | Policies | Internal Controls |
|---|---|---|

Then provide a table with one row per identified policy and its objective, rationale for grouping, and key bullet points:

| Policy Title | Objective | Grouping Rationale | Mandatory Topics |
|---|---|---|---|

Then provide a table with one row per identified internal control, its key activities, and the policy or policies from the previous table that best relate to it:

| Internal Control Title | Key Activities | Related Policy |
|---|---|---|

**Stage output:** A proposed mapping of requirements to policies and controls. The descriptions of policies and internal controls must be bullet lists of the mandatory items they need to address.

## 4. Suggest policy creation

**Start only after Stage 3 is complete. Review the proposed policies and their mapped requirements. Suggest that the user create all proposed policies using the Create an Eramba Policy skill, or briefly identify the policy or policies you recommend creating and explain why. Base recommendations on the requirement mapping and policy objectives from Stage 3.**

**Use the mandatory-topic bullet points defined for each policy in Stage 3 as that policy’s Content. Do not omit or replace those bullet points when preparing the policy for creation.**

**Do not create policies unless the user explicitly selects the policies to create. A proposed mapping does not create associations between Eramba records. If it is unclear whether the user wants to create all proposed policies or only selected ones, ask which policies they want to create. When the user selects a policy or policies, continue using the Create an Eramba Policy skill and provide it with each selected policy’s name, description, and Content. Use the policy title as its name, derive its description from the objective and grouping rationale in Stage 3, and use its mandatory-topic bullet points as its Content.**

**Stage output: A concise recommendation to create all proposed policies or the identified recommended policies using the Create an Eramba Policy skill. If the user selects policies, pass each selected policy’s name, description, and Content to that skill.**
```
