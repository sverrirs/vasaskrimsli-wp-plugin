"""Builds dist/vasaskrimsli.zip, the file you upload to WordPress (and the GitHub Release asset).

Run after `npm run build`:  python scripts/package.py [--expect-version 0.1.0]
Downloads plugin-update-checker into vendor/ if it is not there yet.
"""

import argparse
import io
import re
import sys
import tarfile
import urllib.request
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
PUC_VERSION = "v5.7"  # https://github.com/YahnisElsts/plugin-update-checker/releases
PUC_DIR = ROOT / "vendor" / "plugin-update-checker"

# What goes into the plugin. Sources (src/, shared/js), tests and migration tooling stay out.
INCLUDE = ["vasaskrimsli.php", "LICENSE", "readme.txt", "includes", "features", "build", "vendor"]
EXCLUDE = re.compile(r"(^|/)(src|\.git\w*|node_modules|tests?)(/|$)|\.(map|md)$")


def fetch_puc():
    if (PUC_DIR / "plugin-update-checker.php").exists():
        return
    url = f"https://github.com/YahnisElsts/plugin-update-checker/archive/refs/tags/{PUC_VERSION}.tar.gz"
    print("downloading", url)
    data = urllib.request.urlopen(url, timeout=60).read()
    with tarfile.open(fileobj=io.BytesIO(data), mode="r:gz") as tar:
        prefix = tar.getmembers()[0].name.split("/")[0] + "/"
        for m in tar.getmembers():
            if not m.name.startswith(prefix) or not m.isfile():
                continue
            rel = m.name[len(prefix):]
            if rel.startswith(("examples/", ".")):
                continue
            target = PUC_DIR / rel
            if PUC_DIR.resolve() not in target.resolve().parents:
                sys.exit(f"unsafe path in archive: {m.name}")
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(tar.extractfile(m).read())


def main():
    ap = argparse.ArgumentParser()
    ap.add_argument("--expect-version")
    args = ap.parse_args()

    header = (ROOT / "vasaskrimsli.php").read_text(encoding="utf-8")
    version = re.search(r"^ \* Version:\s*(\S+)", header, re.M).group(1)
    constant = re.search(r"define\( 'VASASKRIMSLI_VERSION', '([^']+)' \)", header).group(1)
    if version != constant:
        sys.exit(f"plugin header Version {version} != VASASKRIMSLI_VERSION {constant}")
    if args.expect_version and args.expect_version.lstrip("v") != version:
        sys.exit(f"tag {args.expect_version} does not match plugin version {version}")
    if not (ROOT / "build" / "view.js").exists():
        sys.exit("build/ is missing: run `npm run build` first")

    fetch_puc()
    out = ROOT / "dist" / "vasaskrimsli.zip"
    out.parent.mkdir(exist_ok=True)
    count = 0
    with zipfile.ZipFile(out, "w", zipfile.ZIP_DEFLATED) as z:
        for item in INCLUDE:
            path = ROOT / item
            files = [path] if path.is_file() else sorted(p for p in path.rglob("*") if p.is_file()) if path.exists() else []
            for f in files:
                rel = f.relative_to(ROOT).as_posix()
                if EXCLUDE.search(rel) and not rel.startswith("vendor/"):
                    continue
                z.write(f, "vasaskrimsli/" + rel)
                count += 1
    print(f"{out.relative_to(ROOT)}: version {version}, {count} files, {out.stat().st_size // 1024} KB")


if __name__ == "__main__":
    main()
