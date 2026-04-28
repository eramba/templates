You are given a CSV file.

Task:
Read the compliance requirement text from the column I specify, and create one new column containing a single-sentence policy summary for each row.

Objective:
The new sentence must describe, as clearly and unambiguously as possible, what a policy must address in order to satisfy the requirement. The output will later be used by another LLM to assess whether a policy contains sufficient content, so the wording must be strict, concrete, and testable.

Instructions:
- Read the input only from this column: [Requirement Detailed Instruction]
- Create exactly one additional column named: [LLM Test v1]
- Do not modify, remove, reorder, or rename any existing columns.
- Preserve all existing rows exactly as they are.
- For each row, write exactly one sentence in the output column.
- Every output sentence must start with: "Policy:"
- The sentence must state only what the policy must contain or address.
- Use direct, specific, evaluable language.
- Include explicit references to required roles, responsibilities, authority, accountability, approvals, documentation, reviews, records, controls, conditions, exceptions, assets, users, departments, or processes whenever they appear in the requirement text.
- Make ownership, approval authority, accountability, decision rights, and review obligations explicit whenever the requirement implies or states them.
- Do not use vague wording such as "consider", "support", "promote", "appropriate", "where relevant", or "as needed" unless the source text makes that unavoidable.
- Do not explain the requirement.
- Do not add commentary, notes, confidence scores, or extra columns.
- Do not mention the name of the framework or standard unless it is necessary to preserve the meaning.
- Do not output bullet points, lists, or multiple sentences.
- If the requirement contains several mandatory elements, compress them into one precise sentence rather than omitting them.
- Keep each output as brief as possible without losing required meaning.

Output requirements:
- Return the result as the same CSV with all original columns plus the new output column.
- The output column must contain the generated one-line policy summary for each row.

Example transformation:
Input requirement:
"Allocation of information security roles and responsibilities should be done in accordance with the information security policy..."

Output:
"Policy: Define and document all information security roles, responsibilities, authority, accountability, risk ownership, delegation rules, cross-functional coordination, and competence requirements, with clear identification of the person ultimately accountable for information security."
