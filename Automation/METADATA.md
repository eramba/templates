# Automation metadata

Every automation README starts with a YAML front matter block. It describes the automation so it can be indexed and grouped by vendor, technology and use case without moving folders. [CATALOGUE.md](CATALOGUE.md) and [catalogue.json](catalogue.json) are generated from it.

## Indexing fields

| Field | Type | Required | Meaning |
|---|---|---|---|
| `id` | string | yes | Stable, unique identifier. Never reuse or rename it. |
| `name` | string | yes | Automation name, also its folder name (`–` becomes `-`). For internal controls, the exact control title. |
| `vendors` | list | yes | Vendors whose product the automation connects to. |
| `technologies` | list | yes | Products or APIs used, with the names listed in the vocabulary. Each one belongs to one vendor. |
| `use_cases` | list | yes | What the automation is for in eramba: `account-reviews`, `internal-controls`, `compliance`, `third-party-risk`, `online-assessments`, `incident-management`, `examples`. |
| `domains` | list | no | Security topic, such as `identity-access` or `vulnerability-management`. |
| `frameworks` | list | internal controls | Frameworks the tested control maps to, taken from [the control mapping](../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/mapping_controls_to_requirements.csv). |
| `eramba_module` | string | yes | eramba section where the automation runs. |
| `vendor` | string | yes | Main vendor. It is the name of the vendor folder the automation lives in. |
| `technology` | string | yes | Human-readable summary of the technology. Indexers should use `technologies`. |

The allowed values of `use_cases`, `domains`, `frameworks`, `vendors` and `technologies` are in [metadata-vocabulary.yml](metadata-vocabulary.yml). To use a new value, add it there first.

The other fields (`version`, `status`, `trigger`, `controls`, `policies`, `audit_frequency`, `guide`, `secrets`, `variables`, `dependencies`, `timeout_seconds`, `eramba_version_tested`, `last_tested`) are described in the [README template](_TEMPLATE/Internal%20Control/README.md).

## Rules

- An automation that tests an internal control has `internal-controls` in `use_cases`, its control titles in `controls`, and the frameworks from the mapping in `frameworks`. If the control maps to any framework, it is also `compliance`.
- `vendors` lists the vendors of its `technologies`. `eramba` may be left out when another vendor is present.
- The automation lives in `Automation/<vendor>/<name>/`: `<vendor>` is its `vendor` field and `<name>` its `name`, with `-` instead of `–`. There are no folders per eramba section or use case: `eramba_module` and `use_cases` say where it goes and what it is for.
- READMEs without front matter (vendor guides such as `AWS/README.md`), `_TEMPLATE`, `_shared`, `docs`, `skills` and `tools` are not indexed.

## Example

```yaml
---
id: entra-mfa-coverage
name: Multi-Factor Authentication Coverage Review
vendor: Microsoft
technology: Microsoft Entra ID
eramba_module: Internal Controls
vendors:
  - Microsoft
technologies:
  - Microsoft Entra ID
use_cases:
  - internal-controls
  - compliance
domains:
  - identity-access
frameworks:
  - "PCI DSS v4.0.1"
  - "ISO 27002:2022"
  # ...
---
```

## Rebuild the catalogue

After changing any README front matter or the vocabulary:

```sh
pip install pyyaml
python3 Automation/tools/build_catalogue.py          # validate and regenerate
python3 Automation/tools/build_catalogue.py --check  # validate only; fails if the catalogue is stale
```
