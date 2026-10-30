"""Types and evolution chains for the Skrímslaskrá PDF cards: features/ordsifjar/data/throun.json.

Reads the pokemondb.json files in vasaskrimsli-dict (data/monsters/<nnnn>-<slug>/) and writes, per national
number, {"t": [types], "k": [[stage 1 numbers], [stage 2 numbers], ...]}. "k" is left out for monsters that do
not evolve. Forms that share a number (Alolan, Galarian ...) count once.

  python -X utf8 migration/throun.py [path/to/vasaskrimsli-dict]
"""

import json
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
OUT = ROOT / "features" / "ordsifjar" / "data" / "throun.json"
DEFAULT_DICT = ROOT.parent / "vasaskrimsli-dict"


def stages(chain):
    """Breadth-first: the numbers at each depth of the evolution tree, in order, without repeats."""
    out, seen, level = [], set(), [n for n in chain if n.get("number")]
    while level:
        row = []
        for node in level:
            if node["number"] not in seen:
                seen.add(node["number"])
                row.append(node["number"])
        if row:
            out.append(sorted(row))
        level = [c for node in level for c in node.get("evolves_to", []) if c.get("number")]
    return out


def main():
    base = Path(sys.argv[1]) if len(sys.argv) > 1 else DEFAULT_DICT
    out = {}
    for f in sorted((base / "data" / "monsters").glob("*/pokemondb.json")):
        d = json.loads(f.read_text(encoding="utf-8"))
        n = int(d["numer"])
        row = {"t": d.get("types") or []}
        k = stages((d.get("evolution") or {}).get("chain") or [])
        if sum(len(s) for s in k) > 1:
            row["k"] = k
        out[str(n)] = row
    if len(out) < 1000:
        sys.exit(f"only {len(out)} monsters found under {base}; throun.json not written")
    OUT.write_text(json.dumps(out, ensure_ascii=False, separators=(",", ":")) + "\n", encoding="utf-8")
    print(f"{OUT.relative_to(ROOT)}: {len(out)} monsters, {sum('k' in r for r in out.values())} with evolutions")


if __name__ == "__main__":
    main()
