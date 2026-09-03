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
  subsequent administrator/supervisor/**hr** account is created by an
  already-logged-in Administrator via `POST /api/admin/users`
  (`AdminUserController`, `StoreStaffUserRequest`) — **not** by re-running
  the seeder and **not** via public registration. `role: hr` additionally
  **requires** `company_id` in that same request (the company must already
  exist — `POST /api/admin/companies` first).
  **`grafolog` is provisioned differently since 2026-09-02** — see
  "Pendaftaran grafolog lewat verifikasi data" below; it's neither
  `AdminUserController::store()` nor public `/auth/register` anymore.
  `RegisterRequest` now only accepts `role: user`; this is enforced by a
  test (`test_register_rejects_administrator_and_supervisor_and_grafolog_roles`)
  — if that test ever needs to change, it means the provisioning decision
  changed and root `ROADMAP.md` / memory need updating too.
- Supervisor has **no dedicated functionality yet** — the role exists and
  is assignable, but there's no review queue or supervisor-specific view.
  That was explicitly deferred (see ROADMAP.md Fase 05 entry), not
  forgotten — don't build it speculatively without a product decision on
  what a supervisor actually reviews.

## Pendaftaran grafolog lewat verifikasi data — added 2026-09-02

User minta jalur pendaftaran grafolog yang lewat verifikasi ("cukup
biodata dan bukti profesi atau apapun"). Sebelum ini, `RegisterRequest`
publik (`/auth/register`) langsung mengizinkan `role: grafolog` tanpa
review sama sekali (`test_register_can_create_grafolog_role`, dari MGA
Fase 05) — **jalur itu sekarang DITUTUP** (`RegisterRequest.rules()` cuma
izinkan `role: user`), digantikan alur baru ini.

- **`grafolog_applications` table / `App\Models\GrafologApplication`**:
  `name`, `email`, `password` (cast `hashed` - dipakai APA ADANYA saat
  approve, bukan di-generate ulang, karena Laravel's `hashed` cast
  mendeteksi string yang sudah ter-hash lewat `Hash::isHashed()` dan tidak
  hash ulang), `phone` nullable, `catatan` nullable (bebas - pengalaman/
  sertifikasi/apa pun, sesuai instruksi user "atau apapun"),
  `document_path`+`document_original_name` (bukti profesi), `status`
  (`pending`/`approved`/`rejected`, default `pending`), `reviewed_by`
  (nullable FK users)/`reviewed_at`/`review_note`.
- **`POST /api/grafolog-applications`** (publik, throttle:20,1 - satu grup
  dengan `/auth/register`/`/auth/login`) — `GrafologApplicationController`
  (bukan di bawah `Admin\`). **Beda mendasar dari `AuthController::register`**:
  TIDAK menerbitkan token Sanctum, TIDAK membuat baris `users` sama
  sekali - cuma `grafolog_applications` berstatus `pending`. Dokumen
  disimpan di disk `local` (private, `storage/app/private`, BUKAN disk
  `public`) - mengikuti pola `ReportPdfService`/`ReportController::pdf()`,
  bukan pola upload lama Rapid tier yang sudah dihapus (itu dulu pakai
  disk publik tanpa authorization check, ditandai sebagai security finding
  yang belum diperbaiki sebelum akhirnya seluruh Rapid tier di-retire).
  `StoreGrafologApplicationRequest`: email harus belum jadi `users` DAN
  belum punya pengajuan `pending` lain (pengajuan yang sudah `rejected`
  BOLEH dipakai daftar ulang - lihat `Rule::unique(...)->where('status',
  'pending')`), `document` wajib `mimes:jpg,jpeg,png,pdf|max:5120` (5MB).
  Audit log `ajukan_akun_grafolog` dengan `actor_user_id` **null** (belum
  ada user staf yang login di titik ini - pengajuan publik/anonim).
- **`Admin\GrafologApplicationController`** (`role:administrator`):
  `index()` (paginated, filter `?status=`, eager-load `reviewer:id,name`),
  `document()` (streaming download lewat `Storage::disk('local')->download()`,
  sama pola dengan `ReportController::pdf()` - tidak ada URL publik ke
  dokumen ini sama sekali), `approve()`, `reject()`.
  - **`approve()`**: guard `status !== 'pending'` (422, sudah diproses) dan
    guard email sudah dipakai `users` lain (422 - kasus tepi kalau
    seseorang lain sempat daftar dengan email yang sama di antara
    pengajuan dan approval). Kalau lolos: `DB::transaction` bikin `User`
    baru (`role: grafolog, is_active: true`, password dari
    `$grafologApplication->password` yang sudah hashed) + update
    `grafolog_applications.status = approved` + `reviewed_by`/`reviewed_at`,
    audit log `setujui_akun_grafolog`.
  - **`reject()`**: guard `status !== 'pending'` sama, set `status =
    rejected` + `review_note` opsional (`RejectGrafologApplicationRequest`),
    audit log `tolak_akun_grafolog`. Dokumen TIDAK dihapus otomatis saat
    ditolak - tetap ada untuk jejak audit (bisa dilihat admin kapan pun
    lewat `document()`), bukan retensi 30-hari otomatis seperti akun
    pengguna biasa (lihat Kebijakan Privasi) - beda konteks (dokumen
    pengajuan yang ditolak, bukan data akun aktif).
- **Frontend**: `RegisterGrafologView.vue` (`/daftar-grafolog`, publik,
  `guestOnly`) - form `FormData`/`multipart` (bukan JSON biasa seperti
  `RegisterView.vue` - lihat `guratan-web/CLAUDE.md`), tidak redirect ke
  dashboard setelah submit (beda dari `RegisterView`/`auth.register()`) -
  cuma tampilkan pesan "tunggu review admin", tidak ada token untuk
  di-redirect ke mana pun. `RegisterView.vue`'s role dropdown
  (`user`/`grafolog`) **dihapus** - sekarang cuma daftar `user`, dengan
  link ke `/daftar-grafolog` untuk calon grafolog.
  `AdminGrafologApplicationsView.vue` (`/admin/grafolog-applications`,
  nav "Verifikasi Grafolog" + `CommandPalette.vue` entry) - expand-row
  pattern yang sama dengan tab-tab lain (bukan modal), status filter,
  tombol "Lihat Bukti Profesi" (blob download + `window.open()` di tab
  baru, sama pola dengan `ReportView.vue`'s unduh PDF), tombol
  Setujui/Tolak (Tolak punya input catatan opsional inline, bukan
  `window.prompt()` - tidak ada preseden `window.prompt()` di codebase
  ini).
- Test: `GrafologApplicationControllerTest` (publik, 7 test - submit,
  hash password bukan plaintext, tidak menerbitkan token/user, email
  sudah-jadi-user ditolak, pengajuan pending duplikat ditolak, pengajuan
  setelah `rejected` boleh daftar ulang, tipe file salah ditolak, dokumen
  wajib) dan `Admin\GrafologApplicationControllerTest` (8 test - guest/non-
  admin ditolak, list+filter, download dokumen, approve sukses (termasuk
  verifikasi password baru bisa dipakai lewat `Hash::check()` - **BUKAN**
  request `/auth/login` sungguhan di test yang sama, karena
  `actingAs(..., 'sanctum')` mengganti guard default request itu dan bikin
  `Auth::attempt()` di `AuthController::login` gagal dengan
  `BadMethodCallException`, bukan gagal karena password salah - gotcha
  yang ditemukan saat menjalankan test ini), approve gagal kalau email
  sudah dipakai / pengajuan sudah diproses, reject dengan catatan.
  `AuthControllerTest`'s `test_register_can_create_grafolog_role` **DIHAPUS**
  (jalur itu tidak ada lagi); `test_register_rejects_administrator_and_
  supervisor_roles` diganti jadi `..._and_grafolog_roles`, sekarang
  menguji ketiganya ditolak `/auth/register`.
- **Verifikasi**: 473 test backend total (1 kegagalan pre-existing tidak
  terkait, `ExampleTest`), `pint --test` lolos, `npm run lint`/`build`
  lolos. Browser-verified end-to-end (Playwright): `/register` tidak lagi
  punya dropdown role, submit pengajuan lewat `/daftar-grafolog` dengan
  file sungguhan (PNG 1x1) tidak membuat token/akun, email pending
  duplikat ditolak, admin login → lihat pengajuan di
  `/admin/grafolog-applications` → buka dokumen (blob tab baru berhasil)
  → Setujui → grafolog baru berhasil login pakai email+password yang
  sama persis diajukan.

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

## Narasi terpadu (laporan klien) — added 2026-08-22

User mengklarifikasi: breakdown Sindrom/Aspek/Indikator (`data` JSON,
`ScoringEngineService::generate()`) itu sebenarnya bahan kerja/verifikasi
pengukuran, BUKAN laporan yang seharusnya dikirim ke klien. Tugas grafolog
adalah mendinamikakan data itu jadi laporan deskriptif yang komunikatif dan
mengalir (Bahasa Indonesia atau Inggris). Dikonfirmasi lewat AskUserQuestion
sebelum eksekusi (3 keputusan produk sekaligus membalik prinsip root
CLAUDE.md "LLM tidak live per-user" yang sebelumnya dikunci):

1. Narasi dibuat AI (draft, 1 call live per-laporan) lalu **wajib**
   direview/diedit grafolog sebelum final — bukan grafolog menulis manual
   dari nol, dan bukan juga langsung dikirim ke klien tanpa review.
2. Breakdown Sindrom/Aspek/Indikator (`data`) jadi **internal-only**
   (grafolog/admin/hr) — klien TIDAK PERNAH menerimanya lagi sama sekali,
   bukan cuma disembunyikan di UI.
3. Satu laporan = satu bahasa, dipilih grafolog per-laporan (bukan
   dua-duanya sekaligus).

**Kenapa ini beda dari `NarasiCacheService`/`LlmProviderInterface` yang
sudah ada**: kontrak itu sengaja dibatasi per-aspek-per-level (40 aspek x 4
level = 160 kombinasi tetap, bisa di-cache permanen selamanya — lihat
docblock `LlmProviderInterface`). Narasi terpadu genuinely per-laporan
(kombinasi 40 skor tiap klien unik, tidak bisa di-cache), jadi sengaja
**tidak** lewat abstraksi itu — `NarasiTerpaduService` berdiri sendiri,
manggil Anthropic langsung pakai `config('services.llm.*')` yang sama.
Kalau `LLM_PROVIDER` masih `none` (default `.env.example`), generate akan
gagal dengan 503 yang bersih (pola sama dengan `DokuService::
ensureConfigured()`), bukan silent passthrough — beda dari
`NullLlmProvider` karena di sini tidak ada "teks asli" yang masuk akal
untuk di-passthrough (ini sintesis, bukan terjemahan).

- **Migrasi**: `personality_reports` dapat `narasi_terpadu` (longText),
  `narasi_bahasa` (enum id/en), `narasi_status` (enum belum_dibuat/draft/
  final, default belum_dibuat), `pdf_path_klien` (cache PDF klien terpisah
  dari `pdf_path` internal karena kontennya beda total). `report_revisions.
  jenis` enum dapat nilai ke-3 `edit_narasi_terpadu` (migrasi driver-aware,
  pola sama dengan `expand_users_role_enum.php` — raw ALTER untuk MySQL
  asli, `Schema::table()->enum()->change()` untuk sqlite test).
- **`App\Services\Reporting\NarasiTerpaduService::generateDraft()`** —
  merangkai `data.sindrom[].aspek[].narasi` jadi 1 prompt, system prompt
  eksplisit melarang menambah klaim baru di luar data yang diberikan
  (aturan sama seperti `ApiLlmProvider`) dan mewajibkan framing insight
  reflektif bukan diagnosis klinis.
- **`ReportController::generateNarasiTerpadu()`** (`POST /reports/{id}/
  narasi-terpadu/generate`, grafolog pemilik sample) — selalu jadi
  `narasi_status: draft`, TIDAK PERNAH langsung `final`. **
  `updateNarasiTerpadu()`** (`PATCH /reports/{id}/narasi-terpadu`) — grafolog
  edit manual + set bahasa + tandai status (draft/final); snapshot versi
  sebelumnya ke `report_revisions` lewat `ReportRevisionService::
  snapshotNarasiTerpaduBeforeChange()` (method terpisah dari
  `snapshotBeforeChange()` karena bentuk `data` beda — `{narasi_terpadu,
  narasi_bahasa, narasi_status}`, bukan breakdown sindrom). Keduanya
  me-null-kan `pdf_path_klien` supaya PDF klien tidak pernah basi dari
  sebelum draft direvisi.
- **Gating akses klien** — `ReportController::isClientViewer()`
  (`$user->role === 'user'`, selalu berarti subjek tes baik self-service
  maupun kandidat HR, lihat "Candidate is intentionally not a new
  table/model" di atas) dipakai di `show()` dan `pdf()`:
  - Klien: `abort_unless(narasi_status === 'final', 403, ...)`, respons
    HANYA `{id, tier, status, generated_at, narasi_terpadu, narasi_bahasa}`
    — TIDAK menyertakan `data`/`aspek_scores` sama sekali (bukan cuma
    disembunyikan di frontend). PDF klien pakai `ReportPdfService::
    generateKlien()` + `resources/views/reports/pdf-klien.blade.php`
    (narasi_terpadu saja).
  - Grafolog/admin/hr: tetap dapat semuanya (breakdown + narasi_terpadu draft
    ataupun final) via `pdf.blade.php` yang sudah ada, sekarang berfungsi
    sebagai PDF internal/verifikasi.
  - **`index()` juga dibatasi eksplisit** (`->select([...])`, tidak
    menyertakan `data`/`narasi_terpadu`) untuk SIAPA PUN, bukan cuma klien —
    frontend `RiwayatView.vue` cuma pernah pakai id/tier/status, jadi ini
    penyempitan aman yang sekaligus menutup celah endpoint list yang
    tadinya mengirim breakdown penuh ke klien juga (ditemukan & diperbaiki
    sesi yang sama, sebelum sempat jadi masalah nyata).
- **Frontend**: `NarasiTerpaduPanel.vue` (baru) — bahasa dropdown, tombol
  "Generate Draft AI", textarea, "Simpan sebagai Draft"/"Tandai Final".
  `ReportView.vue` sekarang bercabang total by role: `auth.isClient` render
  narasi polos saja (`report.narasi_terpadu`, tanpa breakdown karena
  memang tidak pernah dikirim backend); staf render `NarasiTerpaduPanel` +
  breakdown yang sudah ada di bawah heading "Data Pengukuran (Internal)".
  `ReportRevisionHistory.vue` render snapshot `edit_narasi_terpadu` sebagai
  teks polos (bukan lewat `ReportDocument`, bentuk datanya beda).
- 12 test baru (`NarasiTerpaduControllerTest`) + fix `ReportControllerTest`
  index-nya masih hijau tanpa perubahan (frontend tidak butuh kolom yang
  dihapus). 373 backend tests total (up from 361).
- **Belum diverifikasi lewat browser sungguhan / call Anthropic asli** —
  sesi ini cuma testing lewat `Http::fake()` (kontrak request/response
  API-nya diverifikasi cocok dengan `ApiLlmProvider` yang sudah pernah
  dipakai produksi, bukan ditebak) dan `php artisan test`/`npm run build`/
  `npm run lint`. `.env` masih `LLM_PROVIDER=none` di semua environment
  dev yang diketahui — generate draft akan gagal sampai kredensial
  Anthropic asli diisi (sekarang gagalnya tercatat di `narasi_generation_
  error`, bukan 503 langsung — lihat optimalisasi di bawah). Perlu 1 sesi
  verifikasi manual lewat browser + kredensial asli sebelum dianggap
  production-ready.

### Narasi terpadu — optimalisasi 2026-08-22

User bertanya: laporan asli bisa 20-40 halaman (40 aspek + bukti Indikator),
apakah `max_tokens` cukup dan apakah generate-nya bisa "dijadikan database
interpretasi semua kemungkinan skor" biar tidak selalu panggil AI? Dihitung
dari data KB sungguhan proyek ini: ruang kombinasi narasi_level per laporan
adalah **4^40 ≈ 1,2 septiliun** (40 aspek × 4 level, independen) — jauh di
luar apa pun yang bisa di-pregenerate/cache penuh (beda kelas masalah dari
`NarasiCacheService` yang cuma 160 kombinasi tetap). Keputusan: generate
tetap 1 call live per-laporan (tidak berubah), tapi 3 optimisasi nyata yang
memang bisa dikerjakan:

1. **`NarasiTerpaduService::MAX_TOKENS` dinaikkan 4000 → 16000** — 4000
   token (~6-8 halaman) memotong draft panjang di tengah kalimat tanpa
   error; 16000 (~24 halaman) sesuai rekomendasi default non-streaming
   Anthropic. `Http` client diberi `->timeout(300)` (dari default 30 detik)
   karena generate segitu banyak token bisa makan 1-3 menit.
2. **Generate dipindah ke queue job asinkron** (`App\Jobs\
   GenerateNarasiTerpaduJob`, `QUEUE_CONNECTION=database` sama seperti
   `SendReportCompletedNotification`) — dengan `max_tokens` yang lebih
   besar, request sinkron 1-3 menit berisiko timeout PHP/web server.
   `ReportController::generateNarasiTerpadu()` sekarang cuma set
   `narasi_status = 'generating'` lalu `dispatch()`, return langsung (tidak
   nunggu). Job yang benar-benar panggil `NarasiTerpaduService::
   generateDraft()`; kalau gagal (LLM belum dikonfigurasi, dst), status
   dikembalikan ke `draft` (kalau sebelumnya sudah ada draft — tidak
   dihapus) atau `belum_dibuat` (kalau belum pernah berhasil sama sekali),
   dan pesannya disimpan ke kolom baru `narasi_generation_error` supaya
   grafolog tahu kenapa, bukan diam-diam macet di `generating` selamanya.
   Frontend (`NarasiTerpaduPanel.vue`) polling `GET /reports/{id}` tiap 4
   detik selama status `generating`, auto-update begitu selesai — tidak
   perlu WebSocket/broadcasting, cukup polling sederhana karena frekuensi
   generate sangat rendah (1 klik grafolog, bukan traffic tinggi).
3. **Dedup-guard + Anthropic prompt caching** — kolom baru
   `narasi_input_hash` (sha256 dari `data.sindrom` + bahasa) disimpan tiap
   generate berhasil. Klik "Generate" lagi tanpa skor berubah ditolak 409
   kecuali `force: true` (frontend menampilkan `confirm()`) — mencegah LLM
   call percuma dari klik ganda/regenerate tanpa alasan. **Ini BUKAN cache
   lintas-laporan** (mustahil, lihat perhitungan 4^40 di atas) — cuma
   mencegah generate ulang PADA LAPORAN YANG SAMA kalau datanya belum
   berubah. System prompt (instruksi tetap, tidak pernah menyertakan data
   laporan) ditandai `cache_control: ephemeral` di request Anthropic —
   Anthropic tidak proses ulang dari nol tiap call, sedikit lebih cepat/
   murah; data laporan sendiri (di `messages`, genuinely unik tiap laporan)
   sengaja TIDAK di-cache karena memang tidak akan pernah cache-hit.
- Migrasi driver-aware baru: `narasi_status` enum dapat nilai ke-4
  `generating`, kolom baru `narasi_input_hash`/`narasi_generation_error`.
- 6 test baru (dispatch job via `Queue::fake()`, dedup-guard
  ditolak/dilewati/reset-setelah-koreksi-skor, revert status + error
  message saat job gagal) - 379 backend tests total (up from 373).
  **Catatan testing**: `Http::fake()` MERGE stub baru DI BELAKANG yang
  lama untuk pola URL sama dan ambil kecocokan pertama - test yang perlu
  >1 respons berbeda dalam 1 test (regenerate) harus pakai
  `Http::sequence()`, bukan panggil `Http::fake()` berulang (baru ketahuan
  lewat 2 test gagal saat pertama ditulis pakai pola lama).

### Narasi terpadu — 3 celah keselarasan/race-condition, ditutup 2026-08-22

User bertanya lebih spesifik soal "sistem antriannya seperti apa, biar
tidak terpotong/beradu" dan "saat perubahan nilai jangan sampai generate
ulang otomatis". Investigasi ketemu 3 celah nyata di implementasi
asinkron sebelumnya (bukan cuma pertanyaan teoretis):

1. **Worker timeout 60 detik (default `queue:work`) lebih pendek dari
   waktu generate (bisa 1-3 menit)** — job bisa dipaksa mati SIGALRM di
   tengah panggilan Anthropic (biaya API tetap kepotong, hasil hilang,
   laporan macet permanen di status `generating`).
   `GenerateNarasiTerpaduJob::$timeout = 360` (margin di atas timeout HTTP
   client 300s di `NarasiTerpaduService`) menimpa default itu KHUSUS untuk
   job ini (Laravel baca properti publik ini lewat job payload -
   dikonfirmasi lewat baca langsung source `Illuminate\Queue\Jobs\Job::
   timeout()`, bukan ditebak).
2. **`retry_after` default Laravel (90 detik) lebih pendek dari waktu
   generate** — kalau 1 job belum selesai lebih dari 90 detik, worker LAIN
   bisa menganggapnya "hilang" dan menjalankan ULANG job yang sama secara
   BERSAMAAN (2 panggilan Anthropic untuk laporan yang sama, biaya dobel,
   hasil menang acak siapa selesai duluan - "beradu" yang ditanyakan user).
   `config/queue.php`'s koneksi `database` dinaikkan ke 400 detik
   (`DB_QUEUE_RETRY_AFTER` env, fallback baru). **Ini connection-level**
   (Laravel versi ini tidak punya per-job `retryAfter` override seperti
   `$timeout` - dikonfirmasi lewat grep source, tidak ada) - aman untuk job
   lain di koneksi yang sama (`SendReportCompletedNotification`) karena
   cuma memperlambat deteksi "hilang", tidak memengaruhi job yang memang
   cepat selesai.
3. **Race condition klik-ganda** — `ReportController::generateNarasiTerpadu()`
   sebelumnya baca status lalu tulis 'generating' sebagai 2 langkah
   terpisah tanpa lock; 2 request nyaris bersamaan (klik ganda sebelum
   tombol ke-disable di frontend) bisa dua-duanya lolos pengecekan SEBELUM
   salah satu sempat commit. Diperbaiki dengan `DB::transaction()` +
   `PersonalityReport::whereKey(...)->lockForUpdate()` - request kedua
   menunggu (blocking row lock) sampai request pertama commit, baru baca
   status TERBARU ('generating') dan ditolak dengan benar. Bekerja di
   MySQL asli (row lock sungguhan); di sqlite test `lockForUpdate()` no-op
   aman (grammar sqlite Laravel mengembalikan string kosong untuk lock
   hint - tidak pernah error, cuma tidak benar-benar mengunci, tidak
   masalah karena test PHPUnit tidak genuinely paralel).
4. **Koreksi skor setelah narasi_terpadu sudah `final`** —
   `ScoringController::correct()` menulis ulang `data` (breakdown) tapi
   TIDAK pernah menyentuh `narasi_terpadu`, jadi kalau grafolog koreksi
   skor SETELAH laporan narasi sudah ditandai final (sudah terlihat
   klien), klien terus melihat narasi lama yang sudah tidak merefleksikan
   skor terbaru, ditandai final seolah-olah masih valid. Diperbaiki: kalau
   `narasi_status === 'final'` saat `correct()` dipanggil, diturunkan balik
   ke `draft` (klien otomatis kehilangan akses ke versi basi lewat gating
   `show()`/`pdf()` yang sudah ada) - teks `narasi_terpadu` LAMA
   dipertahankan apa adanya, TIDAK ada panggilan AI otomatis (prinsip "LLM
   cuma dipanggil lewat aksi eksplisit grafolog" tetap dijaga, sesuai
   permintaan user eksplisit "jangan sampai generate ulang otomatis").
   `narasi_input_hash` sengaja tidak disentuh - otomatis tidak cocok lagi
   dengan hash data baru, jadi generate berikutnya (kapan pun grafolog
   klik) tidak akan ketahan dedup-guard walau tanpa `force`.
- 2 test baru di `ScoringCorrectionTest` (final→draft downgrade tanpa
  panggilan AI - dijamin lewat TIDAK memasang `Http::fake()` sama sekali
  di test itu, jadi kalau kode diam-diam memanggil LLM beneran, testnya
  akan gagal karena percobaan koneksi keluar; draft tetap draft, tidak
  berubah). 381 backend tests total (up from 379).

## Kombinasi Temuan — manajemen dibangun 2026-08-22, data Excel TERTUNDA

User bertanya: selain cascade "A tercentang → B ikut tercentang"
(`indikator_rules` rule_type `indikator_checked`, sudah lama ada), apakah
bisa kombinasi BEBERAPA Indikator/Aspek/Sindrom sekaligus menghasilkan 1
sifat/interpretasi BARU (mis. "Indikator 3 tinggi + Indikator 6 rendah →
sifat X", lintas Indikator/Aspek/Sindrom)? Dicek ke KB JSON sumber
(`grafologi_knowledge_base.json`) — **struktur data ini TIDAK ADA di KB
sekarang** (`indikator_cross_reference` cuma 1-ke-banyak pointer "bukti
yang sama diperluas", bukan "kombinasi N kondisi → makna baru"; Sindrom
cuma punya catatan polaritas, tidak ada narasi berjenjang). Dikonfirmasi
user: kontennya ADA di referensi Excel asli grafolog (sama seperti 704
Indikator/40 Aspek lain), **belum didigitalisasi** — user minta
manajemennya (skema + admin UI + mesin evaluasi) dibangun DULUAN supaya
begitu Excel-nya siap tinggal diinput, bukan nunggu Excel dulu baru mulai
membangun.

**Koreksi penting dari diskusi**: Indikator itu murni boolean (tercentang/
tidak), TIDAK punya "level tinggi/rendah" sendiri — itu konsep Aspek
(`narasi_level`, dari skor) dan Sindrom (dihitung sama dari rata-rata).
Jadi syarat level Indikator cuma `tercentang`/`tidak_tercentang`; syarat
level Aspek/Sindrom pakai 4 bucket `narasi_level` yang SUDAH ADA
(low/medium/high/very_high, `ScoringEngineService::narasiLevelUntukSkor()`)
— bukan skema baru, biar konsisten dengan bucket yang sudah dipakai narasi
per-Aspek.

- **`kombinasi_temuan`** (`nama`, `teks_interpretasi`, `logika_gabung`
  AND/OR) + **`kombinasi_syarat`** (`level` indikator/aspek/sindrom, FK
  eksplisit per-level — `indikator_id`/`aspek_id`/`sindrom_id`, cuma 1
  terisi sesuai `level` — pola sama dengan `indikator_rules`'
  `variable_a_id`/`variable_b_id`/`depends_on_indikator_id` eksplisit,
  bukan polymorphic generic, `kondisi` string). Validasi lintas-field di
  `StoreKombinasiSyaratRequest` (target wajib sesuai level, field level
  lain terlarang, `kondisi` harus salah satu nilai valid untuk level itu).
- **`App\Services\Scoring\KombinasiTemuanService::evaluate(array
  $skorPerAspek, HandwritingSample $sample): array`** — dipanggil dari
  `ScoringController::submit()` DAN `correct()` (post-processing, seperti
  `attachIndikatorNarasi()`, `ScoringEngineService::generate()` sengaja
  tidak disentuh), hasil ditulis ke `data.kombinasi_ditemukan` (top-level,
  BUKAN nested per-Aspek — 1 temuan bisa merentang beberapa Aspek/Sindrom
  sekaligus). Menghitung level Aspek dari skor input langsung (bukan baca
  ulang `data` yang sudah jadi), level Sindrom dari rata-rata skor
  Aspek-Aspek yang ADA di input ini (tolerate partial input, sama filosofi
  `ScoringEngineService::generate()`), status Indikator dari
  `sample->indikatorChecks()`. AND butuh SEMUA syarat true, OR cukup 1.
  Temuan tanpa syarat sama sekali TIDAK PERNAH match (guard eksplisit,
  bukan div-by-zero/vacuous-true accident).
- **Ikut masuk ke narasi terpadu** — `NarasiTerpaduService` menambahkan
  seksi "Pola Kombinasi" ke ringkasan yang dikirim ke LLM (grounding
  tambahan, aturan "jangan tambah klaim baru" tetap berlaku sama seperti
  bukti Indikator). Karena `kombinasi_ditemukan` murni fungsi dari skor +
  status Indikator (keduanya sudah tercermin di `data.sindrom`),
  `NarasiTerpaduService::inputHashFor()` (dedup-guard) TIDAK perlu diubah —
  otomatis ikut berubah kalau kombinasi yang match berubah. **Batasan yang
  disengaja**: kalau ADMIN mengubah/menambah rule Kombinasi TANPA skor
  berubah, `data.kombinasi_ditemukan` pada laporan yang sudah ada tetap
  versi lama sampai grafolog memanggil `correct()` lagi - sama persis
  seperti narasi per-Aspek tidak retroactive kalau teks KB-nya diedit,
  bukan inkonsistensi baru.
- **Admin UI**: tab ke-7 "Kombinasi Temuan" di `AdminKnowledgeView.vue`,
  komponen baru `KombinasiTemuanManager.vue` (self-contained, pola sama
  dengan `ConceptMapExplorer.vue` — bukan ditambahkan langsung ke file
  `AdminKnowledgeView.vue` yang sudah >1300 baris). Create/edit/delete
  temuan + nested syarat builder (dropdown level → target sesuai level →
  kondisi sesuai level).
- Muncul di breakdown internal (`ReportDocument.vue` seksi baru "Pola
  Kombinasi Ditemukan", `pdf.blade.php` internal PDF) - TIDAK pernah
  dikirim ke klien (sama seperti breakdown lain, cuma lewat narasi terpadu
  kalau grafolog generate ulang).
- 18 test baru (`KombinasiTemuanServiceTest` unit - AND/OR, 3 level,
  partial input, temuan-tanpa-syarat; `KombinasiTemuanControllerTest` -
  auth, validasi, CRUD, cascade delete; 1 test baru di
  `ScoringControllerTest` - `data.kombinasi_ditemukan` muncul di respons
  submit()). 399 backend tests total (up from 381).
- **BELUM ADA DATA** — tabel `kombinasi_temuan`/`kombinasi_syarat` kosong
  di semua environment. Konten Excel asli belum diserahkan user - lihat
  root `ROADMAP.md` untuk item tertunda. Jangan isi data lewat tebakan/AI;
  tunggu file Excel atau contoh baris nyata dari user.

## Narasi terpadu — alur tematik tetap, 2026-08-22

User bertanya: laporan sebaiknya punya struktur yang sama tiap kali, atau
boleh beda-beda? Dibahas 3 opsi (bebas total / template heading ketat /
hybrid alur tematik tanpa heading), user pilih **hybrid**.
`NarasiTerpaduService`'s system prompt sekarang menyertakan 4 urutan
tematik tetap (gambaran umum → kekuatan utama → area perlu diperhatikan →
catatan penutup reflektif) sebagai instruksi eksplisit, TANPA memaksa
heading terpisah - tetap 1 dokumen mengalir, tapi alurnya predictable
antar laporan (gampang di-QA grafolog, berguna kalau nanti dibandingkan
antar kandidat B2B) tanpa terasa template-kotak seperti breakdown yang
sengaja dihindari.

## Topik (kategorisasi) — dibangun 2026-08-22

User mengusulkan kategorisasi yang mengelompokkan interpretasi jadi 1
kelompok (mis. "Karier", "Percintaan") - disebutkan bisa dipakai untuk
tampilan report, chat interaktif, ATAU segmen khusus B2B (mis. HR minta
laporan segmen Karier saja) - **tapi TIDAK memutuskan yang mana sekarang**.
Instruksi eksplisit: "buat ini menjadi fungsi yang bisa di management...
tapi mekanisme yang ada jangan dirubah, itu menjadi main product-nya, yang
saya ceritakan ini hanya produk turunan". Jadi yang dibangun MURNI
infrastruktur tagging + 1 contoh baca-saja (endpoint segmen) yang
membuktikan datanya nyambung - BUKAN fitur chat, BUKAN endpoint/UI B2B
lengkap, BUKAN perubahan apa pun ke `ScoringEngineService`/
`NarasiCacheService`/`ChecklistEngineService`/`KombinasiTemuanService::
evaluate()`/`NarasiTerpaduService`'s core generation - semua itu sengaja
tidak disentuh.

- **`topik`** (nama unik, deskripsi) + 2 pivot many-to-many:
  **`aspek_topik`** dan **`kombinasi_temuan_topik`** - Aspek dipilih
  sebagai unit tagging utama (bukan Sindrom yang terlalu luas, bukan
  Indikator yang terlalu granular/704 baris - narasi per-level yang sudah
  dicache ada di level Aspek), Kombinasi Temuan ikut ditag juga karena dia
  juga menghasilkan teks interpretasi berdiri sendiri. **Pivot pertama di
  codebase ini** yang pakai `belongsToMany` - sebelumnya semua relasi
  eksplisit FK per-baris (pola `indikator_rules`), tapi tagging many-to-many
  genuinely butuh bentuk ini.
  **Gotcha yang kejadian & sudah diperbaiki**: model `Topik` awalnya lupa
  `protected $table = 'topik'` eksplisit - Eloquent nebak `topiks` (plural
  otomatis), langsung 4 test gagal `no such table: topiks` begitu
  `AspekController`/`KombinasiTemuanController` eager-load relasi `topik`.
  Semua model KB lain di proyek ini ('aspek', 'sindrom', 'indikator', dst)
  memang butuh `$table` eksplisit karena tidak ikut konvensi pluralisasi
  Inggris Eloquent - jangan lupa lagi kalau nambah model baru bertabel
  Indonesia tanpa 's'.
- **Admin CRUD `TopikController`** (`GET/POST/PUT/DELETE
  /admin/knowledge/topik`) - tab ke-8 "Topik" di `AdminKnowledgeView.vue`,
  inline (bukan komponen terpisah, CRUD-nya sesederhana Sindrom). **Sync
  tagging** lewat 2 endpoint baru: `PUT /admin/knowledge/aspek/{aspek}/topik`
  (`AspekController::syncTopik()`, checkbox multi-select baru di panel edit
  Aspek yang sudah ada) dan `PUT /admin/knowledge/kombinasi/{kombinasiTemuan}/topik`
  (`KombinasiTemuanController::syncTopik()`, checkbox serupa di
  `KombinasiTemuanManager.vue`) - keduanya pakai `->sync()` (ganti seluruh
  set tag sekaligus dari multi-select UI, bukan attach/detach 1-1).
  `SyncTopikRequest` divalidasi `exists:topik,id` per elemen array.
- **`App\Services\Reporting\TopikFilterService::filter(array $data, array
  $topikIds): array`** - contoh konkret baca-saja: ambil `data.sindrom`/
  `data.kombinasi_ditemukan` yang SUDAH TERSIMPAN di 1 laporan, saring ke
  Aspek/Kombinasi Temuan yang ditag salah satu `$topikIds` (OR logic
  antar-topik), Sindrom yang aspek-nya jadi kosong ikut dibuang. **Tidak
  pernah memanggil ulang mesin skoring/AI apa pun** - murni transformasi
  data yang sudah ada. Endpoint baru `GET /reports/{report}/segmen?topik_ids[]=N`
  (`ReportController::segmen()`, staff-only - gate `isClientViewer()` yang
  sama dengan breakdown internal biasa, klien tetap tidak pernah bisa
  akses ini). `topik_ids` kosong = kembalikan semua tanpa filter (fallback
  aman, bukan 0 hasil).
- 22 test baru (`TopikControllerTest` - CRUD, unique-nama, sync ganti-set,
  reject id tidak valid, cascade delete cuma lepas tag bukan hapus Aspek;
  `ReportSegmenTest` - filter benar, kosong = unfiltered, sindrom-kosong
  dibuang, klien & orang lain ditolak). 413 backend tests total (up from
  399).
- **Belum dipakai fitur konsumen manapun** (sengaja, sesuai instruksi
  user) - tagging + endpoint segmen ini infrastruktur murni, menunggu
  keputusan produk berikutnya soal yang mana dulu mau dibangun (tampilan
  report bersegmen, chat, atau endpoint B2B formal). `narasi_terpadu`
  (laporan klien) TIDAK menerima parameter topik apa pun - tetap selalu
  laporan lengkap seperti sebelumnya.

## Guard biaya AI — added 2026-08-23

`POST /reports/{report}/narasi-terpadu/generate` sekarang punya
`throttle:20,60` sendiri, DI ATAS `throttle:60,1` grup umum yang sudah ada
(dua middleware throttle independen bertumpuk pada 1 route — pola yang
sudah ada di route lain seperti `/auth/register`, bukan pola baru). Ini
satu-satunya endpoint yang memanggil Anthropic (biaya nyata per klik) —
`throttle:60,1` terlalu longgar untuk itu. 20/jam per grafolog cukup untuk
pemakaian wajar (generate awal + beberapa kali regenerate per laporan)
tapi menutup risiko klik berulang/`force: true` berulang membakar biaya
tanpa sengaja. Dedup-guard (`narasi_input_hash`, lihat "Narasi terpadu —
optimalisasi" di atas) sudah menutup regenerate-tanpa-perubahan-data;
throttle ini menutup celah yang tersisa — `force: true` sengaja
melewati dedup-guard, jadi tanpa batas terpisah, klik berulang dengan
`force: true` bisa memanggil Anthropic tanpa batas.
`NarasiTerpaduControllerTest::test_generate_endpoint_is_rate_limited_
separately_from_general_throttle` mengunci ini (20 request lolos, request
ke-21 dalam jam yang sama dapat 429) — 420 backend tests total (up from
419).

## UX gaps per persona (end user, grafolog, B2B) — fixed 2026-08-23

User meminta audit gap UX untuk 3 persona (end user/klien, grafolog, B2B/HR).
Ditemukan 3 gap konkret lewat pembacaan kode langsung (bukan spekulasi),
semuanya dampak nyata, semuanya bisa diperbaiki tanpa kredensial/keputusan
bisnis baru:

1. **Dashboard HR benar-benar rusak (B2B).** `DashboardController::index()`
   sebelumnya `$user->isGrafolog() ? grafologDashboard() : clientDashboard()`
   — HR bukan grafolog, jatuh ke `clientDashboard()`, yang scope sample-nya
   lewat `user_id = $user->id` (HR bukan pernah jadi SUBJEK tes). Hasilnya:
   KPI HR SELALU 0 dan activity SELALU kosong, walau HR sudah impor puluhan
   kandidat sungguhan (`HrCandidatesView` sendiri, yang scope lewat
   `created_by`, menunjukkan datanya ada). Karena router mengarahkan semua
   role ke `/dashboard` setelah login, ini adalah halaman PERTAMA yang
   dilihat HR — rusak sejak awal. Fix: `hrDashboard()` baru, scope
   `created_by` (sama seperti `HrCandidatesView`/`SampleController::index`),
   KPI `total_candidates`/`unassigned` (belum ditugaskan grafolog & belum
   selesai)/`completed`/`avg_turnaround_days`.
2. **KPI + activity "Selesai" klien salah sinyal (end user).** `clientDashboard()`
   dan `RiwayatView.vue` sebelumnya memakai `PersonalityReport.status`/
   `HandwritingSample.status` (breakdown internal sudah dihitung) untuk
   menentukan "Selesai" — tapi klien cuma BENAR-BENAR bisa buka laporan
   kalau `narasi_status === 'final'` (lihat `ReportController::show`,
   "Narasi terpadu" di atas). Sebelum fix ini klien bisa lihat badge
   "Selesai" di Riwayat/Dashboard lalu begitu diklik dapat 403 "laporan
   belum final" — status yang ditampilkan tidak mencerminkan apa yang
   sungguh bisa mereka akses. Fix: `clientDashboard()`'s KPI `completed`
   sekarang hitung `narasi_status='final'` (bukan `sample.status`);
   `recentActivity()` dapat parameter `bool $clientView` — kalau true,
   label/status per-item dihitung dari `narasi_status` lewat
   `clientFacingStatus()` (final→'completed', selain itu→'generating'),
   bukan `report.status` mentah. `RiwayatView.vue` (frontend) melakukan hal
   sama secara lokal via `displayStatus()`, memakai `report.narasi_status`
   yang sudah ada di respons `index()` (sudah di-select sejak fitur narasi
   terpadu, cuma belum pernah dipakai frontend). **Grafolog/admin/hr TIDAK
   terkena perubahan ini** — mereka boleh lihat status breakdown internal
   apa adanya, cuma klien yang gatingnya beda.
3. **Grafolog bisa habiskan waktu isi 40 aspek lalu baru ketahuan token
   tidak cukup (grafolog).** Gate token (`TokenCost`/`TokenWalletService`,
   lihat "Token system" di atas) baru dicek `ScoringController::submit()`
   di akhir — tidak ada sinyal apa pun di `PortalGrafologView` sebelum itu.
   Fitur ini sengaja off-by-default (`TokenCost::activeTokensFor()` null =
   tidak ada gate) jadi dampaknya baru terasa begitu admin mengaktifkan
   biaya token untuk suatu tier. Fix: `TokenController::wallet()` dapat
   field baru `costs: {comprehensive, master}` (dari
   `TokenCost::activeTokensFor()` per tier, null kalau belum dikonfigurasi
   admin — bukan endpoint baru, extend yang sudah ada). `PortalGrafologView`
   memuat wallet sekali di `onMounted` (gagal senyap, non-fatal — bukan
   endpoint kritikal), tampilkan peringatan ⚠ + link "Beli token" di step 1
   (sebelum sample dibuat) DAN step 2 (pengingat selama isi form) kalau
   `wallet.balance < costs[tier]`.
- 5 test baru (`TokenControllerTest` — field `costs` muncul di wallet;
  `DashboardControllerTest` — HR dashboard scope benar, klien
  completed/sample-completed-tapi-narasi-belum-final dihitung sebagai
  in_progress, activity klien pakai narasi_status). 424 backend tests total
  (up from 420).
- **Belum browser-verified** — sesi ini cuma lewat `php artisan test`/
  `vendor/bin/pint --test`/`npm run lint`/`npm run build`, belum click-through
  Playwright. Kalau ada waktu sesi berikutnya, verifikasi dashboard HR
  dengan data kandidat sungguhan dan peringatan token dengan
  `TokenCost` sungguhan diaktifkan.

## Monitoring & backup — added 2026-08-23

Item 14/15 dari daftar kesiapan publikasi ("apalagi yang perlu dikembangkan
supaya siap publikasi") — dua-duanya bisa dikerjakan tanpa keputusan bisnis
baru, aktivasi penuhnya menunggu server production nyata. Detail lengkap
config/langkah aktivasi ada di root `DEPLOYMENT.md`, ini ringkasannya:

- **`sentry/sentry-laravel`** (error monitoring) — terpasang & terhubung ke
  `bootstrap/app.php`'s exception handler lewat `Integration::handles()`,
  tapi mati total (tidak ada request keluar sama sekali) sampai
  `SENTRY_LARAVEL_DSN` diisi di `.env` — `config('sentry.dsn')` default
  `null`, SDK-nya sendiri no-op kalau DSN kosong. Aman didaftarkan
  unconditional di semua environment.
- **`spatie/laravel-backup`** (backup database + storage/app) —
  dikonfigurasi (`config/backup.php`) dan dijadwalkan
  (`routes/console.php`: `backup:clean` 01:00, `backup:run` 01:30,
  `backup:monitor` 10:00 setiap hari) tapi **jadwal ini tidak jalan sendiri**
  — butuh cron 1x/menit memanggil `schedule:run` di server production (lihat
  `DEPLOYMENT.md`). `source.files.include` SENGAJA dipersempit dari default
  paket (`base_path()`, seluruh direktori aplikasi) jadi cuma `storage/app`
  — kode sudah aman di git, dan `base_path()` ikut menyeret `.env`
  (kredensial DB/DOKU/SMTP/Anthropic) ke arsip backup, riskan kalau
  `BACKUP_DESTINATION_DISK` diarahkan ke cloud storage. Notifikasi cuma
  untuk kegagalan/tidak-sehat (bukan email "sukses" harian yang jadi
  noise), tujuan default `ADMIN_EMAIL` (dipakai ulang dari
  `config/admin.php`).
- **Verifikasi sesi ini**: jalur file backup (`backup:run --only-files`,
  `list`, `monitor`, `clean`) dijalankan sungguhan lawan DB sqlite di
  sandbox ini — sukses end-to-end, artefak uji coba sudah dibersihkan.
  Jalur database dump (`backup:run` penuh, butuh `mysqldump`) **belum bisa
  dites di sandbox ini** (tidak ada binary `mysqldump`/`sqlite3` terpasang)
  — perlu diverifikasi sekali begitu ada akses ke server dengan
  `mysqldump` di PATH-nya, sebelum dianggap production-ready. `php artisan
  schedule:list` dikonfirmasi mendaftarkan ketiga jadwal dengan benar.
- **Bonus temuan, sudah diperbaiki**: `composer audit` menemukan 8 advisory
  keamanan (beberapa `high`) di `guzzlehttp/guzzle`/`league/commonmark` —
  dependensi transitif `laravel/framework` sendiri, bukan dari kode
  proyek ini. Di-patch dengan `composer update guzzlehttp/guzzle
  league/commonmark --with-dependencies` (murni dalam batas constraint
  yang sudah ada di `composer.json`, tidak mengubah versi Laravel
  itu sendiri) — `composer audit` sekarang bersih, 424 test tetap lolos.
- 424 backend tests total (tidak berubah dari sebelumnya — perubahan ini
  murni infrastruktur/config, tidak ada logic aplikasi baru untuk diuji).

## Gap management ditutup — audit log viewer + lifecycle akun staf/company, 2026-08-23

User bertanya "apakah secara management sudah lengkap dan baik?" — dicek
langsung ke kode, 2 gap konkret ditemukan dan dikonfirmasi user untuk
dikerjakan (lihat ROADMAP.md "Kesiapan Publikasi" untuk daftar lengkap).
Investigasi kedua juga menemukan gap ketiga yang lebih parah dari dugaan
awal: **tidak ada UI perusahaan sama sekali** — `POST /api/admin/companies`
sudah ada sejak MGA Fase 06 tapi tidak pernah punya frontend caller, jadi
akun HR (butuh `company_id`) sebenarnya tidak bisa benar-benar dibuat lewat
aplikasi tanpa API call manual.

- **`users.is_active`/`companies.is_active`** (boolean, default `true`,
  migrasi driver-agnostic - cuma `Schema::table()->boolean()`, tidak butuh
  raw SQL/doctrine-dbal seperti migrasi enum sebelumnya). **Bukan
  hard-delete** - riwayat `created_by`/`AuditLog`/`Assignment` yang mengacu
  ke user/company itu tetap valid setelah dinonaktifkan. Gotcha DB-default-
  tidak-refetch yang sama seperti `DiscountCode`/`Announcement` dimitigasi
  dengan `protected $attributes = ['is_active' => true]` di kedua model.
- **`AdminUserController::update()`** (`PATCH /admin/users/{user}`, baru) -
  edit nama/email/role/company_id + toggle `is_active` + reset password
  opsional, satu endpoint (bukan dipecah seperti `DiscountCodeController`'s
  single-purpose toggle, karena kebutuhannya genuinely edit penuh + toggle
  sekaligus). **404 kalau target akun bukan staf** (`role === 'user'`) -
  endpoint ini tetap khusus akun staf, akun klien punya alurnya sendiri,
  sama seperti batasan `StoreStaffUserRequest` yang sudah ada.
  **Administrator tidak bisa menonaktifkan akun sendiri** (422) - guard
  eksplisit, mencegah admin terkunci dari sistemnya sendiri. Menonaktifkan
  (`is_active: false`) langsung `$user->tokens()->delete()` - bukan cuma
  mencegah login BERIKUTNYA, sesi Sanctum yang sedang berjalan pun putus di
  request berikutnya, bukan menunggu token itu sendiri kedaluwarsa (yang
  memang tidak pernah kedaluwarsa, lihat "Open security findings").
- **`AuthController::login()`** menolak akun `is_active=false` (403, pesan
  eksplisit "Akun Anda telah dinonaktifkan..." - beda dari pesan generik
  "email/password salah", sengaja karena akun staf dibuat administrator
  bukan pendaftaran publik jadi tidak ada risiko enumerasi berarti).
- **`CompanyController::update()`** (`PATCH /admin/companies/{company}`,
  baru) - edit nama + toggle `is_active`. **Menonaktifkan company TIDAK
  otomatis menonaktifkan akun hr yang sudah terikat ke sana** (keputusan
  desain eksplisit, didokumentasikan di docblock + dikunci test) - cuma
  mencegah company itu dipakai untuk akun hr BARU. Kalau perlu mencabut
  akses hr terkait, itu tindakan terpisah lewat `AdminUserController::
  update()` per akun - menghindari efek cascade implisit yang mengejutkan.
- **`Api\Admin\AuditLogController::index()`** (`GET /admin/audit-logs`,
  baru) - PERTAMA KALI 45 titik `AuditLog::record()` di seluruh aplikasi
  bisa dibaca kembali lewat aplikasi, bukan cuma lewat query DB manual.
  Paginated (25/halaman), filter `aksi` (partial match LIKE), `actor_user_id`
  (exact), `from`/`to` (rentang tanggal `created_at`). Murni baca - tidak
  ada `store`/`update`/`destroy`, log audit tidak boleh diedit/dihapus lewat
  aplikasi (integritasnya justru dari situ).
- Audit log: `buat_akun_staf`/`ubah_akun_staf`, `buat_perusahaan`/
  `ubah_perusahaan` (perubahan pada mekanisme audit log itu sendiri juga
  tercatat lewat mekanisme yang sama - konsisten, bukan pengecualian).
- **Frontend**: `AdminUsersView.vue` diperluas signifikan - tabel user
  dapat kolom Perusahaan/Status + tombol "Ubah" (khusus akun staf, akun
  klien tidak dapat tombol ini) yang membuka panel edit inline (pola sama
  dengan tab-tab KM: expand-row, bukan modal - tidak ada komponen modal di
  codebase ini). **Section "Perusahaan" baru** (form buat + tabel dengan
  toggle aktif/nonaktif) - MENUTUP gap company-tanpa-UI di atas, dan
  dropdown company di form buat/edit HR sekarang benar-benar terisi dari
  data nyata (sebelumnya field ini bahkan tidak ada di form create). Halaman
  baru `AdminAuditLogView.vue` (`/admin/audit-logs`, nav link "Log Audit" +
  entry `CommandPalette.vue`) - tabel read-only dengan filter aksi
  (debounced 400ms) + rentang tanggal + pagination prev/next, pola sama
  dengan tab Indikator di `AdminKnowledgeView.vue`.
- 14 test baru (`AdminUserControllerTest` - edit field, deaktivasi+cabut
  token, tolak nonaktifkan diri sendiri, reset password, tolak akun klien,
  tolak non-admin; `CompanyControllerTest` - update nama+status, deaktivasi
  company tidak mencabut akses hr; `AuditLogControllerTest` baru - list,
  filter aksi, filter actor, forbidden untuk non-admin; `AuthControllerTest`
  - akun nonaktif ditolak login). 438 backend tests total (up from 424).
- **Browser-verified 2026-08-23** lewat Playwright headless (server sqlite
  throwaway + AdministratorSeeder, dibersihkan setelah selesai) - alur
  penuh: buat Company baru → dropdown Perusahaan di form buat-HR
  terkonfirmasi TERISI data nyata (sebelumnya mustahil dites karena
  memang tidak ada UI-nya) → buat akun HR dengan company itu → edit nama +
  nonaktifkan akun itu lewat panel inline → coba login dengan akun itu di
  sesi baru → ditolak 403 dengan pesan yang benar → login balik sebagai
  admin → buka Log Audit → konfirmasi `buat_perusahaan`/`buat_akun_staf`/
  `ubah_akun_staf` semua tercatat dengan aktor & waktu benar → filter
  pencarian aksi "perusahaan" menyaring ke 1 entri yang benar. 9/9
  pemeriksaan lolos, 0 error konsol nyata (beberapa `ERR_CONNECTION_RESET`
  di konsol adalah artefak `php artisan serve` PHP built-in server,
  dikonfirmasi bukan request gagal sungguhan - setiap endpoint yang
  dipanggil tercatat sukses di log server, termasuk 403 yang MEMANG
  diharapkan untuk percobaan login akun nonaktif).
- **Bug produksi nyata ditemukan & diperbaiki saat verifikasi ini**:
  `config/backup.php`'s notification `'to'` sebelumnya
  `env('BACKUP_NOTIFICATION_EMAIL', env('ADMIN_EMAIL', 'your@example.com'))`
  - kelihatan aman tapi SALAH, karena `env()`'s argumen default kedua
  HANYA kepakai kalau key benar-benar tidak ada di `.env`, BUKAN kalau
  nilainya string kosong. `ADMIN_EMAIL=` kosong itu STATE YANG SENGAJA
  DIDUKUNG (lihat `config/admin.php` "leave empty to skip seeding") -
  jadi begitu `ADMIN_EMAIL` kosong, `'to'` resolve jadi `''`, dan
  `spatie/laravel-backup` VALIDASI FORMAT EMAIL ITU SAAT BOOT (bukan cuma
  saat backup benar-benar jalan) - hasilnya SETIAP request/artisan
  command di SELURUH aplikasi crash dengan `InvalidConfig` begitu
  `ADMIN_EMAIL` dikosongkan. Ditemukan sesaat setelah `cp .env.example
  .env` + `php artisan key:generate` gagal total di sesi verifikasi ini.
  Fix: ganti jadi `env('BACKUP_NOTIFICATION_EMAIL') ?: (env('ADMIN_EMAIL')
  ?: 'your@example.com')` - operator `?:` memperlakukan string kosong
  sebagai falsy dan lanjut ke fallback berikutnya, beda dari `env()`'s
  default kedua yang cuma reaktif terhadap key yang benar-benar tidak ada.

## Notifikasi/Pengumuman/Promo — bel persisten per-user, 2026-08-23

User minta "buat notifikasi, pemberitahuan, pengumuman, promo, diskon
untuk grafolog, user dan b2b". Dicek dulu ke kode: `Announcement`
(pengumuman, target per-role termasuk `hr`) dan `DiscountCode` (kode
diskon comprehensive/master/token) **sudah ada dan berfungsi** sejak
Commerce Fase B/F - tidak dibangun ulang. Dikonfirmasi lewat
AskUserQuestion, user pilih ketiganya: (1) mekanisme notifikasi baru yang
genuinely personal & persisten (bukan cuma banner dashboard yang dismiss
lokal), (2) perkuat yang sudah ada, (3) buka jalur promo untuk B2B.

**Keputusan penting soal #3 (B2B)**: TIDAK membangun sistem billing/
subscription perusahaan baru - itu keputusan bisnis besar yang sengaja
masih ditunda (lihat "Deferred on purpose" di bagian HR di atas), tidak
bisa ditebak. Sebagai gantinya: `Announcement` SUDAH BISA ditarget ke
role `hr` sejak awal (`target_roles: ['hr']`) - jadi "promo B2B" untuk
sekarang direalisasikan sebagai kanal notifikasi/pengumuman ke HR (mis.
"paket khusus perusahaan, hubungi kami"), bukan kode diskon dengan
transaksi otomatis (karena memang belum ada alur pembelian B2B untuk
didiskon). Ini genuinely menutup kebutuhan #3 tanpa menebak model harga.

- **`announcement_reads`** (tabel baru, `announcement_id`+`user_id`+
  `read_at`, unique pair) - per-user read state, MENGGANTIKAN
  `dismissedIds` lokal-session lama di `DashboardView.vue` yang sengaja
  tidak persisten (lihat catatan lama "jangan tambah persistence tanpa
  keputusan produk eksplisit" - sekarang ADA keputusan eksplisit itu).
- **`AnnouncementController::index()`** (rewrite) - respons sekarang
  `{data: [...], unread_count: N}` (BUKAN array polos lagi - breaking
  change API, semua konsumen diperbarui) - tiap item dapat `is_read`
  dihitung dari `announcement_reads` milik user yang login.
  **`markRead()`**/**`markAllRead()`** (baru, `POST /announcements/
  {id}/read` dan `/announcements/read-all`) - `markRead` menolak 404
  untuk pengumuman yang memang tidak visible untuk user itu (tidak bisa
  "curi baca" pengumuman yang bukan haknya). Upsert (`updateOrCreate`)
  jadi aman dipanggil berkali-kali tanpa duplikat/error.
- **Frontend**: `useNotifications.js` (composable singleton module-level,
  pola sama `useTheme.js`/`useToast.js`) menampung state notifikasi
  bersama di seluruh app. `AppNavbar.vue` dapat ikon lonceng + badge count
  + panel dropdown (klik di luar untuk tutup) - GLOBAL di navbar, bukan
  cuma halaman Dashboard, jadi terlihat dari halaman manapun. Membuka
  panel otomatis `markAllRead()` (satu aksi, bukan per-item toggle -
  sengaja sederhana). `DashboardView.vue`'s banner+dismiss lama
  **dihapus total**, digantikan bel ini sepenuhnya (tidak ada 2 UI
  paralel untuk hal yang sama). `AdminAnnouncementsView.vue`'s teks
  penjelasan diperbarui (sebelumnya bilang "banner di Dashboard, dismiss
  per sesi" - sudah tidak akurat sejak perubahan ini).
- 6 test baru + 2 test lama diperbarui shape-nya (`AnnouncementControllerTest`
  - unread default true, mark-read mengubah count, mark-read dua kali
  tidak duplikat, tidak bisa mark-read punya orang lain/tidak visible,
  read-state per-user independen, mark-all-read). 444 backend tests total
  (up from 438).
- **Browser-verified 2026-08-23** lewat Playwright: admin buat 3
  pengumuman (umum/khusus-grafolog/khusus-hr) → grafolog login lihat
  badge=2 isi benar (umum+grafolog, BUKAN B2B) → buka bel → badge hilang
  → **reload halaman → badge tetap 0** (membuktikan persisten di server,
  bukan cuma state lokal) → klien login lihat badge=1 (cuma yang umum) →
  HR login lihat badge=2 isi benar (umum+B2B, BUKAN grafolog) - kanal
  promo B2B via Announcement terkonfirmasi bekerja end-to-end. 9/9
  pemeriksaan lolos, 0 error konsol nyata.

## B2B Fase 1 — dashboard admin lintas-perusahaan, 2026-08-23

User minta daftar fitur B2B yang belum ada dipecah jadi 3 fase (lihat
ROADMAP.md "Kesiapan Publikasi" untuk fase 2/3). Fase 1: admin sebelumnya
cuma lihat nama+status tiap Company di `AdminUsersView.vue`'s section
Perusahaan, tanpa ringkasan aktivitas sama sekali.

- **`Api\Admin\CompanyController::index()`** diperluas — tiap Company di
  respons sekarang dapat 4 field tambahan: `hr_count`, `total_candidates`,
  `completed_reports`, `avg_turnaround_days`. **Tidak ada `company_id`
  langsung di `Project`/`HandwritingSample`** (dikonfirmasi lewat
  eksplorasi kode sebelum implementasi) — rantai query-nya
  `Company` → `User` (`company_id`, `role=hr`) → `Project.created_by` →
  `HandwritingSample`, sama seperti yang dipakai
  `DashboardController::hrDashboard()` per akun HR individual, di sini
  digabung per company (bisa >1 akun HR per company).
- `avgTurnaroundDays()` **sengaja diduplikasi kecil** dari
  `DashboardController` (bukan diekstrak ke helper bersama) — coupling
  Admin\* controller ke controller non-admin untuk ~10 baris logika tidak
  sepadan.
- **Tidak ada endpoint baru** — `index()` yang sudah ada diperkaya,
  konsumen lama otomatis dapat field baru tanpa breaking change.
- Frontend: `AdminUsersView.vue`'s tabel Perusahaan dapat 4 kolom baru
  (HR/Kandidat/Selesai/Rata-rata Durasi) — tidak dibuat halaman terpisah,
  Company sudah punya satu rumah di situ.
- 3 test baru (`CompanyControllerTest` — stats benar untuk company aktif,
  semua-nol untuk company kosong, tidak bocor lintas-company). 447
  backend tests total (up from 444).
- **Browser-verified 2026-08-23**: seed 1 company + 1 HR + 2 kandidat (1
  selesai 3 hari, 1 pending) → tabel Perusahaan tampilkan
  HR=1/Kandidat=2/Selesai=1/Durasi=3 hari, semuanya cocok data seed. 2/2
  pemeriksaan lolos.

## B2B Fase 2 — laporan tersegmentasi per-Topik, 2026-08-23

Lanjutan Fase 1 (lihat di atas). Infrastruktur Topik/`TopikFilterService`/
`ReportController::segmen()` sudah dibangun lengkap sejak "Topik
(kategorisasi)" (2026-08-22) tapi sengaja tanpa UI konsumen sama sekali -
sekarang disambungkan untuk B2B.

- **`Api\TopikController::index()`** baru (`GET /topik`, staff-only —
  `abort_if(role === 'user')`, BUKAN `role:administrator`) — bacaan
  ringan `{id, nama}`. Beda dari `Api\Admin\TopikController` (CRUD penuh,
  admin-only) yang sudah ada - HR bukan administrator, tidak bisa pukul
  `/admin/knowledge/topik`, tapi tetap perlu tahu daftar Topik untuk
  memilih filter. **Route alias diperlukan** (`AdminTopikController`) di
  `routes/api.php` - nama class `TopikController` sudah dipakai controller
  admin, collision kalau tidak dialiaskan (ditemukan lewat `php -l` error
  "Cannot use ... TopikController because the name is already in use").
- **`ReportController::segmen()` TIDAK diubah** - sudah benar sejak awal,
  cuma butuh pemakai frontend.
- 4 test baru (`TopikControllerTest` - guest ditolak, klien ditolak,
  HR/grafolog bisa akses). 451 backend tests total (up from 447).
- **Browser-verified 2026-08-23**: seed 1 Sindrom/2 Aspek/1 Topik (Aspek
  "Karier" ditag, Aspek "Lain" tidak), laporan HR nyata dengan keduanya →
  centang filter "Karier" → cuma narasi Karier tampil, narasi Lain hilang
  → uncheck → breakdown penuh kembali. 4/4 pemeriksaan lolos.

## B2B Fase 3 — kontrak custom per perusahaan (record-only), 2026-08-23

Fase terakhir dari 3-fase B2B (lihat Fase 1/2 di atas). Model harga B2B
ditanya ke user lewat AskUserQuestion sebelum implementasi — dipilih
**kontrak custom per perusahaan** (sales-led): sistem CUMA mencatat
kesepakatan yang sudah dinegosiasikan manual, TIDAK menghitung tagihan
otomatis, TIDAK menyentuh payment gate/flow apa pun yang sudah ada.

- **`company_contracts`** (`judul`, `catatan` teks bebas — sengaja bukan
  field terstruktur per item karena tiap kontrak hasil negosiasi beda-beda,
  `nilai_kontrak` decimal nullable **murni referensi internal admin, TIDAK
  dipakai kalkulasi apa pun**, `mulai_at`, `berakhir_at` nullable — null =
  tanpa batas waktu, `status` enum draft/aktif/dihentikan diubah manual
  oleh admin — **TIDAK auto-transisi via cron**, `created_by`).
  `CompanyContract belongsTo Company`, `Company` dapat relasi `contracts()`
  baru.
- **`Api\Admin\CompanyContractController`** (nested di bawah Company, pola
  sama persis `MeasurementCategoryController`/`MeasurementVariableController`) -
  `store()`/`update()`/`destroy()`, tidak ada `index()` terpisah,
  `CompanyController::index()` sekarang `with('contracts')` supaya
  dashboard Fase 1 langsung dapat riwayat kontrak tanpa request kedua.
  **Route alias diperlukan** (`CompanyContractController` vs
  `CompanyController` import) - Pint mengurutkan alfabetis otomatis,
  "CompanyContract..." < "CompanyController" secara leksikal jadi
  urutannya benar tanpa perlu diatur manual.
- **Gotcha ditemukan & diperbaiki saat browser-verify**: cast Eloquent
  `'mulai_at' => 'date'` polos men-serialize ke JSON sebagai timestamp ISO
  penuh (`"2026-01-01T00:00:00.000000Z"`), bukan tanggal bersih - frontend
  menampilkan itu apa adanya, jelek. Fix: `'date:Y-m-d'` (format eksplisit)
  di `CompanyContract`'s cast - field ini genuinely tanggal, bukan waktu.
- Audit log: `buat_kontrak_b2b`/`ubah_kontrak_b2b`/`hapus_kontrak_b2b`.
- 8 test baru (`CompanyContractControllerTest` - CRUD, auth non-admin
  ditolak, validasi `berakhir_at >= mulai_at`, cascade delete saat Company
  dihapus, `CompanyController::index()` menyertakan kontrak). 459 backend
  tests total (up from 451).
- **Browser-verified 2026-08-23**: admin buka panel "Kontrak" di baris
  perusahaan → catat kontrak (judul/status Aktif/tanggal/nilai/catatan) →
  muncul di riwayat dengan format tanggal bersih + badge status "Aktif" di
  baris utama tabel Perusahaan → hapus kontrak → hilang dari daftar. 4/4
  pemeriksaan lolos, 0 error konsol nyata.
- **Menutup seluruh rencana 3-fase B2B** dari ROADMAP.md "Kesiapan
  Publikasi" — dashboard admin lintas-perusahaan, laporan tersegmentasi
  per-Topik, dan kontrak B2B semuanya selesai dikerjakan hari yang sama.

## Perlindungan data (simpel) + dukungan pelanggan + placeholder legal — 2026-08-30

User minta "perlindungan data yg simpel saja, tidak perlu ada disclaimer
AI, legal saya ada di biro psikologi siapkan aja tempatnya, buat dukungan
pelanggan yang managementnya bisa diatur." Sengaja **reuse** infrastruktur
CMS `ContentBlock` yang sudah ada (lihat "Homepage CMS" di atas) daripada
membangun sistem admin baru — persis memenuhi "managementnya agar bisa
diatur" dengan hampir nol permukaan backend baru.

- **6 key baru di `ContentBlock::EDITABLE_KEYS`** (total sekarang 27,
  ingat: update `EDITABLE_KEYS` DAN `ContentBlockSeeder.php` bersamaan,
  keduanya tidak saling derive):
  - `support_email`, `support_whatsapp`, `support_hours`, `support_note`
    — dikonsumsi halaman `/bantuan` baru (`guratan-web`'s `HelpView.vue`).
  - `legal_entity_name`, `legal_contact_email` — entitas hukum/mitra
    pengawas (mis. biro psikologi) yang dirujuk di footer + Kebijakan
    Privasi/Ketentuan Layanan. **Sengaja default string kosong, BUKAN
    placeholder ber-kurung-siku** — user menyatakan legitimasi hukum
    produk berasal dari sebuah biro psikologi tapi belum menyebutkan
    namanya, dan Claude sengaja TIDAK mengarang nama entitas ini. Setiap
    halaman publik yang merujuk field ini menyembunyikan barisnya
    sepenuhnya (`v-if`) kalau masih kosong — supaya tidak tampil janggal
    ke publik sebelum admin mengisi lewat panel `/admin/content` yang
    sudah ada.
  - Tidak ada endpoint/tabel baru sama sekali — 6 key ini lewat
    `GET /api/content` (public) dan `PUT /api/admin/content/{key}`
    (admin) yang sudah ada apa adanya.
- **AI disclosure ke klien SENGAJA TIDAK dibangun** — item ini sempat
  masuk daftar tertunda di `ROADMAP.md`, dicoret eksplisit atas instruksi
  user ("tidak perlu ada disclaimer AI"). Jangan tambahkan kembali tanpa
  keputusan baru dari user.
- **Tidak ada dashboard self-service ekspor/hapus data** — mekanisme
  disederhanakan jadi: permintaan hak data-subjek (salinan/koreksi/hapus
  data, sesuai UU PDP) diarahkan lewat kanal dukungan pelanggan di
  `/bantuan`, bukan lewat UI khusus. Kebijakan Privasi menyatakan retensi
  data tetap 30 hari kerja pasca penghapusan akun.
- Privacy Policy/ToS draft lama di `legal/privacy-policy.md`/
  `legal/terms-of-service.md` (berlabel "JANGAN publikasikan sebelum
  ditinjau") **tetap ada sebagai draft mentah**, tidak dihapus — konten
  yang benar-benar tayang di `/kebijakan-privasi`/`/ketentuan-layanan`
  adalah versi yang sudah disederhanakan/final di `guratan-web`'s
  `PrivacyPolicyView.vue`/`TermsOfServiceView.vue`, bukan draft ini secara
  langsung.
- Lihat `guratan-web/CLAUDE.md` untuk detail frontend (3 halaman publik
  baru + footer global).
- **Verifikasi**: 459 backend test tetap hijau (1 kegagalan pre-existing
  tidak terkait, `ExampleTest` soal `APP_KEY` kosong di lingkungan test
  bawaan Laravel — bukan regresi dari perubahan ini), `pint --test` lolos,
  `npm run lint`/`npm run build` lolos. Browser-verified end-to-end
  (Playwright): footer + 3 halaman baru tampil benar dengan
  `legal_entity_name` kosong (baris Kontak tersembunyi sepenuhnya), admin
  mengisi 6 field lewat `/admin/content`, lalu footer/`/bantuan`/
  `/kebijakan-privasi` semuanya langsung merefleksikan nilai baru tanpa
  reload manual di luar navigasi halaman.

## Laporan/Rekap admin — Fase 1 (CSV export + Rekap Pengguna/Grafolog), 2026-09-03

User minta rekap + dashboard analitik lengkap, diminta bikin daftar dulu
sebelum dikerjakan (lihat `ROADMAP.md`'s entri matching untuk daftar
lengkap 10 item, dipecah 4 fase). Riset kode sebelum mulai: **tidak ada
agregasi cross-user/global sama sekali** (`DashboardController` cuma
per-user, `Admin\CompanyController` cuma per-company) dan **tidak ada
export CSV apapun** di aplikasi ini — keduanya genuinely gap baru, bukan
duplikasi. Fase 1 membangun fondasi yang dipakai ulang di Fase 2+:

- **`App\Support\CsvStreamer`** (class static-method baru, BUKAN trait —
  tidak ada trait sama sekali di `app/`, precedent yang dipakai adalah
  `App\Support\IndikatorKode`) — `CsvStreamer::download(string $filename,
  array $header, iterable $rows)` membungkus `response()->streamDownload()`.
  Setiap controller Rekap punya method `export()` **terpisah** dari
  `index()` (bukan `?format=csv` di endpoint yang sama) karena shape
  paginator vs unpaginated-stream beda jauh — keduanya berbagi 1 private
  `filteredQuery()` supaya filter yang tampil di layar dan yang di-export
  TIDAK PERNAH beda. `export()` dipanggil dengan `->cursor()` (bukan
  `->get()`) supaya memori tetap flat berapa pun jumlah barisnya.
- **`PersonalityReport::avgTurnaroundDaysFor(Collection $sampleIds): ?float`**
  (baru, di model) — diekstrak dari duplikasi byte-identik yang
  sebelumnya ada 2x (`DashboardController`'s dan
  `Admin\CompanyController`'s private `avgTurnaroundDays()`, yang terakhir
  bahkan punya docblock eksplisit membela duplikasi itu "tidak sepadan
  diekstrak"). Alasan diekstrak SEKARANG (bukan tetap duplikat lagi):
  `GrafologRecapController` di fase ini butuh logika yang sama persis
  ke-3 kalinya, dan Fase 4's `grafologPerformance()` bakal jadi ke-4 —
  diekstrak ke **model** (bukan helper controller/trait) supaya tidak
  jadi "Admin controller coupled ke controller non-admin" seperti yang
  tadinya jadi keberatan `CompanyController`'s docblock. Kedua controller
  lama didelegasikan ke method baru ini (perubahan mekanis, test lama
  yang sudah ada jadi bukti kebenaran refactor, bukan ditulis ulang) —
  konfirmasi eksplisit dari user lewat AskUserQuestion sebelum dikerjakan.
- **`Admin\UserRecapController`** (`GET /admin/recap/users[/export]`) —
  roster lintas-role (user/grafolog/administrator/supervisor/hr), filter
  `role`/`is_active`/`company_id`/`from`-`to` (created_at)/`search`
  (nama/email). Beda dari `AdminUsersView.vue`'s tabel staf yang cuma
  CRUD — ini murni pembacaan terfilter+export untuk kebutuhan rekap.
- **`Admin\GrafologRecapController`** (`GET /admin/recap/grafolog[/export]`) —
  sama filter minus `role`/`company_id` (selalu `role=grafolog`), tiap
  baris ditambah `completed_reports` + `avg_turnaround_days` (pola sama
  `Admin\CompanyController::index()` per-company, di sini per-grafolog).
- **Export dicatat di Log Audit** (`ekspor_rekap_pengguna`/
  `ekspor_rekap_grafolog`, actor = admin yang export) — beda dari
  `index()` yang tidak dicatat (melihat tabel di layar dianggap setara
  membuka `AdminAuditLogView.vue` sendiri, tidak dicatat) — export adalah
  ekstraksi data massal (email dkk), dikonfirmasi eksplisit oleh user
  untuk dicatat, konsisten dengan `log.report_access` yang juga mencatat
  setiap PEMBACAAN laporan sensitif, bukan cuma perubahan data.
- Test baru: `UserRecapControllerTest`, `GrafologRecapControllerTest` (7+4
  test: guard auth/role, filter benar termasuk company+search gabungan,
  isi CSV yang di-stream cocok fixture, stat kosong = 0/null bukan error,
  audit log tercatat saat export). 484 backend tests total (up from 473).
- **Verifikasi**: `php artisan test` (483/484 hijau, 1 kegagalan
  `ExampleTest` pre-existing tidak terkait), `pint --test` lolos,
  `npm run lint`/`build` lolos. Browser-verified (Playwright): login
  admin, filter role/search di Rekap Pengguna menemukan user seed yang
  benar, Export CSV di kedua halaman benar-benar mengunduh file nyata,
  Rekap Grafolog menampilkan 0/null dengan benar untuk grafolog tanpa
  laporan, 0 error konsol.
- Lihat `guratan-web/CLAUDE.md` untuk detail frontend (2 halaman rekap
  baru + `src/lib/downloadBlob.js`).

## Not built yet

- Frontend checkout UI (see "Payment (DOKU)" above — backend is done,
  frontend trigger point is an open product question).
- Production DOKU credentials (sandbox-only scaffolding so far).
