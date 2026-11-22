---
author: "Esteban"
version: "1.0"
modules:
  - "Risk Management"
use_cases:
  - risk
---

# Title

```text
External Threat-Informed Risk Scenario Generator
```

## Description

```text
Use when a user asks to assess a company’s external risk exposure from a domain, build an OSINT-based risk register, or enrich Eramba Risk Management with threat intelligence. Produces prioritised, evidence-linked risk scenarios with auditable likelihood ratings.
```

## Skill

````markdown
# Eramba External Threat-Informed Risk Scenario Generator

Turn a company domain into a defensible, evidence-linked risk-scenario register inside Eramba.

## When to use this skill

Use when the user asks to:
- Assess a company's external risk exposure from a domain name.
- Build or enrich an Eramba risk register with threat intelligence.
- Produce risk scenarios with likelihood ratings backed by evidence.
- Re-score existing risks after new intelligence appears.

Do **not** use for: active exploitation, credential testing, authenticated scanning, or any target the customer has not explicitly authorised.

---

## Inputs

| Input | Required | Example | Notes |
|---|---|---|---|
| `company_domain` | Yes | `globant.com` | Root domain; skill derives brands, subsidiaries, and email domains from it |
| `company_name` | No | `Globant` | Inferred from domain if omitted |
| `scope_authorized` | Yes | `true` | Hard gate — refuse to run if absent or false |
| `eramba_target` | No | `risk_module` | Where to write: `risk_module` (default), `json_only`, or `report_only` |
| `existing_risk_check` | No | `true` | Deduplicate against current Eramba risks before creating new ones |

If `scope_authorized` is not `true`, stop and return a refusal explaining that written authorisation is required.

---

## Guardrails (non-negotiable)

1. **Passive only.** Use public sources, commercial threat-intel APIs, passive DNS, and certificate transparency. No port scanning of customer infrastructure unless a separate authorisation covers it.
2. **Never use discovered credentials.** Report exposure; do not authenticate, test, or validate by logging in.
3. **Never exploit findings.** No CNAME claiming, no bucket access, no secret use.
4. **Secrets stay redacted.** Record secret *type*, repository, and commit SHA — never the full value. Truncate to first 4 characters plus length.
5. **Verify before scoring Critical.** A Critical rating requires at least two independent corroborating sources, or one source plus a confirmed historical precedent.
6. **Mark uncertainty explicitly.** Tag every finding `confirmed`, `probable`, or `unverified`. Unverified findings cannot exceed Medium.

---

## Procedure

### Phase 0 — Scope and authorisation

1. Confirm `scope_authorized == true`. If not, refuse and explain.
2. Resolve the domain to a canonical company entity: legal name, brand variants, acquired brands, subsidiaries, and employee email domains.
3. Build a **target inventory**: root domains, known subdomains, email domains, GitHub/GitLab org names, and ASNs.
4. Record the collection window (start/end timestamps) — every finding carries a timestamp.

**Output:** `target_inventory` object.

---

### Phase 1 — Company profiling

Goal: understand what the company does and which risks are structurally relevant.

Collect:
- **Business model and sector** — products, services, customer segments (from website, annual reports, Crunchbase, LinkedIn company page).
- **Regulatory exposure** — PCI DSS (payments), HIPAA (health data), GDPR/CCPA (EU/US personal data), DORA/NIS2 (EU financial/critical infrastructure), FedRAMP (US public sector).
- **Technology stack** — web frameworks, cloud providers, CDNs, email providers, SaaS platforms. Derive from HTTP headers, DNS/CNAME patterns, and Wappalyzer-style fingerprinting.
- **Digital estate** — subdomains via certificate transparency (`crt.sh`), passive DNS, and `amass` in passive mode.
- **Employee footprint** — email naming convention, approximate headcount, key technical staff, public developer accounts.

**Output:** `company_profile` with `industry`, `regulatory_exposure[]`, `tech_stack[]`, `cloud_providers[]`, `domain_inventory[]`, `employee_email_domains[]`.

**Why it matters:** the profile is the filter for later phases. Only query leak sources for confirmed email domains; only treat GitHub orgs as in-scope if they map to the confirmed entity.

---

### Phase 2 — Dark-web and credential-leak intelligence

Goal: find threats already circulating in criminal ecosystems.

Query, per confirmed email domain:

| Source class | What to extract | Example providers |
|---|---|---|
| Infostealer log aggregators | Employee credentials, exposed application names, malware family, last-seen date | Hudson Rock, SpyCloud, Flare |
| Breach and combolist indexes | Breach name, date, record count, data classes | Have I Been Pwned (domain search), DeHashed |
| Dark-web marketplaces and forums | Company mentions, access-for-sale listings, ransomware chatter, leak-site posts | Flashpoint, Anomali, mnemonic DTM |
| Supplier/vendor monitoring | Compromised third-party accounts on partner domains | SpyCloud Supply Chain, HackNotice |

For each credential finding, capture:
- `account_type`: employee / contractor / third-party
- `application`: Okta, GitHub, Jira, Confluence, Microsoft 365, etc.
- `malware_family`: Lumma, RedLine, etc.
- `last_seen`: date
- `password_strength`: weak / reused / unknown (if the source provides it)
- `source_confidence`: confirmed / probable / unverified

Also record **aggregate hygiene indicators** where available (weak-password percentage, endpoint AV coverage, MFA posture) — these are the strongest single drivers of likelihood.

**Output:** `credential_exposure[]`, `darkweb_mentions[]`, `hygiene_indicators{}`.

---

### Phase 3 — Cloud and network enumeration

Goal: map where systems run and identify exposed or claimable resources. Passive and semi-passive only.

Checks:

| Check | Method | Risk implication |
|---|---|---|
| Cloud provider fingerprinting | CNAME patterns (`*.amazonaws.com`, `*.azurewebsites.net`, `*.run.app`, `*.cloudfront.net`), ASN lookup | Determines which cloud-specific risks apply |
| Exposed services | Passive DNS + public scan datasets (Shodan, Censys) — no direct scanning | Direct attack surface |
| Dangling CNAMEs | Resolve each subdomain; flag CNAMEs to non-existent cloud resources (`NoSuchBucket`, unclaimed `*.azurewebsites.net`) | Subdomain takeover |
| Public storage buckets | Bucket-name dictionaries against `s3.amazonaws.com`, `blob.core.windows.net`, `storage.googleapis.com` using **metadata-only** checks | Data exposure |
| TLS posture | Certificate expiry, weak ciphers, hostname mismatch | MITM / trust erosion |
| IP co-tenancy | Reverse-IP lookups on key hosts | Shared-infrastructure blast radius |

If a commercial passive-DNS or cloud-exposure feed is unavailable, mark cloud findings as `provisional` and state the gap explicitly — an empty result is **not** a clean result.

**Output:** `cloud_footprint[]`, `exposure_findings[]`, `enumeration_gaps[]`.

---

### Phase 4 — Code repository reconnaissance

Goal: find secrets, internal architecture detail, and confidential material in public repos.

Steps:

1. **Organisation discovery** — enumerate GitHub/GitLab/Bitbucket orgs for the company name and variants; identify employee personal accounts linked to the org.
2. **Repository inventory** — languages, CI/CD configs, dependency manifests, Dockerfiles, IaC templates.
3. **Current-code secret scan** — `gitleaks detect` / `trufflehog filesystem` on the default branch.
4. **Full git-history scan** — secrets removed in later commits remain in Git objects; scan the entire history.
5. **Dorking for sensitive files** — `.env`, `config.json`, `id_rsa`, `credentials`, `*.pem`, database connection strings, internal hostnames.
6. **Internal-infrastructure leakage** — extract internal hostnames, IPs, API endpoints, and environment names from code and CI logs.

For each secret finding record: `repo`, `file_path`, `commit_sha`, `secret_type`, `truncated_value`, `is_live` (if determinable without use), `detected_date`.

**Output:** `repository_findings[]`, `secret_findings[]`.

---

### Phase 5 — Incident history mining

Goal: learn which threat actors and vectors have succeeded against this company before.

Search news archives, breach-notification sites, regulator filings, leak-site indexes, and threat-intel platforms for:
- Data breaches (size, data classes, attacker group)
- Ransomware incidents (strain, ransom, downtime)
- Business email compromise campaigns
- Third-party / supply-chain incidents
- Regulatory fines or consent orders related to security
- Industry-wide campaigns the company's sector is currently facing

Record for each: `date`, `actor`, `vector`, `impact`, `confirmed_by_company` (boolean), `source_url`.

**Output:** `incident_history[]`.

**Why it matters:** confirmed past incidents are the strongest evidence for recurrence likelihood.

---

### Phase 6 — Risk scenario generation and likelihood scoring

#### 6.1 Map findings to scenarios

| Finding category | Scenario template | Context link |
|---|---|---|
| Infostealer credentials for SSO | "Compromised {IdP} credentials enable unauthorised access to corporate applications and client environments" | Asset: Identity Provider |
| Leaked code-platform credentials | "Leaked {GitHub/Jira/Confluence} credentials permit source-code theft and project-data exfiltration" | Asset: Code Repository |
| Endpoint malware | "Infostealer malware ({family}) on employee endpoints harvests credentials and enables lateral movement" | Asset: Corporate Endpoints |
| Third-party account compromise | "Compromised third-party SaaS accounts ({vendors}) provide a pivot into the company environment" | Third Party: {vendor} |
| Historical repository breach | "Recurrence of source-code repository compromise via credential-based access" | Asset: Code Repository |
| Cloud misconfiguration | "Public-facing cloud misconfiguration ({S3/CNAME/IAM}) exposes client data or enables subdomain takeover" | Asset: Cloud Infrastructure |
| Dark-web data listing | "Dark-web sale of internal documents enables client-targeted social engineering and downstream supply-chain attacks" | Asset: Corporate Data |

Use scenario-style titles (describing an event, not a control gap). Link every risk to exactly one context object.

#### 6.2 Score likelihood

Apply this rubric and record the rationale verbatim in the risk description:

| Rating | Criteria |
|---|---|
| **Critical** | Valid credentials for a privileged or SSO account present in a leak **and** MFA absent or unverified; **or** internet-facing asset with EPSS > 0.7 or CISA KEV membership; **or** confirmed, exploited secret with live permissions |
| **High** | Valid credentials for a standard employee account in a leak; **or** confirmed secret in a public repo; **or** claimable dangling CNAME; **or** confirmed past incident with the same vector and no verified remediation |
| **Medium** | Stale or expired credentials in a leak; **or** public bucket with non-sensitive data; **or** past breach with no recurrence evidence; **or** any single unverified high-severity finding |
| **Low** | Theoretical exposure with no exploitation evidence; **or** finding already mitigated by a verified control |

Weighting guidance: credential validity and exposure/reachability carry the most weight; threat-actor targeting, past-incident precedent, and control maturity carry secondary weight.

**Constraints:**
- `unverified` findings cap at **Medium**.
- `provisional` cloud findings cap at **Medium** until re-validated.
- Every scenario needs at least one cited source in its rationale.

**Output:** `risk_scenarios[]` with `title`, `context_type`, `context_name`, `inherent_likelihood`, `rationale`, `evidence_refs[]`, `confidence`, `recommended_actions[]`.

---

## Output contract

Return this structure (and write it to Eramba when `eramba_target != json_only`):

```json
{
  "meta": {
    "target": "globant.com",
    "company_name": "Globant S.A.",
    "collection_window": { "start": "ISO-8601", "end": "ISO-8601" },
    "scope_authorized": true,
    "sources_used": ["hudsonrock", "crt.sh", "darkforums", "news"],
    "enumeration_gaps": ["no commercial cloud-exposure feed available"]
  },
  "company_profile": { },
  "credential_exposure": [ ],
  "darkweb_mentions": [ ],
  "cloud_footprint": { },
  "repository_findings": [ ],
  "incident_history": [ ],
  "risk_scenarios": [
    {
      "id": "RS-001",
      "title": "...",
      "context_type": "Asset",
      "context_name": "Identity Provider (Okta)",
      "inherent_likelihood": "Critical",
      "confidence": "confirmed",
      "rationale": "...",
      "evidence_refs": ["EV-003", "EV-011"],
      "recommended_actions": ["Enforce MFA on Okta", "Rotate exposed credentials"]
    }
  ],
  "summary": {
    "total": 7,
    "critical": 2,
    "high": 3,
    "medium": 2,
    "low": 0,
    "top_priority_actions": [ ]
  }
}
```

---

## Writing to Eramba

1. **Deduplicate.** If `existing_risk_check` is true, match on normalised title against current risks before creating.
2. **Create risk objects** via the Eramba REST API under Risk Management. Set the scenario title, description (rationale + evidence refs), and inherent likelihood.
3. **Link context.** Attach each risk to its Asset, Third Party, or Business Unit record. Create stub records if the context object does not exist, and flag them for review.
4. **Attach evidence.** Store evidence refs and source URLs in the risk description — never in a free-text field that is broadly visible.
5. **Redact.** Truncate all secret and credential values before write.
6. **Notify.** Emit a notification for any Critical risk created.

Operational constraints: Eramba automations are Enterprise-only, capped at five scripts per installation, with execution-time limits. Keep the Eramba-side script a thin orchestrator; do the heavy collection in an external Risk-Intel Service and pass back JSON.

---

## Quality checklist

Before returning results:

- [ ] `scope_authorized` was verified and true
- [ ] Every scenario has at least one evidence reference
- [ ] No Critical rating without either two independent sources or a confirmed precedent
- [ ] All `unverified` findings capped at Medium
- [ ] Cloud gaps explicitly listed rather than reported as clean
- [ ] Secrets truncated, never stored in full
- [ ] Every risk linked to exactly one context object
- [ ] Collection window and source list recorded
- [ ] Recommended actions are specific and assignable

---

## Failure modes and handling

| Situation | Handling |
|---|---|
| No leak data found | Do not conclude "no exposure". State that leak coverage is source-dependent and list which sources were queried. |
| Commercial API unavailable | Mark affected findings `provisional`, cap likelihood at Medium, list the gap in `enumeration_gaps`. |
| Company name is ambiguous | Resolve via domain; if multiple entities match, report the ambiguity and score only the confirmed entity. |
| Dark-web listing found but unverifiable | Record as a monitoring finding at Medium; recommend continuous monitoring rather than a Critical rating. |
| Findings duplicate an existing Eramba risk | Update the existing risk's rationale and likelihood rather than creating a duplicate. |

---

## Worked example (condensed) — `globant.com`

**Profile:** AI-native IT services firm, 28,700+ employees, 31 countries; supplier to banks and governments (downstream supply-chain relevance); AWS multi-account, primary `us-east-1`.

**Leak intelligence:** 445 compromised credentials, 222 employees, 509 third-party accounts; exposed apps include Okta, GitHub, Jira, Confluence; infostealer families Lumma and RedLine; 68.85% weak passwords; >51% endpoints lacking AV.

**Cloud:** AWS confirmed (AS16509). No dangling CNAME or public bucket found — marked provisional, commercial feed recommended.

**Repositories:** 2022 Lapsus$ breach confirmed by the company — ~70 GB leaked, admin credentials for GitHub/Jira/Confluence/Crucible. Current GitHub credentials still present in leak datasets.

**Incident history:** Lapsus$ (March 2022, confirmed); DarkForums listing by "Vekdanor" (unverified, monitoring).

**Resulting register:**

| # | Scenario | Context | Likelihood |
|---|---|---|---|
| 1 | Compromised Okta SSO credentials enable unauthorised access | Asset: Okta | **Critical** |
| 2 | Leaked GitHub/Jira/Confluence credentials permit source-code theft | Asset: Code Repository | **Critical** |
| 3 | Infostealer malware on endpoints harvests credentials | Asset: Endpoints | **High** |
| 4 | Compromised third-party SaaS accounts provide a pivot | Third Party: Microsoft, Udemy | **High** |
| 5 | Recurrence of source-code repository compromise | Asset: Code Repository | **High** |
| 6 | Public-facing AWS misconfiguration exposes client data | Asset: Cloud Infrastructure | **Medium** (provisional) |
| 7 | Dark-web sale of internal documents enables client-targeted attacks | Asset: Corporate Data | **Medium** (monitoring) |

**Immediate actions:** create #1–#2 as Critical with owners for credential reset and MFA enforcement; link #4 to supplier records and trigger reviews; queue a cloud-exposure scan to resolve #6; place #7 under continuous monitoring; run full git-history secret scanning.
````
