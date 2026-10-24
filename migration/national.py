"""National Pokédex list and sprites from pokemondb.net, stored locally in the plugin.

Reads the saved page snapshot (migration/source/pokemondb-national-*.html) and:
  1. writes features/ordsifjar/data/national.json  [{numer, kynslod, slug, enska}] for all 1025 entries
  2. downloads each sprite ONCE into .sprite-cache/ (skips files already there), honouring the
     site's robots.txt Crawl-delay of 2 seconds. Source: img.pokemondb.net/sprites/home/normal/<slug>.png,
     the transparent PNG of the same render the national list shows. (The list's own .avif/.jpg files
     have a solid white background baked in.)
  3. checks every image really is transparent, then writes two WebP sizes the plugin serves from your
     own site (no requests to pokemondb.net from visitors):
       features/ordsifjar/assets/sprites/<numer>.webp        96 px, the thumbnail column
       features/ordsifjar/assets/sprites/large/<numer>.webp  256 px (full size), the hover tooltip

Run:  python -X utf8 migration/national.py [--no-download]
"""

import html
import json
import re
import sys
import time
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
SNAPSHOT = sorted((ROOT / "migration" / "source").glob("pokemondb-national-*.html"))[-1]
CACHE = ROOT / ".sprite-cache"
OUT = ROOT / "features" / "ordsifjar" / "assets" / "sprites"
DATA = ROOT / "features" / "ordsifjar" / "data" / "national.json"
UA = "Mozilla/5.0 (vasaskrimsli.is one-time sprite copy; contact sverrirs@gmail.com)"
CRAWL_DELAY = 2.0
SIZES = {"": 96, "large": 256}  # sub-folder -> px; thumbnails show at 48 px, the tooltip at the full 256 px
SOURCE = "https://img.pokemondb.net/sprites/home/normal/{slug}.png"


def parse():
    page = SNAPSHOT.read_text(encoding="utf-8")
    entries = []
    for gen, section in re.findall(r'<h2 id="gen-(\d+)">.*?</h2>(.*?)(?=<h2 id="gen-|\Z)', page, re.S):
        for card in re.findall(r'<div class="infocard ">(.*?)</div>', section, re.S):
            num = int(re.search(r"<small>#(\d+)</small>", card).group(1))
            name = re.search(r'<a class="ent-name" href="/pokedex/([^"]+)">([^<]+)</a>', card)
            entries.append({"numer": num, "kynslod": int(gen), "slug": name.group(1), "enska": html.unescape(name.group(2))})
    return entries


def download(entries):
    CACHE.mkdir(exist_ok=True)
    todo = [e for e in entries if not (CACHE / f"{e['numer']:04d}.png").exists()]
    print(f"{len(entries) - len(todo)} cached, {len(todo)} to download (~{len(todo) * CRAWL_DELAY / 60:.0f} min)", flush=True)
    for i, e in enumerate(todo, 1):
        url = SOURCE.format(slug=e["slug"])
        try:
            req = urllib.request.Request(url, headers={"User-Agent": UA})
            (CACHE / f"{e['numer']:04d}.png").write_bytes(urllib.request.urlopen(req, timeout=30).read())
        except Exception as err:
            print(f"  #{e['numer']} {url}: {err}", flush=True)
        time.sleep(CRAWL_DELAY)
        if i % 50 == 0:
            print(f"  {i}/{len(todo)}", flush=True)


def convert(entries):
    from PIL import Image

    for sub in SIZES:
        (OUT / sub).mkdir(parents=True, exist_ok=True)
    missing, opaque = [], []
    for e in entries:
        src = CACHE / f"{e['numer']:04d}.png"
        if not src.exists():
            missing.append(e["numer"])
            continue
        img = Image.open(src).convert("RGBA")
        if img.getchannel("A").getextrema()[0] == 255:  # no transparent pixel at all
            opaque.append(e["numer"])
            continue
        for sub, px in SIZES.items():
            out = img.copy()
            out.thumbnail((px, px), Image.LANCZOS)
            out.save(OUT / sub / f"{e['numer']}.webp", "WEBP", quality=85, method=4)
            check = Image.open(OUT / sub / f"{e['numer']}.webp")
            if check.mode != "RGBA" or check.getchannel("A").getextrema()[0] == 255:
                opaque.append(e["numer"])
    for sub in SIZES:
        files = list((OUT / sub).glob("*.webp"))
        print(f"sprites/{sub or '.'}: {len(files)} files, {sum(f.stat().st_size for f in files) // 1024} KB")
    print(f"missing: {missing or 'none'}; not transparent: {sorted(set(opaque)) or 'none'}")
    return missing or opaque


def main():
    entries = parse()
    per_gen = {}
    for e in entries:
        per_gen[e["kynslod"]] = per_gen.get(e["kynslod"], 0) + 1
    print(f"{len(entries)} entries, per generation: {per_gen}")
    nums = [e["numer"] for e in entries]
    if nums != list(range(1, len(entries) + 1)):
        sys.exit("national numbers are not 1..N in order")
    DATA.write_text(json.dumps([{k: e[k] for k in ("numer", "kynslod", "slug", "enska")} for e in entries],
                               ensure_ascii=False, indent=1) + "\n", encoding="utf-8")
    if "--no-download" not in sys.argv:
        download(entries)
    return 1 if convert(entries) else 0


if __name__ == "__main__":
    sys.exit(main())
