# Build Log

Running log of what's been done on the WordPress rebuild, in order. Newest
entry on top. See `docs/REBUILD-PLAN.md` for the overall architecture and
phasing this work follows.

---

## 2026-09-03 — Milpa AI chatbot (REST endpoint + widget)

Ported the original app's assistant — `AIChatBot.tsx` +
`server/geminiService.ts` — as a REST route (`POST /wp-json/milpa/v1/chat`)
plus a small vanilla-JS floating widget, bottom-right (Deskuss's own
support bubble already owns bottom-left).

Same three-tier strategy as the original: Gemini with search grounding,
Gemini without grounding, then a scripted fallback. Only the fallback
tier is live right now — the Gemini tiers are fully wired but inert
until `MILPA_GEMINI_API_KEY` is defined in `wp-config.php`, which
nobody's provided yet. The fallback text is ported close to verbatim
from the original per-crop replies, so it's a genuinely useful assistant
on its own, not a placeholder. Verified all the keyword-routing paths
(agave, café, maíz, aguacate, price queries, "how do I invest", generic)
in both languages via `curl` against the live REST endpoint before
touching the browser at all.

The system prompt's crop context now comes from `wc_get_products()` +
ACF's `get_field()` — i.e. the real live listings — rather than a second
hardcoded copy of the same four crops the original TS array had.

**Bug caught before it shipped:** the widget initially rendered *open*
by default on page load, when it's supposed to start collapsed. Cause:
`#milpa-chat-panel { display: flex; }` in the CSS has higher specificity
than the browser's built-in `[hidden] { display: none }` rule, so my own
style was winning and silently canceling the `hidden` attribute's
effect. Fix: an explicit `#milpa-chat-panel[hidden] { display: none; }`
rule, which has higher specificity than either. Caught by checking a
*fresh* browser tab rather than trusting the tab I'd already been
clicking around in — the first tab's state wasn't a reliable read, since
by then I could no longer tell whether "open" reflected the page's real
default or just something my own testing had already clicked.

Deployed via FTP, `MILPA_CORE_VERSION` bumped to 0.5.0 then 0.5.1 for
the fix.

---

## 2026-09-03 — Producer/Trader/Investor public signup

Public registration now assigns the right role — previously the three
roles existed (see the earlier entry below) but only an admin could
assign them via Users → Add User. Built on Dokan's existing customer/
vendor picker on the native WooCommerce registration form rather than a
separate signup page: see `includes/registration.php` for how the
Producer/Trader sub-choice is injected and how the role gets layered on
top of Dokan's own (so store pages, vendor dashboard, etc. all keep
working — those key off the literal "seller" role, so producer/trader
users now hold both roles at once, same idea for investor + customer).

Verified live end-to-end: registered a real account through the public
form as a Trader, confirmed via REST API it landed with
`roles: ['seller', 'trader']`, deleted the test account after.

Deployed via FTP as usual, `MILPA_CORE_VERSION` bumped to 0.4.0.

---

## 2026-09-03 — Remaining 3 crop listings + real product images

Created the other three crops from the original export's `initialCrops`
data (Café de Altura Sostenible, Aguacate Hass Regenerativo, Agave
Angustifolia-Espadín de Oaxaca) — all four listings from the original app
now exist as real WooCommerce products with the full Crop Investment
Details field set (token economics, NFT/traceability fields, development
log) and category assignments (Granos/Café/Frutales/Agave).

**How, since there's still no admin-panel file upload:** generated a
WooCommerce REST API key (Read/Write) and a WordPress Application
Password from wp-admin (both are button-click-generated tokens, not
typed passwords — the actual secrets aren't stored anywhere in this
repo). Product creation and ACF field data went through the WooCommerce
REST API (`/wc/v3/products`); ACF values were set via each field's
`meta_data` entry *plus* its paired `_fieldname` reference-meta entry
(pointing at the field key) — confirmed in wp-admin afterward that ACF
renders these correctly, exactly as if entered through the normal edit
screen.

**Images**, which were blocked all session, are now solved: WooCommerce's
`images: [{src: url}]` auto-sideload only accepts URLs with a real image
file extension, and Unsplash's URLs don't have one — so instead each
image was downloaded locally, uploaded as a proper Media Library
attachment via the core REST API (`/wp/v2/media`, raw binary body +
`Content-Disposition` header, no multipart form needed), and referenced
by attachment ID (`images: [{id: ...}]`). This is now the standard path
for any future image needs (product photos, avatars, etc.) — no browser
file-picker required.

One data problem, not a technical one: the original export's Unsplash
photo ID for the Agave listing was wrong — it rendered as a makeup
palette, not a plant. Found and verified a real blue agave field photo
before swapping it in; the original file was deleted from the Media
Library rather than left orphaned.

One process mistake worth flagging so it doesn't repeat: re-ran the
product-creation script a second time (to fix a category bug) without
checking it wasn't idempotent, which silently created three duplicate
products. Caught it by listing all products before assuming success, and
deleted the duplicates. Lesson: WooCommerce's create endpoint has no
built-in dedupe — any script that creates content needs to either check
for an existing record first or only be run once, deliberately.

---

## 2026-09-03 — Producer/Investor/Trader roles; marketplace switched Live

Added the three roles (`includes/roles.php`), cloning capabilities live
from Dokan's `seller` and WooCommerce's `customer` roles rather than
hardcoding a capability list — see the file's docblock for why. Producer
vs. Trader (both Dokan sellers) is tracked separately via a
`milpa_vendor_type` user meta field, editable on the user's profile page.

Also flipped **WooCommerce → Settings → Site visibility** from "Coming
soon" to "Live" (the site was fully built behind that flag this whole
time — the "Store coming soon" badge in the admin bar is now gone) and
confirmed **Dokan → Settings → Selling Options → Enable Selling** is
"Automatically," so a new vendor can list immediately after registering,
no manual approval step. Published the one crop product that was still
in Draft. Confirmed on the live `/shop/` page: the risk badge + funding
bar card display and the vendor role list all work end to end.

Deployed via the same FTP flow as the logo/favicon work — bumped
`MILPA_CORE_VERSION` to 0.3.0.

---

## 2026-09-03 — FTP access obtained; header logo + favicon shipped

Found the hosting provider (InterServer, via the domain's nameservers —
GoDaddy is only the registrar) and got FTP credentials for the account
`milpatec` on host `milpa.tech`. Confirmed via `curl ftp://...` that this
account has full read/write access to `public_html/test/` (the staging
site) — **and also to `public_html/` directly, which is a separate,
already-installed production WordPress site we have not touched.**
Everything from here on stays scoped to `public_html/test/`.

This unblocks the two things manual wp-admin access couldn't do:

1. **Direct plugin deploys.** No more zip-and-upload-through-wp-admin —
   changed files now go straight to `wp-content/plugins/milpa-tech-core/`
   via `curl -T`. Deploy steps updated in the plugin's own README.
2. **The site logo + favicon**, previously blocked entirely (no way to
   get an image file onto the server without file-upload access). Added
   `includes/branding.php`: injects the corn/circuit mark from the
   original export (`assets/img/milpa-mark.svg`, copied as-is from
   `public/milpa_tech_logo_transparent.svg`) next to the site title via a
   small `wp_footer` script, and sets it as an SVG favicon via `wp_head`.
   Done this way — rather than through the Customizer's Site Icon/Logo
   pickers — because those still require a Media Library upload, which is
   a different admin screen than the FTP access we now have; this was
   the faster, equally valid path for a block theme with no classic
   `header.php` to edit.

One snag: after the first FTP deploy, the logo showed up but rendered
huge — the browser was serving a cached copy of `milpa-brand.css` from
its exact previous URL (`?ver=0.1.0`, unchanged). Fix: bump
`MILPA_CORE_VERSION` on any CSS/JS-touching deploy, not just PHP-logic
changes — it's what busts the cache. Confirmed fixed after bumping to
`0.2.0` and reloading.

**Credential handling:** the FTP password was shared in chat, used
directly in local `curl` commands, and is not written into any file in
this repo (check `.gitignore` / grep before committing if that ever
seems necessary — it shouldn't).

## `milpa-tech-core` deployed to staging (earlier same day)

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
