---
name: project-overview
description: "What Guratan is (grafology SaaS), its 3 tiers, and the 8-Sindrom/40-Aspek/704-Indikator knowledge base structure"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9355ddff-ff22-468b-8c0a-a63f0a0e28f8
  modified: 2026-07-26T04:06:21.391Z
---

Guratan is an Indonesian-language SaaS for graphology (handwriting) analysis, backend repo name "guratan-api" (Laravel, at d:\Project\grafolog). Three tiers:
- **Rapid**: free, automated CV-based scoring from a photo upload — NOT built yet, deliberately postponed to post-MVP.
- **Comprehensive** and **Master**: paid tiers. A certified grafolog manually measures handwriting and fills in scores for 40 aspects via a form (Portal Grafolog).

Scoring and report narrative are driven by a real knowledge base converted from the user's Excel file: **8 Sindrom → 40 Aspek → 704 Indikator**. All DB tables use standard Laravel auto-increment `id` PKs; the original Excel codes are kept in a `kode` column for reference only, never as the PK.

**Why:** the manual-measurement tiers are the actual product (grafolog expertise as a service); Rapid/CV is a future automation layer on top, not required for MVP.
**How to apply:** don't suggest building CV/image-scoring features unless the user explicitly moves Rapid tier into scope. When touching scoring/report code, remember the KB is 3 levels deep (Sindrom → Aspek → Indikator) and codes from Excel are non-PK reference fields.

**Business/product context (added 2026-07-26, from a product brief the user shared — full text now lives in the repo's root `CLAUDE.md`):**
- **Positioning is deliberate and load-bearing**: graphology has weaker scientific grounding than standard psychometrics (Big Five etc.), so the product is explicitly framed as a *reflective insight tool*, never a clinical diagnostic. This isn't a footnote — it should shape copy, UI language, and any B2B/recruitment-facing feature.
- **Markets**: B2C (individual self-insight, playful/interactive UX) now; B2B (HR screening) is deliberately post-MVP, not current focus.
- **Pricing**: Rapid free, Comprehensive ~Rp49rb/laporan, Master premium (+ live consult with a grafolog).
- **Why Rapid=AI but Comprehensive/Master=manual human grafolog is intentional**: the paid tiers, used for higher-stakes decisions, lean on certified-human judgment specifically because it's more credible and more legally defensible than an AI-accuracy claim would be. Don't suggest replacing manual scoring with CV/AI for the paid tiers even post-MVP without flagging this tradeoff.
- **Additional KB detail**: 37 grafometric measurement variables (margin, spacing, slant, pressure, etc.) with precision-range categories (mm/ratio) — these are the manual-grafolog measurement reference, separate from the 8 Sindrom/40 Aspek/704 Indikator narrative layer.
- **Deliberately-not-built list** (explicit product decisions, not oversights): B2B dashboard, batch processing, culture-fit matching, psychologist marketplace, payment gateway, final Privacy Policy/ToS, production deployment, Indonesian-language narrative translation (KB narrative text is still English).
- **Four guiding principles** stated by the user, applicable to all technical decisions: (1) never overclaim scientific accuracy — always frame as reflective insight, especially in B2B/recruitment contexts; (2) the data is sensitive psychological data → security/audit logging is mandatory, not optional (see [[feedback-guratan-principles]]); (3) use LLM as minimally/defensively as possible (cache, not live per-user calls) — for cost AND privacy; (4) MVP-first — don't build features not yet needed (CV, B2B dashboard, etc.).

See [[project-status]] for what's built vs. pending, and [[feedback-guratan-principles]] for hard constraints to respect while building.
