#!/usr/bin/env python3
"""Build the automation catalogue from the front matter of every README.md.

Reads the YAML front matter of each automation README under Automation/,
validates it against Automation/metadata-vocabulary.yml and the control
mapping CSV, and writes:

  Automation/catalogue.json  machine-readable index (one entry per automation)
  Automation/CATALOGUE.md    the same index grouped by vendor, eramba section and framework

`frameworks` is not written in the READMEs: it comes from `controls` and the
mapping CSV, and is added to the catalogue here.

Usage:
  python3 Automation/tools/build_catalogue.py          # validate and write
  python3 Automation/tools/build_catalogue.py --check  # validate; fail if outputs are stale

Requires PyYAML (pip3 install pyyaml).
"""
from __future__ import annotations

import csv
import json
import sys
from pathlib import Path
from urllib.parse import quote

import yaml

AUTOMATION = Path(__file__).resolve().parent.parent
REPO = AUTOMATION.parent
VOCABULARY = AUTOMATION / "metadata-vocabulary.yml"
MAPPING = REPO / "GRC Templates" / "LLM - GRC Templates" / "Controls" / "mapping_controls_to_requirements.csv"
OUT_JSON = AUTOMATION / "catalogue.json"
OUT_MD = AUTOMATION / "CATALOGUE.md"
NOT_INDEXED = ("_TEMPLATE", "_shared", "docs", "skills", "tools")

REQUIRED = ["id", "name", "status", "vendor", "technology", "eramba_module"]
LISTS = ["technology", "domains", "controls", "policies"]
DERIVED = ["frameworks"]  # computed here; not allowed in a README
EXPORTED = [
    "id", "name", "version", "status", "vendor", "technology", "eramba_module",
    "domains", "controls", "frameworks", "policies", "audit_frequency", "trigger", "guide",
]


def front_matter(path: Path) -> dict | None:
    text = path.read_text(encoding="utf-8").replace("\r\n", "\n")
    if not text.startswith("---\n"):
        return None
    end = text.find("\n---\n", 4)
    if end < 0:
        raise ValueError("front matter is not closed with ---")
    data = yaml.safe_load(text[4:end]) or {}
    if not isinstance(data, dict):
        raise ValueError("front matter is not a mapping")
    return data


def control_frameworks(known: list[str]) -> tuple[dict[str, list[str]], list[str]]:
    with MAPPING.open(encoding="utf-8", newline="") as fh:
        reader = csv.DictReader(fh)
        columns = [c for c in reader.fieldnames or [] if c != "Control Title"]
        mapping = {
            r["Control Title"]: [c for c in columns if (r.get(c) or "").strip() not in ("", "-", "—")]
            for r in reader
        }
    unknown = [c for c in columns if c not in known]
    errors = [f"{MAPPING.name}: framework {c!r} is not in metadata-vocabulary.yml" for c in unknown]
    return mapping, errors


def collect(vocab: dict) -> tuple[list[dict], list[str]]:
    tech_vendor = vocab["technologies"]
    allowed = {"technology": set(tech_vendor), "domains": set(vocab["domains"])}
    mapping, errors = control_frameworks(vocab["frameworks"])
    entries, seen = [], {}

    for tech, vendor in tech_vendor.items():
        if vendor not in vocab["vendors"]:
            errors.append(f"metadata-vocabulary.yml: technology {tech!r} has vendor {vendor!r}, which is not in `vendors`")

    for run in sorted(AUTOMATION.rglob("run.php")):
        folder = run.parent.relative_to(AUTOMATION).parts
        if folder[0] in NOT_INDEXED:
            continue
        readme = run.parent / "README.md"
        if not readme.exists() or front_matter(readme) is None:
            errors.append(f"{run.parent.relative_to(REPO)}: has run.php but its README.md has no front matter")

    for readme in sorted(AUTOMATION.rglob("README.md")):
        rel = readme.relative_to(REPO)
        folder = readme.parent.relative_to(AUTOMATION).parts
        if folder and folder[0] in NOT_INDEXED:
            continue
        try:
            meta = front_matter(readme)
        except (ValueError, yaml.YAMLError) as exc:
            errors.append(f"{rel}: {exc}")
            continue
        if meta is None:
            continue  # vendor guide, not an automation

        def err(msg: str) -> None:
            errors.append(f"{rel}: {msg}")

        for key in REQUIRED:
            if not meta.get(key):
                err(f"missing `{key}`")
        for key in DERIVED:
            if key in meta:
                err(f"remove `{key}`: build_catalogue.py derives it (see METADATA.md)")
        for key in LISTS:
            if key in meta and not isinstance(meta[key], list):
                err(f"`{key}` must be a list")
        for key, values in allowed.items():
            for value in meta.get(key) or []:
                if value not in values:
                    err(f"`{key}` value {value!r} is not in metadata-vocabulary.yml")
        for key, vocab_key in (("vendor", "vendors"), ("eramba_module", "eramba_modules"), ("status", "statuses")):
            if meta.get(key) is not None and meta.get(key) not in vocab[vocab_key]:
                err(f"`{key}` {meta.get(key)!r} is not in metadata-vocabulary.yml")

        tech_vendors = {tech_vendor[t] for t in meta.get("technology") or [] if t in tech_vendor}
        if tech_vendors and meta.get("vendor") not in tech_vendors:
            err(f"`vendor` must be the vendor of one of its technologies: {sorted(tech_vendors)}")
        expected_folder = (str(meta.get("vendor")), str(meta.get("name", "")).replace("–", "-"))
        if folder != expected_folder:
            hint = " The difference is only upper/lower case: rename the folder in two steps (A -> tmp -> B)." \
                if "/".join(folder).lower() == "/".join(expected_folder).lower() else ""
            err(f"must live in Automation/{expected_folder[0]}/{expected_folder[1]}/ (`vendor`, then `name` with - for –).{hint}")

        if meta.get("id") in seen:
            err(f"duplicate id {meta['id']!r} (also in {seen[meta['id']]})")
        seen[meta.get("id")] = rel

        module = meta.get("eramba_module")
        frameworks: list[str] = []
        if module == "Internal Controls":
            if not meta.get("controls"):
                err("internal control automations need `controls`")
            if meta.get("audit_frequency") not in vocab["audit_frequencies"]:
                err(f"`audit_frequency` must be one of {list(vocab['audit_frequencies'])}")
            for control in meta.get("controls") or []:
                if control not in mapping:
                    err(f"control {control!r} is not in {MAPPING.name}")
                    continue
                frameworks += [f for f in mapping[control] if f not in frameworks]
        elif meta.get("audit_frequency") is not None:
            err("`audit_frequency` is only for Internal Controls")

        derived = dict(meta)
        derived["frameworks"] = [f for f in vocab["frameworks"] if f in frameworks]
        entry = {k: derived[k] for k in EXPORTED if derived.get(k) not in (None, [], "")}
        entry["path"] = readme.parent.relative_to(REPO).as_posix()
        entries.append(entry)

    entries.sort(key=lambda e: (str(e.get("vendor", "")).lower(), e.get("name", ""), e.get("id", "")))
    return entries, errors


def link(entry: dict) -> str:
    rel = Path(entry["path"]).relative_to("Automation").as_posix()
    return f"[{entry['name']}]({quote(rel)}/)"


def status(entry: dict) -> str:
    return " ".join(str(entry[k]) for k in ("version", "status") if entry.get(k)) or "–"


def use(entry: dict) -> str:
    """What the automation is for, in one cell."""
    if entry.get("controls"):
        return "Control: " + ", ".join(entry["controls"])
    return entry["eramba_module"]


def render_md(entries: list[dict], vocab: dict) -> str:
    tech_vendor = vocab["technologies"]
    out = [
        "# Automation catalogue",
        "",
        "<!-- Generated by Automation/tools/build_catalogue.py from the README front matter. Do not edit by hand. -->",
        "",
        "Every automation, grouped by the vendor and technology it connects to, by eramba section, and by framework. "
        "An automation that uses technologies of several vendors appears under each one. The same data is in "
        "[catalogue.json](catalogue.json); the fields are described in [METADATA.md](METADATA.md).",
        "",
        "## By vendor and technology",
        "",
    ]
    vendors = sorted({tech_vendor[t] for e in entries for t in e["technology"]}, key=str.lower)
    for vendor in vendors:
        out += [f"### {vendor}", "", "| Technology | Automation | eramba section | Use | Status |", "|---|---|---|---|---|"]
        rows = [(t, e) for e in entries for t in e["technology"] if tech_vendor[t] == vendor]
        for tech, e in sorted(rows, key=lambda r: (r[0].lower(), r[1]["name"])):
            out.append(f"| {tech} | {link(e)} | {e['eramba_module']} | {use(e)} | {status(e)} |")
        out.append("")

    out += ["## By eramba section", ""]
    for module, description in vocab["eramba_modules"].items():
        matches = sorted((e for e in entries if e["eramba_module"] == module), key=lambda e: (e["name"], e["id"]))
        if not matches:
            continue
        out += [f"### {module}", "", description, ""]
        out += [f"- {link(e)} – {', '.join(e['technology'])}" for e in matches] + [""]

    out += ["## By framework", "", "Internal control automations whose control maps to each framework.", ""]
    for framework in vocab["frameworks"]:
        matches = sorted((e for e in entries if framework in e.get("frameworks", [])), key=lambda e: (e["name"], e["id"]))
        if matches:
            out += [f"### {framework}", ""] + [f"- {link(e)} – {', '.join(e['technology'])}" for e in matches] + [""]
    return "\n".join(out)


def main() -> int:
    check = "--check" in sys.argv[1:]
    vocab = yaml.safe_load(VOCABULARY.read_text(encoding="utf-8"))
    entries, errors = collect(vocab)
    if errors:
        print("Metadata errors:", *errors, sep="\n  ", file=sys.stderr)
        return 1
    outputs = {
        OUT_JSON: json.dumps({"automations": entries}, indent=2, ensure_ascii=False) + "\n",
        OUT_MD: render_md(entries, vocab),
    }
    stale = [p for p, text in outputs.items() if not p.exists() or p.read_text(encoding="utf-8") != text]
    if check:
        if stale:
            print("Out of date, run build_catalogue.py:", *[p.relative_to(REPO) for p in stale], sep="\n  ", file=sys.stderr)
            return 1
        print(f"OK: {len(entries)} automations")
        return 0
    for path in stale:
        path.write_text(outputs[path], encoding="utf-8")
    print(f"{len(entries)} automations; wrote {len(stale)} file(s)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
