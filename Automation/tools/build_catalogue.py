#!/usr/bin/env python3
"""Build the automation catalogue from the front matter of every README.md.

Reads the YAML front matter of each automation README under Automation/,
validates it against Automation/metadata-vocabulary.yml and the control
mapping CSV, and writes:

  Automation/catalogue.json  machine-readable index (one entry per automation)
  Automation/CATALOGUE.md    the same index grouped by vendor and technology

Usage:
  python3 Automation/tools/build_catalogue.py          # validate and write
  python3 Automation/tools/build_catalogue.py --check  # validate; fail if outputs are stale

Requires PyYAML (pip install pyyaml).
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

REQUIRED = ["id", "name", "vendor", "technology", "eramba_module", "vendors", "technologies", "use_cases"]
LISTS = ["vendors", "technologies", "use_cases", "domains", "frameworks", "controls", "policies"]
EXPORTED = [
    "id", "name", "version", "status", "vendors", "technologies", "use_cases", "domains",
    "frameworks", "eramba_module", "controls", "policies", "trigger", "audit_frequency", "guide",
    "vendor", "technology",
]


def front_matter(path: Path) -> dict | None:
    text = path.read_text(encoding="utf-8")
    if not text.startswith("---\n"):
        return None
    end = text.find("\n---\n", 4)
    if end < 0:
        raise ValueError("front matter is not closed with ---")
    data = yaml.safe_load(text[4:end]) or {}
    if not isinstance(data, dict):
        raise ValueError("front matter is not a mapping")
    return data


def control_frameworks() -> dict[str, list[str]]:
    with MAPPING.open(encoding="utf-8", newline="") as fh:
        rows = csv.DictReader(fh)
        return {
            r["Control Title"]: [k for k, v in r.items() if k != "Control Title" and (v or "").strip() not in ("", "-", "—")]
            for r in rows
        }


def collect() -> tuple[list[dict], list[str]]:
    vocab = yaml.safe_load(VOCABULARY.read_text(encoding="utf-8"))
    allowed = {
        "use_cases": set(vocab["use_cases"]),
        "domains": set(vocab["domains"]),
        "frameworks": set(vocab["frameworks"]),
        "vendors": set(vocab["vendors"]),
        "technologies": set(vocab["technologies"]),
    }
    tech_vendor = vocab["technologies"]
    mapping = control_frameworks()
    entries, errors, seen = [], [], {}

    for readme in sorted(AUTOMATION.rglob("README.md")):
        rel = readme.relative_to(REPO)
        if readme.relative_to(AUTOMATION).parts[0] in ("_TEMPLATE", "docs", "skills", "tools"):
            continue
        try:
            meta = front_matter(readme)
        except (ValueError, yaml.YAMLError) as exc:
            errors.append(f"{rel}: {exc}")
            continue
        if meta is None:
            continue  # section README, not an automation

        def err(msg: str) -> None:
            errors.append(f"{rel}: {msg}")

        for key in REQUIRED:
            if not meta.get(key):
                err(f"missing `{key}`")
        for key in LISTS:
            if key in meta and not isinstance(meta[key], list):
                err(f"`{key}` must be a list")
        for key, values in allowed.items():
            for value in meta.get(key) or []:
                if value not in values:
                    err(f"`{key}` value {value!r} is not in metadata-vocabulary.yml")

        tech_vendors = {tech_vendor[t] for t in meta.get("technologies") or [] if t in tech_vendor}
        listed = set(meta.get("vendors") or [])
        if not (tech_vendors - {"eramba"} <= listed <= tech_vendors) or not listed:
            err(f"`vendors` must be the vendors of its technologies ({sorted(tech_vendors)}); eramba is optional")

        folder = readme.parent.relative_to(AUTOMATION).parts
        if len(folder) != 2 or folder[0] != meta.get("vendor") or folder[0] not in listed:
            err("must live in Automation/<vendor>/<name>/, where <vendor> is its `vendor` and is listed in `vendors`")

        if meta.get("id") in seen:
            err(f"duplicate id {meta['id']!r} (also in {seen[meta['id']]})")
        seen[meta.get("id")] = rel

        if "internal-controls" in (meta.get("use_cases") or []):
            if not meta.get("controls"):
                err("internal-controls automations need `controls`")
            expected: list[str] = []
            for control in meta.get("controls") or []:
                if control not in mapping:
                    err(f"control {control!r} is not in {MAPPING.name}")
                    continue
                expected += [f for f in mapping[control] if f not in expected]
            if set(expected) != set(meta.get("frameworks") or []):
                err(f"`frameworks` must match {MAPPING.name}: {expected}")
            if expected and "compliance" not in meta["use_cases"]:
                err("controls mapped to frameworks are also `compliance`")

        entry = {k: meta[k] for k in EXPORTED if meta.get(k) not in (None, [], "")}
        entry["path"] = readme.parent.relative_to(REPO).as_posix()
        entries.append(entry)

    entries.sort(key=lambda e: (e["vendors"][0].lower() if e.get("vendors") else "", e["name"], e["id"]))
    return entries, errors


def link(entry: dict) -> str:
    rel = Path(entry["path"]).relative_to("Automation").as_posix()
    return f"[{entry['name']}]({quote(rel)}/)"


def render_md(entries: list[dict]) -> str:
    vocab = yaml.safe_load(VOCABULARY.read_text(encoding="utf-8"))
    tech_vendor = vocab["technologies"]
    out = [
        "# Automation catalogue by vendor and technology",
        "",
        "<!-- Generated by Automation/tools/build_catalogue.py from the README front matter. Do not edit by hand. -->",
        "",
        "Every automation, grouped by the vendor and technology it connects to. An automation that uses several "
        "vendors appears under each one, next to the technology it uses from that vendor. The same data is in [catalogue.json](catalogue.json); "
        "the fields are described in [METADATA.md](METADATA.md).",
        "",
        "## By vendor and technology",
        "",
    ]
    vendors = sorted({v for e in entries for v in e.get("vendors", [])}, key=str.lower)
    for vendor in vendors:
        out += [f"### {vendor}", "", "| Technology | Automation | Use cases | eramba module | Status |", "|---|---|---|---|---|"]
        rows = []
        for e in entries:
            if vendor not in e.get("vendors", []):
                continue
            for tech in e["technologies"]:
                if tech_vendor[tech] == vendor:
                    rows.append((tech, e))
        for tech, e in sorted(rows, key=lambda r: (r[0].lower(), r[1]["name"])):
            status = " ".join(str(e[k]) for k in ("version", "status") if e.get(k)) or "–"
            out.append(f"| {tech} | {link(e)} | {', '.join(e['use_cases'])} | {e['eramba_module']} | {status} |")
        out.append("")

    out += ["## By use case", ""]
    for use_case, description in vocab["use_cases"].items():
        matches = [e for e in entries if use_case in e["use_cases"]]
        out += [f"### {use_case}", "", description, ""]
        if not matches:
            out += ["No automations yet.", ""]
            continue
        for e in sorted(matches, key=lambda e: (e["name"], e["id"])):
            out.append(f"- {link(e)} – {', '.join(e['technologies'])}")
        out.append("")
    return "\n".join(out)


def main() -> int:
    check = "--check" in sys.argv[1:]
    entries, errors = collect()
    if errors:
        print("Metadata errors:", *errors, sep="\n  ", file=sys.stderr)
        return 1
    outputs = {
        OUT_JSON: json.dumps({"automations": entries}, indent=2, ensure_ascii=False) + "\n",
        OUT_MD: render_md(entries),
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
