# Build Log

Running log of what's been done on the WordPress rebuild, in order. Newest
entry on top. See `docs/REBUILD-PLAN.md` for the overall architecture and
phasing this work follows.

---

## 2026-09-03 — `milpa-tech-core` deployed to staging

Zipped the plugin (git commit `a722fd7`) and the user uploaded it via
**Plugins → Add New → Upload Plugin**. First activation attempt hit
WordPress's fatal-error protection and got auto-deactivated: the two
Code Snippets entries were still active and declared the same PHP
function names (`milpa_register_crop_fields()`, etc.) as the plugin,
so activating it tried to redeclare them. Fix: deactivate the two
snippets first, then activate the plugin. Original deploy instructions
had the order backwards — corrected in the plugin's own README.

Verified after activation: shop page still shows the risk badge/funding
bar/yield on the "Maíz Criollo Orgánico" card, and the homepage hero
still renders correctly — same output as the snippets, now from
version-controlled code. The two Code Snippets entries are left in
place but deactivated (not deleted) as a fallback.

## 2026-09-02/03 — Foundation, crop schema, first listing, homepage + shop styling

**Context:** Converting the `milpa-tech (4).zip` AI Studio export (React 19
+ Firebase demo — see repo root) into a real WordPress site at the staging
environment `milpa.tech/test`. Decided approach: full native WordPress
rebuild, targeting real functionality eventually, gated behind a legal/
compliance review before any real money or KYC/AML flows go live (see
Phase 0 in the rebuild plan).

**Plugin foundation installed & configured on staging:**
- WooCommerce — currency set to MXN, store country Mexico (placeholder
  address, needs the real registered business address later)
- Dokan (free/Lite) — multi-vendor marketplace, setup wizard completed
  (physical products, vendor-managed delivery, local store management)
- Advanced Custom Fields (free tier — no repeater fields, so the crop
  "development log" is a single manually-updated snapshot for now, not a
  timestamped history)
- BuddyPress — for community profiles / reviews (not yet configured)
- Code Snippets — installed as a stopgap so custom PHP could be added
  through wp-admin without SFTP access; **now superseded** by the
  `milpa-tech-core` plugin in `wp-plugin/` (see below)

**Crop investment data schema:** built the "Crop Investment Details" ACF
field group (location, crop type, risk level, token price, total/sold
tokens, projected yield, harvest date, sustainability score, NFT/
traceability fields, development-stage snapshot) on the WooCommerce
product type. NFT/traceability fields intentionally left blank on real
listings — instructions note they stay empty until Phase 3 tokenization is
legally cleared.

**First real listing:** "Maíz Criollo Orgánico" — $50 MXN/token, 10,000
total tokens, 6,500 sold, 12.5% projected yield, harvest 15 Nov 2026,
sustainability score 95 — populated from the real data in the export's
`App.tsx`, not placeholder values.

**Shop page styling:** WooCommerce product cards now show a risk badge
(BAJO/MEDIO/ALTO, color-coded), a funding progress bar (tokens sold /
total), and projected yield — pulled live from the ACF fields via a
`woocommerce_after_shop_loop_item_title` hook. Verified working on the
live shop page.

**Homepage:** built and published a branded "Inicio" page (set as the
site's static front page) — hero, three feature cards (Tokenización,
Gestión de Riesgos con IA, Agricultura Regenerativa), impact stats
(+2,500 hectáreas, $5M inversión, 150+ productores, 100% trazabilidad),
closing CTA to the marketplace. Copy is the real bilingual copy from the
export's translation dictionary, not rewritten. Brand system (colors,
Inter font) reverse-engineered from the export's actual Tailwind classes
and the logo SVG's gradient, not invented fresh.

**Migrated to version control:** the ACF field group and the brand CSS /
shop-display logic were first prototyped live on staging as two Code
Snippets entries (fastest way to iterate with only wp-admin access, no
SFTP). They've now been rewritten as a proper plugin — `wp-plugin/
milpa-tech-core/` — for real code review and safe deploys going forward.
**This plugin has not been uploaded to the site yet** — see its own
README for the deploy steps, which currently require a manual upload
since this session has no file-upload access to the site.

**Known rough edges / next up:**
- Site brand (logo, favicon) not applied yet — needs a Media Library
  image upload, which also isn't possible from this session without
  file-upload access. Either the user uploads the exported logo files
  manually, or we get SFTP/git-deploy access.
- Shop card "Read more"/Add-to-cart button isn't picking up the brand
  green in the block-theme product grid (Twenty Twenty-Five renders it
  via a different markup path than the classic hooks the CSS targets).
- Store address on the WooCommerce settings is a placeholder (Ciudad de
  México) — needs the real registered business address.
- Producer/Investor/Trader role distinction not yet built (currently just
  Dokan Vendor vs. WooCommerce Customer).
- Only one of the four sample crops has been entered as a real listing.
