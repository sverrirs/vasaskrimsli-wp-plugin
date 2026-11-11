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

### Skrímslaskrá (`features/ordsifjar/block-skra/`, same data)
- **Block "Skrímslaskrá"** (or shortcode `[skrimslaskra]`): a picture grid of every entry with a popup card.
  Buttons at the top show every monster, only Mega forms or only Gígamax forms (`SYNIR` in `src/view/safn.js`).
  The heading of the generation in view stays at the top while scrolling.
- **Forms**: special and regional forms with Icelandic names (`data/formir.json`, `assets/sprites/formir/`).
- **Safnið mitt**: visitors collect up to 50 monsters or forms (kept in their browser only) and download them as
  - *Sækja PDF*: one A6 card per monster (`src/view/kort.js`), or
  - *Bókamerki*: A4 landscape sheets of five 52 x 190 mm bookmarks with cut marks (`src/view/bokamerki.js`).
  Both are drawn on a canvas in the browser and written by `src/view/pdf.js`; no PDF library.
- **Pictures in circles** are placed by each sprite's own midpoint (`data/midjur.json`, `iHring()` in
  `src/view/safn.js`), the same calculation as the printed books.

### Bókaskrá: the book lists (`features/baekur/`)
- **One block per series**, all named "Verslun: …" (or `[baekur rod="lestrarbaekur"]`): Orðsifjaskrá Vasaskrímslanna,
  Vasaskrímslabækurnar, Lestrarbækurnar, Auðlesið, Verkefnahefti, Veggspjöld, Smáspjöld. A block is only the series'
  list and its filter (all, on sale, coming): the headings and texts about the books are written on the WordPress page
  around it, so they can be edited without a new plugin version. Verkefnahefti go on a page of their own.
- **Each block's settings** (toolbar and the "Útlit listans" panel): alignment left, center or right; horizontal rows
  or a vertical list; the gap between items (0 to 120 px, 24 by default); the size of the pictures and text (small,
  medium, large). The shortcode takes the same: `jofnun`, `stefna` (`larett`, `lodrett`), `bil`, `staerd`
  (`litil`, `midlungs`, `stor`).
- **Status words** agree with what a list shows (`kyn` of the series): books "Fáanleg", lots of books "Fáanlegar",
  volumes, booklets and posters "Fáanlegt" (`Block::ORD`).
- **Lots:** Lestrarbækurnar are sold ten to a lot (1 to 10, 11 to 20, ...) and Auðlesið in three (A to H, I to R and
  S to Ö: 10, 11 and 11 books), so their lists show the lots; a lot's viewer lists its books and shows each one.
- A cover opens the viewer: the cover and three pages (in a lot the arrows go on through every book, and round), the
  page count, size and ISBN folded away under the pictures, and for what is on sale the shop's own
  **Buy Button** (options, price, "Bæta í körfu", the cart on this page). The address becomes `…#bok=<id>`, so a book
  or lot can be linked to directly.
- **No database.** The catalogue is `data/baekur.json` and the pictures in `assets/`, both made by
  `migration/baekur.py` from the hand-edited `migration/baekur-skra.json` and the books' print PDFs in their own
  repositories (`../vasaskrimsli-storubaekur`, `../vasaskrimsli-lestrarbaekur`, `../vasaskrimsli-audlesid`,
  `../vasaskrimsli-ordsifjaskra`, `../vasaskrimsli-excerises`). Posters use pictures downloaded once.
- **Prices** come from Háskólaprent's Shopify store: the plugin reads each product every six hours
  (`includes/Verslun.php`), and the copy in `baekur.json` is the fallback, so the page never waits on the store.

**Maintaining it** (edit `migration/baekur-skra.json`, then run `python -X utf8 migration/baekur.py`):

| To | Do |
|---|---|
| Put a book on sale | set its `"stada": "faanleg"` and `"vara"` to the product's handle in the shop (the end of its address, `haskolaprent.is/products/<handle>`) |
| Put a lot on sale | the same, on the lot in the series' `pakkar` (its `baekur` are the numbers of its books) |
| Hide a book | `"stada": "falin"` (`"vaentanleg"` shows it as coming) |
| Add a book | add it to its series' `baekur` with a new `id` (a-z, 0-9 and dashes), after building it in its repository |
| Choose the sample pages | `"synishorn": [3, 10, 11]` (page numbers); otherwise three are picked, one from each third of the book, the most colourful of a few pages that differ from book to book |
| Change the Buy Button's look | `verslun.kaupahnappur.options`: the `options` of Háskólaprent's embed code, with the owner's changes |
| Add a series | a new entry in `radir` with a `heimild` (where its PDFs are), and a line in `Block::RADIR` for its own block |

The script renders only pictures whose PDF changed, keeps the last pictures of a book whose PDF is being rebuilt (with
a warning), and removes pictures no book uses. The store answers Python's own HTTP client with 429, so it reads the
products through `curl`.

## Data from vasaskrimsli-dict

All names and texts come from `../vasaskrimsli-dict` (local only). The Claude session working there
("Pokémon monster data index") sends a message when something changed; rebuild here, then report back to it.
Never release without the owner's go.

| Plugin file | Script | Reads in vasaskrimsli-dict | Reaches live sites |
|---|---|---|---|
| `data/breytingar-<N>.json` | `migration/breytingar.py N`, then `migration/lysing.py N` | `ideas/lokalisti/lokalisti.json` (nafn, upprunaord_html, thyding_html), `ideas/lysing-2026/` | by data version N (below) |
| `data/formir.json` + `assets/sprites/formir/` | `migration/formir.py` | `ideas/formheiti.json`, `ideas/gigamax.json`, saved pokemondb pages | shipped as it is with each release |
| `data/throun.json` | `migration/throun.py` | `data/monsters/*/pokemondb.json` | shipped as it is |
| `data/midjur.json` | `migration/midjur.py` | `ideas/myndir/midjur.json` (run `tools/midjur.py` there after changing sprites) | shipped as it is |

The plugin does not read the Pokédex texts (`lokalisti.json` → `pokedex`) at all.

**Data versions** (`Upgrade::VERSION`, now 10) change entries already on live sites. They run once, on the first
wp-admin page load after an update: a backup CSV is written first, and a field changes **only where the site still
shows the released text** (`fyrri`), so anything edited in wp-admin is kept and listed as skipped in the notice.

- A released `breytingar-<N>.json`, `nofn-2026.json` or `thyding-2026.json` must **never be edited**: together
  they are the baseline the next version is compared with.
- New dict changes after a release: `python -X utf8 migration/breytingar.py <N+1>`, then
  `python -X utf8 migration/lysing.py <N+1>` (it adds to the same file), set `Upgrade::VERSION` to N+1.
- Changes before a version is released: delete `breytingar-<N>.json` and run both scripts again for N.
- Check that no field gains or loses a `<br>` (the dict has lost line breaks before), and that names only change
  when the dict session says so.
- `tests/run.php` applies every `breytingar-<N>.json` in order to a simulated live site and fails if any field
  would be skipped.

### Nöfn 2026 (data versions 3 and 4, history)

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

Only after the owner says yes in chat. The branch is `main`.

1. Add an entry at the top of `== Changelog ==` in `readme.txt` (Icelandic). Check `git log` since the last tag
   for commits made by other sessions, so they are in the entry too.
2. Bump the version in `vasaskrimsli.php` (header `Version:` and `VASASKRIMSLI_VERSION`), `Stable tag` in
   `readme.txt` and `version` in `package.json`.
3. Run the PHP and JS tests, `npm run build` and `python -X utf8 -I scripts/package.py --expect-version X.Y.Z`.
4. Commit "Version X.Y.Z", `git push origin main`, then `git tag vX.Y.Z && git push origin vX.Y.Z`.
5. The Release workflow tests, builds and publishes `vasaskrimsli.zip` (`gh run watch`, `gh release view vX.Y.Z`).
   The site then shows "Update available" under Plugins; a new data version runs on the first wp-admin load.

## Development

```bash
npm install
npm run build                      # React apps -> build/
npm test                           # JS tests (search, collection, bookmarks, midpoints)
"E:\SDKs\php\8.3.7\php.exe" tests/run.php   # CSV/import tests (pure PHP)
python -X utf8 migration/verify.py          # seed vs. the original page, cell by cell
python scripts/package.py          # -> dist/vasaskrimsli.zip
```

A local test WordPress with the plugin: see `tests/playground/README.md`. On 2026-11-08 Playground hung at
startup (502 for 10 minutes) and was not used for data version 10; the PHP tests cover the upgrade itself.

## Layout

```
vasaskrimsli.php          plugin header and bootstrap
includes/                 shared PHP (asset loading, GitHub updater)
shared/js/                shared JS (Icelandic search folding)
features/ordsifjar/
  includes/               Csv, Format, Nofn (pure PHP), Sanitize, Store, Import, Upgrade, Rest, Admin, Block
  block/, block-skra/     block.json and editor placeholders (Orðsifjafræði, Skrímslaskrá)
  src/admin, src/view     React apps; view also has kort.js (A6 cards), bokamerki.js, pdf.js, safn.js
  data/                   seed.csv and kynslodir.json from the original page, national.json, nofn-2026.json,
                          thyding-2026.json, breytingar-<N>.json, formir.json, throun.json, midjur.json
  assets/sprites/         <n>.webp (96 px), large/ (256 px), formir/ (forms)
features/baekur/          the book lists: includes/ (Block, Verslun), src/ (React), data/baekur.json, assets/
migration/                one script per data file (see "Data from vasaskrimsli-dict"), baekur.py and baekur-skra.json
```
