"""Lýsing texts (what each monster looks like and does, so its name makes sense) into data version N.

Reads vasaskrimsli-dict ideas/lysing-2026/kynslod-*.json [{numer, nafn, lysing}] (plain text, *italic* allowed) and
adds a "lysing" field change to features/ordsifjar/data/breytingar-<N>.json for every entry whose text differs from
what the released versions left ("" before the first one). Rows already in the file (from migration/breytingar.py)
are kept; run that script first. Nofn::plan_changes() fills the field only where the site still shows that released
text, so a Lýsing written or edited in wp-admin is kept.

  python -X utf8 migration/lysing.py <N> [path/to/vasaskrimsli-dict]
"""

import html
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "features" / "ordsifjar" / "data"
DEFAULT_DICT = ROOT.parent / "vasaskrimsli-dict"


def to_html(text):
    s = html.escape(re.sub(r"\s+", " ", text).strip(), quote=False)
    return re.sub(r"(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])", r"<em>\1</em>", s)


def key(numer):
    """Númer as the data files write it: 26 as a number, "351a" as text."""
    return int(numer) if str(numer).isdigit() else str(numer)


def main():
    version = int(sys.argv[1])
    base = Path(sys.argv[2]) if len(sys.argv) > 2 else DEFAULT_DICT
    released = {}
    for m in range(5, version):
        for r in json.loads((DATA / f"breytingar-{m}.json").read_text(encoding="utf-8")):
            if "lysing" in r.get("nytt", {}):
                released[str(r["numer"])] = r["nytt"]["lysing"]
    out_path = DATA / f"breytingar-{version}.json"
    rows = json.loads(out_path.read_text(encoding="utf-8")) if out_path.exists() else []
    by = {str(r["numer"]): r for r in rows}
    added, problems = 0, []
    for f in sorted((base / "ideas" / "lysing-2026").glob("kynslod-*.json")):
        for e in json.loads(f.read_text(encoding="utf-8")):
            text = (e.get("lysing") or "").strip()
            if not text:
                problems.append(f"#{e['numer']}: no text in {f.name}")
                continue
            if "—" in text or "–" in text:
                problems.append(f"#{e['numer']}: dash in text")
            new, old = to_html(text), released.get(str(e["numer"]), "")
            if new == old:
                continue
            r = by.get(str(e["numer"]))
            if not r:
                r = by[str(e["numer"])] = {"numer": key(e["numer"]), "fyrri": {}, "nytt": {}}
                rows.append(r)
            r["fyrri"]["lysing"], r["nytt"]["lysing"] = old, new
            added += 1
    rows.sort(key=lambda r: (int(re.match(r"\d+", str(r["numer"])).group()), str(r["numer"])))
    out_path.write_text(json.dumps(rows, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"{out_path.relative_to(ROOT)}: {added} Lýsing texts, {len(rows)} rows")
    if problems:
        sys.exit("VILLA:\n  " + "\n  ".join(problems))


if __name__ == "__main__":
    main()
