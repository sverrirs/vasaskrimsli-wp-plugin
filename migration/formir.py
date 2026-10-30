"""Special and regional forms (Alolan, Galarian, Hisuian, Paldean, Mega, Rotom's appliances ...) for the forms
card in the Skrímslaskrá grid and the Orðsifjafræði table.

  1. reads the saved pokemondb.net Pokédex pages in vasaskrimsli-dict (raw/pokemondb/pokedex/<nnnn>-<slug>.html.gz):
     every page whose artwork gallery has more than one picture has forms. Order and names come from the
     gallery ("Alolan Raichu official artwork"); types from data/monsters/<nnnn>-<slug>/pokemondb.json.
  2. downloads each form's sprite ONCE into .sprite-cache/formir/ (skips files already there), at the site's
     robots.txt Crawl-delay of 2 seconds, from img.pokemondb.net/sprites/home/normal/<form-slug>.png, or the
     official artwork (artwork/avif/<form-slug>.avif) when there is no sprite; its white background is made
     transparent from the edges in.
  3. writes features/ordsifjar/assets/sprites/formir/<form-slug>.webp (256 px, transparent) and
     features/ordsifjar/data/formir.json: {"26": [{"s": "raichu-alolan", "h": "Alolan Raichu", "t": [types]}]}.
     The base form itself is not listed.

  python -X utf8 migration/formir.py [path/to/vasaskrimsli-dict]
"""

import gzip
import html
import json
import re
import sys
import time
import urllib.request
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DICT = ROOT.parent / "vasaskrimsli-dict"
CACHE = ROOT / ".sprite-cache" / "formir"
OUT = ROOT / "features" / "ordsifjar" / "assets" / "sprites" / "formir"
DATA = ROOT / "features" / "ordsifjar" / "data" / "formir.json"
UA = "Mozilla/5.0 (vasaskrimsli.is one-time sprite copy; contact sverrirs@gmail.com)"
CRAWL_DELAY = 2.0
SIZE = 256
SOURCE = "https://img.pokemondb.net/sprites/home/normal/{slug}.png"
# The newest forms (Legends Z-A Megas) have no HOME sprite yet: use the official artwork, on white, instead.
ARTWORK = "https://img.pokemondb.net/artwork/avif/{slug}.avif"
GALLERY = re.compile(r'href="https://img\.pokemondb\.net/artwork/large/([a-z0-9-]+)\.jpg" data-title="([^"]+?) official artwork"')


def parse(base):
    out = {}
    for page in sorted((base / "raw" / "pokemondb" / "pokedex").glob("*.html.gz")):
        m = re.match(r"(\d{4})-(.+)\.html\.gz$", page.name)
        numer, slug = int(m.group(1)), m.group(2)
        seen, forms = set(), []
        for s, title in GALLERY.findall(gzip.open(page, "rt", encoding="utf-8").read()):
            if s not in seen:
                seen.add(s)
                forms.append((s, html.unescape(title)))
        if len(forms) < 2:
            continue
        types = {}
        db = base / "data" / "monsters" / f"{numer:04d}-{slug}" / "pokemondb.json"
        if db.exists():
            types = {f["name"]: f.get("types") or [] for f in json.loads(db.read_text(encoding="utf-8")).get("forms", [])}
        rows = []
        for s, title in forms:
            inner = re.fullmatch(r".+? \((.+)\)", title)  # "Rotom (Heat Rotom)" -> "Heat Rotom"
            name = inner.group(1) if inner else title
            rows.append({"s": s, "h": name, "t": types.get(title) or types.get(name) or []})
        out[str(numer)] = [r for r in rows if r["s"] != slug]
        if len(out[str(numer)]) == len(rows):  # The base slug was not in the gallery: keep the first as base.
            out[str(numer)] = out[str(numer)][1:]
    return {k: v for k, v in out.items() if v}


def download(forms):
    CACHE.mkdir(parents=True, exist_ok=True)
    slugs = [f["s"] for rows in forms.values() for f in rows]
    todo = [s for s in slugs if not (CACHE / f"{s}.png").exists() and not (CACHE / f"{s}.avif").exists()]
    print(f"{len(slugs) - len(todo)} cached, {len(todo)} to download (~{len(todo) * CRAWL_DELAY / 60:.0f} min)", flush=True)
    for i, s in enumerate(todo, 1):
        for url, ext in ((SOURCE.format(slug=s), "png"), (ARTWORK.format(slug=s), "avif")):
            try:
                req = urllib.request.Request(url, headers={"User-Agent": UA})
                (CACHE / f"{s}.{ext}").write_bytes(urllib.request.urlopen(req, timeout=30).read())
                time.sleep(CRAWL_DELAY)
                break
            except Exception as err:
                print(f"  {url}: {err}", flush=True)
                time.sleep(CRAWL_DELAY)
        if i % 50 == 0:
            print(f"  {i}/{len(todo)}", flush=True)


def clear_white(img):
    """Artwork on white: white connected to the edges becomes transparent, fading out near the threshold."""
    from collections import deque

    img = img.convert("RGBA")
    w, h = img.size
    px = img.load()
    seen = bytearray(w * h)
    todo = deque((x, y) for x in range(w) for y in (0, h - 1)) + deque((x, y) for y in range(h) for x in (0, w - 1))
    while todo:
        x, y = todo.popleft()
        if seen[y * w + x]:
            continue
        seen[y * w + x] = 1
        r, g, b, a = px[x, y]
        lo = min(r, g, b)
        if lo < 225:
            continue
        px[x, y] = (r, g, b, 0 if lo >= 245 else int(255 * (245 - lo) / 20))
        for nx, ny in ((x + 1, y), (x - 1, y), (x, y + 1), (x, y - 1)):
            if 0 <= nx < w and 0 <= ny < h and not seen[ny * w + nx]:
                todo.append((nx, ny))
    side = max(w, h)
    square = Image.new("RGBA", (side, side), (0, 0, 0, 0))
    square.paste(img, ((side - w) // 2, (side - h) // 2))
    return square


def convert(forms):

    OUT.mkdir(parents=True, exist_ok=True)
    missing = []
    for rows in forms.values():
        for f in rows:
            png, avif = CACHE / f"{f['s']}.png", CACHE / f"{f['s']}.avif"
            if png.exists():
                img = Image.open(png).convert("RGBA")
            elif avif.exists():
                img = clear_white(Image.open(avif))
            else:
                missing.append(f["s"])
                continue
            img.thumbnail((SIZE, SIZE), Image.LANCZOS)
            img.save(OUT / f"{f['s']}.webp", "WEBP", quality=85, method=4)
    files = list(OUT.glob("*.webp"))
    print(f"sprites/formir: {len(files)} files, {sum(f.stat().st_size for f in files) // 1024} KB; missing: {missing or 'none'}")
    return missing


def main():
    base = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_DICT
    forms = parse(base)
    print(f"{len(forms)} monsters with {sum(len(v) for v in forms.values())} extra forms")
    download(forms)
    missing = set(convert(forms))
    # A form without a picture is still listed (the card shows its name).
    DATA.write_text(json.dumps(forms, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"{DATA.relative_to(ROOT)} written; {len(missing)} without a picture")


if __name__ == "__main__":
    main()
