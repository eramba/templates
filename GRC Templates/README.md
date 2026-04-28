# GRC Templates – Overview

This project provides a practical set of governance templates designed to support security and compliance activities.

It is composed of three main building blocks:

- Policies
- Internal Controls
- Automation Scripts

Each element has a distinct purpose and they are designed to work together.

---

## Components

### Policies
Policies define management intent and rules.

They describe:
- What is required
- What is allowed or forbidden
- Who is responsible

Policies do not describe technical implementation details.

More detailed explanations about the **policy templates**, their structure, and how they are intended to be used can be found here:  
https://github.com/eramba/grc_templates/blob/master/Policies%20README.md

---

### Internal Controls
Internal Controls define how policies are enforced and verified.

They describe:
- What must be checked or reviewed
- What activity proves a policy is being followed
- How often verification happens

An Internal Control may reference one or more policies.

---

### Automation Scripts
Automation scripts are used to execute or support Internal Controls.

They:
- Collect evidence automatically
- Perform technical checks
- Reduce manual effort

A single Internal Control may use multiple automation scripts, or none at all.

---

## Relationship Between Components

- Policies define what must be done
- Internal Controls define how compliance is checked
- Automation Scripts define how checks are executed
- Compliance requirements are satisfied by a combination of policies and controls

---

## Conceptual Model

```mermaid
flowchart LR
    CR[Compliance Requirements]
    P[Policies]
    IC[Internal Controls]
    AS[Automation Scripts]
    MA[Manual Audit Task List]

    CR --> P
    CR --> IC

    P --> IC

    IC --> AS
    IC --> MA
