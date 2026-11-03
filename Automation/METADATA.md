# Automation metadata

Every automation README starts with a YAML front matter block. It describes the automation so it can be indexed by vendor, technology, eramba section, use and framework. Each fact is written once: anything that can be derived is computed by [the catalogue script](tools/build_catalogue.py) and only appears in [CATALOGUE.md](CATALOGUE.md) and [catalogue.json](catalogue.json).

## Indexing fields

| Field | Written in the README | Meaning |
|---|---|---|
| `vendor` | always | Main vendor. It is also the folder the automation lives in: `Automation/<vendor>/<name>/`. |
| `technology` | always, list | Products or APIs it reads or acts on, with the names in the vocabulary. Each belongs to one vendor, so an automation that uses several vendors (for example eramba API plus OpenAI API) is found under each of them. |
| `eramba_module` | always | eramba section the automation is attached to: where it is created and what triggers it. Account Review Feeds, Internal Controls, Third Parties, Online Assessments, Security Incidents, or Any. |
| `review_family` | Account Review Feeds only | What the feed sends: `Account review` (one row per account), `Permission review` (one row per group, policy or application) or `Employee list` (current employees, for Exit reviews). |
| `controls` | Internal Controls only, list | Exact titles of the controls it tests, as in [the control mapping](../GRC%20Templates/LLM%20-%20GRC%20Templates/Controls/mapping_controls_to_requirements.csv). |
| `audit_frequency` | Internal Controls only | Recommended audit frequency: `weekly`, `twice-monthly`, `monthly`, `quarterly` or `annually`. |
| `domains` | optional, list | Security topic, such as `identity-access` or `vulnerability-management`. |
| `status` | always | `draft`, `tested`, `stable`, `deprecated`, or `example` for the function examples. |

What the automation is for is therefore: `review_family` for an account review feed, `controls` for an internal control, and the name for the rest (one automation each).

### Derived fields (do not write them)

| Field | Comes from |
|---|---|
| `frameworks` | `controls` and the control mapping. This is what to index for compliance. |
| `feed_type` | `review_family`: the eramba feed type to choose (*List of accounts to be reviewed* or *List of current employees*). |

The script fails if a README sets either of them.

The allowed values of every indexing field are in [metadata-vocabulary.yml](metadata-vocabulary.yml). To use a new value, add it there first.

The other fields (`id`, `name`, `version`, `trigger`, `policies`, `guide`, `secrets`, `variables`, `dependencies`, `timeout_seconds`, `eramba_version_tested`, `last_tested`) are described in the [README template](_TEMPLATE/Internal%20Control/README.md). `id`, `name`, `status`, `vendor`, `technology` and `eramba_module` are required.

## Rules

- The folder is `Automation/<vendor>/<name>/`, with `-` instead of `–` in the name. There are no folders per eramba section.
- `vendor` is the vendor of one of its technologies.
- Keys go in this order: `id`, `name`, `version`, `status`, `vendor`, `technology`, `eramba_module`, then the rest.
- Every folder with a `run.php` must have a README with front matter. READMEs without front matter (vendor guides such as `AWS/README.md`), `_TEMPLATE`, `_shared`, `docs`, `skills` and `tools` are not indexed.

## Examples

```yaml
---
id: entra-mfa-coverage
name: Multi-Factor Authentication Coverage Review
version: 0.1.1
status: draft
vendor: Microsoft
technology:
  - Microsoft Entra ID
eramba_module: Internal Controls
domains:
  - identity-access
controls:
  - Multi-Factor Authentication Coverage Review
audit_frequency: annually
# ...
---
```

```yaml
---
id: workday-current-employees
name: Workday – Current employees
version: 0.1.1
status: draft
vendor: Workday
technology:
  - Workday HCM
eramba_module: Account Review Feeds
review_family: Employee list
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

On macOS a folder rename that only changes upper/lower case (for example `Github` to `GitHub`) is not applied on disk. Rename it in two steps (`Github` → `tmp` → `GitHub`); the script points this out when it happens.
