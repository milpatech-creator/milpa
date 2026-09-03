# Rebuild Plan: AI Studio Demo → Real WordPress Platform

Source: `milpa-tech (4).zip`, an AI Studio export — React 19 + Vite +
Firebase, entirely a front-end demo. Every crop/user/marketplace record is
hardcoded in `src/App.tsx`; wallet, KYC/AML, and payments are simulated
(no gateway, no verification provider, no chain writes). Confirmed by
reading the actual component code, not assumed.

Decisions made (2026-09-02):
- **Scope:** build toward a real platform (real payments, real KYC/AML,
  possibly real tokenization) — not staying a demo.
- **Architecture:** full native WordPress rebuild, not an embedded React
  app.

## Gate before real money moves: Phase 0 (legal, non-technical)

Tokenizing crops and selling fractional shares with a projected yield is
the shape of a securities/collective-investment offering. In Mexico that
likely means CNBV oversight, possibly ITF registration under the Ley
Fintech, plus real KYC/AML obligations. This needs Mexican fintech/
securities counsel before Phase 2 (real payments, real KYC pass/fail
states) goes live — not something resolved by picking a plugin. Phase 1
below ships a fully live site on real infrastructure but keeps today's
simulated economics; no real investor funds are collected until Phase 0
clears.

## Feature → WordPress mapping

| Feature | Plugin/tool | Notes |
|---|---|---|
| Landing, About, blog/SEO | Pagelayer, SiteSEO (have) | Native rebuild |
| Cookie consent | CookieAdmin (have) | Required once collecting KYC data |
| Transactional email | GoSMTP (have) | Route all order/KYC/verification emails through it |
| Backups | Backuply (have) | Non-negotiable with real payment/identity data |
| Speed/caching | SpeedyCache (have) | Exclude dashboard/cart/account from full-page cache |
| Login security | Loginizer (have) | Confirm Pro tier includes 2FA |
| Docs/media | FileOrganizer (have) | Admin-side KYC document handling |
| Support | Deskuss (have) | Visitor→support only, not investor↔producer messaging |
| AI onboarding assistant | SoftWP (verify) | Check if it can take a custom prompt / call an external model |
| Commerce engine | WooCommerce (add) | Base for everything below — **done** |
| Multi-vendor marketplace | Dokan (add) | Vendor payout approval = escrow mechanism — **done** |
| Crop listings + investment data | ACF on WooCommerce products (add) | **Done** — see `wp-plugin/milpa-tech-core` |
| Producer/Investor/Trader roles | User Role Editor + Dokan vendor role (add) | Not yet built |
| Profiles, reviews | BuddyPress (add) | Installed, not yet configured |
| Investor↔producer messaging | BuddyPress DMs, or keep Firebase for real-time | Not yet decided |
| Wallet, portfolio dashboard | Custom (`milpa-tech-core`) | Not yet built |
| Transaction ledger view | Custom (`milpa-tech-core`) | Not yet built |
| Live price ticker | Custom (`milpa-tech-core`) | Real version needs a real pricing source — there is no public market for these tokens |
| Weather/soil/NDVI | Custom + real API/hardware partner | Phase 3 |
| AI chatbot | Custom Gemini bridge, or SoftWP if capable | Not yet built |
| KYC/AML intake form | FormLayer (add) | Not yet built |
| Real KYC/AML verification | Vendor (Truora/Metamap/Sumsub) + custom | Phase 2, gated on Phase 0 |
| Real payments | Mexico gateway (Conekta/OpenPay/Clip/Stripe) | Phase 2, gated on Phase 0 |
| Real blockchain tokenization | Specialist engagement | Phase 3, gated on Phase 0 |

## Phases

- **Phase 0** — legal/compliance groundwork (parallel, non-technical)
- **Phase 1** — foundation: live site, today's simulated economics *(in progress — see BUILD-LOG.md)*
- **Phase 2** — real money & real identity (gated on Phase 0)
- **Phase 3** — real tokenization & field data (gated on Phase 0)

Full original write-up (with design rationale) was also published as a
Claude artifact during planning; this file is the git-tracked version of
record going forward.
