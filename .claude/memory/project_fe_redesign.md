---
name: project-fe-redesign
description: "2026-08-07: homepage redesigned from single hero block to full company-profile page, new 'Kertas Berani' palette, CMS expanded to 21 fields - complete"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-07T07:09:08.351Z
---

User said the homepage FE was too simple/monotonous ("sederhana dan
monoton") - verified true: LandingView.vue was literally one hero block
(eyebrow, H1, tagline, 2 buttons), nothing else. Asked for a
company-profile-style page with multiple interactive sections (cara kerja,
harga, keuntungan, cara daftar), and for the same energy applied to client
and admin dashboards.

Process followed (matches this project's established pattern of
mockup-before-code for big product decisions, see [[project_mga_pivot]]
and [[project_commerce_initiative]]): built a throwaway interactive HTML
mockup artifact first (tabs for Beranda/Dashboard Klien/Dashboard Admin),
discussed it, then implemented into the real Vue codebase only after the
user confirmed direction - including a live side-by-side color palette
comparison inside the same mockup before committing to one.

Color decision: user found the existing warm-cream palette (#f8f3e7
paper, muted oxblood #9e3b3b seal) "terasa tua dan kaku" (feels old and
stiff) when asked to target Gen Z. Presented two concrete directions in
the mockup - a dark "night journal" direction vs a bright higher-contrast
"Kertas Berani" (Bold Paper) direction - user picked Kertas Berani.
Deliberately avoided two AI-design cliches while doing this: didn't just
brighten into "near-black + one neon accent" (flagged explicitly as a
common generic AI look), and kept the same ink/seal/sage/gold four-hue
family rather than introducing generic new hues - just raised saturation
and contrast. Final tokens: paper #fffcf7/#ffffff, ink #191330, seal
#e0483f, sage #2f9e63, new gold #c99a35 (Master-tier accent). Both
light and dark mode token blocks in base.css were updated to match.

Scope decisions made without being asked, worth remembering:
- Only the beranda (homepage) needed new CMS work. The client and
  admin dashboards the user also asked about already had proper content
  management via existing systems (Announcements for promo banners,
  Pricing/DiscountCode APIs for product cards) - no new backend work
  needed there, just visual polish in the mockup stage.
- Sindrom/Aspek names shown in the public-facing homepage explorer are
  softened, natural Indonesian marketing copy
  (e.g. "Authoritarian" -> "Ketegasan"), explicitly requested by the user
  ("jangan pake bahasa inggris... tidak plek ketiplek dengan sindrom
  knowledge"). This is static data hardcoded in LandingView.vue,
  deliberately not wired to the real sindrom/aspek KB tables - the
  technical English terms remain unchanged everywhere else (grafolog
  scoring form, generated reports). Don't confuse this presentation-layer
  translation with a real KB localization effort (see
  [[project_overview]]'s note that the KB itself is still un-translated
  English, a separate open item).
- AppNavbar is already global (mounted in App.vue), so the redesigned
  LandingView.vue does not duplicate a nav bar, unlike the mockup which
  had its own for standalone preview purposes.

Technical approach for full-bleed sections: the app's shared
.app-main wrapper in App.vue constrains every route to a 960px padded
column. Rather than changing that shared layout (which every other page
depends on), LandingView.vue breaks out of it locally via a scoped
negative-margin technique (margin: 0 calc(50% - 50vw)) so its
alternating section backgrounds can run edge-to-edge. This pattern is
worth reusing if another page ever needs full-bleed sections without
touching the shared app shell.

CMS scale-up: ContentBlock::EDITABLE_KEYS grew from 3 to 21 fields
to cover every new section's heading/subtext, plus a new
ContentBlock::LIST_KEYS concept for the 4 fields holding repeated items
(comparison bullets, cara-kerja steps, honesty points) - stored as
JSON-encoded strings in the same plain text column (no schema change),
rendered by AdminContentView.vue as small fixed-count structured list
editors, never raw JSON textareas. Still strictly "fixed fields, not a
page-builder" per the original Commerce Fase E decision - an admin edits
text within fixed slots, can't add/remove items.

Fully verified via live browser test: hero renders from CMS, pricing cards
pull real Rp100.000/Rp149.000 from GET /api/pricing, Sindrom
accordion works with the translated labels, and an admin edit to a list
field appeared on the public page immediately after reload. 192/192
backend tests passing.

How to apply: if asked to redesign the client or admin dashboard UI
next (the mockup covered both, only the homepage was actually
implemented), the same mockup-first workflow applies - but remember
dashboards likely need less new backend CMS work than the homepage did,
since promo/product content there is already dynamic.

See [[project_commerce_initiative]] for the pricing/announcement systems
this reuses, [[project_overview]] for the still-open KB translation gap.
