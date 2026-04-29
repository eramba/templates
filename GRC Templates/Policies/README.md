# Information Security Policies – Overview

This repository contains a set of information security policies intended to serve as a **baseline** for defining governance, technical requirements, and security responsibilities.

These documents are **not plug-and-play**.  
Anyone using them is expected to review each policy **sentence by sentence** and determine whether content must be **removed, modified, or extended** to reflect their organization’s environment, risks, and obligations.

---

## 1. General Document Format

All policy documents follow a consistent structure to ensure clarity and governance alignment.

Each policy includes, at a minimum:
- **Purpose** – why the policy exists
- **Scope** – who and what the policy applies to
- **Responsibilities** – who is accountable for implementation and compliance

Additional sections are included where necessary, depending on the policy topic.

---

## 2. Policy Grouping Model

Policies are grouped to clearly separate **technology-specific requirements**, **cross-cutting technical controls**, **governance and risk activities**, and **framework-specific content**.

Each policy belongs to one primary group.

---

## 3. Technological Stack Policies

These policies define security requirements that apply to **specific technology stacks**, such as endpoints, operating systems, networks, or remote access technologies.

| Policy Name |
|------------|
| Network Management Policy |
| Operating System Standards |
| Mobile Device and Remote Access Policy |

---

## 4. Cross-Stack Technology Policies

These policies define technical controls that apply **across all technological stacks**, regardless of platform, environment, or location.

| Policy Name |
|------------|
| Cryptography Policy |
| Backup and Recovery Policy |
| Logging and Monitoring Policy |
| Data Management Policy |
| Acceptable Use of Assets Policy |

---

## 5. GRC (Governance, Risk, and Compliance) Policies

These policies focus on governance, risk management, and security management processes rather than specific technologies.

| Policy Name |
|------------|
| Information Security Policy |
| Information Security Roles and Responsibilities |
| Awareness Policy |
| Audit and GRC Monitoring Policy |
| Asset Management Policy |
| Access Control Policy |
| Change Management Policy |
| Incident Management Policy |
| Third-Party Security Policy |

---

## 6. Framework-Specific Policies

These policies are written to support specific security frameworks or standards, such as ISO/IEC 27001.

| Policy Name |
|------------|
| Information Security Policy *(ISO/IEC 27001 context)* |

---

## 7. CSV Policy Import and LLM Usage

This repository also includes a **CSV file ready to be imported** into governance and policy management tools.

The CSV:
- Contains references to the **raw policy documents**
- Allows direct access to policy text in a machine-readable format
- Can be consumed by **Large Language Models (LLMs)** for analysis

This enables use cases such as:
- Asking what policy changes are needed to meet a specific control or requirement
- Mapping policies to security frameworks (for example, CIS Controls or ISO/IEC 27001)
- Identifying gaps, overlaps, or inconsistencies across policies

Example questions that can be asked using the CSV and raw documents include:
- *What changes are required in our policies to meet CIS Controls v8.1 Requirement 1.2?*
- *Which policies are relevant to asset discovery and inventory?*
- *Are there conflicting requirements across network and endpoint policies?*

The accuracy of these outcomes depends on the **quality of the review and interpretation** performed by the organization using the documents.

---

## Important Note on Usage

These policies are intentionally written at a **generic level**.

Organizations adopting these documents must:
- Read each policy carefully, line by line
- Remove statements that do not apply
- Modify wording to reflect actual practices
- Add missing requirements where needed

Using these documents without proper review may result in misalignment between policy and reality, weakening both security and compliance.
