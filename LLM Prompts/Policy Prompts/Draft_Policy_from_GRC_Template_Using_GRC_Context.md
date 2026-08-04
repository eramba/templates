# GRC Policy Drafting Prompt

Use this prompt with Claude (or another capable LLM with web/file access) to draft a company-specific GRC policy from Eramba's open-source template library.

---

## PROMPT

You are helping me draft a company GRC policy. Follow these steps in order and do not skip or reorder any of them.

**Phase summary**
1. Require company context upload.
2. Ask for a prior partial policy draft to resume from (optional).
3. List available policy templates and ask which to use; then list compliance frameworks found in it and ask which to keep — or, if a prior draft was imported in Step 2, suggest the template/frameworks it already used instead of starting blank (still optional to change).
4. Gap-check questionnaire: go item by item through every policy statement, asking if it's done and what technology is used if so, showing how many remain, until all are resolved or I say to stop.
5. Ask preferred output format (HTML or Markdown).
6. Suggest updating the original context file with the information gathered in Step 4.
7. Produce the final policy and, if I agreed in Step 6, the updated context file — with a summary of what was modified.

**Step 1 — Company context (mandatory)**
Ask me to upload a document describing my company's context (departments, scopes, technology inventory, suppliers, etc.). Do not proceed to Step 2 until this file has been provided and read.

**Step 2 — Prior policy draft (optional)**
Ask me whether I have a previously drafted/partial policy from an earlier run (e.g. one where I chose to stop the questionnaire early — see Step 4). If I provide one, import it. If I don't have one, proceed normally.

**Step 3 — Policy template and compliance frameworks**
If no prior draft was imported in Step 2:
- Fetch the list of available policy templates from this repository:
  `https://github.com/eramba/templates/tree/master/GRC%20Templates/LLM%20-%20GRC%20Templates/Policies`
- Present them as a numbered list and ask which one(s) I want to work with (one or more by number).
- Read the selected template(s). Each contains tagged sections marked either `**Applies to all frameworks**` (always kept) or `**<Framework name> (<clause/control references>)**` (framework-specific, e.g. ISO 27002:2022, ISO 27701:2025, CIS Controls v8.1, PCI DSS v4.0.1, NIST 800-53 Rev5, SCF 2025, SOC 2 (TSP 2017), NIS2 Article 21). Extract the distinct framework tags present and present them as a numbered list. Based on my company context, mark the framework(s) most likely applicable — still show the full list. Ask which framework number(s) I want included (optional — I may choose none).

If a prior draft was imported in Step 2:
- Detect which template and which framework(s) it was already built from, and state them back to me as a suggestion (e.g. "This looks like the Endpoint Security policy using ISO 27002:2022 + ISO 27701:2025 — continue with these?"). Let me confirm, change the template, or change the frameworks.

Once template + frameworks are settled, filter the template: keep only sections/bullets tagged "Applies to all frameworks" plus the selected framework(s), remove the rest, and renumber any numbered procedure steps sequentially. Do not show the raw tags in any output — they only decide what to keep.

**Step 4 — Gap-check questionnaire (mandatory before drafting)**
Break the filtered template down into individual policy statements — one item per bullet/requirement, not per section. Skip any item already answered by an imported prior draft. Build the full list of remaining items, then loop through them one at a time:
- Tell me how many open items remain (e.g. "12 of 18 remaining").
- Ask about **one item at a time**, phrased as a closed two-part question: (a) is this done or not, and (b) if done, what technology/tool/process is used.
- Alongside every question, always explicitly offer the option to stop: e.g. "(Or tell me to stop here and produce the policy as-is with remaining items flagged as gaps.)"
- Record my answer verbatim — either the named technology/process, or a confirmed "not done" — and reduce the remaining count.
- Continue automatically to the next item.
- Stop when either: (a) all items are resolved, or (b) I invoke the stop option — any remaining unanswered items are then treated as unconfirmed gaps rather than assumed. Either way, retain the current state (context + all answers so far + filtered template) as a "previously drafted/partial policy" I can resume from later.

Do not batch multiple items into one question, and do not proceed to Step 5 until the loop has ended one of these two ways.

**Step 5 — Output format**
Ask me whether I want the final policy delivered as **HTML** or **Markdown**. Do not default to one — wait for my choice.

**Step 6 — Suggest a context-file update**
Before producing the final policy, review everything I confirmed in Step 4 (technologies, tools, processes named, and confirmed absences) and check it against my original context file. Propose the specific additions or corrections that should be made to that file so it reflects what was just learned (e.g. a technology inventory row that was missing or wrong). Ask if I want this update applied. This is optional — I may decline.

**Step 7 — Produce the final outputs**
Draft the final policy in the chosen format from Step 5, using my company context, the filtered/approved template, and every Step 4 answer. For each individual policy statement, attach its own inline annotation directly beneath that statement — never one generic note per section:
- **Confirmed as done:** name the specific technology/tool/process next to the statement it satisfies.
- **Confirmed as not done / unresolved:** flag it as a gap directly beneath that specific statement.

If I agreed to the context-file update in Step 6, also produce the updated context file, along with a short summary of exactly what was added or changed in it (e.g. as a changelog list, not just a diff of the whole document).

---

## Notes for reuse
- Step 1 is always mandatory; Step 2 is optional. Step 3's framework selection is optional; the template selection itself is not.
- If I select multiple policies in Step 3, repeat Steps 3–4 per policy, then combine or keep them separate as I instruct.
- The gap-check questionnaire (Step 4) always runs, even if I skipped framework selection — it just checks against the "applies to all frameworks" content in that case.
- The questionnaire is item-by-item at the individual policy-statement level, not section-level — a section with 4 bullets means 4 separate questions, not one.
- Always show the remaining open-item count on every loop turn.
- Always display the "stop and produce as-is" option alongside every gap-check question.
- If I stopped early on a previous run, providing that partial policy in Step 2 skips re-answering resolved items and lets me confirm the template/frameworks instead of re-choosing from scratch.
- Every policy statement gets its own inline annotation in the final draft (technology used, or gap flagged) — never one generic note per section.
- The context-file update (Step 6) is proposed, not silently applied — I always get to say no.
- Always ask before assuming an output format.
