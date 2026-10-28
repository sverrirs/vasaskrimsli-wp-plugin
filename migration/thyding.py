"""Data version 4: the rewritten Þýðing texts, bundled as features/ordsifjar/data/thyding-2026.json.

Version 3 (nofn-2026.json, released in 0.3.0/1.0.0) already wrote names and texts on live sites. This file
carries, for every entry whose Þýðing was rewritten afterwards, the text version 3 wrote ("fyrri", taken from
nofn-2026.json, which must stay as released) and the new one ("thyding", from lokalisti.json). Nofn::plan_texts()
replaces the text only where a site still shows "fyrri", so text edited in wp-admin is kept.

  python -X utf8 migration/thyding.py [path/to/lokalisti.json]
Default path: ../vasaskrimsli-dict/ideas/lokalisti/lokalisti.json
Then run the tests ("E:\\SDKs\\php\\8.3.7\\php.exe" tests/run.php).
"""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "features" / "ordsifjar" / "data"
OUT = DATA / "thyding-2026.json"
DEFAULT_SOURCE = ROOT.parent / "vasaskrimsli-dict" / "ideas" / "lokalisti" / "lokalisti.json"
ALLOWED = {"strong", "em", "br", "sup", "sub", "a"}  # Must match Sanitize::allowed_html()


def plain(s):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", s or "")).strip()


def main():
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_SOURCE
    rows = {int(r["numer"]): r for r in json.loads(source.read_text(encoding="utf-8"))}
    v3 = json.loads((DATA / "nofn-2026.json").read_text(encoding="utf-8"))
    out, problems = [], []
    for old in v3:
        n = old["numer"]
        r = rows.get(n)
        if not r:
            problems.append(f"#{n}: in nofn-2026.json but not in {source.name}")
            continue
        new = (r.get("thyding_html") or "").strip()
        if not plain(new) or plain(new) == plain(old["thyding"]):
            continue
        if r["islenska"] != old["nafn"].replace("&amp;", "&"):
            problems.append(f"#{n}: name {r['islenska']!r} differs from version 3 {old['nafn']!r}; needs a name step, not a text step")
            continue
        bad = sorted({t.lower() for t in re.findall(r"</?\s*([a-zA-Z][\w-]*)", new)} - ALLOWED)
        if bad:
            problems.append(f"#{n}: tags the plugin strips: {bad}")
        out.append({"numer": n, "nafn": old["nafn"], "fyrri": old["thyding"], "thyding": new})
    if problems:
        for p in problems:
            print("ERROR:", p)
        sys.exit(f"{len(problems)} problems; {OUT.name} not written")
    OUT.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"{OUT.relative_to(ROOT)}: {len(out)} rewritten texts of {len(v3)} version 3 rows")


if __name__ == "__main__":
    main()
