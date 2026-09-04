# Milpa Tech Core

Custom WordPress plugin holding the site logic that doesn't come from an
installed plugin: the tokenized-crop data schema, the marketplace card
display (risk badge / funding bar / yield), and the brand font + styles.

## Requires

- WooCommerce (crop listings are WooCommerce products)
- Advanced Custom Fields (the "Crop Investment Details" field group)
- Dokan recommended, for multi-vendor selling (not a hard dependency)

## What's in here

| File | Purpose |
|---|---|
| `milpa-tech-core.php` | Plugin bootstrap |
| `includes/fields-crop-investment.php` | Registers the ACF field group on WooCommerce products |
| `includes/shop-display.php` | Renders risk badge / funding bar / yield on shop-loop cards |
| `includes/assets.php` | Enqueues the Inter font + brand stylesheet |
| `includes/branding.php` | Header logo mark + SVG favicon (see note below on why this is JS/wp_head-based rather than the Customizer) |
| `assets/css/milpa-brand.css` | Brand tokens + WooCommerce card/button restyling |
| `assets/img/milpa-mark.svg` | The corn/circuit logo mark, copied as-is from the original export's `public/milpa_tech_logo_transparent.svg` |

## Deploying to the site

**Status: installed and active on `milpa.tech/test`** as of 2026-09-03.

As of the FTP credentials obtained 2026-09-03, deploys go straight to the
server — no more manual zip uploads through wp-admin:

```bash
BASE='ftp://milpa.tech/public_html/test/wp-content/plugins/milpa-tech-core'
curl -u 'USERNAME:PASSWORD' -T milpa-tech-core.php "$BASE/milpa-tech-core.php"
# ...one -T per changed file, --ftp-create-dirs if a new folder is involved
```

Credentials are **not** stored in this repo — they were shared out of band
(chat) and live only in the local shell history / password manager. Ask
whoever set up the FTP account (cPanel → FTP Accounts, host `milpa.tech`,
user `milpatec`) if they need to be rotated or re-shared.

**Bump `MILPA_CORE_VERSION`** in `milpa-tech-core.php` on every deploy that
touches CSS/JS — it's the cache-busting query string
(`milpa-brand.css?ver=X`), and without a bump, sites that already loaded
the old asset will keep serving it from browser cache indefinitely.

The plugin was first prototyped directly on the site as two Code Snippets
entries (fastest way to iterate before FTP access existed); those are now
deactivated in favor of this plugin, left in place as a fallback rather
than deleted.

If a fresh install (rather than an update) is ever needed again — e.g. a
new environment with no FTP access yet — fall back to zipping the folder
and using **Plugins → Add New → Upload Plugin** in wp-admin. In that case,
**deactivate anything defining the same PHP function names first** (e.g.
those two Code Snippets) — activating this plugin while they're still
active causes a fatal "cannot redeclare function" error. (This bit us once
already — see BUILD-LOG.md.)
