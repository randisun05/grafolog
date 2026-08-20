---
name: project-status
description: "Snapshot (2026-07-26, code-verified) of what's built vs. pending in guratan-api/guratan-web, and what's left"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9355ddff-ff22-468b-8c0a-a63f0a0e28f8
  modified: 2026-07-27T15:44:41.987Z
---

**Superseded 2026-07-25 snapshot said "ScoringEngineService not built yet" —
that was wrong/stale by 2026-07-26.** A prior session (not connected to this
memory) built much more than this memory knew about. This snapshot was
produced by directly reading code, running `php artisan route:list`,
`php artisan test`, `npm run build`, and starting both dev servers — not by
trusting old notes. Full detail now lives in `guratan-api/CLAUDE.md` and
`guratan-web/CLAUDE.md` (both created 2026-07-26; neither existed before).
**Read those two files first** for anything code-shaped — this memory is a
pointer/summary, not the source of truth.

**Done (guratan-api):**
- Full KB migrations/models + `GrafologiKnowledgeSeeder`, as before.
- `ScoringEngineService::generate()` — fully built and tested
  (`tests/Unit/ScoringEngineServiceTest.php`). See
  [[feedback-scoring-engine-design]] for the corrected design (the old design
  memory had two wrong claims about it).
- Sanctum auth (register/login/logout/me), rate limiting (`throttle` on all
  sensitive routes), Form Request validation (`Auth`, `Scoring`, `Sample`),
  audit logging (write via observer, read via `LogReportAccess` middleware).
- `handwriting_samples`, `personality_reports`, `report_aspek_scores`,
  `audit_logs` tables + models; `users.role` added.
- `AuthController`, `SampleController`, `ScoringController`,
  `ReportController`, `SindromController`, `UserLookupController` — 18 routes
  total, all wired.
- Rapid tier: upload flow works but scoring is an explicit random-score
  placeholder (`SampleController::generatePlaceholderRapidReport`) — no real
  CV, as intended.
- PDF export via `ReportPdfService` (barryvdh/laravel-dompdf).
- Email notification on report completion: `PersonalityReportObserver` →
  queued `SendReportCompletedNotification` job → `ReportCompletedMail`. Real
  code, correctly queued (`QUEUE_CONNECTION=database`). **Verified fully
  working 2026-07-27** with a real Gmail SMTP account: created a real
  report via Eloquent, flipped status to `completed`, confirmed job queued,
  ran `queue:work --once`, confirmed delivery with 0 `failed_jobs`. (This was
  first missed in the 2026-07-26 verification pass — I didn't check
  `app/Jobs`, `app/Mail`, `app/Observers` — then caught and corrected the same
  day when the user pasted a product brief claiming it worked. Lesson: a
  "done vs pending" sweep needs to walk every `app/*` subdirectory, not just
  Models/Services/Http.)
- **Fixed 2026-07-27**: the 500-vs-401 auth bug (see below) — was a real bug
  in `guratan-api`, not a red herring. Root cause: Laravel's
  `ApplicationBuilder::withMiddleware()` defaults to
  `redirectGuestsTo(fn () => route('login'))` before the app's own
  `bootstrap/app.php` callback runs; this API-only app has no `login` route.
  Fix: explicit `$middleware->redirectGuestsTo(fn () => null)` added to
  `bootstrap/app.php`. Confirmed 401 in both Accept-header cases,
  `php artisan test` still 6/6.
- **Fixed 2026-07-27 (security review)**: `ScoringController::submit` now
  rejects resubmission on an already-`completed` sample (422) — previously
  it would silently create a second `PersonalityReport` with no guard.
- **Fixed 2026-07-27**: `public/storage` symlink was dangling (pointed at
  the pre-reorg `/d/Project/guratan-api/...` path). Recreated via
  `php artisan storage:link`. Was a dormant bug — no frontend view currently
  renders `image_path` as an `<img>`, so nothing user-visible broke, but the
  upload feature's public URL was 404 the whole time until this was found.
- Test coverage: **41 tests as of 2026-07-27** (up from 6) — Feature tests
  for Auth/Sample/Scoring/Report controllers added, covering authorization/
  IDOR, validation, rate limiting, audit logging, PDF generation.

**Done (guratan-web):**
- All 9 planned views exist. `components/report/` and `components/scoring/`
  exist.
- **Built 2026-07-27**: `components/layout/AppNavbar` (extracted from
  `App.vue`), `components/upload/UploadDropzone` (real drag-and-drop, wired
  into `UploadView`), `components/shared/LoadingSpinner` (wired into 3
  views), `ToastNotification` + `composables/useToast.js` (wired into
  `ReportView`'s PDF-download failure path), `ProgressTracker` (wired into
  `PortalGrafologView`'s 3-step flow). All real usages, not unused shells.
  Build + lint pass. **Not visually verified in an actual browser** this
  session — only build/lint/dev-serve-200-OK were checked.
- Auth token storage bug (should be `localStorage`) — confirmed fixed.
- CSS/styling bug — confirmed fixed, design tokens applied.
- `npm run build` and `npm run dev` both confirmed working 2026-07-26 and
  again 2026-07-27 after the new components.

**Fase 1 of ROADMAP.md is now done as of 2026-07-27** (controller tests,
missing frontend components, security review — see [[reference-roadmap]]).

**Fase 2 started 2026-07-27 — payment gateway backend done, several things
still open (see [[reference-roadmap]] and `guratan-api/CLAUDE.md` "Payment
(DOKU)" section for full detail):**
- User picked **DOKU** as payment provider. Built: `payments` migration,
  `App\Services\Payment\DokuService` (Checkout API + HMAC-SHA256 signature,
  algorithm sourced from developers.doku.com docs 2026-07-27, not guessed),
  `PaymentController` (create payment + webhook notification receiver), 6
  new tests (total 47). Credentials go in `.env`:
  `DOKU_CLIENT_ID`/`DOKU_SECRET_KEY`/`DOKU_IS_PRODUCTION`, read via
  `config('services.doku.*')`. Currently still empty placeholders (like the
  earlier dummy-SMTP situation) — nothing will actually charge until real
  sandbox credentials go in.
- **Still open / not done:**
  1. Real DOKU sandbox credentials not yet supplied (same pattern as the
     SMTP dummy-credential situation from Fase 0).
  2. `config/pricing.php`: `comprehensive` = 49000 confirmed (matches root
     CLAUDE.md), **`master` = 149000 is an unconfirmed guess** — get the real
     number from the business.
  3. **No self-service checkout UI exists on the frontend at all** — and
     this isn't just "not built yet," it's a genuine architecture gap: the
     only existing sample-creation flow for comprehensive/master tier is
     grafolog-initiated (`PortalGrafologView`), where `user_id` (the payer)
     is the *client*, who never opens the app themselves. Before wiring any
     "Bayar Sekarang" button, the product flow needs to be decided — I
     raised this as an open question rather than guessing a checkout UX.
  4. Webhook body field names (`order.invoice_number`, `transaction.status`)
     are sourced from official DOKU docs but never cross-checked against a
     literal real notification payload — verify with one real Sandbox test
     transaction before trusting in production.
- Also done: `legal/privacy-policy.md` and `legal/terms-of-service.md`
  drafts (Indonesian, heavily bracketed `[ISI INI]` placeholders — company
  name, address, retention policy, refund policy — none of this is
  final/legal-reviewed), and `DEPLOYMENT.md` (env var checklist, DOKU
  production vs sandbox credential warning, `storage:link` reminder,
  Supervisor queue-worker example). Both are documentation/scaffolding only
  — no production server exists, no legal sign-off has happened.

**Known gaps / not built yet:**
1. `DeskriptifLookup` model exists but is dead code — never wired into
   `ScoringEngineService`. See [[feedback-scoring-engine-design]].
2. Two security findings from the 2026-07-27 Fase 1 review are **open
   product decisions, not fixed**: (a) rapid-tier uploaded images serve from
   a public disk with no ownership check (low practical risk — filenames are
   non-guessable hashes — but inconsistent with sensitive-data handling);
   (b) Sanctum tokens never expire (`config/sanctum.php` `expiration =>
   null`), no ability scoping. Both need the user to weigh UX/security
   tradeoffs, not a silent code fix. Full detail in `guratan-api/CLAUDE.md`'s
   "Open security findings" section.
3. guratan-web still has no test runner configured (no frontend tests
   exist at all, only backend Feature/Unit tests).

**Dev setup notes:**
- API must run on port 8123 (`php artisan serve --port=8123`) to match
  guratan-web's `VITE_API_URL`; the composer `dev` script's default 8000
  won't match unless overridden. **Gotcha (hit 2026-07-27):** port 8123 was
  once occupied by a stale `php artisan serve` process from
  `D:\Project\web-aspro` — this project's pre-reorg folder name (see root
  `CLAUDE.md`'s history note). A request to 8123 briefly returned a
  confusing 500 that looked like a `guratan-api` bug but was actually the
  wrong app entirely. Always check the exception trace's file paths (or
  `Get-Process -Id <pid from netstat> | select Path`) before trusting that a
  bug reproduced against 8123 is really in `guratan-api`.
- MySQL (`mysqld`) is NOT always running on this machine (Laragon) — start it
  before any DB-touching test; `php artisan tinker` DB calls will fail with
  "connection refused" otherwise.
- `.env` DB is real MySQL (`guratan_db`) — tests use sqlite in-memory
  (`phpunit.xml` override), never `RefreshDatabase` against the real DB.
- `LLM_PROVIDER=none` confirmed active — `NullLlmProvider` is a true
  passthrough, no network calls happen.
- `.env` `MAIL_USERNAME`/`MAIL_PASSWORD` are real (a Gmail account) as of
  2026-07-27 — don't ever echo/paste these into docs, commits, or chat; if
  mail suddenly stops sending, check whether they were reset to placeholders
  before assuming a code regression.

**Why:** this replaces a snapshot that had drifted significantly out of date
after just one day, because build work happened in a session this memory
wasn't connected to.
**How to apply:** when asked "what's next", check `ROADMAP.md` at project
root first (see [[reference-roadmap]]) — Fase 0 and Fase 1 are done; Fase 2
is in progress (payment backend done, frontend checkout flow and real
credentials still open, ToS/Privacy/Deployment are drafts). Don't suggest
building `ScoringEngineService`, Sanctum auth, controller tests, the missing
frontend components, or the DOKU backend integration again — they all exist
now. Don't invent a checkout UI unprompted — that's a flagged open question.
Before trusting any specific claim here after time has passed, re-verify
against `guratan-api/CLAUDE.md` / `guratan-web/CLAUDE.md` or the code itself,
the same way this snapshot was produced.

See [[project-overview]] for product context, [[feedback-guratan-principles]]
for constraints that still apply, [[feedback-scoring-engine-design]] for the
corrected scoring engine design.
