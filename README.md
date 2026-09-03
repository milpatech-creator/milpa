# Milpa Tech

Bilingual agricultural tokenization & regenerative farming investment
platform. Rebuilding from an AI Studio (React/Firebase) demo export into a
real WordPress site.

- **`milpa-tech (4).zip`** — the original AI Studio export. Source of
  truth for real copy, data, and design tokens; not itself deployed.
- **`docs/REBUILD-PLAN.md`** — architecture decisions, the feature →
  WordPress plugin mapping, and the phase gating (notably: real payments/
  KYC/tokenization are gated behind a legal review, tracked as Phase 0).
- **`docs/BUILD-LOG.md`** — running log of what's actually been done, in
  order. Read this for current status before assuming anything in the
  plan is finished.
- **`wp-plugin/milpa-tech-core/`** — the custom WordPress plugin holding
  site-specific logic (crop data schema, marketplace card styling, brand
  assets). Version-controlled here; **not yet uploaded** to the staging
  site (see that folder's README for the manual deploy step).

Staging site: `milpa.tech/test` (WordPress, not this repo — no
SFTP/git-deploy connected yet, so changes here don't go live automatically).
