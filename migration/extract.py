"""Extract the orðsifjafræði tables from the page snapshot into the plugin's seed files.

Reads only migration/source/ (never the live site) and writes:
  features/ordsifjar/data/seed.csv        formatted CSV, same format as the "CSV með sniði" export
  features/ordsifjar/data/kynslodir.json  generation headings and notes

Run:  python -X utf8 migration/extract.py
"""

import csv
import html
import json
import re
import sys
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SOURCE = ROOT / "migration" / "source" / "ordsifjafraedi-2026-10-23.rest.json"
OUT_DIR = ROOT / "features" / "ordsifjar" / "data"

# Must match Vasaskrimsli\Ordsifjar\Sanitize::allowed_html() on the PHP side.
ALLOWED = {"strong": set(), "em": set(), "br": set(), "sup": set(), "sub": set(),
           "a": {"href", "target", "rel"}}
VOID = {"br"}

# Column order of the formatted CSV. Must match Csv::COLUMNS on the PHP side.
HEADER = ["id", "numer", "kynslod", "nafn_html", "uppruni_html", "thyding_html",
          "enska_html", "flokkar", "athugasemdir_html"]


class Canon(HTMLParser):
    """Re-serialises a cell's inner HTML with only the allowed tags and attributes.

    Text is stored as real Unicode characters (&#8211; becomes –), escaping only & < >.
    Anything outside the allow-list is recorded so nothing is dropped silently.
    """

    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.out = []
        self.dropped = []

    def handle_starttag(self, tag, attrs):
        if tag not in ALLOWED:
            self.dropped.append(f"<{tag}>")
            return
        kept = []
        for name, value in attrs:
            if name in ALLOWED[tag]:
                kept.append(f' {name}="{html.escape(value or "", quote=True)}"')
            elif not (tag in ("td", "th") or name == "class"):
                self.dropped.append(f"{tag}[{name}]")
        self.out.append(f"<{tag}{''.join(kept)}>")

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)

    def handle_endtag(self, tag):
        if tag in ALLOWED and tag not in VOID:
            self.out.append(f"</{tag}>")
        elif tag not in ALLOWED:
            self.dropped.append(f"</{tag}>")

    def handle_data(self, data):
        self.out.append(html.escape(data, quote=False))


def canon(fragment):
    p = Canon()
    p.feed(fragment)
    p.close()
    return "".join(p.out).strip(), p.dropped


def plain(fragment):
    return re.sub(r"\s+", " ", html.unescape(re.sub(r"<[^>]+>", "", fragment))).strip()


def main():
    page = json.loads(SOURCE.read_text(encoding="utf-8"))[0]
    content = page["content"]["rendered"]

    # Each table belongs to the last <h3> before it; paragraphs between that heading and the
    # table are the generation's note. Content before the first heading stays on the WP page.
    groups, cursor = [], 0
    for fig in re.finditer(r'<figure class="wp-block-table[^"]*">(.*?)</figure>', content, re.S):
        before = content[cursor:fig.start()]
        heads = list(re.finditer(r'<h3([^>]*)>(.*?)</h3>', before, re.S))
        if not heads:
            sys.exit("table without a heading before it")
        h = heads[-1]
        anchor = re.search(r'\bid="([^"]+)"', h.group(1))
        groups.append((anchor.group(1) if anchor else "", h.group(2), before[h.end():], fig.group(1)))
        cursor = fig.end()
    if len(groups) != 4:
        sys.exit(f"expected 4 generation tables, found {len(groups)}")

    rows, kynslodir, problems = [], [], []
    for gen, (anchor, heading, between, table) in enumerate(groups, start=1):
        note = " ".join(canon(p)[0] for p in re.findall(r"<p[^>]*>(.*?)</p>", between, re.S) if plain(p))
        kynslodir.append({"kynslod": gen, "akkeri": anchor, "heiti_html": canon(heading)[0],
                          "skyring_html": note})

        for tr in re.findall(r"<tbody>(.*?)</tbody>", table, re.S)[0].split("</tr>"):
            cells = re.findall(r"<td[^>]*>(.*?)</td>", tr, re.S)
            if not cells:
                continue
            if len(cells) != 5:
                problems.append(f"kynslóð {gen}: row with {len(cells)} cells: {plain(tr)[:80]}")
                continue
            out = []
            for i, cell in enumerate(cells):
                value, dropped = canon(cell)
                if dropped:
                    problems.append(f"kynslóð {gen} '{plain(cells[1])}' column {i}: dropped {dropped}")
                out.append(value)
            numer, nafn, uppruni, thyding, enska = out
            rows.append({"id": "", "numer": plain(numer), "kynslod": str(gen), "nafn_html": nafn,
                         "uppruni_html": uppruni, "thyding_html": thyding, "enska_html": enska,
                         "flokkar": "", "athugasemdir_html": ""})

    OUT_DIR.mkdir(parents=True, exist_ok=True)
    with open(OUT_DIR / "seed.csv", "w", encoding="utf-8-sig", newline="") as f:
        w = csv.DictWriter(f, fieldnames=HEADER, quoting=csv.QUOTE_MINIMAL, lineterminator="\r\n")
        w.writeheader()
        w.writerows(rows)
    (OUT_DIR / "kynslodir.json").write_text(
        json.dumps(kynslodir, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")

    per_gen = {}
    for r in rows:
        per_gen[r["kynslod"]] = per_gen.get(r["kynslod"], 0) + 1
    print(f"rows: {len(rows)}  per kynslóð: {per_gen}")
    print("problems:", problems or "none")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())
