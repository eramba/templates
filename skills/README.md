# Eramba AI Assistant Skills

Store each completed skill in this directory as one Markdown file. Use a lowercase, hyphen-separated filename based on the skill title, for example `analyse-compliance-requirements.md`.

Each skill file must begin with YAML metadata, followed by the title, description, and skill text:

```markdown
---
author: "Name of author or authors"
version: "1.0"
modules:
  - "Relevant Eramba module"
use_cases:
  - compliance
---

# Title

Skill title

## Description

Brief description of when the skill is used and its purpose.

## Skill

Complete skill instructions in Markdown.
```

## Metadata rules

- `author`: Required. Name the person, team, or assistant that authored the skill. List multiple authors when appropriate; do not guess.
- `version`: Required. Start a new skill at `1.0`. Increase the version when revising that skill, keeping its existing filename.
- `modules`: Optional. Include one or more relevant Eramba module names when applicable. Omit this field when no module applies.
- `use_cases`: Required. Include one or more of these values: `compliance`, `risk`, `incident`, `online assessments`, `awareness programs`, `data privacy`.

The `## Skill` section contains the full instructions. Write those instructions in Markdown syntax, which Eramba displays as plain text because its Instructions field does not support rich-text formatting. Do not include the YAML metadata in the Eramba Instructions field.

Keep each skill self-contained in a single file. Do not add a separate instructions file for the same skill. The README is directory guidance, not a skill.
