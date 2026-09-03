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

This plugin isn't installed on the staging site yet — it currently only
exists here in git. Earlier setup work (the ACF field group, the shop card
styling, the font) was prototyped directly on the site via the Code
Snippets plugin so we could see it working immediately; this plugin is the
version-controlled replacement for those two snippets.

To deploy:

1. Zip the `milpa-tech-core` folder.
2. In wp-admin: **Plugins → Add New → Upload Plugin**, upload the zip, activate.
3. Deactivate the two Code Snippets entries ("Milpa Tech: Crop Investment
   Fields (ACF)" and "Milpa Tech: Brand Fonts + Global Styles") so the same
   rules aren't registered twice.

Uploading has to be a manual step for now — the assistant driving this repo
doesn't have file-upload access to the site's browser session. Once the
site has SFTP/SSH or a git-deploy hook available, this step can be
automated.
