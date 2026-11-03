# Automation metadata

Every automation README starts with a YAML front matter block. It describes the automation so it can be indexed by vendor, technology, eramba section and framework. Each fact appears once. [CATALOGUE.md](CATALOGUE.md) and [catalogue.json](catalogue.json) are generated from it.

## Indexing fields

| Field | Type | Meaning |
|---|---|---|
| `vendor` | string | Main vendor. It is also the folder the automation lives in: `Automation/<vendor>/<name>/`. |
| `technology` | list | Products or APIs it uses, with the names in the vocabulary. Each belongs to one vendor, so an automation that uses several vendors (for example eramba API plus OpenAI API) is found under each of them. |
| `eramba_module` | string | eramba section where it runs, which also says what it is for: Account Review Feeds, Internal Controls, Third Parties, Online Assessments, Security Incidents, or Any. |
| `domains` | list, optional | Security topic, such as `identity-access` or `vulnerability-management`. |
| `frameworks` | list, internal controls | Frameworks the tested control maps to, taken from [the control mapping](../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/mapping_controls_to_requirements.csv). This is what to index for compliance. |

`id`, `name`, `vendor`, `technology` and `eramba_module` are required. The allowed values of `vendor`, `technology`, `eramba_module`, `domains` and `frameworks` are in [metadata-vocabulary.yml](metadata-vocabulary.yml). To use a new value, add it there first.

The other fields (`version`, `status`, `trigger`, `controls`, `policies`, `audit_frequency`, `guide`, `secrets`, `variables`, `dependencies`, `timeout_seconds`, `eramba_version_tested`, `last_tested`) are described in the [README template](_TEMPLATE/Internal%20Control/README.md).

## Rules

- The folder is `Automation/<vendor>/<name>/`, with `-` instead of `–` in the name. There are no folders per eramba section.
- `vendor` is the vendor of one of its technologies.
- An Internal Controls automation lists its control titles in `controls`, and `frameworks` must match the mapping for them.
- READMEs without front matter (vendor guides such as `AWS/README.md`), `_TEMPLATE`, `_shared`, `docs`, `skills` and `tools` are not indexed.

## Example

```yaml
---
id: entra-mfa-coverage
name: Multi-Factor Authentication Coverage Review
vendor: Microsoft
technology:
  - Microsoft Entra ID
eramba_module: Internal Controls
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
pip3 install pyyaml
python3 Automation/tools/build_catalogue.py          # validate and regenerate
python3 Automation/tools/build_catalogue.py --check  # validate only; fails if the catalogue is stale
```
