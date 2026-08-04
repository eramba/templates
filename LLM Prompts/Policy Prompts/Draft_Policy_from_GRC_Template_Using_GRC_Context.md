# GRC Policy Drafting Prompt

Use this prompt with Claude (or another capable LLM with web/file access) to draft a company-specific GRC policy from Eramba's open-source template library.

---

## PROMPT

You are helping me draft a company GRC policy. Follow these steps in order and do not skip or reorder any of them.

**Phase summary**
1. Require company context upload; optionally ask for a prior partial policy draft to resume from.
2. List available policy templates; ask which to use.
3. List applicable compliance frameworks found in the template; ask which to keep (optional).
4. Filter the template to keep only universal + selected-framework content.
5. Gap-check loop: go item by item through every policy statement, asking if it's done and what technology is used if so, showing how many remain, until all are resolved or I say to stop.
6. Ask preferred output format (HTML or Markdown).
7. Draft the final policy using company context + filtered template + gap-check answers.

**Step 1 — Company context (mandatory)**
Ask me to upload a document describing my company's context (departments, scopes, technology inventory, suppliers, etc.). Do not proceed to Step 2 until this file has been provided and read.

Also ask me — as an optional question — whether I have a previously drafted/partial policy from an earlier run (e.g. one where I chose to stop early — see Step 5). If I provide one, import it and use it in Step 2/3 to recommend likely options, and in Step 5 to skip re-asking anything it already answered. If I don't have one, proceed normally.

**Step 2 — List policy templates**
Fetch the list of available policy templates from this repository:
`https://github.com/eramba/templates/tree/master/GRC%20Templates/LLM%20-%20GRC%20Templates/Policies`
Present them as a numbered list. If a previous partial policy was imported in Step 1, mark that same template as "likely continuation" at the top of the list, still showing all others. Ask me which one(s) I want to work with (I may select one or more by number).

**Step 3 — Compliance frameworks (optional)**
Read the selected policy template(s). Each template contains tagged sections marked either:
- `**Applies to all frameworks**` (always mandatory, always kept), or
- `**<Framework name> (<clause/control references>)**` (framework-specific, e.g. ISO 27002:2022, ISO 27701:2025, CIS Controls v8.1, PCI DSS v4.0.1, NIST 800-53 Rev5, SCF 2025, SOC 2 (TSP 2017), NIS2 Article 21).

Extract the distinct framework tags present in the selected template(s) and present them as a **numbered list**. Based on my company context (e.g. sector, geography, customer base, regulatory hints in the context file) and any prior policy work imported in Step 1, mark the framework(s) most likely applicable to my case — still show the full list, don't hide options. Ask me which framework number(s) I want included (this step is optional — I may choose none).

**Step 4 — Filter the template**
Keep only:
- sections/bullets tagged "Applies to all frameworks", and
- sections/bullets tagged with the framework(s) I selected.

Remove all other framework-specific tagged content. Renumber any numbered procedure steps sequentially after filtering. Do not show me the raw tags in the final draft — they are only used to decide what to keep or remove.

**Step 5 — Gap-check loop (mandatory before drafting)**
Before producing any output, break the filtered template down into individual policy statements — one item per bullet/requirement, not per section (e.g. "screen locking enforced after 15 min" is one item, "configuration baselines reviewed annually" is another separate item). Do this for every Policy Statement subsection; procedures don't need separate gap-checks since they inherit from the policy statements they verify.

Build the full list of items first, then loop through them one at a time:
- Tell me how many open items remain (e.g. "12 of 18 remaining").
- Ask about **one item at a time**, phrased as a closed two-part question: (a) is this done or not, and (b) if done, what technology/tool/process is used (e.g. "Is full-disk encryption enforced on all endpoints? If yes, what's used — FileVault, BitLocker, a third-party tool?").
- Alongside every question, always explicitly offer the option to stop: e.g. "(Or tell me to stop here and produce the policy as-is with remaining items flagged as gaps.)"
- Record my answer verbatim — either the named technology/process, or a confirmed "not done" — and reduce the remaining count.
- Continue automatically to the next item.
- Stop when either: (a) all items are resolved, or (b) I invoke the stop option (e.g. "that's enough", "stop", "draft it as is") — any remaining unanswered items are then treated as unconfirmed gaps in the final draft rather than assumed. In this case, also retain the current state (context + all answers so far + filtered template) as the "previously drafted/partial policy" referenced in Step 1, in case I resume this later.

Do not batch multiple items into one question, and do not proceed to Step 6 until the loop has ended one of these two ways.

**Step 6 — Output format**
Ask me whether I want the final policy delivered as **HTML** or **Markdown**. Do not default to one — wait for my choice.

**Step 7 — Draft the policy**
Using my uploaded company context, the filtered/approved template content, and every gap-check answer, draft the final policy in the chosen format. For each individual policy statement, attach the relevant detail inline or as its own short note directly beneath that statement — never a single generic note per section. Two cases:
- **Confirmed as done:** name the specific technology/tool/process next to the statement it satisfies (e.g. "Satisfied via FileVault on macOS devices.").
- **Confirmed as not done / unresolved:** flag it as a gap directly beneath that specific statement (e.g. "Gap: no application allowlisting in place. Remediation required.").

Every policy statement that was part of the gap-check gets its own per-item annotation — do not consolidate multiple statements' gaps into one paragraph.

---

## Notes for reuse
- Steps 1–2 are always mandatory; Step 3 is optional (I can skip framework mapping entirely).
- If I select multiple policies in Step 2, repeat Steps 3–5 per policy, then combine or keep them separate as I instruct.
- The gap-check loop (Step 5) always runs, even if I skipped Step 3 — it just checks against the "applies to all frameworks" content in that case.
- The loop is item-by-item at the individual policy-statement level, not section-level — a section with 4 bullets means 4 separate questions, not one.
- Always show the remaining open-item count on every loop turn.
- Always display the "stop and produce as-is" option alongside every gap-check question — never make me guess that stopping early is possible.
- If I stopped early on a previous run, starting the prompt again with both the context file and that partial policy imported skips re-answering resolved items and highlights the likely template/framework choices rather than re-showing a blank list.
- Every policy statement gets its own inline annotation in the final draft (technology used, or gap flagged) — never one generic note per section.
- Always ask before assuming an output format.
