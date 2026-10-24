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
- **CSV**: *Sækja CSV með sniði* (formatting as HTML, for exact backups and re-import) and
  *Sækja CSV án sniðs* (plain text). Both open correctly in Excel (UTF-8 with BOM).
- **CSV import** with a preview before anything changes. Rows with an `id` update that entry, rows
  without one are created, entries missing from the file are left alone. Importing a plain CSV only
  changes cells whose text you edited; unchanged cells keep their formatting. Every import first saves
  a backup (downloadable from the import dialog); importing that backup undoes the import.

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
  includes/               Csv (pure PHP), Sanitize, Store, Import, Rest, Admin, Block
  block/                  block.json and editor placeholder
  src/admin, src/view     React apps
  data/                   seed.csv and kynslodir.json from the original page
migration/                extract.py, verify.py and the page snapshots
```
