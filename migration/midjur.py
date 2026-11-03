"""The midpoint of every sprite the PDFs draw, so a monster sits in the middle of its circle: the book's own numbers.

  1. reads vasaskrimsli-dict ideas/myndir/midjur.json, the one store of midpoints (made by tools/midjur.py there, which
     also measures this plugin's sprites; hand corrections live in ideas/myndir/midjur-leidrett.json there).
  2. writes features/ordsifjar/data/midjur.json: {"large/3": [cx, cy, r], "formir/raichu-alolan": [cx, cy, r]}.
     cx and cy are the centre of mass of the solid pixels as shares of the file's width and height; r is how far the
     farthest solid pixel is from it, as a share of the file's longer side.

Stops when a sprite is missing from the store or has another size there: run tools/midjur.py in vasaskrimsli-dict first.
Rerun after adding or replacing sprites.

  python -X utf8 migration/midjur.py [path/to/vasaskrimsli-dict]
"""

import json
import sys
from pathlib import Path

from PIL import Image

ROOT = Path(__file__).resolve().parent.parent
DEFAULT_DICT = ROOT.parent / "vasaskrimsli-dict"
SPRITES = ROOT / "features" / "ordsifjar" / "assets" / "sprites"
DATA = ROOT / "features" / "ordsifjar" / "data" / "midjur.json"


def main():
    base = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_DICT
    store = json.loads((base / "ideas" / "myndir" / "midjur.json").read_text(encoding="utf-8"))["midjur"]
    out, bad = {}, []
    for sub in ("large", "formir"):
        for path in sorted((SPRITES / sub).glob("*.webp")):
            key = f"{sub}/{path.stem}"
            row = store.get(f"sprites/{key}.webp")
            with Image.open(path) as im:
                if not row or (row["w"], row["h"]) != im.size:
                    bad.append(key)
                    continue
            out[key] = [row["cx"], row["cy"], row["r"]]
    if bad:
        raise SystemExit(f"Not in the store, or another size there (run tools/midjur.py in vasaskrimsli-dict): {bad}")
    DATA.write_text(json.dumps(out, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"{DATA.relative_to(ROOT)}: {len(out)} sprites, {DATA.stat().st_size // 1024} KB")


if __name__ == "__main__":
    main()
