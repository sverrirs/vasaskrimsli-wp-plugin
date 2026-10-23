# Local test site (WordPress Playground)

A throwaway WordPress on http://127.0.0.1:9400 with the plugin active, the 390 seeded
entries and an Orðsifjafræði page. Nothing here touches vasaskrimsli.is.

```bash
npm run build && python scripts/package.py
python -c "import zipfile;zipfile.ZipFile('dist/vasaskrimsli.zip').extractall('.playground')"
MSYS_NO_PATHCONV=1 npx @wp-playground/cli@3.1.60 server --wp=7.1.3 --php=8.3 --port=9400 \
  --mount-dir .playground/vasaskrimsli /wordpress/wp-content/plugins/vasaskrimsli \
  --blueprint=tests/playground/blueprint.json
```

Log in at http://127.0.0.1:9400/wp-login.php as the local test editor `ritstjori`; the password is in
`blueprint.json`. The account only exists inside this throwaway local site.
