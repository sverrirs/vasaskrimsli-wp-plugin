"""Prove the seed holds everything the live page shows, cell by cell.

Deliberately independent of extract.py: it reads the OTHER snapshot (the full public page HTML,
not the REST copy) with plain regexes instead of an HTML parser, and compares every cell of every
row against a CSV file on three things:
  1. visible text (entities decoded, whitespace collapsed)
  2. every link target, in order
  3. the sequence of formatting tags (strong, em, a, br, sup, sub), in order

Run:  python -X utf8 migration/verify.py [csv]     (default: features/ordsifjar/data/seed.csv)
The same check is used on the plugin's own "CSV með sniði" export after a real WordPress import.
"""

import csv
import html
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PAGE = ROOT / "migration" / "source" / "ordsifjafraedi-2026-10-23.html"
FIELDS = ["numer", "nafn_html", "uppruni_html", "thyding_html", "enska_html"]


def text(s):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", s))).strip()


def links(s):
    return [html.unescape(h) for h in re.findall(r'<a\s[^>]*href="([^"]*)"', s)]


def shape(s):
    return [t.lower() for t in re.findall(r"<(/?(?:strong|em|a|br|sup|sub))\b", s)]


def source_rows():
    page = PAGE.read_text(encoding="utf-8")
    main = re.search(r"<main.*?</main>", page, re.S).group(0)
    rows = []
    for gen, table in enumerate(re.findall(r"<table.*?</table>", main, re.S), start=1):
        for tr in re.findall(r"<tr>(.*?)</tr>", table, re.S):
            cells = re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)
            if cells:
                rows.append((str(gen), cells))
    return rows


def main():
    target = Path(sys.argv[1]) if len(sys.argv) > 1 else ROOT / "features/ordsifjar/data/seed.csv"
    with open(target, encoding="utf-8-sig", newline="") as f:
        sample = f.read(4096)
        f.seek(0)
        dialect = csv.Sniffer().sniff(sample, delimiters=",;")
        got = list(csv.DictReader(f, dialect=dialect))

    want = source_rows()
    errors = []
    if len(got) != len(want):
        errors.append(f"row count: page has {len(want)}, csv has {len(got)}")

    # Match by (generation, number, name) so a reordered export still verifies.
    def key(gen, numer, nafn):
        return (gen, text(numer), text(nafn))

    by_key = {key(r["kynslod"], r["numer"], r["nafn_html"]): r for r in got}
    for gen, cells in want:
        k = key(gen, cells[0], cells[1])
        row = by_key.get(k)
        if row is None:
            errors.append(f"missing row {k}")
            continue
        for field, src in zip(FIELDS, cells):
            dst = row[field]
            for check in (text, links, shape):
                if check(src) != check(dst):
                    errors.append(f"{k} {field} {check.__name__}:\n    page: {check(src)}\n    csv:  {check(dst)}")

    cells_checked = len(want) * len(FIELDS)
    if errors:
        print(f"FAIL: {len(errors)} differences")
        print("\n".join(errors[:40]))
        return 1
    print(f"OK: {len(want)} rows, {cells_checked} cells identical in text, links and formatting ({target.name})")
    return 0


if __name__ == "__main__":
    sys.exit(main())
