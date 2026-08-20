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
- Tests: `php artisan test` — **197 tests as of 2026-08-07** (up from 6).
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

## Routes (48 total, `routes/api.php`)

```
POST /api/auth/register, /api/auth/login        (throttle:20,1, public)
GET  /api/content                                (PUBLIC, no auth → ContentController — homepage CMS text)
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
POST /api/clients                                (auth:sanctum, grafolog-only → UserLookupController@store — register a walk-in client)
GET  /api/grafologs                              (auth:sanctum, role:hr,administrator → UserLookupController@grafologs)
GET/POST /api/admin/users                        (auth:sanctum, role:administrator → AdminUserController)
GET/POST /api/admin/companies                    (auth:sanctum, role:administrator → CompanyController)
GET  /api/admin/pricing, PUT /api/admin/pricing/{tier} (auth:sanctum, role:administrator → Admin\PricingController)
GET/POST/PATCH /api/admin/discount-codes[/{id}]  (auth:sanctum, role:administrator → Admin\DiscountCodeController)
GET/PUT /api/admin/content[/{key}]               (auth:sanctum, role:administrator → Admin\ContentBlockController)
GET  /api/announcements                          (auth:sanctum, → AnnouncementController@index — visible-to-me only)
GET/POST/PUT /api/admin/announcements[/{id}]     (auth:sanctum, role:administrator → Admin\AnnouncementController)
GET  /api/tokens/price                           (auth:sanctum → TokenController@price — current price per token, null if unset)
GET  /api/tokens/wallet                          (auth:sanctum, role:grafolog → TokenController@wallet — balance + recent ledger)
POST /api/tokens/preview                         (auth:sanctum, role:grafolog → TokenController@preview — quantity + optional code → final amount)
POST /api/tokens/purchase                        (auth:sanctum, role:grafolog → TokenPurchaseController@store — starts DOKU checkout)
GET/PUT /api/admin/token-price                   (auth:sanctum, role:administrator → Admin\TokenPriceController)
GET/PUT /api/admin/token-costs[/{tier}]          (auth:sanctum, role:administrator → Admin\TokenCostController)
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
  `MeasurementVariable`, `MeasurementCategory`,
  `ScoringRuleBand`, `DeskriptifLookup`, `NarasiCache`, `User`, `Project`,
  `HandwritingSample`, `PersonalityReport`, `ReportAspekScore`, `Payment`,
  `PricingPlan`, `DiscountCode`, `ContentBlock`, `Announcement`, `Company`,
  `Assignment`, `AuditLog`, `TokenPrice`, `TokenCost`, `TokenPurchase`,
  `TokenLedgerEntry`, `MetodologiPenilaian`, `IndikatorRule` (2026-08-08,
  see "Knowledge Management System" below).
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

## Koreksi laporan & riwayat versi — added 2026-08-08

User meminta pengembangan lanjutan report: (1) koreksi manual skor
Indikator/Sindrom setelah laporan selesai (dengan regenerasi otomatis),
(2) edit narasi manual langsung, (3) kategorisasi topik (karier/cinta/dst)
untuk basis chat interaktif klien nanti. **Cuma #1+#2 yang dikerjakan
sesi ini** ("#2 dl" - user eksplisit minta ini duluan) - kategorisasi
topik dan chat interaktif masih belum dikerjakan, lihat memory
`project_report_editing` untuk breakdown lengkap & kenapa chat perlu
diskusi produk terpisah (bertentangan dengan prinsip "LLM tidak live
per-user" yang sudah dikunci).

- **`report_revisions` table / `App\Models\ReportRevision`**: jejak audit
  UNTUK KEDUANYA (koreksi skor DAN edit narasi manual) - satu mekanisme,
  bukan dua sistem terpisah. Menyimpan snapshot `personality_reports.data`
  SEBELUM perubahan diterapkan, plus `jenis` (`koreksi_skor`/`edit_manual`),
  `catatan` (alasan, opsional), `actor_user_id`.
  `PersonalityReport.data` sendiri SELALU mencerminkan versi TERKINI (tidak
  ada perubahan di `ReportController::show`/`pdf` yang sudah ada) - kode
  lama yang membaca laporan tidak perlu tahu apa-apa soal revisi.
- **`App\Services\Reporting\ReportRevisionService::snapshotBeforeChange()`**
  - satu titik dipakai baik oleh `ScoringController::correct()` maupun
  `ReportController::updateNarasi()`, supaya keduanya konsisten.
- **`ScoringController::correct()`** (`POST /api/samples/{sample}/scores/correct`)
  - kebalikan tepat dari `submit()`: `submit()` MENOLAK sample yang sudah
  `completed`, `correct()` JUSTRU MENSYARATKANNYA. Otorisasi sama
  (`isGrafolog` + `isScorableBy` - grafolog pemilik sample, TANPA approval
  tambahan, sesuai keputusan user). Menerima `SubmitScoresRequest` yang
  sama (40 aspek lengkap wajib) + field `catatan` opsional. **Token TIDAK
  ditagih ulang** - ini koreksi atas laporan yang sudah dibayar, bukan
  laporan baru (keputusan desain, bukan bug). `report_aspek_scores` lama
  dihapus & ditulis ulang (bukan diversi terpisah - hanya `data` JSON final
  yang di-versi lewat `report_revisions`, riwayat skor mentah implisit ada
  di situ juga karena JSON-nya menyertakan skor per aspek).
- **`ReportController::updateNarasi()`** (`PATCH /api/reports/{report}/aspek/{kode}/narasi`,
  grafolog pemilik sample saja) - menimpa `narasi` 1 entri Aspek langsung
  di JSON `data` (bukan tabel terpisah) + menambahkan flag
  `narasi_diedit_manual: true` ke entri itu, supaya frontend bisa
  menandainya. **Flag & teks override ini HILANG OTOMATIS begitu laporan
  dikoreksi skornya** (`ScoringController::correct()` menulis `data` yang
  benar-benar baru dari `ScoringEngineService::generate()`, yang tidak tahu
  apa-apa soal override manual) - keputusan desain sengaja, bukan bug:
  regenerasi penuh dari KB dianggap lebih dipercaya daripada teks manual
  yang mungkin sudah tidak relevan dengan skor baru. Diuji eksplisit di
  `ScoringCorrectionTest::test_correcting_clears_manual_narasi_override_on_regeneration`.
- **`ReportController::revisions()`/`showRevision()`** (`GET /api/reports/{report}/revisions[/{revision}]`)
  - otorisasi `isViewableBy` (lebih luas dari `isScorableBy` - klien
  pemilik juga boleh lihat riwayat, bukan cuma grafolog).
- Audit log: `koreksi_skor_laporan`, `edit_narasi_laporan`.
- Browser-verified end-to-end dengan data KB sungguhan (40 aspek nyata,
  bukan fixture minimal): edit narasi manual → badge muncul → koreksi skor
  penuh (klik tombol skor 9 di 40 baris sungguhan lewat UI, bukan API
  langsung) → response mengonfirmasi skor berubah jadi 9 → ground-truth DB
  dicek langsung (bukan cuma baca browser) mengonfirmasi flag override
  hilang dari versi aktif setelah regenerasi, riwayat menyimpan versi lama
  dengan benar. 14 test baru (`ScoringCorrectionTest`, `ReportRevisionTest`),
  355 backend tests total (up from 341).

## Range-mode irregularity rules, worksheet-based correction, per-Indikator narasi — added 2026-08-17

User mengklarifikasi bahwa ke-20 ambang "irregular" asli (lihat
"Aturan Irregularity" di atas) SEMUANYA literal berbunyi "Range is more
than..." - bukan hiasan kata. "Range" = selisih (nilai terbesar - nilai
terkecil) yang diamati grafolog untuk 1 variabel di 1 sample, bukan 1 nilai
ukur titik tunggal seperti yang diimplementasikan sebelumnya. Ini juga
membuka 3 request terkait: (1) grafolog perlu bisa input nilai
terbesar/terkecil, bukan cuma 1 nilai, (2) koreksi laporan perlu bisa
membuka ulang measurement worksheet (bukan cuma form skor 1-10), (3)
`Indikator.keterangan` (narasi per-indikator, sudah ada di KB sejak awal
tapi tidak pernah dipakai) harus ikut masuk laporan.

- **`measurement_readings.nilai_min`/`nilai_max`** (nullable decimal,
  migrasi driver-aware sama seperti migrasi enum role - `nilai` juga
  dilonggarkan jadi nullable karena 1 baris sekarang boleh cuma berisi
  rentang saja tanpa nilai titik). `MeasurementController::store` menghapus
  baris hanya kalau KETIGA field (`nilai`/`nilai_min`/`nilai_max`) null -
  bukan cuma `nilai` seperti sebelumnya.
- **`indikator_rules.variable_a_value_mode`** (`nilai`|`range`, default
  `nilai`) - cuma variable_a yang butuh mode ini (variable_b/compare_value
  di semua 20 ambang user selalu nilai titik, tidak pernah "Range of..."
  di sisi kanan). `ChecklistEngineService::evaluateSample()` menghitung
  peta `$ranges` (variable_id => nilai_max - nilai_min, cuma untuk baris
  yang punya keduanya) di samping `$values` yang sudah ada;
  `evaluateRule()` baca dari `$ranges` alih-alih `$values` untuk sisi A
  kalau mode-nya `range`, dan tetap unresolved (null) kalau min/max belum
  lengkap - sama seperti pola unresolved untuk `nilai` yang sudah ada.
  Teks alasan (`keterangan_pemicu`) diberi prefix "Range " biar grafolog
  tahu itu bukan nilai titik biasa (mis. "Range Ovals height: 3 >
  Middle zone height (2)").
- **`IrregularityRuleSeeder` diretrofit, bukan ditulis ulang** - SEMUA 28
  baris lama (termasuk 4 rule Extension Spacing OR/AND) sekarang
  `variable_a_value_mode = 'range'`, key `updateOrCreate` tidak berubah
  jadi ini murni migrasi konten in-place. **5 Indikator "Middle zone
  height irregular/regular" yang dulu SENGAJA di-skip** (dikira typo
  self-reference - ambang membandingkan MZH dengan dirinya sendiri)
  sekarang ditambahkan sebagai kasus valid: variable_a=MZH mode `range`,
  variable_b=MZH mode `nilai` (titik) - 2 mode berbeda untuk variabel yang
  sama, bukan lagi ambigu begitu mekanisme range ada. `indikator_rules`
  sekarang 106 baris (101 + 5). Live-verified lawan dev DB sungguhan:
  Ovals height min=1/max=4 (selisih 3) vs MZH nilai=2 → "Ovals height
  irregular" (27-5b) tercentang benar dengan alasan "Range Ovals height: 3
  > Middle zone height (2)"; MZH sendiri min=1/max=3.5 (selisih 2.5) vs
  nilai titik 2 → "Middle zone height irregular" (27-5a) tercentang,
  pasangan "regular"-nya (11-1b) TIDAK - saling eksklusif seperti
  seharusnya.
- **`MeasurementController::store` dan `ChecklistController::toggle` tidak
  lagi memblokir sample `status === 'completed'`** - dibutuhkan alur
  koreksi laporan lewat measurement worksheet (lihat di bawah). Mengedit
  hasil ukur/checklist di sini TIDAK mengubah laporan aktif - itu tetap
  versi beku sampai grafolog sengaja memanggil
  `ScoringController::correct()` dengan tally terbaru, exactly seperti
  alur koreksi form-manual yang sudah ada. `ScoringController::submit`
  TETAP menolak resubmit ke sample completed - guard yang dilonggarkan
  cuma di 2 endpoint draft/scratch itu, bukan endpoint yang benar-benar
  mengunci laporan.
- **`indikator.keterangan` (narasi per-indikator) sekarang ikut laporan** -
  `ScoringController::attachIndikatorNarasi()` (post-processing murni,
  `ScoringEngineService::generate()` sengaja TIDAK disentuh) menempel
  `aspek.indikator_terkait: [{kode, nama, keterangan}]` untuk tiap Aspek
  yang punya `sample_indikator_checks.checked=true` terkait. No-op untuk
  laporan mode manual (tidak pernah ada baris `sample_indikator_checks`
  sama sekali) - key `indikator_terkait` cuma muncul kalau memang ada data.
  Dipanggil dari `submit()` DAN `correct()`, jadi koreksi lewat worksheet
  juga memperbarui daftar ini (indikator yang sudah tidak lagi tercentang
  hilang dari laporan setelah regenerasi).
- Live-verified end-to-end lawan dev DB sungguhan (bukan cuma test): submit
  40 aspek nyata → laporan berisi `indikator_terkait` dengan teks
  `keterangan` sungguhan dari KB; edit measurement pada sample yang SUDAH
  `completed` (Ovals height range dipersempit sampai tidak lagi
  "irregular") → checklist re-evaluasi otomatis → `scores/correct` dengan
  tally baru → `indikator_terkait` di laporan aktif hilangkan
  "Ovals height irregular" sementara `report_revisions` menyimpan
  snapshot versi SEBELUM koreksi lengkap dengan entri lama. 362 backend
  tests total (up from 355) - 6 test baru
  (`MeasurementControllerTest`/`ChecklistControllerTest`), 3 test
  diperbarui (`IrregularityRuleSeederTest`, count 28→33), 2 test baru
  `ChecklistEngineServiceTest` (range mode + unresolved-tanpa-min/max), 1
  test baru `ScoringControllerTest` (`indikator_terkait` muncul di respons
  submit). **Browser-verified lewat Playwright** (sesi lanjutan): sample
  nyata diberi MZH=2 (nilai) + Ovals height min=1/maks=4 lewat worksheet ->
  Indikator 27-5b auto-checked dengan alasan "Range Ovals height: 3 >
  Middle zone height (2)" -> laporan jadi, `indikator_terkait` Aspek 27
  berisi keterangan Indikator itu -> buka "Koreksi Skor" mode Measurement
  Worksheet, field MZH/Ovals min/maks ter-prefill dari hasil ukur
  tersimpan. 0 error console. Data uji coba dibersihkan.

## Unifikasi cross-reference ke indikator_rules — 2026-08-19

User mengajukan diskusi: kenapa cascade "centang A -> ikut centang B"
(cross-reference, KM-F) adalah mekanisme TERPISAH dari sistem aturan
measurement/irregularity, padahal secara konsep sama-sama "kriteria yang
membuat Indikator tercentang otomatis"? Usulan: jadikan satu sistem -
`indikator_rules` jadi satu-satunya tempat definisi kriteria, cross-
reference jadi rule_type ketiga. Dikonfirmasi lewat AskUserQuestion: (1)
rantai ketergantungan BOLEH berlapis (A->B->C, bukan cuma satu-hop seperti
cascade lama), (2) migrasi PENUH - tabel `indikator_cross_reference` lama
dihapus, bukan berdampingan.

- **`indikator_rules` rule_type ketiga: `indikator_checked`.** Kolom baru
  `depends_on_indikator_id` (FK ke `indikator`, cascadeOnDelete - rule jadi
  tidak berarti kalau Indikator yang di-depend dihapus). `variable_a_id`
  dilonggarkan nullable (migrasi driver-aware, sama pola dengan migrasi
  enum sebelumnya) karena rule tipe ini tidak pakai measurement variable
  sama sekali - sisi kirinya adalah status tercentang Indikator lain.
- **`ChecklistEngineService::evaluateSample()` ditulis ulang jadi evaluasi
  titik-tetap (fixed-point), bukan 1 pass + cascade terpisah.** Loop
  berulang sampai tidak ada perubahan, dibatasi maksimal N iterasi (N =
  jumlah Indikator). **Ini aman/terjamin berhenti** karena rule
  `indikator_checked` HANYA bisa mengembalikan true/null (tidak pernah
  false eksplisit - "belum tercentang" bukan berarti "tidak akan pernah"),
  jadi status Indikator non-manual monoton naik (false->true, tidak pernah
  dibalik) - setiap iterasi minimal 1 baris berubah atau loop berhenti,
  tidak butuh deteksi siklus terpisah. 2 Indikator yang saling depends_on
  (A<->B) otomatis stabil di "tidak tercentang", bukan infinite loop -
  dibuktikan test (`test_mutually_dependent_indikator_stay_unchecked_not_infinite_loop`).
  `applyCascadeFrom()` (method terpisah lama) **dihapus total** - fungsinya
  sekarang otomatis tercakup evaluasi rule biasa.
- **`sumber` pada `sample_indikator_checks` tetap `'auto'`/`'cascade'`**
  (bukan digabung jadi 1 nilai) - dipilih dari rule_type pemenang saat
  Indikator jadi tercentang (`category`/`comparison` -> `'auto'`,
  `indikator_checked` -> `'cascade'`), supaya badge UI "Auto"/"Terkait"
  yang sudah ada di `IndikatorChecklist.vue` tidak perlu berubah sama
  sekali meski mekanisme di baliknya sekarang satu sistem.
  **Perilaku live-reconciliation ikut berubah**: baris `cascade` sekarang
  direkonsiliasi ulang tiap `evaluateSample()` sama seperti `auto` (dulu
  dibekukan permanen begitu tercipta, sama seperti `manual`) - konsisten
  dengan filosofi baru "cascade dan auto sama-sama derivasi otomatis."
  Konsekuensi: `toggle()`'s force-uncheck (`alsoUncheckCascaded`) sekarang
  membekukan target jadi `sumber='manual'` juga (bukan cuma `checked=false`
  seperti dulu) - kalau tidak, target dengan rule OR yang punya sumber lain
  yang masih valid bisa diam-diam tercentang lagi, membatalkan keputusan
  grafolog barusan.
- **Migrasi data**: 1 migration one-time (`migrate_cross_reference_into_indikator_rules_and_drop_table`)
  mengonversi 257 baris `indikator_cross_reference` yang `aktif=true` DAN
  `match_status='matched'` di database ini SAAT migrasi benar-benar
  dijalankan jadi baris `indikator_rules`, lalu drop tabel lama (plus
  kolom `sample_indikator_checks.cross_reference_id` yang jadi redundan -
  cascade sekarang ditandai lewat `rule_id` yang sama dengan rule lain).
  23 baris `unmatched` tidak dibawa (tidak pernah berfungsi di sistem
  lama juga). `GrafologiKnowledgeSeeder::seedCrossReference()` diretrofit
  jadi menulis LANGSUNG ke `indikator_rules` dari sumber JSON (bukan lagi
  tabel perantara) - supaya fresh install/test suite tetap dapat 257
  relasi yang sama tanpa lewat migrasi data satu-kali itu.
  **Trade-off yang disengaja, didokumentasikan bukan bug**: beda dari
  `aktif` KM-F lama yang sengaja dijaga tidak tertimpa reseed, sekarang
  "menonaktifkan" = hapus rule row, dan reseed AKAN menulis ulang relasi
  itu dari JSON kalau masih ada di sana (sama seperti seeder konten lain -
  Irregularity/CategoryMatch/dst - bukan kelas risiko baru, cuma
  konsisten). Dikunci test `test_reseeding_recreates_a_cross_reference_rule_deleted_via_admin_ui`.
- **Admin UI**: tab "Referensi Silang" (KM-F, 7→6 tab) **dihapus total** -
  membuat/mengelola relasi sekarang lewat sub-bagian "Aturan Operator" di
  tab Indikator yang sudah ada (KM-E), pilih Tipe Aturan "Indikator Lain
  Tercentang". `ConceptMapController` (KM-H, Peta Konsep) ditulis ulang
  baca dari `indikator_rules` bukan tabel lama - **bentuk response API
  SAMA PERSIS** (`cross_ref_count`, `referensi_keluar`, `referensi_masuk`),
  jadi `ConceptMapExplorer.vue` di frontend **tidak perlu diubah sama
  sekali**.
- 8 test baru/diperbarui di `IndikatorRuleControllerTest` (validasi tipe
  ketiga: wajib `depends_on_indikator_id`, tolak field measurement, tolak
  depends-on-diri-sendiri, cascade delete), 5 test baru di
  `ChecklistEngineServiceTest` (cascade via rule, tanpa rule = tanpa
  cascade, rantai berlapis, mutual-dependency stabil), test
  `IndikatorCrossReferenceControllerTest` **dihapus seluruhnya** (fitur
  tidak ada lagi), `ConceptMapControllerTest`/`GrafologiKnowledgeSeederTest`/
  `ChecklistControllerTest` diperbarui pakai `IndikatorRule` bukan
  `IndikatorCrossReference`. 361 test backend lolos (dari 355, net turun
  meski banyak tes baru karena 9 test `IndikatorCrossReferenceControllerTest`
  dihapus).
- Browser-verified end-to-end via Playwright lawan data KB sungguhan: tab
  "Referensi Silang" terkonfirmasi hilang dari 6 tab yang tersisa, tambah
  rule `indikator_checked` baru lewat UI (Indikator "01-1'" depends_on
  "01-2'") tampil sebagai "Tercentang jika Indikator 01-2' tercentang",
  hapus lagi lewat UI yang sama; Peta Konsep untuk Indikator "01-1'" (data
  hasil migrasi asli, bukan buatan) menampilkan badge "3 referensi" +
  3 chip Referensi Silang Keluar (15-2a, 21-2, 30-2a) dengan garis
  penghubung SVG benar. 0 error console di kedua skenario. `indikator_rules`
  dikonfirmasi kembali ke 363 baris (tidak ada sisa data uji) setelah
  verifikasi.

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
- **`POST /api/clients`** (`UserLookupController::store`, gated inline via
  `abort_unless($request->user()->isGrafolog())` — same style as `byEmail()`
  in this controller, added 2026-08-07). Lets a grafolog register a
  walk-in client who hasn't self-registered via `/auth/register` — before
  this, `GET /api/users/lookup` returning 404 was a dead end. Only ever
  creates `role: 'user'` accounts; grafolog can't create staff (that stays
  `Admin\AdminUserController`, administrator-only). `password` is
  optional — if omitted, `Str::password(10)` generates one, returned once
  as `generated_password` in the response for the grafolog to relay
  directly to the client (spoken/written), **not** emailed — `MAIL_MAILER`
  is still `log` (no real SMTP), so an email-invite flow would silently
  go nowhere right now. Doesn't reuse `AuthController::register` — that
  issues a Sanctum token and would swap the calling grafolog's session for
  the new client's, which is wrong here since the grafolog needs to stay
  logged in as themselves. Every creation is
  `AuditLog::record('daftarkan_klien', ...)`-ed.
- **Deferred on purpose, not forgotten**: Billing/Subscription for company
  plans — no pricing model has been decided for this, it's a business
  decision, not a technical one, don't build a `Subscription` table
  speculatively. Also deferred: an admin-facing "Master Data" editor and a
  cross-project Reports view (mentioned in the original MGA plan's Fase 05
  admin panel, still not built), and any bulk-reassignment or
  assignment-history UI.

## Payment (DOKU) — added 2026-07-27

- **Credentials go in `.env`**: `DOKU_CLIENT_ID`, `DOKU_SECRET_KEY`,
  `DOKU_IS_PRODUCTION` (bool), and `DOKU_CALLBACK_URL` (**new 2026-08-06**,
  optional — defaults to `{FRONTEND_URL}/dashboard`, where DOKU's
  `auto_redirect` sends the client's browser after they pay). Get the
  credentials from DOKU Back Office — **sandbox and production are separate
  accounts with separate credentials**, don't reuse one for the other. Read
  via `config('services.doku.*')` (`config/services.php`). Currently empty/
  placeholder — payment creation throws `RuntimeException` internally
  (`DokuService::ensureConfigured()`), which `PaymentController::store`
  **catches** (2026-08-06 fix) and turns into a clean `503` with a generic
  client-facing message — see the note below, don't let this exception
  reach the client raw again.
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
  from `PricingPlan::activePriceFor($tier)` (was `config('pricing.tiers.*')`
  — that config file is deleted, see "Pricing & commerce" below).
  `comprehensive` matches the root CLAUDE.md's "~Rp49rb" as of writing;
  **both tiers' real prices are admin-managed** now via `/admin/pricing`,
  not hardcoded anywhere — check the DB, not this file, for the current
  number. **Accepts an optional `discount_code`** (2026-08-06, Commerce
  Fase D) — validated through `DiscountCode::isValidFor()`, same method the
  Fase B preview endpoint uses. Stores `base_amount` (pre-discount) and
  `discount_code_id` alongside `amount` (what's actually charged) so a
  discounted invoice stays traceable.
- **DOKU config errors are caught, not leaked** (2026-08-06 fix): if
  `DokuService::createCheckout()` throws (unconfigured credentials, DOKU
  rejects the request), `PaymentController::store` catches the
  `RuntimeException`, logs the real message via `Log::error()`, and returns
  a generic `503 {"message": "Pembayaran sedang tidak tersedia, coba lagi
  nanti."}` to the client. Before this fix it was an uncaught 500 with a
  full stack trace in the response body whenever `APP_DEBUG=true` — fine in
  dev, a real information-leak risk if debug mode is ever left on in
  production. `tests/Feature/Api/PaymentControllerTest.php::
  test_unconfigured_doku_returns_clean_503_not_raw_500` locks this in —
  don't remove the try/catch without understanding why that test exists.
- **`::notification`** is DOKU's webhook (public route, no `auth:sanctum` —
  DOKU has no Sanctum token). Security depends entirely on
  `DokuService::verifyNotificationSignature()`; never relax or bypass it.
  On a `SUCCESS` status transition (checked via a `$wasAlreadyPaid` guard so
  a duplicate webhook retry doesn't double-count), it also calls
  `$payment->discountCode?->incrementUsage()` — usage quota is consumed at
  actual payment success, never at preview or at payment creation.
  **Still not fully verified against a real DOKU sandbox notification** —
  the body field names used (`order.invoice_number`, `transaction.status`)
  are sourced from DOKU's official docs but a literal example payload
  couldn't be fetched. Send one real test transaction from DOKU Sandbox and
  compare against what lands in `payments.notification_payload` before
  trusting this in production (this is Commerce Fase C, not yet done).
- **Migration**: `payments` table (`sample_id`, `invoice_number` unique,
  `amount`, `base_amount` nullable, `discount_code_id` nullable FK,
  `status`: pending/paid/failed/expired, `doku_token_id`,
  `doku_payment_url`, `notification_payload` json, `paid_at`).
- **Frontend checkout UI now exists** (`OrderView.vue`, `/pesan`,
  `role: 'user'` — added 2026-08-06, Commerce Fase D, see
  `guratan-web/CLAUDE.md`). It orchestrates the two existing endpoints
  (`POST /api/samples` then this controller's `store`) from the frontend —
  no new combined "create order" endpoint was added, both pieces already
  existed and just needed a UI pointing at them together. The real DOKU
  redirect itself has **not** been tested end-to-end (no sandbox
  credentials yet) — only verified up to the point of calling `store` and
  handling its response/error correctly.
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
- **`incrementUsage()` is called from `PaymentController::notification()`**
  (wired 2026-08-06, Commerce Fase D) — only on a `SUCCESS` webhook, guarded
  against double-counting a retried notification. **Not** called from
  `PricingController::preview` — that would burn quota just from someone
  typing a code to see what it does, before ever paying.
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

## Homepage CMS — added 2026-08-06 (Commerce Fase E), expanded 2026-08-07

- **`content_blocks` table / `App\Models\ContentBlock`**: a flat
  key-value store, deliberately **not** a page-builder (business decision
  2026-08-06). `ContentBlock::EDITABLE_KEYS` is the whitelist of keys an
  admin can write — **21 keys as of 2026-08-07** (grew from the original 3
  when `LandingView.vue` was rebuilt from a single hero block into a full
  company-profile page — one heading/subtext pair per section, plus the
  list-type keys below) — `Admin\ContentBlockController::update` 404s on
  anything else. **Adding a new editable field means updating
  `EDITABLE_KEYS` AND `database/seeders/ContentBlockSeeder.php`'s
  defaults, both — the seeder's defaults exist specifically so a fresh
  install (or one where an admin never touched a field) still shows
  sensible text, not a blank string.**
- **`ContentBlock::LIST_KEYS`** (new 2026-08-07): the subset of
  `EDITABLE_KEYS` whose `value` is a **JSON-encoded array**, not a plain
  string — `landing_compare_old`/`landing_compare_new` (array of 3
  strings), `landing_steps`/`landing_honesty_points` (array of
  `{title, desc}`, 4 and 3 items respectively). The `content_blocks.value`
  column is still plain `text` — this is purely an application-level
  encoding convention, not a schema change. Each list key has a **fixed**
  item count (still "fixed fields, not a page-builder" — an admin can't
  add/remove items, only edit the text of each fixed slot).
  `AdminContentView.vue` renders these as a small structured list editor
  (title/desc input pairs), never a raw JSON textarea — don't let an admin
  hand-edit JSON directly, a malformed value silently falls back to
  `LandingView.vue`'s hardcoded defaults (`JSON.parse` wrapped in
  try/catch there) rather than erroring, which would be confusing to
  debug if someone bypassed the admin UI.
- **`GET /api/content`** (public, no auth): returns a **flat** `{key:
  value}` object (`ContentBlock::pluck('value', 'key')`), not a paginated
  list — this is the shape `LandingView.vue` actually consumes directly,
  don't change it to a list-of-objects without updating the frontend to
  match. List-type values come through as their raw JSON **string**;
  `LandingView.vue` does the `JSON.parse` itself.
- **Admin CRUD**: `GET/PUT /api/admin/content[/{key}]`
  (`Admin\ContentBlockController`, `role:administrator`). `update()` uses
  `updateOrCreate` keyed on `key`, so calling it on a key with no existing
  row creates one — there's no separate "create" step the frontend needs
  to worry about. Every update is `AuditLog::record('ubah_konten', ...)`-ed,
  same pattern as pricing/discount changes. Validation is unchanged
  (`value: string, max:2000`) — a JSON-encoded array of 3-4 short items
  comfortably fits under that limit, no special-casing needed for
  `LIST_KEYS` at the validation layer.
- **Seeder values are the exact text that's hardcoded as `LandingView.vue`'s
  fallback object** — migrating/expanding the CMS changed zero visible
  content until an admin actually edits a field through `/admin/content`.
- Tests: `tests/Feature/Api/ContentControllerTest.php` (public endpoint,
  flat shape), `tests/Feature/Api/Admin/ContentBlockControllerTest.php`
  (admin CRUD, unknown-key rejection, audit log, update-not-duplicate, a
  new-simple-key update, and a `LIST_KEYS` JSON round-trip).

## Announcements — added 2026-08-06 (Commerce Fase F)

- **`announcements` table / `App\Models\Announcement`**: `title`, `body`,
  `is_active` (default `true`), `starts_at`/`ends_at` (both nullable and
  independent — a row can have just one, both, or neither), `target_roles`
  (nullable json array of role strings — `null` means visible to every
  role, not an empty array), `created_by` (nullable FK to `users`,
  `nullOnDelete`).
- **`isVisibleTo(User $user): bool`** is the single source of truth for
  "should this user see this announcement right now" — checks
  `is_active`, the time window, and `target_roles` all in one place, same
  role as `DiscountCode::isValidFor()`. **Always call this, never
  reimplement the checks inline.**
- **`GET /api/announcements`** (`Api\AnnouncementController::index`,
  auth:sanctum): filters `Announcement::all()` through `isVisibleTo()` in
  PHP rather than a DB query — deliberate, announcement volume is expected
  to stay low enough that this isn't worth a query builder. Returns only
  what the current user should see, never the full table.
- **Admin CRUD** (`Api\Admin\AnnouncementController`, `role:administrator`):
  `store()` creates; `update()` allows **full in-place edit**, including
  `is_active`, `title`, `body`, `target_roles`, and the date window — this
  is the opposite of `PricingPlan`/`DiscountCode`'s deactivate-and-recreate
  pattern, and that's intentional: an announcement carries no "past usage"
  semantic worth protecting, so editing one doesn't retroactively redefine
  anything the way editing a redeemed discount code's value would. Every
  create/update is `AuditLog::record()`-ed (`buat_pengumuman`/
  `ubah_pengumuman`), same principle as pricing/discount/content changes.
- **Gotcha avoided, don't reintroduce it**: same class of bug as
  `DiscountCode` (Fase B) — `is_active`'s DB-level default doesn't get
  refetched onto the in-memory model after Eloquent's `create()` on MySQL.
  Mirrored via `protected $attributes = ['is_active' => true];` on the
  model from the start, so this one never actually broke a test — if you
  add another boolean/counter column with a DB default anywhere, mirror it
  in `$attributes` too.
- Tests: `tests/Unit/AnnouncementTest.php` (visibility logic in isolation —
  untargeted, inactive, not-yet-started, ended, role-targeted),
  `tests/Feature/Api/AnnouncementControllerTest.php` (public endpoint,
  role-filtering behavior), `tests/Feature/Api/Admin/AnnouncementControllerTest.php`
  (admin CRUD, validation, audit log).

## Token system for grafolog — added 2026-08-07

- **Grafolog spend tokens to generate reports.** `TokenCost` (per tier)
  and `TokenPrice` (per-token Rupiah, buying tokens) both use the exact
  history-preserving pattern as `PricingPlan::setPriceFor()` — changing a
  value deactivates the old row, creates a new one. **Both tables start
  EMPTY** — `TokenCost::activeTokensFor($tier)` returning `null` is
  treated as `0` (no gate) in `ScoringController::submit`, deliberately,
  so this feature does not silently block any existing grafolog before an
  admin turns it on by setting a cost via `/admin/tokens`.
- **`TokenWalletService` is the ONLY place allowed to change
  `User::token_balance`.** `credit()`/`debit()` lock the user row
  (`lockForUpdate`) and write one immutable `token_ledger_entries` row
  (with `balance_after` snapshotted) in the same transaction — the cached
  `users.token_balance` column and the ledger can never drift apart even
  under concurrent requests. `debit()` throws a 402 when balance is
  insufficient (`abort_if`) — this is called from *inside*
  `ScoringController::submit`'s existing `DB::transaction`, right after
  the report is created, so an insufficient-balance report is never
  partially persisted; there's also a fast pre-check `abort_if` before the
  transaction even opens, for a quick 402 without doing any work first.
  `User::token_balance` is technically fillable (added to the `#[Fillable]`
  attribute so `User::factory()->create(['token_balance' => N])` works in
  tests) but `TokenWalletService` still writes it via `forceFill()` as the
  one authorized path — no controller should ever set it directly.
- **Token purchases reuse the DOKU integration, not a second payment
  flow.** `DokuService::createCheckout()` no longer type-hints `Payment` —
  it takes `(string $invoiceNumber, int $amount, string $currency, ...)`
  as plain arguments, so both `Payment` (sample checkout) and the new
  `TokenPurchase` share one implementation. **DOKU Back Office only has
  ONE Notification URL** (configured outside this codebase, not per
  request), so `PaymentController::notification` now looks up the
  invoice_number in `Payment` first, then `TokenPurchase` — distinguished
  by prefix (`INV-` vs `TOKEN-`) — and dispatches to
  `handleSamplePaymentNotification()` or `handleTokenPurchaseNotification()`
  accordingly. Tokens are only credited (`TokenWalletService::credit()`)
  on a genuine `SUCCESS` transition, guarded by `$wasAlreadyPaid` against
  double-crediting a retried webhook — same pattern as discount usage.
- **Discount codes now also apply to token purchases.**
  `StoreDiscountCodeRequest`'s `applicable_tiers.*` accepts `token` in
  addition to `comprehensive`/`master` — `DiscountCode::isValidFor('token')`
  and `amountOff()` are called completely unchanged, no separate discount
  logic for tokens anywhere.
- **`TokenController`** (public/authenticated reads only — `price()`,
  `wallet()`, `preview()`) is kept separate from **`TokenPurchaseController`**
  (the one action that actually starts a DOKU checkout), mirroring the
  `PricingController` (read) vs `PaymentController` (action) split
  elsewhere in this codebase.
- `DashboardController`'s grafolog KPI list gained `token_balance` /
  "Sisa Token" — no other backend change needed for it to show up, the
  frontend's KPI cards already render generically from that array.
- Tests: `tests/Unit/TokenWalletServiceTest.php` (credit/debit/402 in
  isolation), `tests/Feature/Api/TokenControllerTest.php`,
  `tests/Feature/Api/TokenPurchaseControllerTest.php`, two new methods in
  `tests/Feature/Api/ScoringControllerTest.php` (gate blocks/allows +
  deducts), two new methods in `tests/Feature/Api/PaymentControllerTest.php`
  (webhook credits tokens, does not double-credit a retried SUCCESS),
  `tests/Feature/Api/Admin/TokenPriceControllerTest.php`,
  `tests/Feature/Api/Admin/TokenCostControllerTest.php`.
- **Live-verified against the real dev DB, not just tests** (2026-08-07):
  admin set a price/cost via the browser, dashboard showed the new KPI,
  the wallet page's buy flow failed cleanly on unconfigured DOKU (same
  503 as `OrderView`), and the full gate lifecycle — blocked at balance 0
  → credited via `TokenWalletService::credit()` directly (no real DOKU
  webhook possible without sandbox credentials) → submit succeeded and
  deducted exactly the configured amount, ledger entry linked to the
  resulting report — was confirmed through direct API calls. All test
  data was cleaned up and `token_prices`/`token_costs` reset back to
  inactive afterward, so the gate stays off for other dev accounts until
  an admin deliberately configures it for real.

## Knowledge Management System (KM) — Fase KM-A added 2026-08-08

Full plan (KM-A through KM-H): see the "Rencana Knowledge Management System —
Guratan" artifact from the planning session (not in this repo). **KM-A
(foundational infra) is done** — the rest (KM-B onward: CRUD panels, rule
builder, worksheet, cascade activation, concept map) is **not built yet**.

- **`metodologi_penilaian` table / `App\Models\MetodologiPenilaian`**: labels
  *how* a measurement gets its number (mis. "Master" = manual grafolog with
  calipers). Seeded with exactly one row (`kode: 'master'`) — both a
  migration (`2026_08_08_100000_...`) and `GrafologiKnowledgeSeeder` insert
  it via `updateOrInsert`, so it exists whether you run migrations alone or
  reseed later. **Deliberately not attached to `Sindrom`/`Aspek`/`Indikator`**
  — those are psychological content, identical across any future methodology
  (CV, a revised Master, etc.); only `measurement_variable.metodologi_id`
  carries the label. `measurement_category` was deliberately **not** given
  its own `metodologi_id` — it's always owned by exactly one `variable_id`,
  so its methodology is transitive through the variable; a second column
  would just risk drifting out of sync with its parent.
- **`Indikator.posisi` / `Indikator.varian`** (new columns, both nullable at
  the DB level, following the same pattern as `handwriting_samples.
  project_id`): every Indikator's position (1-10) within its Aspek and
  optional lettered sub-variant (a/b/c/...), previously only inferable by
  parsing the `kode` string. Backfilled for all 704 existing rows via
  `App\Support\IndikatorKode::parse()` — reused by both the one-time backfill
  migration and `GrafologiKnowledgeSeeder` going forward, so a reseed doesn't
  need two copies of the same regex to stay in sync. Handles the source
  data's inconsistent kode formats (apostrophe-suffixed `"01-1'"`,
  space-separated `"31 5b"`, single-digit aspek `"4-10"`, `"-dupN"` suffixes
  that mark a duplicate row at the *same* posisi/varian, not a new one). All
  704 parse successfully; only Aspek 24 is missing position 9 in the source
  data (a genuine data gap, not a parsing bug).
- **`GrafologiKnowledgeSeeder` is now idempotent** (previously used blind
  `DB::table()->insert()`/`insertGetId()` — re-running it on non-empty tables
  either duplicated rows or hit unique-constraint errors). Now uses
  `updateOrInsert` keyed on each table's natural unique column
  (`sindrom.kode_romawi` — **newly made unique** via migration
  `2026_08_08_100100_...`, `aspek.kode`, `indikator.kode`,
  `measurement_variable.kode`, `deskriptif_lookup.kode`). Tables with no
  natural key of their own (`measurement_category` — replaced wholesale per
  `variable_id` on every run; `scoring_rule_band`, `indikator_cross_reference`
  — both fully truncated and reinserted every run) have no per-row identity
  worth preserving *yet*. **`indikator_cross_reference`'s truncate-and-
  reinsert must be revisited before KM-F** (admin-editable cross-reference
  cascade) ships — it would silently wipe any admin-set "aktif" flag on
  reseed. The whole `run()` is wrapped in one `DB::transaction()`. Verified
  by running it twice against the real dev DB: identical row counts and
  identical primary-key IDs (e.g. `sindrom` "I" stayed id 1) both times —
  safe for existing FKs (`ReportAspekScore`, etc.) that point at these IDs.
- **Test fixture change**: `Tests\Concerns\SeedsGrafologiKb::seedMinimalAspek()`
  now uses `Sindrom::firstOrCreate(['kode_romawi' => 'I'], ...)` instead of
  `Sindrom::create()` — required once `kode_romawi` became unique, since
  several tests call this helper twice per test (once per fixture user) and
  need to share the same underlying Sindrom row rather than collide on the
  new constraint.

**KM-B (admin CRUD panels) added 2026-08-08**, all `role:administrator`,
namespace `App\Http\Controllers\Api\Admin\`:

- **`SindromController`** (`/admin/knowledge/sindrom`): full CRUD.
  `destroy()` **guards against `aspek.sindrom_id`'s `cascadeOnDelete`** —
  `abort_if($sindrom->aspek()->exists(), 422, ...)` — without this, deleting
  1 Sindrom would silently cascade-delete every Aspek (and their Indikator,
  cascading again) underneath it. `index()` returns `withCount('aspek')` so
  the frontend can show/enforce this before even trying to delete.
- **`MeasurementVariableController`** (`/admin/knowledge/measurement-
  variables`): full CRUD. `store()` defaults `metodologi_id` to the
  `"master"` row (`MetodologiPenilaian::findByKode('master')?->id`) when the
  request omits it — there's only one methodology today, no reason to force
  the admin to pick it every time. `destroy()` has **no** dependency guard
  (unlike Sindrom) — `measurement_category.variable_id`'s cascadeOnDelete is
  intentional here, categories are wholly owned by their variable (see KM-A's
  §3.0 rationale in the KM plan for why `measurement_category` never got its
  own `metodologi_id`).
- **`MeasurementCategoryController`** (nested,
  `/admin/knowledge/measurement-variables/{id}/categories` for `store()`,
  `/admin/knowledge/measurement-categories/{id}` for `update`/`destroy`): no
  standalone `index()` — categories come back already eager-loaded on
  `MeasurementVariableController::index()`.
- **`ScoringRuleBandController`** (`/admin/knowledge/scoring-rule-bands`):
  full CRUD, no dependency guard needed —
  `ScoringRuleBand::labelUntukSkor()` looks bands up dynamically by
  `polaritas`/`rentang_skor` at report-generation time, nothing stores an FK
  to a specific band row.
- All 4 write audit log entries (`buat_sindrom`/`ubah_sindrom`/
  `hapus_sindrom`, `buat_variabel_ukur`/..., `buat_kategori_ukur`/...,
  `buat_band_skor`/...), same pattern as every other admin controller.
- **Aspek (with its 4 narasi levels), Indikator, and the operator/rule
  system are NOT built yet** — that's KM-C/D/E. This phase only covers the
  4 "simple" entities from the KM plan's roadmap table.
- 30 new tests (`tests/Feature/Api/Admin/{Sindrom,MeasurementVariable,
  MeasurementCategory,ScoringRuleBand}ControllerTest.php`) — 242 total.

**KM-C (Aspek CRUD) added 2026-08-08**, same `Api\Admin\` namespace:

- **`AspekController`** (`/admin/knowledge/aspek`): full CRUD, including the
  4 narasi levels (`narasi_very_high`/`_high`/`_medium`/`_low`) +
  `keterangan_umum`. `destroy()` guards against `indikator.aspek_id`'s
  `cascadeOnDelete` the same way `SindromController::destroy()` guards
  against `aspek.sindrom_id` — `abort_if($aspek->indikator()->exists(), 422,
  ...)`. `index()` returns `with('sindrom:id,kode_romawi,nama')->
  withCount('indikator')` so the admin UI can show/enforce the delete guard
  without a second request.
- Audit log: `buat_aspek`/`ubah_aspek`/`hapus_aspek`.
- 9 new tests (`tests/Feature/Api/Admin/AspekControllerTest.php`) — 251
  total.

**KM-D (Indikator CRUD) added 2026-08-08**, same `Api\Admin\` namespace:

- **`IndikatorController`** (`/admin/knowledge/indikator`): full CRUD, but
  **`index()` is paginated + searchable/filterable** — unlike the other 3 KM
  controllers, this one can't just return everything (704 rows). `?search=`
  matches `kode` or `nama` (`LIKE`), `?aspek_id=` filters to one Aspek,
  `?page=` via `paginate(25)`. `posisi`/`varian` (added in KM-A) are now real
  editable fields, not just backfilled data. **No delete guard needed**
  (unlike Sindrom/Aspek) — `indikator_cross_reference.indikator_sumber_id`
  is `nullOnDelete`, not `cascadeOnDelete`, so deleting an Indikator at most
  nulls out a cross-reference row, never cascades further deletes.
- Audit log: `buat_indikator`/`ubah_indikator`/`hapus_indikator`.
- 9 new tests (`tests/Feature/Api/Admin/IndikatorControllerTest.php`) — 260
  total.
**KM-E (operator/rule builder) added 2026-08-08** — the piece the whole KM
plan was building toward: connecting an Indikator to a measurement-based
auto-check rule.

- **`indikator_rules` table / `App\Models\IndikatorRule`**: 2 rule types per
  the KM plan's §3.2. `category` — `variable_a_id` + `category_label`
  (string, mis. "Middle zone height" @ "large"). `comparison` —
  `variable_a_id` `operator` (`equals`/`greater_than`/`less_than`/
  `greater_or_equal`/`less_or_equal`) `koefisien` × EITHER `variable_b_id`
  OR `compare_value` (exactly one, never both/neither). Both FKs
  `cascadeOnDelete` — a rule referencing a deleted Indikator or
  MeasurementVariable is meaningless, so it goes with it.
- **`Indikator.rule_group_logic`** (`AND`/`OR`, default `OR`) — determines
  how >1 rule row for the SAME Indikator combine. **Deliberately NOT a
  column on `indikator_rules`** (each rule row repeating an identical value)
  — that would let 2 rules for 1 Indikator disagree on their own combine
  logic, a nonsensical state. One Indikator, one group-logic value.
- **`StoreIndikatorRuleRequest`/`UpdateIndikatorRuleRequest`**: the
  type-conditional validation (category fields forbidden on a comparison
  rule and vice versa, exactly-one-of variable_b/compare_value) is done in
  `withValidator()`'s `after()` hook, not via declarative `required_if`/
  `prohibited_if` chains — much more readable for 2 mutually-exclusive
  shapes than fighting Laravel's conditional-rule combinators.
  **`category_label` is validated against real `measurement_category` rows**
  for the chosen `variable_a_id`, not just "any string" — a category label
  that doesn't match a real category would silently never match anything at
  scoring time (KM-G), a correctness bug worth catching at write time.
- **`Api\Admin\IndikatorRuleController`** (nested under `IndikatorController`,
  same pattern as `MeasurementCategoryController` under
  `MeasurementVariableController`): `store()` at
  `POST /admin/knowledge/indikator/{indikator}/rules`,
  `update`/`destroy` at `/admin/knowledge/indikator-rules/{indikatorRule}`.
  No standalone `index()` — rules come back eager-loaded on
  `IndikatorController::index()` (`rules.variableA`/`rules.variableB`).
- **`MeasurementVariableController::destroy()` gained a guard** (previously
  had none, see KM-B note above) — `abort_if` if the variable is referenced
  by any rule as `variable_a_id` OR `variable_b_id`. Deleting a
  `measurement_category` is still unguarded (fully owned by its variable),
  but a variable used by an admin-authored operator rule is no longer safe
  to silently cascade away.
- Audit log: `buat_aturan_indikator`/`ubah_aturan_indikator`/
  `hapus_aturan_indikator`.
- 16 new tests (`tests/Feature/Api/Admin/IndikatorRuleControllerTest.php`)
  — 276 total.
- **Still not built**: KM-G (the actual Measurement Worksheet form + wiring
  `ScoringController` to auto-check Indikator via these rules instead of
  manual 1-10 input), KM-H (visual knowledge concept map). **Rules exist
  and can be authored, but nothing yet EVALUATES them** —
  `ScoringController::submit` is completely unchanged, still takes manual
  `skorPerAspek` input. That's KM-G's job.

**KM-F superseded 2026-08-19** — see "Unifikasi cross-reference ke
indikator_rules" above. `indikator_cross_reference` table, its controller,
and the admin "Referensi Silang" tab described below no longer exist;
the same relationships now live as `indikator_rules` rows
(`rule_type='indikator_checked'`). Kept below for history only.

**KM-F (`indikator_cross_reference` management) added 2026-08-08** —
activates a table that was dormant since the original JSON→DB conversion
(257 matched / 280 total rows), per the KM plan's §3.3.

- **`indikator_cross_reference.aktif`** (new boolean column, default
  `true`): the admin-editable "is this cascade relationship live" flag.
  **This is NOT the cascade-trigger UI itself** — checking Indikator A
  auto-suggesting Indikator B only makes sense once a real
  grafolog-facing Indikator checklist form exists, which is KM-G's job.
  This phase is purely the data-management layer underneath that future
  UI: view/search/toggle-active/fix/delete the 280 cross-reference rows.
- **`GrafologiKnowledgeSeeder::seedCrossReference()` rewritten to be
  idempotent** (previously the one remaining delete-then-reinsert method,
  flagged as a known gap since KM-A) — `updateOrInsert` keyed on the
  composite pair `(indikator_sumber_raw, mereferensikan_ke_kode)`,
  confirmed unique across all 280 rows before relying on it. **`aktif` is
  deliberately excluded from the update payload** — re-seeding an admin-
  deactivated row must not silently flip it back to `true`. Verified with
  a real deactivate → reseed → still-deactivated round trip, both in the
  sqlite test suite and against the real dev DB.
- **`Api\Admin\IndikatorCrossReferenceController`**: full CRUD +
  pagination/search (`?search=` matches `indikator_sumber_raw` or
  `mereferensikan_ke_kode`, `?aktif=`/`?match_status=` filter). `match_status`
  is **computed server-side** on every write (`matched` only if BOTH
  `indikator_sumber_id` resolves to a real Indikator AND
  `mereferensikan_ke_kode` matches a real Indikator's `kode`) — never
  accepted as raw input, so an admin can't create a row that claims
  "matched" while the underlying data doesn't actually resolve.
- **`IndikatorController::options()`** (new,
  `GET /admin/knowledge/indikator-options`): a lightweight unpaginated
  `{id, kode, nama}` list of all 704 Indikator, added specifically to
  populate the cross-reference form's "pilih Indikator sumber" dropdown —
  the existing `index()` is paginated and unsuitable for that.
- Audit log: `buat_referensi_silang`/`ubah_referensi_silang`/
  `hapus_referensi_silang`.
- 10 new tests (9 in `IndikatorCrossReferenceControllerTest` + 1 new seeder
  test, `test_reseeding_preserves_admin_deactivated_cross_reference`) — 286
  total.

**KM-G (measurement worksheet -> checklist -> scoring) added 2026-08-08** —
the phase the whole KM plan was building toward, and the one flagged as
"paling sensitif" since it's the first KM phase that touches the live
scoring pipeline. **Deliberately does NOT modify `ScoringController`,
`SubmitScoresRequest`, or `ScoringEngineService` at all** — the old manual
1-10 form (`SindromAccordion`) is untouched and stays the default. Instead:

- **`measurement_readings`** (new table/model): 1 row per
  `(sample_id, variable_id)`, the grafolog's raw caliper measurements for
  that sample. Upserted via `POST /api/samples/{sample}/measurements`
  (`MeasurementController`, same authorization guards as
  `ScoringController::preview` - grafolog + `isScorableBy` + not-`rapid` +
  not-`completed` + payment gate). `GET /api/measurement-variables`
  (`MeasurementVariableController`, new, NOT the admin one) is a
  read-only list for any authenticated user, mirroring `SindromController`
  vs `Api\Admin\SindromController`'s split.
- **`sample_indikator_checks`** (new table/model): the single source of
  truth for "is this Indikator checked for this sample right now."
  **`checked` is a boolean column, not row-presence** - unchecking
  something does NOT delete the row, it sets `checked=false` and keeps it.
  This was a deliberate fix after the first implementation used
  delete-on-uncheck and a unit test caught the bug it causes: without a
  persisted "already decided" marker, re-running rule evaluation after a
  later measurement addition would silently re-tick something the grafolog
  had explicitly rejected. `sumber` distinguishes `auto` (matched an
  `indikator_rules` row), `cascade` (via `indikator_cross_reference`), or
  `manual`. `rule_id`/`cross_reference_id`/`keterangan_pemicu` record WHY,
  for on-screen display - the KM plan explicitly required this ("Form
  Indikator wajib tampilkan nilai/referensi measurement yang memicu
  centang otomatis... supaya grafolog tahu KENAPA sesuatu tercentang").
- **`App\Services\Scoring\ChecklistEngineService`** is the whole engine,
  pure PHP, fully unit-tested (`tests/Unit/Services/
  ChecklistEngineServiceTest.php`, 15 tests):
  - `evaluateSample()`: reads `measurement_readings`, runs every
    Indikator's `indikator_rules` (category: resolves
    `MeasurementVariable::kategoriUntukNilai()` and string-compares the
    label; comparison: `variable_a [operator] (koefisien × variable_b OR
    compare_value)`), combines multiple rules per Indikator via
    `rule_group_logic` (AND requires all non-null-true, short-circuits
    false the moment ANY rule is definitively false even if others are
    unresolved; OR is true the moment ANY rule is true). A rule whose
    referenced variable has no reading yet evaluates to `null`
    (unresolved, not false) - an Indikator stays un-auto-checked, not
    "wrongly checked," when data is incomplete. **Only ever creates a row
    for an Indikator that has NO existing row at all** (any `checked`
    value) - this is what makes re-evaluation safe to call repeatedly
    (idempotent) without ever overwriting a grafolog's prior decision.
  - Cascade (`applyCascadeFrom`): **single-hop only, not recursive** - a
    newly-checked Indikator's own `indikator_cross_reference` rows
    (`aktif=true`, `match_status='matched'`) get auto-checked too (if not
    already decided), but a cascade-checked Indikator's own outgoing
    references are NOT chased further. This is a scoping decision (not in
    the original plan text) made to keep behavior bounded and match the
    "flat, not deeply nested" design philosophy already used for
    `rule_group_logic` - revisit only if a real multi-hop case shows up.
  - `toggle()`: manual check/uncheck. Checking always sets
    `sumber='manual'` + reruns the cascade pass. Unchecking something that
    had cascaded to still-checked targets does **NOT** auto-uncheck them
    (per the KM plan's §3.3 spec - cascade is one-directional, one-time) -
    it returns `{ok: false, requires_confirmation: true,
    cascade_candidates: [...]}` without changing anything, and only acts
    once the caller resubmits with `also_uncheck_cascaded: [ids]`.
  - `tallyPerAspek()`: skor per Aspek = **count of distinct `posisi` (not
    Indikator rows) that have >=1 checked Indikator** - lettered variants
    (a/b/c) at the same posisi are OR'd together, matching the KM plan's
    §3.2 "varian a/b/c dalam 1 posisi di-OR-kan." Clamped to `max(1,
    min(10, count))` because `ScoringEngineService::generate()` rejects a
    skor of 0 - **this means 0-checked and 1-checked both read as skor 1**,
    a deliberate, documented floor-collision rather than a bug; revisit
    only with an explicit product decision to widen the scale.
  - `checklistFor()`: the full Sindrom -> Aspek -> Indikator grouped view
    (incl. tally) the frontend renders, calling `evaluateSample()` first
    so it's always fresh against the latest measurements.
- **`Api\ChecklistController`**: `GET /api/samples/{sample}/checklist`,
  `POST /api/samples/{sample}/checklist/toggle`
  (`ToggleIndikatorCheckRequest`). Same authorization pattern as
  `ScoringController`. Every successful toggle is
  `AuditLog::record('ubah_centang_indikator', ...)`-ed; every measurement
  save is `AuditLog::record('isi_pengukuran', ...)`-ed.
- **The bridge back to the untouched scoring endpoint is 100% frontend-side
  and byte-for-byte identical to manual mode**: the frontend reads
  `aspek.skor` off the checklist response and writes it into the exact
  same `scores` ref `SindromAccordion` would have populated, then POSTs to
  the pre-existing `POST /api/samples/{sample}/scores`. Proven by
  `tests/Feature/Api/ChecklistScoringIntegrationTest.php`, which drives
  the real HTTP sequence (measure -> checklist -> manual toggle -> read
  tally -> submit through the unmodified endpoint) and asserts the
  resulting `report_aspek_scores` row matches the tally exactly.
- Browser-verified against real KB data (2026-08-08): created a real
  `indikator_rules` row on Indikator `02-8a` ("Middle zone height large",
  category rule against the real "Middle zone height" variable/bands),
  entered `3.5` (falls in the real "large" 3.26-4.5 band) into the
  worksheet, confirmed the checklist auto-checked it with an "AUTO" badge
  and the reason text "Middle zone height: 3.5 → large" rendered inline
  (screenshot-verified), confirmed a real `indikator_cross_reference` row
  (`02-8a` -> `04-5a`) cascaded a second Indikator in a DIFFERENT Aspek,
  applied the tally to the form, and submitted through the real
  `POST /scores` endpoint to a completed report (201, correct
  `report_aspek_scores`). All throwaway rules/users/samples/tokens were
  cleaned up afterward - dev DB confirmed back to baseline row counts. 315
  backend tests passing (up from 286), `pint --test` clean on touched files.
- **What KM-G deliberately does NOT do**: no "mode" flag was added to
  `SubmitScoresRequest`/`ScoringController` - there was never a need to,
  since the checklist path produces the identical payload shape the
  manual path always produced. "Review-acknowledged" (see
  `guratan-web/CLAUDE.md`'s `IndikatorChecklist.vue` note) is still
  frontend-only, not a `sample_indikator_checks` column - it's a UI gate
  on the hand-off button, not something the backend needs to know or
  enforce.
- **`indikator_rules` starts EMPTY, same pattern as `TokenCost`/`TokenPrice`
  (see "Token system" below) - auto-check is silently a no-op until an
  administrator authors at least one rule** through the KM-E rule builder
  (`/admin/knowledge` → Indikator tab → "Aturan Operator"). Confirmed
  2026-08-08 when a user reported "auto-ceklis tidak bekerja" after
  testing - the engine itself was live-verified working correctly against
  their real sample the moment a rule existed; the count was just 0. If
  this comes up again, check `IndikatorRule::count()` before assuming a
  code bug.

**KM-G fixed post-review, 2026-08-08** - a code review of the freshly-built
KM-G/KM-H work found and fixed 7 real bugs (verified individually, not
taken on faith from the review output) before treating this as
production-ready. The 5 backend-touching ones:
  1. `ChecklistController::index`/`toggle` were missing the
     `requiresPayment()`/`isPaid()` 402 gate that `MeasurementController`
     and `ScoringController` both enforce - an unpaid client-sourced
     sample's checklist was fully viewable/editable. Added, matching the
     existing pattern exactly.
  2. `ChecklistEngineService::toggle()` couldn't distinguish "declined the
     cascade-uncheck prompt" from "hasn't been asked yet" - both were
     represented as an empty `$alsoUncheckCascaded` array, so declining
     just re-triggered the same prompt forever and the source Indikator
     could never actually be unchecked alone. Fixed by adding an explicit
     `bool $confirmed` parameter (also threaded through
     `ToggleIndikatorCheckRequest`/`ChecklistController`) - the frontend
     now always sends `confirmed: true` on the follow-up call regardless
     of the grafolog's answer, so an empty cascade list unambiguously
     means "confirmed, but nothing to cascade."
  3. `evaluateSample()` used to skip ANY Indikator with an existing check
     row, including `sumber=auto` ones - correcting a measurement
     afterward left a stale auto-check (and an inaccurate
     `keterangan_pemicu`) on screen forever. Fixed: only `manual`/`cascade`
     rows are frozen now; `auto` rows are live-reconciled against current
     measurements on every `evaluateSample()` call (and only touched when
     the live result is definitive - `null`/unresolved leaves the last
     known state alone, so a mid-edit blank field doesn't erase anything).
     A **definitive false result is now also persisted** (`checked=false,
     sumber=auto`), not just true - this is what makes a later false→true
     correction detectable as a real transition worth cascading from.
  4. `evaluateIndikator()`'s AND/OR combination used
     `Collection::where('result', false)`, which compares loosely - PHP
     treats `null == false` as true, so an unresolved rule (missing
     measurement) was silently counted as "definitively false" instead of
     "not enough data yet." Fixed by switching to `->filter(fn ($r) =>
     $r['result'] === true/false)` (strict comparison).
  5. `StoreMeasurementReadingsRequest`/`MeasurementController::store` now
     accept `nilai: null` as an explicit "delete this reading" signal
     (previously `nilai` was `required`, so there was no way to remove a
     bad measurement through the API at all - see
     `guratan-web/CLAUDE.md` for the frontend half of this fix).
  12 new/updated backend tests, 327 total (up from 320). Full detail incl.
  the 2 frontend-only fixes is in `guratan-web/CLAUDE.md`.

**KM-H (visual concept map for administrators) added 2026-08-08** - the
final phase of the KM plan, purely read-only, no risk to any of the
CRUD/scoring surfaces above.

- **`Api\Admin\ConceptMapController`** (`role:administrator`, no
  store/update/destroy - editing still happens through the 6 existing
  KM-B..F tabs): 3 progressively-loaded endpoints instead of one endpoint
  dumping all 704 Indikator + every relation at once, which would be both
  slow and unreadable as a "map."
  - `overview()` (`GET /admin/knowledge/concept-map`): all 8 Sindrom with
    nested Aspek + `indikator_count` (via `withCount`) - the whole first
    two rings of the map in one small, cheap request.
  - `aspek(Aspek $aspek)` (`GET .../concept-map/aspek/{aspek}`): that
    Aspek's Indikator list with `rules_count` (`withCount('rules')`) and a
    separately-computed `cross_ref_count` (grouped count query over
    `indikator_cross_reference` keyed by `indikator_sumber_id`, merged in
    PHP) - lets the frontend badge which Indikator have relations worth
    exploring without loading the relations themselves yet.
  - `indikator(Indikator $indikator)` (`GET .../concept-map/indikator/
    {indikator}`): full detail for one Indikator - its `rules` (with
    `variableA`/`variableB` names eager-loaded), **outgoing** cross-
    references (`referensiKeluar()`, `aktif=true`+`matched` only) resolved
    to their target Indikator via `kode`, AND **incoming** ones (queried by
    `mereferensikan_ke_kode = $indikator->kode`, same aktif+matched filter)
    resolved via `indikatorSumber`. Showing both directions is the point -
    the existing Referensi Silang admin tab (KM-F) only shows the outgoing
    row you're editing, never "who points at me."
- Frontend: 7th tab "Peta Konsep" in `AdminKnowledgeView.vue` (see
  `guratan-web/CLAUDE.md` for the `ConceptMapExplorer.vue` UI detail).
- Browser-verified against real KB data (2026-08-08, same throwaway rules
  as the KM-G verification, cleaned up after): selected Sindrom I -> Aspek
  "02 - Ego Needs" (19 Indikator) -> Indikator "02-8a", confirmed its rule
  text and 3 real outgoing cross-reference chips rendered, clicked one
  (`04-5a`) and confirmed the map jumped to it (new Sindrom/Aspek/
  Indikator selection + a fresh detail fetch), confirmed 2 SVG connector
  lines rendered through the selected path. Zero console errors. 5 new
  tests (`tests/Feature/Api/Admin/ConceptMapControllerTest.php`), 320
  backend tests total (up from 315).
- **This closes the entire KM-A through KM-H plan** - every knowledge
  entity has admin CRUD, the operator/rule system is both authorable
  (KM-E) and live-evaluated (KM-G), cross-references are both managed
  (KM-F) and both cascade-active and browsable in both directions (KM-G,
  KM-H). Nothing from the original plan is outstanding.

## Aturan Irregularity — konten pertama lewat rule builder, 2026-08-08

**`database/seeders/IrregularityRuleSeeder.php`** (dipanggil dari
`DatabaseSeeder`, setelah `GrafologiKnowledgeSeeder`) - 28 baris
`indikator_rules` pertama yang benar-benar berisi konten sungguhan
(sebelum ini `indikator_rules` selalu kosong, hanya diuji lewat data
buatan test). Bukan bagian dari `GrafologiKnowledgeSeeder` karena datanya
BUKAN dari JSON sumber Excel - ini hasil analisis baru (user memberi 20
ambang ukur untuk konsep "irregular", dicocokkan ke nama 45 Indikator
yang mengandung kata "regular"/"irregular" lewat diskusi langsung, bukan
tebakan otomatis). Idempoten (`updateOrCreate`), aman dijalankan ulang.

- **20 ambang yang diberikan user** (semuanya relatif ke variabel
  "Middle zone height" via rasio, kecuali 6 item sudut yang pakai ambang
  absolut derajat) dicocokkan ke Indikator yang namanya benar-benar
  menyebut variabel itu + kata "irregular"/"regular" - bukan dibuat
  untuk semua 20 variabel secara membabi-buta. **7 dari 20 variabel tidak
  punya Indikator "irregular/regular" yang cocok sama sekali** (Diacriticals
  height, Baseline Spacing, UZ&LZ width, Upper/Lower zone upslant-downslant)
  - sengaja tidak dibuatkan aturan, tidak ada target untuk itu.
- **"Regular" = kebalikan matematis dari "irregular"** pada ambang yang
  sama (`greater_than` → `less_or_equal`), bukan variabel/ambang
  terpisah - keputusan eksplisit user. Untuk Extension Spacing yang
  "irregular"-nya adalah OR dari 2 syarat (>4mm ATAU >1×MZH), pasangan
  "regular"-nya otomatis jadi AND dari 2 syarat terbalik (≤4mm DAN
  ≤1×MZH) - hukum De Morgan, bukan aturan baru yang ditebak.
- **"Middle zone height irregular/regular" (5 Indikator) SENGAJA tidak
  diberi aturan** - sumber data user untuk item ini secara harfiah
  membandingkan variabel dengan dirinya sendiri ("Range is more than 1x
  Middle zone height" untuk variabel "Middle zone height" itu sendiri),
  kemungkinan typo/kesalahan transkripsi di sumber asli. User memutuskan
  untuk skip, bukan menebak variabel pembanding yang benar. Begitu pula
  ~15 Indikator "irregular"/"regular" lain yang nama variabelnya tidak
  ada di 20 daftar user (Connectedness, Pressure pattern, Margin(s),
  "Regularity" generik tanpa nama variabel) - tidak ada hubungan jelas,
  tidak dibuatkan aturan.
- **`M, Z, Ovals width` (1 measurement_variable, gabungan 3 hal terukur
  jadi 1 kolom nilai) dipakai bersama oleh 2 pasang Indikator berbeda**
  ("Middle zone width irregular" DAN "Ovals width irregular") dengan
  ambang yang sama - bukan bug, itu memang bagaimana data sumbernya
  digabung sejak konversi awal (lihat KM-A's catatan soal variabel yang
  masih perlu direvisi).
- Live-verified (bukan cuma test unit) lewat `ChecklistEngineService`
  sungguhan dengan sample nyata: Ovals height=3 vs MZH=2 (rasio >1) →
  "Ovals height irregular" tercentang benar; Middle zone upslant=10°
  (≤30°) → "Middle zone upslant irregular" (×4 baris) TIDAK tercentang,
  sementara pasangan "Middle zone upslant regular"-nya JUSTRU tercentang
  - membuktikan pasangan irregular/regular saling eksklusif seperti
  seharusnya, bukan cuma tersimpan benar tapi tidak pernah dievaluasi.
- 6 test baru (`tests/Unit/IrregularityRuleSeederTest.php`), 333 backend
  tests total (up from 327).

**2 batch analisis lanjutan, 2026-08-08 (sama hari, diminta setelah
irregularity di atas):**

- **`CategoryMatchRuleSeeder.php`** - 66 aturan tipe `category`. Pola
  paling sederhana yang ditemukan: Indikator yang namanya PERSIS SAMA
  dengan `"[nama Variabel Ukur] [nama kategori]"` milik variabel itu
  (mis. Indikator "Middle zone height large" == Variabel "Middle zone
  height" @ kategori "large" - sudah ada rentang mm-nya di
  `measurement_category`, jadi TIDAK ADA tebakan ambang sama sekali,
  beda dari batch irregularity yang harus menebak rasio). Dicari lewat
  pencocokan string persis (case-insensitive) antara 704 nama Indikator
  vs 34 Variabel × kategorinya, daftar hasil dibekukan sebagai array PHP
  eksplisit di seeder (bukan dihitung ulang tiap run) supaya perubahan KB
  nanti tidak diam-diam mengubah aturan yang sudah direview. Beberapa
  Indikator dengan nama sama muncul di sampai 5 Aspek berbeda (mis.
  "Middle zone height large") - semuanya dapat aturan yang sama, itu
  memang benar (1 ciri fisik jadi bukti untuk beberapa trait kepribadian
  tergantung Aspek), bukan duplikat keliru.
- **`VariableEqualityRuleSeeder.php`** - 7 aturan tipe `comparison`
  (`operator: equals`, `koefisien: 1.0`). Dari 28 Indikator yang namanya
  mengandung bahasa perbandingan ("equals", "larger than", "narrower
  than", dst), cuma 7 yang jelas membandingkan 2 Variabel Ukur yang
  benar-benar ada ("Middle zone width equals middle zone height",
  "Ovals width equals ovals height"). **21 SENGAJA di-skip**, dengan
  alasan berbeda-beda per kelompok (didokumentasikan lengkap di docblock
  seeder) - paling penting untuk diingat: **Indikator 38-9 ("Score for
  Mental Orientation is 2.0+ points higher than score for Physical
  Energy") membandingkan SKOR ASPEK, bukan measurement mentah** - ini di
  luar kemampuan skema `indikator_rules` sama sekali (dirancang untuk
  membandingkan `measurement_readings`, bukan hasil tally skor Aspek
  yang baru ada SETELAH scoring selesai) - kalau user minta jenis aturan
  ini lagi nanti, itu butuh desain skema baru, bukan sekadar entri baru
  di seeder yang ada. Juga di-skip: perbandingan tanda tangan-vs-teks (9,
  tidak ada variabel terpisah), margin sisi-ke-sisi (3, sudah digabung 1
  kolom di `measurement_variable`), bentuk huruf spesifik (4, terlalu
  granular), 2 kasus butuh konfirmasi user yang belum dijawab (konteks
  "in signature"/"in text", konvensi tanda "ascending").
- Kata "or"/"and" yang muncul di beberapa nama Indikator (mis. "M's and
  N's or m's and n's") DIPERIKSA dan dipastikan cuma tata bahasa Inggris
  biasa (huruf besar ATAU kecil), bukan operator logika - tidak dihitung
  sebagai kandidat `rule_group_logic` AND/OR.
- Live-verified lagi lewat `ChecklistEngineService` sungguhan (bukan
  cuma unit test): MZH=3.5 (masuk kategori "large") → 5 Indikator
  "Middle zone height large" di 5 Aspek berbeda semua tercentang benar;
  M/Z/Ovals width=3.5 = MZH=3.5 → 4 Indikator "equals" tercentang benar;
  Letter Spacing=0.3 → "Letter spacing narrow" tercentang, "balanced"/
  "broad" TIDAK, "Letter spacing regular" (dari batch sebelumnya) juga
  tetap tercentang benar berdampingan - ketiga batch aturan (irregularity,
  category, equality) hidup berdampingan tanpa saling bentrok.
- 8 test baru (`CategoryMatchRuleSeederTest`, `VariableEqualityRuleSeederTest`),
  341 backend tests total (up from 333). `indikator_rules` sekarang 101
  baris total (28 + 66 + 7).

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
