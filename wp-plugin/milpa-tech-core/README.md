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
| `assets/css/milpa-brand.css` | Brand tokens + WooCommerce card/button restyling |

## Deploying to the site

**Status: installed and active on `milpa.tech/test`** as of 2026-09-03.
It was first prototyped directly on the site as two Code Snippets entries
(fastest way to iterate with only wp-admin access); those are now
deactivated in favor of this plugin.

Deploy steps, for future updates (e.g. a v0.2.0 zip):

1. Zip the `milpa-tech-core` folder.
2. **Deactivate first**, if applicable, anything defining the same PHP
   function names — e.g. the two Code Snippets entries mentioned above.
   Activating this plugin while they're still active causes a fatal
   "cannot redeclare function" error, since both sides declare
   `milpa_register_crop_fields()` etc. in the global namespace. (This bit
   us once already — see BUILD-LOG.md.)
3. In wp-admin: **Plugins → Add New → Upload Plugin**, upload the zip.
   If a previous version is already installed, delete it first (WP won't
   overwrite an active plugin via upload).
4. Activate.

Uploading has to be a manual step for now — the assistant driving this repo
doesn't have file-upload access to the site's browser session. Once the
site has SFTP/SSH or a git-deploy hook available, this step can be
automated.
