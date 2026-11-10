"""The book catalogue for the Verslun page: features/baekur/data/baekur.json and the pictures in features/baekur/assets/.

  1. reads migration/baekur-skra.json, the hand-edited source: the series, their texts, and every book with its
     status ("faanleg" on sale, "vaentanleg" coming, "falin" left out), ISBN, contents and product in the web shop.
  2. for every book that is not "falin": opens its print PDFs in the book's own repository (next to this one, the paths
     in the series' "heimild"), cuts the front cover out of the cover PDF (a wraparound cover's right-hand page) and
     renders the cover and some interior pages ("synishorn": page numbers, or picked evenly) as WebP. A book with
     "myndir" (posters) gets those pictures instead, downloaded once into .sprite-cache/baekur/.
  3. reads every product in the web shop (the Shopify store's public product JSON) for its name, address, options
     and prices; the site refreshes them itself (Verslun.php), these are the fallback.
  4. writes baekur.json and removes pictures no book uses any more.

To add a book: add it to baekur-skra.json (or change its "stada"), build it in its repository, then run

  python -X utf8 migration/baekur.py
"""

import io
import json
import sys
import time
import subprocess
import urllib.request
from pathlib import Path

import fitz
from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
REPOS = ROOT.parent
SKRA = ROOT / "migration" / "baekur-skra.json"
FEATURE = ROOT / "features" / "baekur"
DATA = FEATURE / "data" / "baekur.json"
ASSETS = FEATURE / "assets"
CACHE = ROOT / ".sprite-cache" / "baekur"
UA = "Mozilla/5.0 (vasaskrimsli.is book catalogue)"
COVER_PX = 600
PAGE_PX = 900
MM = 25.4 / 72


def mm(rect):
    return round(rect.width * MM), round(rect.height * MM)


def snid(w, h):
    """'A5, 148 × 210 mm' for the standard sizes, else just the size."""
    names = {(148, 210): "A5", (210, 297): "A4", (105, 148): "A6"}
    name = next((n for (a, b), n in names.items() if abs(w - a) <= 2 and abs(h - b) <= 2), "")
    return f"{name}, {w} × {h} mm" if name else f"{w} × {h} mm"


def save(pix_or_img, path, width, quality):
    img = pix_or_img if isinstance(pix_or_img, Image.Image) else Image.open(io.BytesIO(pix_or_img.tobytes("png")))
    img = img.convert("RGB")
    if img.width > width:
        img = img.resize((width, round(img.height * width / img.width)), Image.LANCZOS)
    path.parent.mkdir(parents=True, exist_ok=True)
    img.save(path, "WEBP", quality=quality, method=5)
    return f"{path.relative_to(FEATURE).as_posix()}"


def render(page, clip, width, path, quality, source):
    """The page (or part of it) as WebP, unless the picture is already newer than its PDF."""
    if path.exists() and path.stat().st_mtime >= source.stat().st_mtime:
        return path.relative_to(FEATURE).as_posix()
    zoom = width / clip.width
    return save(page.get_pixmap(matrix=fitz.Matrix(zoom, zoom), clip=clip), path, width, quality)


def picks(count, want):
    """Interior pages to show: evenly spread, after the title pages and before the last."""
    first, last = min(3, count), max(min(3, count), count - 1)
    if last - first + 1 <= want:
        return list(range(first, last + 1))
    step = (last - first) / (want - 1)
    return sorted({round(first + i * step) for i in range(want)})


def fill(pattern, book):
    n = book["n"]
    stafur = book["heiti"].split()[-1]
    return pattern.format(n=n, nn=f"{n:02d}", stafur=stafur)


class Missing(Exception):
    pass


def from_pdfs(rod, book, used):
    h = rod["heimild"]
    repo = REPOS / h["repo"]
    interior = repo / fill(h["bok"], book)
    if not interior.exists():
        raise Missing(f"{book['id']}: no {interior.relative_to(REPOS)}")
    doc = fitz.open(interior)
    trim = doc[0].trimbox
    w, h_mm = mm(trim)
    out = {"sidur": doc.page_count, "snid": snid(w, h_mm)}
    base = ASSETS / rod["id"] / book["id"]
    cover_pdf = repo / fill(h["kapa"], book) if "kapa" in h else None
    if cover_pdf and cover_pdf.exists():
        cover = fitz.open(cover_pdf)[0]
        ct = cover.trimbox
        if ct.width > trim.width * 1.5:  # Wraparound cover: the front is the right-hand page.
            ct = fitz.Rect(ct.x1 - trim.width, ct.y0, ct.x1, ct.y1)
        out["kapa"] = render(cover, ct, COVER_PX, base.with_name(f"{book['id']}-kapa.webp"), 80, cover_pdf)
    else:  # No cover file: the first page is the cover.
        out["kapa"] = render(doc[0], trim, COVER_PX, base.with_name(f"{book['id']}-kapa.webp"), 80, interior)
    used.add(out["kapa"])
    want = 6 if book["stada"] == "faanleg" else 4
    out["synishorn"] = []
    for n in book.get("synishorn") or picks(doc.page_count, want):
        page = doc[n - 1]
        rel = render(page, page.trimbox, PAGE_PX, base.with_name(f"{book['id']}-s{n}.webp"), 72, interior)
        used.add(rel)
        out["synishorn"].append({"mynd": rel, "sida": n})
    return out


def download(url):
    CACHE.mkdir(parents=True, exist_ok=True)
    f = CACHE / url.rsplit("/", 1)[-1].split("?")[0]
    if not f.exists():
        req = urllib.request.Request(url, headers={"User-Agent": UA})
        f.write_bytes(urllib.request.urlopen(req, timeout=30).read())
        time.sleep(1)
    return Image.open(f)


def from_pictures(rod, book, used):
    pics = []
    for i, url in enumerate(book["myndir"], 1):
        rel = save(download(url), ASSETS / rod["id"] / f"{book['id']}-{i}.webp", PAGE_PX, 78)
        used.add(rel)
        pics.append(rel)
    return {"kapa": pics[0], "synishorn": [{"mynd": p, "sida": 0} for p in pics[1:]]}


def product(shop, handle):
    """One product. Through curl: the store answers Python's own HTTP client with 429 (too many requests)."""
    res = subprocess.run(["curl", "-sf", "--retry", "3", "-A", UA, f"{shop}/products/{handle}.json"], capture_output=True, check=True)
    p = json.loads(res.stdout)["product"]
    time.sleep(1)
    return {
        "id": str(p["id"]),
        "heiti": p["title"],
        "slod": f"{shop}/products/{handle}",
        "afbrigdi": [{"heiti": "" if v["title"] == "Default Title" else v["title"], "verd": round(float(v["price"]))} for v in p["variants"]],
    }


def main():
    skra = json.loads(SKRA.read_text(encoding="utf-8"))
    before = {b["id"]: b for r in json.loads(DATA.read_text(encoding="utf-8"))["radir"] for b in r["baekur"]} if DATA.exists() else {}
    used = set()
    radir = []
    handles = set()
    for rod in skra["radir"]:
        books = []
        for book in rod["baekur"]:
            if book["stada"] == "falin":
                continue
            try:
                extra = from_pictures(rod, book, used) if book.get("myndir") else from_pdfs(rod, book, used)
            except Missing as err:
                # A PDF being rebuilt in its repository: keep what the last run made of it, if anything.
                old = before.get(book["id"])
                if not old:
                    raise SystemExit(f"{err} (build the book first, or set it \"falin\")")
                print(f"  WARNING {err}: kept from the last run", flush=True)
                extra = {k: old[k] for k in ("sidur", "snid", "kapa", "synishorn") if k in old}
                used.update([extra["kapa"], *(s["mynd"] for s in extra["synishorn"])])
            b = {k: v for k, v in book.items() if k not in ("myndir", "synishorn")} | extra
            books.append(b)
            if b.get("vara"):
                handles.add(b["vara"])
            print(f"  {b['id']}: {b.get('sidur', '-')} pages, {len(b['synishorn'])} samples", flush=True)
        radir.append({k: v for k, v in rod.items() if k not in ("baekur", "heimild")} | {"baekur": books})
    vorur = {h: product(skra["verslun"]["slod"], h) for h in sorted(handles)}
    DATA.parent.mkdir(parents=True, exist_ok=True)
    DATA.write_text(json.dumps({"verslun": skra["verslun"], "vorur": vorur, "radir": radir}, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    stale = [p for p in ASSETS.rglob("*.webp") if p.relative_to(FEATURE).as_posix() not in used]
    for p in stale:
        p.unlink()
    files = list(ASSETS.rglob("*.webp"))
    print(f"{DATA.relative_to(ROOT)}: {sum(len(r['baekur']) for r in radir)} books, {len(vorur)} products; "
          f"{len(files)} pictures, {sum(f.stat().st_size for f in files) // 1024} KB; {len(stale)} old pictures removed")


if __name__ == "__main__":
    sys.exit(main())
