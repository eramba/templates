---
author: "Esteban and Codex"
version: "1.1"
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

Get the Compliance Package name and item names or IDs from the user. In Eramba, locate the package and review each selected item’s title, description, and additional details. Use all available fields; some may be blank. Do not use external sources, particularly internet-based sources.

If the package or items cannot be found, tell the user. If the available details are insufficient to assess an item, explain what is missing and that you cannot continue. Do not guess or proceed with incomplete requirements.

**Stage output:** A complete set of selected requirements, with the available details retrieved from Eramba.

## 2. Group requirements into policies and controls

Start only after Stage 1 is complete. Review all requirements before proposing policies or controls. Aim for the smallest practical set while keeping each item coherent and testable. Do not create one policy or control per requirement.

- **Policies:** Group requirements when they share the same general topic and can be covered by one policy. Keep different topics in separate policies.
- **Internal controls:** Group requirements when they share a topic and their activities could reasonably be tested together using the same testing approach. Keep controls separate when their topics or likely testing methods differ.

For example, a walk-around inspection could test Clear Desk, Physical Security, and Cabling Protection together. CCTV camera operation and coverage would likely need a different test, so keep it separate.

Give each proposed policy and internal control a simple, clear title that anyone can understand. Use the same topic wording as the requirements where appropriate. For example, if a requirement refers to “Physical Security,” use “Physical Security” rather than replacing it with “Facilities.” Do not use acronyms, abbreviations, codes, or requirement identifiers in these titles.

Provide a bullet-point description of what each policy must cover or each control must achieve. Keep a clear mapping from every requirement to the policies and controls addressing it. If requirements cannot be grouped without making an item too broad or difficult to test, create separate items.

At the end of your analysis, provide a table with one row per identified requirement and its associated policies and internal controls:

| Requirement | Policies | Internal Controls |
|---|---|---|

Then provide a table with one row per identified policy and its key bullet points:

| Policy Title | Mandatory Topics |
|---|---|

Then provide a table with one row per identified internal control, its key activities, and the policy or policies from the previous table that best relate to it:

| Internal Control Title | Key Activities | Related Policy |
|---|---|---|

**Stage output:** A proposed mapping of requirements to policies and controls. The descriptions of policies and internal controls must be bullet lists of the mandatory items they need to address.
```
