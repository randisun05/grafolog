# guratan-api

Laravel 13 (PHP ^8.3) backend for Guratan, an Indonesian-language SaaS for
grafology (handwriting) analysis. Verified against actual code on 2026-07-26 —
this file describes what exists, not a plan.

## Product context

**Rapid tier retired 2026-08-01** (MGA pivot decision, see root
`ROADMAP.md` "Pivot — Master Graphology Assistant"): `StoreSampleRequest`
only accepts `tier: comprehensive|master` now — a `rapid` value gets a 422.
The old placeholder-CV code path (`SampleController::generatePlaceholderRapidReport`)
was deleted since it became unreachable. **Existing rapid samples/reports in
the DB are untouched and still fully readable** via `GET /api/samples/{id}`
and `GET /api/reports` — only *creation* of new rapid samples is blocked.
If you're reading this expecting a free/CV tier to exist going forward, it
doesn't; don't resurrect `generatePlaceholderRapidReport` without an
explicit new user decision reversing the retirement.

Two tiers now:
- **Comprehensive** / **Master**: paid. A certified grafolog manually scores
  40 aspek per sample via `ScoringController::submit`.

**`Project` entity added 2026-08-01** (MGA pivot Fase 02): every
`HandwritingSample` now belongs to a `Project` (`project_id` FK, nullable at
the DB level but always set by `SampleController::store`). A Project has a
`source` enum (`grafolog`/`hr`/`client`) recording who initiated it — this is
a **different axis than `tier`**, not a replacement for it; `tier`
(comprehensive/master) still lives on the sample and still drives pricing.
Schema-wise `Project` supports 1 project : many samples (agreed with the
user for the HR bulk-candidate case). `SampleController::store` (the
original grafolog/self-service flow) still always creates exactly one new
`Project` per new sample — **`CandidateImportController` (Fase 06) is the
first place that actually uses the 1:many shape**, creating one `Project`
(`source: hr`) holding every candidate row from one CSV upload. Existing samples
were backfilled 1:1 (one project per pre-existing sample, source inferred
from the sample's creator role) — see
`database/migrations/2026_08_01_144019_add_project_id_to_handwriting_samples_table.php`.

**`DashboardController` added 2026-08-01** (MGA pivot Fase 03):
`GET /api/dashboard` returns role-differentiated content — grafolog gets
`active_projects`/`pending_review`/`completed_this_month`/`avg_turnaround_days`
scoped to samples they created; client (`role: user`) gets
`total_assessments`/`completed`/`in_progress`/`avg_turnaround_days` scoped
to samples where they're the subject (`user_id`). Response shape is a
generic `{ role, kpi: [{key,label,value}], activity: [...] }` so the
frontend doesn't need per-role branching — the backend decides labels and
which KPIs apply. Deliberately does **not** include the wireframe's chart
or quick-actions — out of scope for the agreed Fase 03 minimum.

Knowledge base: 8 Sindrom → 40 Aspek → 704 Indikator, seeded from
`database/seeders/data/grafologi_knowledge_base.json` via
`GrafologiKnowledgeSeeder`. Excel `kode` is kept as a reference column, never
the PK (all tables use Laravel auto-increment `id`).

**Local dev environment note:** this machine runs Laragon. MySQL (`mysqld`)
and the port-8123 dev server are NOT always running by default — start them
before testing anything that touches the DB or hits the API over HTTP. Also,
port 8123 was once found squatted by a stale `php artisan serve` process from
`D:\Project\web-aspro` (this project's pre-reorg folder, see root `CLAUDE.md`)
— if a request to 8123 returns something unexpected, check `netstat -ano` and
the process's `Path` before assuming it's a `guratan-api` bug.

**`mysqld` is genuinely flaky on this machine (observed repeatedly
2026-08-03)** — it dies silently between commands with no crash log,
sometimes minutes after confirming it was up, likely low free RAM (~4GB
observed) from the many dev processes (php, node/vite, VSCode's PHP
language servers, the user's own browser) competing for memory. Symptoms:
API requests suddenly return `SQLSTATE[HY000] [2002] ... actively refused`.
Fix is always the same — restart it: `mysqld.exe --defaults-file=<path to
my.ini>` — and re-verify with `mysql.exe -u root -e "SELECT 1;"` (poll,
don't `sleep`; cold InnoDB init alone can take ~30s). Prefer the harness's
background-task launcher over shell `&`/`disown` for long-running dev
servers (`php artisan serve`, `mysqld`, `npm run dev`) — plain `&`/`disown`
in this git-bash/Windows setup has also been observed to let the process
die silently between tool calls with no log output at all.

## Dev commands

- API dev server: `php artisan serve --port=8123` (guratan-web's
  `VITE_API_URL` expects this exact port — see `.env.development` there).
- `composer run dev` also works (runs server + queue + pail + vite together),
  but defaults to port 8000, which will NOT match the frontend's expectation
  unless you override it.
- Tests: `php artisan test` — **125 tests as of 2026-08-06** (up from 6).
  `tests/Feature/Api/`: `AuthControllerTest`, `SampleControllerTest`,
  `ScoringControllerTest`, `ReportControllerTest` (all real, cover
  authorization/IDOR checks, validation, rate limiting, audit logging, PDF
  generation). `tests/Unit/ScoringEngineServiceTest` is the original engine
  test. Two `ExampleTest` stubs remain (harmless Laravel defaults). Shared
  KB fixture builder: `tests/Concerns/SeedsGrafologiKb::seedMinimalAspek()`.
- `.env`: `DB_CONNECTION=mysql`, `DB_DATABASE=guratan_db` (real dev DB — do
  not let `RefreshDatabase` touch it). Tests use sqlite in-memory instead;
  `pdo_sqlite`/`sqlite3` are enabled in the Laragon php.ini and `phpunit.xml`
  overrides `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`.
- `LLM_PROVIDER=none` in `.env` → `NullLlmProvider` is active (pure
  passthrough, returns source text unchanged, no network call).

## Routes (32 total, `routes/api.php`)

```
POST /api/auth/register, /api/auth/login        (throttle:20,1, public)
POST /api/auth/logout, GET /api/auth/me          (auth:sanctum, throttle:60,1)
GET  /api/pricing                                (PUBLIC, no auth → PricingController@index — active price per tier)
POST /api/pricing/preview                        (auth:sanctum → PricingController@preview — tier + optional discount code → final price)
GET  /api/dashboard                              (auth:sanctum, → DashboardController — role-aware KPI + activity)
GET/POST /api/samples, GET /api/samples/{sample} (auth:sanctum)
POST /api/samples/{sample}/scores/preview         (auth:sanctum, → ScoringController@preview — live calc, no persistence)
POST /api/samples/{sample}/scores                (auth:sanctum, → ScoringController@submit)
POST /api/samples/{sample}/payment               (auth:sanctum, → PaymentController@store)
POST /api/samples/{sample}/assignment            (auth:sanctum, role:hr,administrator → AssignmentController@store)
POST /api/payments/notification                  (throttle:30,1, PUBLIC — DOKU webhook, no Sanctum)
GET  /api/reports, /api/reports/{report}         (auth:sanctum, +log.report_access on show/pdf)
GET  /api/reports/{report}/pdf                   (auth:sanctum, +log.report_access)
GET  /api/sindrom                                (auth:sanctum)
GET  /api/users/lookup                           (auth:sanctum, throttle:15,1 — grafolog-only, exact-email lookup)
GET  /api/grafologs                              (auth:sanctum, role:hr,administrator → UserLookupController@grafologs)
GET/POST /api/admin/users                        (auth:sanctum, role:administrator → AdminUserController)
GET/POST /api/admin/companies                    (auth:sanctum, role:administrator → CompanyController)
GET  /api/admin/pricing, PUT /api/admin/pricing/{tier} (auth:sanctum, role:administrator → Admin\PricingController)
GET/POST/PATCH /api/admin/discount-codes[/{id}]  (auth:sanctum, role:administrator → Admin\DiscountCodeController)
POST /api/hr/candidates/import                   (auth:sanctum, role:hr → CandidateImportController)
```

**Fixed 2026-07-27**: an unauthenticated request to any `auth:sanctum` route
that did **not** send `Accept: application/json` used to get a raw 500
(`RouteNotFoundException: Route [login] not defined`) instead of a clean 401.
Root cause: Laravel's `ApplicationBuilder::withMiddleware()` unconditionally
calls `redirectGuestsTo(fn () => route('login'))` as a default *before*
running the app's own `withMiddleware` callback in `bootstrap/app.php` — since
this is an API-only app with no named `login` route, that default blew up.
Fix: `bootstrap/app.php`'s `withMiddleware` callback now explicitly calls
`$middleware->redirectGuestsTo(fn () => null)` to override the framework
default, so guests fall through to a plain 401 JSON response instead of a
redirect attempt. Verified both with and without the `Accept` header → 401 in
both cases; `php artisan test` still 6/6 passing.

## Architecture

- **Models** (`app/Models/`): `Sindrom`, `Aspek`, `Indikator`,
  `IndikatorCrossReference`, `MeasurementVariable`, `MeasurementCategory`,
  `ScoringRuleBand`, `DeskriptifLookup`, `NarasiCache`, `User`, `Project`,
  `HandwritingSample`, `PersonalityReport`, `ReportAspekScore`, `Payment`,
  `PricingPlan`, `DiscountCode`, `Company`, `Assignment`, `AuditLog`.
  Most KB models have `findByKode()`. **`DeskriptifLookup` is unused** outside
  its own class — it was designed as a per-band generic "ringkasan" but never
  got wired into the scoring engine; treat it as dead code unless someone
  revives that idea.
- **`ScoringEngineService::generate(array $skorPerAspek)`** (kode → skor 1-10):
  for each aspek, looks up `band_label` via `ScoringRuleBand::labelUntukSkor()`
  (3-way per polaritas: Nilai Rendah/Sedang/Tinggi) and a **separate 4-way**
  `narasi_level` via `self::narasiLevelUntukSkor()`:
  `1-3 → low, 4-6 → medium, 7-8 → high, 9-10 → very_high`. This is a real,
  tested 4-bucket split — `narasi_very_high` in the source JSON IS used. (An
  earlier design note said very_high was unused; that was wrong/stale — this
  is the current, correct behavior as of 2026-07-26.) Output is grouped by
  sindrom: `{ sindrom: [{ id, kode_romawi, nama, polaritas, catatan_polaritas,
  rata_rata_skor, band_label_rata_rata, aspek: [{ kode, nama, skor,
  band_label, narasi_level, narasi }] }] }`. Throws `InvalidArgumentException`
  for unknown kode or out-of-range skor (1-10). **Tolerates partial input by
  design** — `$skorPerAspek` doesn't need to cover all 40 aspek, it just
  groups/averages whatever's given. `ScoringController::preview` (2026-08-03,
  MGA Fase 04) relies on exactly this to power the Assessment Workspace's
  live "Auto Calculation" panel without needing any change to this method.
- **`NarasiCacheService::ambil(Aspek, level, bahasa='id')`**: cache-through —
  checks `narasi_cache` table first; on miss, reads `$aspek->narasi[$level]`
  (falls back to `keterangan_umum`), runs it through the active
  `LlmProviderInterface`, and persists the result. With `LLM_PROVIDER=none`
  this never actually calls out — never bypass this service to call an LLM
  provider directly at report time.
- **Audit logging**: writes happen via a `PersonalityReportObserver` (not
  re-verified this session, referenced by `LogReportAccess`'s docblock); every
  *read* of a report (`show`, `pdf`) is logged by the `log.report_access`
  middleware (`App\Http\Middleware\LogReportAccess`, aliased in
  `bootstrap/app.php`) regardless of auth outcome — `lihat_laporan` /
  `lihat_laporan_ditolak`.
- **Form Requests** (`app/Http/Requests/`): `Auth\LoginRequest`,
  `Auth\RegisterRequest`, `Scoring\SubmitScoresRequest`,
  `Sample\StoreSampleRequest` — validation is not inline in controllers.
- **PDF**: `App\Services\Reporting\ReportPdfService` (barryvdh/laravel-dompdf),
  used by `ReportController::pdf`.
- **Email notification**: `PersonalityReportObserver::updated()` dispatches
  `App\Jobs\SendReportCompletedNotification` (queued, `ShouldQueue`) whenever
  a report's status transitions to `completed`; the job sends
  `App\Mail\ReportCompletedMail` (view: `resources/views/emails/report-completed.blade.php`)
  to the sample owner. `QUEUE_CONNECTION=database` — a queue worker
  (`php artisan queue:work` / `queue:listen`) must be running for this to
  actually fire. **Verified working end-to-end 2026-07-27** with a real Gmail
  SMTP account (`MAIL_MAILER=smtp`, credentials in `.env` — not committed
  anywhere, don't paste them into docs/commits): created a real
  sample→report via Eloquent, flipped status to `completed`, confirmed the
  job landed in the `jobs` table, ran `php artisan queue:work --once` and
  confirmed delivery with zero entries in `failed_jobs`. If `.env`'s
  `MAIL_USERNAME`/`MAIL_PASSWORD` ever get reset to placeholder values, this
  path will fail loudly (Gmail returns `535 Bad Credentials`) rather than
  silently — don't assume it's still connected without testing.
- **CORS** (`config/cors.php`): `allowed_origins` from `CORS_ALLOWED_ORIGINS`
  env, defaults to `http://localhost:5173`; `.env` explicitly sets it to the
  same. `supports_credentials => false` — fine, since auth is Bearer-token
  Sanctum (SPA cookie mode is not in use).
- **`ScoringController::submit` now rejects resubmission** (fixed
  2026-07-27): a sample whose `status` is already `completed` gets a 422
  instead of silently creating a second `PersonalityReport` for the same
  sample. Found during the Fase 1 security review — there was no guard
  before, so a grafolog resubmitting the form (double-click, retry after a
  network blip) created duplicate reports with no error.
- **`ScoringController::preview`** (added 2026-08-03): same authorization
  rules as `submit` (must be the grafolog who created the sample, sample
  can't be `rapid`) but does **not** check completeness or `status ===
  'completed'` — it's read-only and safe to call repeatedly while a
  grafolog is still filling the form. Don't add a completeness check here;
  that would defeat its purpose (live feedback on an in-progress form).
- **`public/storage` symlink was dangling** (fixed 2026-07-27): it pointed at
  `/d/Project/guratan-api/...`, the pre-reorg path, which no longer exists.
  Recreated via `php artisan storage:link`. This means rapid-tier uploaded
  images were unreachable at their public URL — dormant bug, since no
  frontend view currently renders `image_path` back as an `<img>`. If you
  wire that up later, verify the symlink still resolves.

## Roles & staff provisioning — added 2026-08-03 (MGA pivot Fase 05)

- `users.role` enum: `user` (client), `grafolog`, `administrator`,
  `supervisor` — expanded from just `user`/`grafolog` via
  `database/migrations/2026_08_03_060730_expand_users_role_enum.php`, then
  `hr` added on top of that via
  `database/migrations/2026_08_06_090915_add_hr_role_and_companies.php`
  (Fase 06) — **all 5 roles from the original MGA plan now exist**. Both
  migrations are **driver-aware**: raw `ALTER TABLE ... MODIFY COLUMN` for
  real MySQL (doctrine/dbal isn't installed, same reason as the `Project`
  migration), `Schema::table()->enum()->change()` for the sqlite test DB
  (Laravel's sqlite grammar rebuilds the table natively, no doctrine/dbal
  needed there). If you ever add another role, update **both** branches in
  a new migration (don't edit the old ones).
- `User::isAdministrator()` / `isSupervisor()` / `isHr()` mirror the
  existing `isGrafolog()`. `User::company()` (`belongsTo Company`) is
  `hr`-specific — `null` for every other role.
- **New generic middleware** `App\Http\Middleware\EnsureUserHasRole`
  (aliased `role` in `bootstrap/app.php`) — `Route::middleware('role:administrator')`.
  This is the intended pattern for any *new* role-gated route going
  forward. The older `abort_unless($user->isGrafolog(), 403)` inline checks
  in `ScoringController`/`SampleController`/`UserLookupController` were
  **not** migrated to this — they still work, touching them risked
  regressions for no benefit.
- **Provisioning** (locked-in product decision, don't re-litigate): the
  first Administrator comes from `database/seeders/AdministratorSeeder.php`,
  which reads `ADMIN_EMAIL`/`ADMIN_PASSWORD`/`ADMIN_NAME` from `.env` via
  `config/admin.php` and silently no-ops if the email/password aren't set —
  it will never create an account with a guessable default password. Every
  subsequent administrator/supervisor/grafolog/**hr** account is created by
  an already-logged-in Administrator via `POST /api/admin/users`
  (`AdminUserController`, `StoreStaffUserRequest`) — **not** by re-running
  the seeder and **not** via public registration. `role: hr` additionally
  **requires** `company_id` in that same request (the company must already
  exist — `POST /api/admin/companies` first). `RegisterRequest` still only
  accepts `role: user|grafolog`; this is enforced by a test
  (`test_register_rejects_administrator_and_supervisor_roles`) — if that
  test ever needs to change, it means the provisioning decision changed and
  root `ROADMAP.md` / memory need updating too.
- Supervisor has **no dedicated functionality yet** — the role exists and
  is assignable, but there's no review queue or supervisor-specific view.
  That was explicitly deferred (see ROADMAP.md Fase 05 entry), not
  forgotten — don't build it speculatively without a product decision on
  what a supervisor actually reviews.

## HR: Company, Candidate import, Assignment — added 2026-08-06 (MGA Fase 06)

- **`Company`** (`app/Models/Company.php`): `name`, `created_by`. Created
  by an Administrator via `POST /api/admin/companies`
  (`Api\Admin\CompanyController`) — must exist before an `hr` user can be
  created for it.
- **"Candidate" is intentionally not a new table/model.** A candidate is a
  regular `User` (`role: user`, `company_id` set to the importing HR's
  company). This is a deliberate reuse decision — it means `Project`,
  `HandwritingSample.user_id`, `ReportController`, `ReportView.vue`, PDF
  export, everything downstream works completely unchanged for HR-sourced
  candidates. Don't build a separate `Candidate` model; if a real need for
  candidate-specific fields shows up later (resume, applied position, ...),
  extend `users` or add a `candidate_profiles` table keyed to `user_id`,
  don't fork the identity.
- **`Api\Hr\CandidateImportController::import`** (`POST
  /api/hr/candidates/import`, `role:hr`): accepts a CSV file upload
  (`name,email` header, parsed with native `fgetcsv` — no CSV library
  dependency added), creates **one** `Project` (`source: hr`) and **one**
  `HandwritingSample` per valid row, all inside a DB transaction. Validates
  every row before creating anything — one bad row fails the whole import
  (422 with a `line`-numbered error list), nothing partial gets committed.
  Existing-email handling: an email already belonging to a non-`user` role
  is rejected (can't turn a staff account into a candidate); an email
  belonging to a `user` in a *different* company is rejected; an email
  belonging to an unaffiliated `user` (`company_id === null`, e.g. a
  self-registered individual client) gets that `company_id` attached but
  its name/password are never touched.
- **`assignments` table / `App\Models\Assignment`**: one row per sample
  (`sample_id` is `unique`), `grafolog_id`, `assigned_by`, `status`
  (`assigned`/`completed`). This is the mechanism that decouples "who owns
  the candidate relationship" (`handwriting_samples.created_by` — the HR
  user for imported candidates) from "who actually scores it" (the
  assigned grafolog, who is very likely a *different* user). Reassigning a
  sample **updates** the existing row rather than adding a new one —
  there's no assignment history, only current state.
- **`Api\AssignmentController::store`** (`POST
  /api/samples/{sample}/assignment`, `role:hr,administrator`): HR can only
  assign samples they own (`created_by === $user->id`); Administrator can
  assign any sample. Target must have `role: grafolog` (422 otherwise).
- **Authorization extension is additive, not a rewrite.** Two new
  `HandwritingSample` methods —
  `isScorableBy(User $user)` (`created_by === user OR assignment->grafolog_id === user`)
  and `isViewableBy(User $user)` (same, plus `user_id === user`) — are now
  used by `ScoringController::preview/submit`, `SampleController::
  show/index`, and `ReportController::index/authorizeAccess`, **replacing**
  their old inline `created_by === user.id` checks with calls to these
  helpers. The original behavior for the grafolog-direct flow is
  unchanged; assignment-based access is a pure OR addition. When
  `ScoringController::submit` completes a report, it also flips the
  sample's `assignment->status` to `completed` if one exists
  (`$sample->assignment?->update(...)` — the `?->` matters, most samples
  have no assignment at all).
- **`GET /api/grafologs`** (`role:hr,administrator`,
  `UserLookupController::grafologs`): flat list of all grafolog accounts
  (id/name/email only) — feeds the assignment dropdown in
  `HrCandidatesView.vue`. Distinct from the older `GET /api/users/lookup`
  (grafolog-only, exact-email match for finding a *client*) — don't
  conflate the two.
- **Deferred on purpose, not forgotten**: Billing/Subscription for company
  plans — no pricing model has been decided for this, it's a business
  decision, not a technical one, don't build a `Subscription` table
  speculatively. Also deferred: an admin-facing "Master Data" editor and a
  cross-project Reports view (mentioned in the original MGA plan's Fase 05
  admin panel, still not built), and any bulk-reassignment or
  assignment-history UI.

## Payment (DOKU) — added 2026-07-27

- **Credentials go in `.env`**: `DOKU_CLIENT_ID`, `DOKU_SECRET_KEY`,
  `DOKU_IS_PRODUCTION` (bool). Get them from DOKU Back Office — **sandbox and
  production are separate accounts with separate credentials**, don't reuse
  one for the other. Read via `config('services.doku.*')`
  (`config/services.php`). Currently empty/placeholder — payment creation
  will throw a clear `RuntimeException` until real sandbox credentials are
  filled in (checked in `DokuService::ensureConfigured()`).
- **`App\Services\Payment\DokuService`**: `createCheckout(Payment, name,
  email)` POSTs to `{base_url}/checkout/v1/payment` (sandbox:
  `api-sandbox.doku.com`, production: `api.doku.com`) and returns
  `['url', 'token_id', 'expired_date']`. Signature scheme (Digest +
  Client-Id/Request-Id/Request-Timestamp/Request-Target/Digest → HMAC-SHA256
  with the secret key) was pulled from developers.doku.com on 2026-07-27, not
  guessed — see the class docblock before changing it, a wrong signature
  fails DOKU-side auth with no useful error message.
- **`App\Http\Controllers\Api\PaymentController::store`**: only the sample's
  *owner* (`user_id`, i.e. the paying client) can trigger a payment — not the
  grafolog who may have created the sample on their behalf. Rejects `rapid`
  tier (free) and samples that already have a `paid` payment. Price comes
  from `PricingPlan::activePriceFor($tier)` (**changed 2026-08-06**, was
  `config('pricing.tiers.*')` — that config file is deleted, see "Pricing &
  commerce" section below). `comprehensive` = 49000 matches the root
  CLAUDE.md's "~Rp49rb"; **`master` = 149000 is still an unconfirmed
  placeholder** — confirm the real price with the business before go-live,
  it can now be changed via `PUT /api/admin/pricing/master` without a
  deploy.
- **`::notification`** is DOKU's webhook (public route, no `auth:sanctum` —
  DOKU has no Sanctum token). Security depends entirely on
  `DokuService::verifyNotificationSignature()`; never relax or bypass it.
  **Not fully verified against a real DOKU sandbox notification** — the body
  field names used (`order.invoice_number`, `transaction.status`) are sourced
  from DOKU's official docs but a literal example payload couldn't be
  fetched. Send one real test transaction from DOKU Sandbox and compare
  against what lands in `payments.notification_payload` before trusting this
  in production.
- **Migration**: `payments` table (`sample_id`, `invoice_number` unique,
  `amount`, `status`: pending/paid/failed/expired, `doku_token_id`,
  `doku_payment_url`, `notification_payload` json, `paid_at`).
- **Still no frontend checkout UI** (as of 2026-08-06, Commerce Fase A —
  Fase D in root `ROADMAP.md`'s "Inisiatif — Commerce & CMS" will build
  this). `PortalGrafologView` (grafolog creates the sample, client as
  `user_id`) is still the only *practical* path — but note
  `StoreSampleRequest` already lets a regular `user` call `POST /api/samples`
  themselves too (this has existed since Fase 02, `Project.source =
  'client'`), it's just never had a UI. **As of 2026-08-06 that path is
  payment-gated** (see below) so it's no longer a free-scoring loophole,
  but it's still not a real checkout flow — no price shown, no discount, no
  "Bayar Sekarang" button anywhere.
- Tests: `tests/Feature/Api/PaymentControllerTest.php` — mocks DOKU's HTTP
  response via `Http::fake()`, covers authorization (only owner pays),
  rapid-tier rejection, already-paid rejection, and both valid/invalid
  webhook signature verification (computed independently in the test to
  cross-check the production algorithm, not just re-using the same code path).

## Pricing & commerce — added 2026-08-06 (Commerce Fase A)

- **`pricing_plans` table / `App\Models\PricingPlan`** replaces the old
  static `config/pricing.php` (deleted — nothing references it anymore,
  don't recreate it). One row per price point; `is_active` marks the
  current price for a tier. `PricingPlan::activePriceFor($tier)` is the
  read path (`PaymentController`, public `GET /api/pricing`).
  `PricingPlan::setPriceFor($tier, $price, $user)` is the *only* write
  path — it deactivates the previous active row and creates a new one,
  **never** updates a row in place, so price history survives (useful for
  reconciling old invoices against the price that was active when they
  were paid). If you need "what was the price on date X," query by
  `updated_at`/`created_at` across all rows for that tier, not just the
  active one.
- **Public read**: `GET /api/pricing` (no auth) → active price per tier.
  Will be consumed by the checkout page once Commerce Fase D exists.
- **Admin write**: `GET/PUT /api/admin/pricing[/{tier}]`
  (`Api\Admin\PricingController`, `role:administrator`). Every price
  change is logged via `AuditLog::record('ubah_harga', ...)` — pricing is
  sensitive business data, same principle as report-access logging.
- **Payment gate on client-sourced samples** (`HandwritingSample::
  requiresPayment()` / `isPaid()`, checked in `ScoringController::
  preview`/`submit`, returns HTTP 402): closes the gap described in the
  "No frontend checkout UI" note above. **Scoped to `Project.source ===
  'client'` only** — samples from `SampleController::store` when called by
  a self-service client. Grafolog-direct (`source: grafolog`) and
  HR-imported (`source: hr`) samples are explicitly exempt; the assumption
  (not yet confirmed by the business, flagged in root `ROADMAP.md`'s
  Commerce inisiatif) is that those have payment/invoicing arranged
  outside the per-sample flow. If that assumption changes, this is the
  method to extend — don't add a second, separate gate elsewhere.
- Tests: `tests/Feature/Api/PricingControllerTest.php` (public endpoint),
  `tests/Feature/Api/Admin/PricingControllerTest.php` (admin CRUD + audit
  log), and three new cases in `ScoringControllerTest.php` covering the
  402 gate (unpaid blocks both preview and submit, paid allows it,
  grafolog/hr-sourced samples are unaffected).

## Discount codes — added 2026-08-06 (Commerce Fase B)

- **`discount_codes` table / `App\Models\DiscountCode`**. Business
  decision (2026-08-06): both `percentage` and `fixed` types are needed,
  not just one. `applicable_tiers` (nullable json array) restricts a code
  to specific tiers — `null` means it applies to all. `max_uses`/
  `used_count` enforce a quota — `max_uses: null` means unlimited.
  `valid_from`/`valid_until` are both optional and independent (a code can
  have just a start, just an end, both, or neither).
- **`isValidFor(string $tier): bool`** is the single source of truth for
  "can this code be used right now for this tier" — checks `is_active`,
  the time window, quota, and tier restriction all in one place.
  **`amountOff(int $basePrice): int`** computes the discount, capped so it
  never exceeds the base price (a fixed-amount code larger than the price
  just makes it free, not negative). **Always call these, never
  reimplement the checks inline** — the preview endpoint and the future
  Fase D checkout both need to agree on what "valid" means, and that only
  works if there's one method deciding it.
- **`incrementUsage()` exists but is called nowhere yet.** It's meant to
  fire once at the point a discount is actually *consumed* (a successful
  payment in Fase D), not at preview time — calling it from
  `PricingController::preview` would burn quota just from someone typing a
  code to see what it does, before ever paying.
- **Gotcha already fixed, don't reintroduce it**: `is_active`/`used_count`
  have DB-level defaults (`true`/`0`) but MySQL doesn't refetch those onto
  the in-memory model after Eloquent's `create()` — without the matching
  `protected $attributes = [...]` on the model, a freshly created code's
  `isValidFor()` would incorrectly return `false` until you called
  `->fresh()`. If you add another boolean/counter column with a DB
  default to this or any other model, mirror it in `$attributes` too or
  write a test that would catch the gap (this one was caught by exactly
  that kind of test failure).
- **`POST /api/pricing/preview`** (`Api\PricingController::preview`,
  auth:sanctum): `{tier, code?}` → `{tier, base_price, code, code_valid,
  code_message, discount_amount, final_price}`. An unknown/expired/
  wrong-tier code does **not** 422 — it returns `code_valid: false` with
  `final_price` equal to `base_price`, so a checkout UI can show inline
  "kode tidak berlaku" feedback without a failed request breaking the
  page. Only `tier` is required.
- **Admin CRUD** (`Api\Admin\DiscountCodeController`, `role:administrator`):
  `store()` creates (code auto-uppercased in
  `StoreDiscountCodeRequest::prepareForValidation()`); `update()` **only**
  toggles `is_active`, no other field is editable after creation — changing
  a code's value/quota/tiers after it may have already been used would
  silently redefine what past redemptions meant. Need different terms?
  Deactivate the old code, create a new one. Every create/activate/
  deactivate call is `AuditLog::record()`-ed, same principle as pricing
  changes above.
- Tests: `tests/Unit/DiscountCodeTest.php` (model validation/calculation
  logic in isolation), `tests/Feature/Api/Admin/DiscountCodeControllerTest.php`
  (admin CRUD + audit log), `tests/Feature/Api/PricingPreviewControllerTest.php`
  (the preview endpoint, including the invalid-code-doesn't-error case).

## Hard constraints (user-stated, still in force)

1. Never call an LLM per-report — always go through `NarasiCacheService`.
2. Rapid tier is retired (2026-08-01) — don't build real CV scoring, don't
   re-add rapid-tier sample creation, without an explicit new user decision.
3. Fix knowledge-base data-quality issues at the JSON source
   (`database/seeders/data/grafologi_knowledge_base.json`), never patch in
   `GrafologiKnowledgeSeeder`.
4. Security/validation (rate limiting, Form Requests, audit log) is mandatory
   — already in place for the routes above; keep it that way for anything new.

## Open security findings (2026-07-27 review — not fixed, need a product decision)

- **Uploaded rapid-tier images are served from the public disk with no
  ownership check**, unlike PDFs/reports which go through
  `ReportController`'s authorization + audit log. Filenames are Laravel's
  default non-guessable hash, so this isn't a practical IDOR today, but it's
  inconsistent with "sensitive psychological data" handling once something
  actually links to `image_path`. Since Rapid tier retirement (2026-08-01)
  this set of images can't grow anymore, but the historical ones from before
  retirement are still served this way — same options as before (keep
  public since low real risk, or move to a private disk + authenticated
  streaming route like `pdf`). Still needs a call, not a silent fix.
- **Sanctum tokens never expire** (`config/sanctum.php` `'expiration' =>
  null`) and carry no ability scoping — a leaked token is valid forever
  until manually revoked via logout. Setting an expiration is a UX tradeoff
  (users get logged out periodically), so left for the user to decide.
- `APP_DEBUG=true` in `.env` is correct for local dev (and is how this
  session's debugging worked at all — stack traces with real file paths were
  essential for diagnosing the 500 bug) but **must** become `false` before
  any production deploy, or internal file paths and stack traces leak to
  anyone who triggers a 500. Already tracked under ROADMAP.md Fase 2.

## Not built yet

- Frontend checkout UI (see "Payment (DOKU)" above — backend is done,
  frontend trigger point is an open product question).
- Production DOKU credentials (sandbox-only scaffolding so far).
