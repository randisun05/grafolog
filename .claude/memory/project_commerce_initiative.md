---
name: project-commerce-initiative
description: "2026-08-06: Commerce and CMS initiative (pricing/discount/checkout/CMS/promo) - Fase A/B/D/E/F all complete, only Fase C (DOKU sandbox credentials) remains"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-06T19:44:39.451Z
---

After the 7-phase MGA roadmap (see [[project_mga_pivot]]) finished
2026-08-06, user asked how product/commerce management works - pricing,
discounts, payment, marketplace-style buying, homepage CMS, client
dashboard content, promotions. Verified against code (not assumed): none
of it existed as manageable features. Pricing was a hardcoded config
file, no discount/coupon system anywhere, DOKU payment backend existed but
had zero frontend checkout UI and empty credentials, no CMS for
homepage/dashboard content, no promotion system.

This is a new initiative, not a continuation of MGA - has its own
phase lettering (A-F) in root ROADMAP.md's "Inisiatif - Commerce & CMS"
section, deliberately not folded into the completed MGA 01-07 numbering.

Planning artifact published 2026-08-06 (data model, checkout sequence
diagram, ER diagram, 6-phase roadmap) - not saved to the repo, see chat
history for visual detail if needed.

All 6 decisions now resolved (2026-08-06):
1. Master tier price: no fixed number - user explicitly said pricing
   should be set in product management and stay changeable, connected to
   discounts/promotions. This confirms pricing_plans (Fase A, admin-
   editable) was the right call; 149000 stays as the seeded default until
   an admin changes it via /admin/pricing. Don't treat this as still
   blocking anything - it isn't.
2. Master's consultation session: manual (we'll contact you), not a
   real booking system.
3. Discount types: both percentage and fixed amount.
4. Self-service checkout adds a third sample-creation path alongside
   grafolog-direct and HR-import - doesn't replace either.
5. CMS scope: fixed editable fields (hero title/subtitle/CTA/features),
   not a full page-builder.
6. Not really a business question - a security gap found during review
   (payment-gate), fixed in Fase A, see below.

Fase A done 2026-08-06 (guratan-api@0639928+35330cc,
guratan-web@937c04c+06fcff9): pricing_plans table replaces
config/pricing.php (now deleted) - history-preserving (price change
deactivates the old row, creates a new one, never overwrites), admin CRUD
at /admin/pricing with AuditLog on every change (pricing is sensitive
business data, same principle as report-access logging). Public GET
/api/pricing for the future checkout page.

Real gap found and fixed while relating this plan to existing
features: StoreSampleRequest has allowed a client to self-create a
comprehensive/master sample via POST /api/samples since MGA Fase 02
(Project.source = 'client'), but ScoringController never checked
payment status before allowing that sample to be scored - a client could
get a full assessment for free through an endpoint that already existed,
just had no UI pointing at it. Fixed with HandwritingSample::
requiresPayment()/isPaid(), a 402 gate in ScoringController::
preview/submit, scoped only to source: client - grafolog-direct
and HR-imported samples are exempt (assumed separate/manual payment
arrangement, not yet confirmed by the business). 104 tests passing (up
from 92). Verified via a live Playwright run: admin edits a price, the
public pricing endpoint reflects it immediately.

Fase B done 2026-08-06 (guratan-api@40f9ece+c9470ef,
guratan-web@23c7e47+2015b22): discount_codes table, both
percentage/fixed types. DiscountCode::isValidFor()/amountOff() are the
single source of truth for validity/calculation - the preview endpoint
uses them now, Fase D's checkout must reuse the same methods rather than
reimplementing the checks. New POST /api/pricing/preview (tier +
optional code -> base/discount/final price) doesn't error on an invalid
code, it returns code_valid: false so a future checkout UI can show
inline feedback. Admin CRUD deliberately has no "edit" beyond activate/
deactivate - changing a used code's value would retroactively redefine
past redemptions; the pattern is deactivate-and-recreate.
DiscountCode::incrementUsage() exists but is called nowhere yet - it's
meant to fire at actual payment time (Fase D), not at preview.

Real bug found via test failures, now fixed: DiscountCode's
is_active/used_count have DB-level defaults but MySQL doesn't refetch
them onto the in-memory Eloquent model after create() - a freshly
created code's isValidFor() incorrectly returned false until
->fresh(). Fixed with matching protected $attributes = [...] on the
model. If you add another boolean/counter column with a DB default to
any model, either mirror it in $attributes or write a test that would
catch this class of bug - this one only surfaced because tests asserted
a freshly-created record's computed behavior, not just its stored
columns. 125 tests passing (up from 104).

Fase D done 2026-08-06 (guratan-api@165692d+66fea97,
guratan-web@a06370c+7c9c520): self-service checkout page (OrderView.vue,
/pesan, role: 'user'). Deliberately built as thin orchestration over
existing endpoints rather than a new combined "create order" endpoint -
POST /api/samples (existed since MGA Fase 02) then
POST /api/samples/{id}/payment (existed since old Fase 2, now extended
with an optional discount_code). payments gained base_amount +
discount_code_id for traceability. DiscountCode::incrementUsage()
finally gets called - from PaymentController::notification() on a
SUCCESS webhook, guarded against double-counting retried notifications,
not from preview (that would burn quota just from someone checking a
code). Added services.doku.callback_url so DOKU actually redirects the
client's browser back into the app after paying.

Real bug found and fixed while testing against current (unconfigured)
DOKU credentials: DokuService's RuntimeException was uncaught in
PaymentController::store, surfacing as a raw 500 with a full stack
trace in the response body whenever APP_DEBUG=true. Harmless in dev,
but a genuine leak risk if debug mode is ever accidentally left on in
production (path names, exception details, DOKU config hints all visible
to any client who hits it). Fixed with a try/catch converting it to a
clean 503 with a generic message, real error still logged server-side.
Test asserts the response has no exception/trace keys, not just the
status code - that's the part that actually matters.

What's verified vs. not: the entire checkout flow up through calling
DOKU is confirmed working (tier selection, discount application with
correct math, sample+payment creation) via a live Playwright run. The
actual DOKU redirect and payment completion cannot be tested without
real sandbox credentials (Fase C) - what's verified instead is that the
current unconfigured-DOKU state fails gracefully (clean error, not a
crash) rather than that a real payment succeeds. Don't claim the DOKU
integration itself is "tested" - only its error handling is.

Fase E done 2026-08-06 (guratan-api@126711c+e1af29e,
guratan-web@303ddfc+1ef8461): homepage CMS. content_blocks is a flat
key-value store gated to a fixed whitelist
(ContentBlock::EDITABLE_KEYS - 3 fields: eyebrow/tagline/CTA label),
not a page-builder - matches decision #5 exactly.
ContentBlockSeeder's defaults are the literal text that used to be
hardcoded in LandingView.vue, so turning the CMS on changed nothing
visible until an admin actually edited something. GET /api/content
returns a flat {key: value} object specifically because that's what the
frontend needs - resist "improving" it into a paginated list later
without checking LandingView.vue first. LandingView.vue keeps its old
hardcoded strings as an in-component fallback object, merged over by the
API response on mount - the homepage is never blank even if the CMS
backend is slow or down. 137 tests passing (up from 129). Verified with a
two-browser-context Playwright run: admin edits the tagline, then a
completely fresh guest session (no login, new context) loads the
homepage and sees the change - confirms the public endpoint and the
merge-over-defaults logic both work for a real anonymous visitor, not
just an authenticated test. Test content was reverted to the original
text after verification.

Fase F done 2026-08-06 (guratan-api@a7b2488+bd363f7,
guratan-web@e0590f6+6a351c4): dashboard promotion banners.
announcements table (title, body, is_active, optional independent
starts_at/ends_at window, optional target_roles json array - null
means all roles). Announcement::isVisibleTo(User) is the single source
of truth for visibility, mirroring DiscountCode::isValidFor()'s role -
GET /api/announcements filters Announcement::all() through it in PHP
(announcement volume expected to stay low, not worth a DB query). Unlike
PricingPlan/DiscountCode, admin CRUD allows full in-place edit
(including is_active) rather than deactivate-and-recreate - an
announcement has no "past usage" semantic worth protecting, so editing one
doesn't retroactively redefine anything. Every create/update is audit
logged. DashboardView.vue fetches announcements after its existing
summary fetch (non-fatal on failure) and renders them as dismissible
banners above the KPI cards - dismissal is per-browser-session only
(local dismissedIds ref, not persisted), so an active announcement
reappears on the next visit by design. 152 tests passing (up from 137).

Bug class proactively avoided, not just fixed: the exact same
MySQL-doesn't-refetch-defaults issue that broke DiscountCode in Fase B
(is_active staying stale in-memory after create()) was pre-empted on
Announcement by adding protected $attributes = ['is_active' => true];
from the start - all 15 new tests passed on the very first run, no repeat
failure. Worth remembering as a general pattern for this codebase: any new
boolean/counter column with a DB-level default needs its default mirrored
in the model's $attributes, or a test written that would actually catch
the gap.

Verified with a 3-context Playwright run: admin creates a client-targeted
announcement -> it appears as a banner on a client's dashboard -> dismissing
it removes it from that session -> a grafolog account (not targeted) never
sees it at all. Zero console errors.

Roadmap status - only Fase C left:
Fase A, B, D, E, F are all complete as of 2026-08-06. The entire
Commerce & CMS initiative now has exactly one remaining item: Fase C -
activate real DOKU sandbox credentials, verify the webhook payload
against a real test transaction. This isn't a decision or code blocker -
it's purely waiting on the user to supply the DOKU client id and secret
key env vars. Fase D's checkout UI is fully built and verified up to
the point of calling DOKU; only the real DOKU round-trip itself is
untested.

How to apply: all 6 original decisions are resolved and all 5
decision-dependent phases are shipped. The only thing that can move this
initiative forward now is the user providing DOKU sandbox credentials - if
they ask "what's left," the answer is exactly that, nothing else. If a
new ambiguity comes up once Fase C's real DOKU test transaction lands
(e.g. exact webhook field names differing from what the code currently
assumes), resolve that specific new question the same way - verify
against reality or ask, don't assume.

See [[project_mga_pivot]] for the platform this sits on top of,
[[project_overview]] for the original 3-tier pricing brief this partially
supersedes (tiers themselves haven't changed, only how prices are stored),
and [[project_token_system]] (2026-08-07) for the grafolog token feature
that reuses this initiative's DOKU integration and DiscountCode system.
