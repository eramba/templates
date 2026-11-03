# Eramba Automation Examples

PHP automations for eramba: account review feeds, internal control audits, the automations built in eramba's tutorial courses, and small examples of the automation functions.

## Layout

Every automation lives in `Automation/<Vendor>/<Automation name>/`, with its `run.php` and a `README.md`. There are no folders per eramba section: the front matter of each README says which technologies it uses, which eramba section it is attached to (`eramba_module`) and, for internal controls, which `controls` it tests. The catalogue adds the frameworks each control covers. See [Automation metadata](METADATA.md).

| Folder | Contents |
|---|---|
| [Anthropic](Anthropic/), [Atlassian](Atlassian/), [AWS](AWS/), [GitHub](GitHub/), [Google](Google/), [Linear](Linear/), [Microsoft](Microsoft/), [Okta](Okta/), [OpenAI](OpenAI/), [Salesforce](Salesforce/), [ServiceNow](ServiceNow/), [Slack](Slack/), [Workday](Workday/), [Zoom](Zoom/) | Automations that connect to that vendor's products. |
| [eramba](eramba/) | Automations that only use eramba (or eramba plus an optional AI provider), and the function examples. |
| [_TEMPLATE](_TEMPLATE/) | PHP and README templates for a new internal control or account review automation. |
| [_shared](_shared/) | Code blocks reused by several automations and copied into each `run.php` by `tools/sync.php`. Users never install it: every `run.php` stays a single file to paste into eramba. |
| [docs](docs/) | Installation and writing guides, and the guides that span several automations. |
| [skills](skills/) | Agent skill to write new internal-control automations. |
| [tools](tools/) | Maintenance tools: shared-block sync and the catalogue generator. |

## Find an automation

- [Catalogue by vendor and technology](CATALOGUE.md), also as [catalogue.json](catalogue.json) for indexing. Both are generated from the README front matter.
- [Account Reviews](docs/account-reviews.md): how account review feeds work, review types and the feed catalogue.
- [Internal control automations](docs/internal-controls.md): install steps, status and recommended audit frequency.
- [Online Assessments – Advanced Configurations](docs/online-assessments-tutorial.md): the three automations of that course (supplier onboarding, assessment launch and the review of submitted assessments).
- [Security Incident Management](docs/security-incidents-tutorial.md): the automation of that course (a Jira issue for each urgent incident).
- [Function examples](docs/function-examples.md): PHP 8.4 single-execution examples for Online Assessments (edit, add, comment, attachment) and writing a file.

## Using a function example

The other automations have their own README with setup steps.

1. Open the `eramba/Example - …` folder that matches the function you want to test.
2. Copy the contents of `run.php` into the Eramba automation editor.
3. Replace macros and placeholder values.
4. Test from Eramba against an Online Assessment item.

Scripts that call Eramba macros need Eramba to run. Local PHP is only useful for syntax checks.

## Adding an automation

Put it in `<Vendor>/<Automation name>/`, fill the front matter as described in [Automation metadata](METADATA.md), and run `python3 Automation/tools/build_catalogue.py` to validate it and regenerate the catalogue.
