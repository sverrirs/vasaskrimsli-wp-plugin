"""The 2026 name decisions, bundled with the plugin as features/ordsifjar/data/nofn-2026.json.

Reads lokalisti.json from the vasaskrimsli-dict repo (one row per Pokémon #252-1025) and keeps only
what the plugin's data update (Upgrade step 4, Nofn.php) needs:

  stada "nýtt"   (gen 4-9): numer, kynslod, nafn, uppruni, thyding  -> fills fields that are still "–"
  stada "breytt" (gen 3, and #233 in gen 2): the same plus fyrra_nafn -> replaces the entry only if its
                                                                        name is still fyrra_nafn
  stada "óbreytt":          left out (nothing to do)

HTML comes from upprunaord_html / thyding_html (the published style). Every value is checked against
the plugin's allow-list (strong, em, br, sup, sub, a), the numbers against national.json, and each
fyrra_nafn against the published name in seed.csv (a mismatch means the update would skip that entry).

Run again whenever the list changes:
  python -X utf8 migration/nofn.py [path/to/lokalisti.json]
Default path: ../vasaskrimsli-dict/ideas/lokalisti/lokalisti.json
Then run the tests ("E:\\SDKs\\php\\8.3.7\\php.exe" tests/run.php), bump the version and release.
"""

import csv
import html
import io
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
DATA = ROOT / "features" / "ordsifjar" / "data"
OUT = DATA / "nofn-2026.json"
DEFAULT_SOURCE = ROOT.parent / "vasaskrimsli-dict" / "ideas" / "lokalisti" / "lokalisti.json"
ALLOWED = {"strong", "em", "br", "sup", "sub", "a"}  # Must match Sanitize::allowed_html()


def markdown_to_html(text):
    """Fallback for rows without *_html: **bold**, *italics*, [text](url), line breaks."""
    s = html.escape(text.strip(), quote=False)
    s = re.sub(r"\[([^\]]+)\]\((https?://[^)\s]+)\)", lambda m: f'<a href="{html.escape(m.group(2))}">{m.group(1)}</a>', s)
    s = re.sub(r"\*\*(.+?)\*\*", r"<strong>\1</strong>", s)
    s = re.sub(r"(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])", r"<em>\1</em>", s)
    return re.sub(r"\s*\n\s*", "<br>", s)


def bad_tags(value):
    return sorted({t.lower() for t in re.findall(r"</?\s*([a-zA-Z][\w-]*)", value)} - ALLOWED)


def published_names():
    """Númer -> Nafn (plain text) from the original page, as seeded."""
    text = (DATA / "seed.csv").read_text(encoding="utf-8-sig")
    return {r["numer"]: html.unescape(re.sub(r"<[^>]+>", "", r["nafn_html"])).strip() for r in csv.DictReader(io.StringIO(text))}


def main():
    source = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_SOURCE
    rows = json.loads(source.read_text(encoding="utf-8"))
    national = {e["numer"]: e for e in json.loads((DATA / "national.json").read_text(encoding="utf-8"))}
    published = published_names()

    out, problems, warnings = [], [], []
    counts = {"nýtt": 0, "breytt": 0, "óbreytt": 0}
    drog = 0
    for r in sorted(rows, key=lambda r: int(r["numer"])):
        n, stada = int(r["numer"]), r.get("stada")
        where = f"#{n} {r.get('english', '')}".strip()
        if stada not in counts:
            problems.append(f"{where}: unknown stada {stada!r}")
            continue
        counts[stada] += 1
        if n not in national:
            problems.append(f"{where}: not in national.json")
            continue
        if int(r["kynslod"]) != national[n]["kynslod"]:
            problems.append(f"{where}: kynslod {r['kynslod']} but national.json says {national[n]['kynslod']}")
        if stada == "óbreytt":
            continue

        nafn = (r.get("islenska") or "").strip()
        if not nafn:
            problems.append(f"{where}: no islenska")
            continue
        uppruni = (r.get("upprunaord_html") or "").strip() or html.escape((r.get("upprunaord") or "").strip(), quote=False)
        thyding = (r.get("thyding_html") or "").strip() or markdown_to_html(r.get("thyding") or "")
        item = {"numer": n, "kynslod": int(r["kynslod"]), "stada": stada, "nafn": html.escape(nafn, quote=False),
                "uppruni": uppruni, "thyding": thyding}
        for f in ("uppruni", "thyding"):
            if not item[f]:
                warnings.append(f"{where}: no {f}, the field is left as it is")
            if bad_tags(item[f]):
                problems.append(f"{where}: {f} has tags the plugin strips: {bad_tags(item[f])}")
        if stada == "breytt":
            fyrra = (r.get("fyrra_nafn") or "").strip()
            if not fyrra:
                problems.append(f"{where}: stada breytt without fyrra_nafn")
                continue
            if fyrra == nafn:
                warnings.append(f"{where}: breytt but fyrra_nafn == islenska")
            if published.get(str(n)) != fyrra:
                warnings.append(f"{where}: fyrra_nafn {fyrra!r} != published name {published.get(str(n))!r}; "
                                f"the update will skip this entry unless the live name is {fyrra!r}")
            item["fyrra_nafn"] = fyrra
        item["drog"] = bool(r.get("drög", r.get("drog", False)))
        drog += item["drog"]
        out.append(item)

    for w in warnings:
        print("warning:", w)
    if problems:
        for p in problems:
            print("ERROR:", p)
        sys.exit(f"{len(problems)} problems; {OUT.name} not written")

    OUT.write_text(json.dumps(out, ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    print(f"{source}: {len(rows)} rows ({counts['nýtt']} nýtt, {counts['breytt']} breytt, {counts['óbreytt']} óbreytt)")
    print(f"{OUT.relative_to(ROOT)}: {len(out)} rows, {drog} with drafted text (drög)")


if __name__ == "__main__":
    main()
