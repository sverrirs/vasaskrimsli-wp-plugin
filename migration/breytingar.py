"""Later name decisions (data version 5 and up), bundled as features/ordsifjar/data/breytingar-<N>.json.

Live sites already have what versions 3 and 4 wrote (nofn-2026.json, then thyding-2026.json), plus every earlier
breytingar-<M>.json. This script compares lokalisti.json with that released state and writes, for each changed
field, the text the site should still show ("fyrri") and the new one ("nytt"). Nofn::plan_changes() changes a
field only where the site still shows "fyrri", so anything edited in wp-admin is kept.

  python -X utf8 migration/breytingar.py <N> [path/to/lokalisti.json]
N is the new Upgrade::VERSION (5 for the first one). Released files (nofn-2026.json, thyding-2026.json and
breytingar-<M>.json for M < N) must never be edited: they are the baseline.
Then run the tests ("E:\\SDKs\\php\\8.3.7\\php.exe" tests/run.php), bump the version and release.
"""

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "features" / "ordsifjar" / "data"
DEFAULT_SOURCE = ROOT.parent / "vasaskrimsli-dict" / "ideas" / "lokalisti" / "lokalisti.json"
ALLOWED = {"strong", "em", "br", "sup", "sub", "a"}  # Must match Sanitize::allowed_html()
SOURCE_FIELD = {"nafn": "islenska", "uppruni": "upprunaord_html", "thyding": "thyding_html"}


def plain(s):
    return re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", (s or "").replace("&amp;", "&"))).strip()


def released(version):
    """Númer -> {nafn, uppruni, thyding} as the released data versions 3 .. version-1 left it."""
    state = {r["numer"]: {f: r[f] for f in SOURCE_FIELD} for r in json.loads((DATA / "nofn-2026.json").read_text(encoding="utf-8"))}
    for r in json.loads((DATA / "thyding-2026.json").read_text(encoding="utf-8")):
        state[r["numer"]]["thyding"] = r["thyding"]
    for m in range(5, version):
        for r in json.loads((DATA / f"breytingar-{m}.json").read_text(encoding="utf-8")):
            state[r["numer"]].update(r["nytt"])
    return state


def main():
    version = int(sys.argv[1])
    source = Path(sys.argv[2]) if len(sys.argv) > 2 else DEFAULT_SOURCE
    if version < 5:
        sys.exit("N must be 5 or more (versions 3 and 4 are released)")
    out_path = DATA / f"breytingar-{version}.json"
    rows = {int(r["numer"]): r for r in json.loads(source.read_text(encoding="utf-8"))}
    out, problems = [], []
    for n, old in sorted(released(version).items()):
        r = rows.get(n)
        if not r:
            problems.append(f"#{n}: released but not in {source.name}")
            continue
        fyrri, nytt = {}, {}
        for f, src in SOURCE_FIELD.items():
            new = (r.get(src) or "").strip()
            if f == "nafn":
                new = new.replace("&", "&amp;")
            if plain(new) and plain(new) != plain(old[f]):
                fyrri[f], nytt[f] = old[f], new
        if not nytt:
            continue
        bad = sorted({t.lower() for v in nytt.values() for t in re.findall(r"</?\s*([a-zA-Z][\w-]*)", v)} - ALLOWED)
        if bad:
            problems.append(f"#{n}: tags the plugin strips: {bad}")
        out.append({"numer": n, "fyrri": fyrri, "nytt": nytt})
    if problems:
        for p in problems:
            print("ERROR:", p)
        sys.exit(f"{len(problems)} problems; {out_path.name} not written")
    out_path.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    for row in out:
        print(f"#{row['numer']}: " + ", ".join(f"{f} {plain(row['fyrri'][f])[:30]!r} -> {plain(row['nytt'][f])[:30]!r}" for f in row["nytt"]))
    print(f"{out_path.relative_to(ROOT)}: {len(out)} changed entries")


if __name__ == "__main__":
    main()
