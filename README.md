# Vasaskrímsli WordPress plugin

Interactive parts of [vasaskrimsli.is](https://vasaskrimsli.is), built as one WordPress plugin with one
block per feature. React 19 apps are bundled with Vite; WordPress itself stays as it is.

## Features

### Orðsifjafræði (`features/ordsifjar/`)
- **Block "Orðsifjafræði"** (or shortcode `[ordsifjar]`): the name etymology tables with Icelandic-aware
  search (`edla` finds *eðla*), generation and category filters, shareable URLs (`?leit=…&kynslod=2`)
  and a card layout on phones. The full tables are rendered as plain HTML first, so search engines and
  readers without JavaScript see everything. The original heading anchors (`#fyrsta-kynslod`, …) still work.
- **wp-admin → Orðsifjar**: a spreadsheet-style grid. Click a cell to edit; bold (Ctrl+B), italics (Ctrl+I),
  links, line breaks (Enter), superscript and subscript. Saves when you leave the cell; Esc cancels.
  Each entry has a full history with restore. Delete moves an entry to the trash (restorable).
- **Flokkar** (categories) and **Kynslóðir** (generation headings and notes) are editable in wp-admin.
- **Generations 4 to 9**: every national Pokédex number from 387 to 1025, with the English name linked to
  pokemondb.net, is added on update (Icelandic name, Upprunaorð and Þýðing start as "–" to fill in).
  Numbers that already exist, including entries in the trash, are never added again.
- **Sprites**: a small transparent image of each entry on the far left; hovering, focusing or tapping it
  shows a larger version in a tooltip card. Both sizes are served from this plugin
  (`features/ordsifjar/assets/sprites/<number>.webp` and `sprites/large/`), so visitors never load images
  from pokemondb.net.
  Refresh them with `python -X utf8 migration/national.py` (downloads once, 2 s apart, per pokemondb.net's robots.txt).
- **Upprunaorð separators** are always em dashes (—): hyphens and en dashes between word parts are converted
  on save and on import; link addresses are never changed.
- **CSV**: *Sækja CSV með sniði* (formatting as HTML, for exact backups and re-import) and
  *Sækja CSV án sniðs* (plain text). Both open correctly in Excel (UTF-8 with BOM).
- **CSV import** with a preview before anything changes. Rows with an `id` update that entry, rows
  without one are created, entries missing from the file are left alone. Importing a plain CSV only
  changes cells whose text you edited; unchanged cells keep their formatting. Every import first saves
  a backup (downloadable from the import dialog); importing that backup undoes the import.

## Nöfn 2026

Version 0.3.0 brings the owner's 2026 decisions on Icelandic names (#252 to #1025). They are bundled as
`features/ordsifjar/data/nofn-2026.json` and applied once by the data update (data version 3) the first
time an editor opens wp-admin after updating, or on first activation of a new install:

- **Generations 4 to 9** ("nýtt"): Nafn, Upprunaorð and Þýðing are filled in **only where the field is still
  "–"**, field by field. Anything typed in wp-admin is kept.
- **Generation 3** ("breytt"): the name, Upprunaorð and Þýðing are replaced **only if the entry's name is
  still the published one** (`fyrra_nafn`). If it was changed in wp-admin, the entry is left as it is and
  listed as skipped in the notice. Names that stay ("óbreytt") are not touched.
- **Generations 3 to 9 are marked "í vinnslu"**: the note "Nöfnin í þessari kynslóð eru í vinnslu og geta
  breyst." is set only where a generation has no note (gen 4 keeps its own), and the gen 3 heading gets
  " (í vinnslu)" unless it already says "vinnslu" or "óstaðfest". A note you remove later stays removed.
- A backup CSV is written first, every changed entry gets a revision (restorable from its history), and an
  interrupted run continues where it stopped. The notice in wp-admin says how many entries were filled,
  changed and skipped.

**Regenerating the data file** when the list changes (before a release):

```bash
python -X utf8 migration/nofn.py      # reads ../vasaskrimsli-dict/ideas/lokalisti/lokalisti.json
"E:\SDKs\php\8.3.7\php.exe" tests/run.php
```

The script keeps only what the plugin needs, checks the HTML against the allow-list, the numbers against
`national.json`, and warns when a `fyrra_nafn` differs from the published name in `seed.csv`. A site that
has already run data version 3 does not apply a regenerated file again; that needs a new upgrade step.

## First install on vasaskrimsli.is

The live page is not changed by installing the plugin. Snapshots of it as it was on 2026-10-23 are in
`migration/source/`.

1. Download `vasaskrimsli.zip` from the latest [GitHub Release](https://github.com/sverrirs/vasaskrimsli-wp-plugin/releases)
   (or build it, see below).
2. wp-admin → Plugins → Add New → Upload Plugin → choose the zip → Install → Activate.
   On first activation the 390 entries from the original page are loaded.
3. wp-admin → Orðsifjar: check the count says 390 and spot-check a few rows.
4. Edit the Orðsifjafræði page. Keep the introduction and the "Hvernig má nota efnið í kennslu?" section.
   Add the **Orðsifjafræði** block below them, then remove the four generation headings and their tables.
   Preview, then Update. WordPress keeps the old version under the page's Revisions.

## Releasing an update

1. Bump the version in `vasaskrimsli.php` (header `Version:` and `VASASKRIMSLI_VERSION`) and
   `Stable tag` in `readme.txt`.
2. Commit, then `git tag v0.2.0 && git push --tags`.
3. The Release workflow tests, builds and publishes `vasaskrimsli.zip`. The site then shows
   "Update available" under Plugins.

## Development

```bash
npm install
npm run build                      # React apps -> build/
npm test                           # JS search tests
"E:\SDKs\php\8.3.7\php.exe" tests/run.php   # CSV/import tests (pure PHP)
python -X utf8 migration/verify.py          # seed vs. the original page, cell by cell
python scripts/package.py          # -> dist/vasaskrimsli.zip
```

A local test WordPress with the plugin: see `tests/playground/README.md`.

## Layout

```
vasaskrimsli.php          plugin header and bootstrap
includes/                 shared PHP (asset loading, GitHub updater)
shared/js/                shared JS (Icelandic search folding)
features/ordsifjar/
  includes/               Csv, Format, Nofn (pure PHP), Sanitize, Store, Import, Upgrade, Rest, Admin, Block
  block/                  block.json and editor placeholder
  src/admin, src/view     React apps
  data/                   seed.csv and kynslodir.json from the original page, national.json, nofn-2026.json
migration/                extract.py, verify.py, national.py, nofn.py and the page snapshots
```
