"""National Pokédex list and sprites from pokemondb.net, stored locally in the plugin.

Reads the saved page snapshot (migration/source/pokemondb-national-*.html) and:
  1. writes features/ordsifjar/data/national.json  [{numer, kynslod, slug, enska}] for all 1025 entries
  2. downloads each sprite ONCE into .sprite-cache/ (skips files already there), honouring the
     site's robots.txt Crawl-delay of 2 seconds
  3. converts them to small transparent WebP files in features/ordsifjar/assets/sprites/<numer>.webp,
     which the plugin serves from your own site (no requests to pokemondb.net from visitors)

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
SIZE = 96  # px; shown at 48 px, so sharp on high-density screens


def parse():
    page = SNAPSHOT.read_text(encoding="utf-8")
    entries = []
    for gen, section in re.findall(r'<h2 id="gen-(\d+)">.*?</h2>(.*?)(?=<h2 id="gen-|\Z)', page, re.S):
        for card in re.findall(r'<div class="infocard ">(.*?)</div>', section, re.S):
            num = int(re.search(r"<small>#(\d+)</small>", card).group(1))
            name = re.search(r'<a class="ent-name" href="/pokedex/([^"]+)">([^<]+)</a>', card)
            avif = re.search(r'srcset="([^"]+\.avif)"', card)
            jpg = re.search(r'<img[^>]+src="([^"]+)"', card)
            entries.append({
                "numer": num, "kynslod": int(gen), "slug": name.group(1), "enska": html.unescape(name.group(2)),
                "avif": avif.group(1) if avif else None, "jpg": jpg.group(1) if jpg else None,
            })
    return entries


def download(entries):
    CACHE.mkdir(exist_ok=True)
    todo = [e for e in entries if not any(CACHE.glob(f"{e['numer']:04d}.*"))]
    print(f"{len(entries) - len(todo)} cached, {len(todo)} to download (~{len(todo) * CRAWL_DELAY / 60:.0f} min)", flush=True)
    for i, e in enumerate(todo, 1):
        for url in filter(None, (e["avif"], e["jpg"])):
            try:
                req = urllib.request.Request(url, headers={"User-Agent": UA})
                data = urllib.request.urlopen(req, timeout=30).read()
                (CACHE / f"{e['numer']:04d}.{url.rsplit('.', 1)[1]}").write_bytes(data)
                break
            except Exception as err:  # try the JPG if the AVIF fails
                print(f"  #{e['numer']} {url}: {err}", flush=True)
            finally:
                time.sleep(CRAWL_DELAY)
        if i % 50 == 0:
            print(f"  {i}/{len(todo)}", flush=True)


def convert(entries):
    from PIL import Image

    OUT.mkdir(parents=True, exist_ok=True)
    missing = []
    for e in entries:
        src = next(iter(sorted(CACHE.glob(f"{e['numer']:04d}.*"))), None)
        if not src:
            missing.append(e["numer"])
            continue
        img = Image.open(src).convert("RGBA")
        img.thumbnail((SIZE, SIZE), Image.LANCZOS)
        img.save(OUT / f"{e['numer']}.webp", "WEBP", quality=82, method=6)
    total = sum(f.stat().st_size for f in OUT.glob("*.webp"))
    print(f"sprites: {len(entries) - len(missing)} written, {total // 1024} KB total; missing: {missing or 'none'}")
    return missing


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
