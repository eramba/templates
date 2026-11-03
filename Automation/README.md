# Eramba Automation Examples

PHP automations for eramba: internal control audits, the automations built in eramba's tutorial courses, and small examples of the automation functions.

## Layout

Every automation lives in `Automation/<Vendor>/<Automation name>/`, with its `run.php` and a `README.md`. There are no folders per eramba section or use case: the front matter of each README says what it is for (`use_cases`: account reviews, internal controls, compliance, third-party risk…), which eramba section it runs in (`eramba_module`) and which technologies it uses. See [Automation metadata](METADATA.md).

| Folder | Contents |
|---|---|
| [AWS](AWS/), [Atlassian](Atlassian/), [Google](Google/), [Linear](Linear/), [Microsoft](Microsoft/), [Zoom](Zoom/) | Automations that connect to that vendor's products. |
| [eramba](eramba/) | Automations that only use eramba (or eramba plus an optional AI provider), and the function examples. |
| [_TEMPLATE](_TEMPLATE/) | PHP and README template for a new automation. |
| [docs](docs/) | Installation and writing guides, and the guides that span several automations. |
| [skills](skills/) | Agent skill to write new internal-control automations. |
| [tools](tools/) | Catalogue generator and metadata validator. |

## Find an automation

- [Catalogue by vendor and technology](CATALOGUE.md), also as [catalogue.json](catalogue.json) for indexing. Both are generated from the README front matter.
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
