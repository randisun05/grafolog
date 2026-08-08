# guratan-web

Vue 3 + Vite frontend for Guratan (grafology SaaS). Verified against actual
code on 2026-07-26 — no `CLAUDE.md` existed here before this one.

## Dev commands

- `npm run dev` (Vite, default port 5173 — matches `guratan-api`'s
  `CORS_ALLOWED_ORIGINS`). Confirmed working (build + dev server both tested
  2026-07-26).
- `npm run build` → `dist/`. Confirmed working, no errors.
- `.env.development`: `VITE_API_URL=http://127.0.0.1:8123/api` — the API
  **must** be started with `php artisan serve --port=8123` for this to
  resolve; the default `php artisan serve` (port 8000) will NOT match.

## Structure (as of 2026-08-01 — Rapid tier retired, see below)

- `src/views/`: `LandingView`, `LoginView`, `RegisterView`, `DashboardView`,
  `RiwayatView`, `ReportView`, `PortalGrafologView`, `AdminUsersView`,
  `AdminPricingView`, `AdminDiscountsView`, `AdminContentView`,
  `AdminAnnouncementsView`, `AdminTokensView`, `TokenWalletView`,
  `AdminKnowledgeView`, `AssignedToMeView`, `HrCandidatesView`, `OrderView`,
  `NotFoundView`.
  **`AdminKnowledgeView` added 2026-08-08** (Knowledge Management System
  KM-B, see root `ROADMAP.md`'s "Inisiatif — Knowledge Management System")
  — `/admin/knowledge`, `role: 'administrator'`. One page, 3 tabs (local
  `activeTab` ref, not 3 routes — same "several small entities, one screen"
  call as `AdminTokensView`): **Sindrom** (create/inline-edit/delete, table
  with `# Aspek` count from the backend's `withCount`, delete blocked
  client-side too if that count is >0 — mirrors the backend's 422 guard so
  the user gets an inline toast instead of always round-tripping to find
  out), **Variabel Ukur** (create/inline-edit/delete; each row expands to a
  nested category table + its own small create form — categories are only
  ever managed in the context of their parent variable, no separate view),
  **Band Skor** (create/inline-edit/delete). No modal component exists in
  this codebase — edit is an inline per-row state toggle
  (`editingId`/`editForm` refs per entity), same convention as everywhere
  else here. **Aspek, Indikator, and the operator/rule system have no panel
  yet** — this view's own intro text says so; don't assume this page is a
  complete KB editor. Nav link "Knowledge Base" + `CommandPalette.vue`
  entry. Browser-verified with Playwright (full create→edit→delete round
  trip on all 3 tabs, zero console/page errors, state confirmed back to
  baseline row counts after cleanup) — not just build/lint-checked.
  **Gained a 4th tab, "Aspek", 2026-08-08 (KM-C)** — same page, same
  create-minimal/edit-full pattern as the Variabel Ukur tab's nested
  categories: the create form only takes `kode`/Sindrom/`nama` (a dropdown
  of `sindromList`, already loaded for the Sindrom tab, reused here — no
  second fetch), and clicking "Ubah" expands a full edit panel below the
  row with `keterangan_umum` + the 4 narasi-level textareas
  (`.admin-km__field`/`.admin-km__categories` classes, reused rather than
  duplicated). Delete is guarded client-side too (toast if `indikator_count
  > 0`, mirrors the backend's 422) before even attempting the request.
  Browser-verified: created a throwaway Aspek, filled and saved all 5 text
  fields, reloaded the page, **re-opened the edit panel and confirmed the
  saved text was still there** (proves it round-tripped through the
  backend, not just local state), then deleted it — zero console errors,
  row count back to the real 40 Aspek afterward.
  **Gained a 5th tab, "Indikator", 2026-08-08 (KM-D)** — unlike the other 4
  tabs, this one is **paginated + searchable** (704 rows): a search box
  (400ms debounced, resets to page 1) matches kode/nama, a dropdown filters
  to one Aspek (reuses `aspekList`, already loaded), and a simple
  prev/next + "Halaman X / Y" footer drives `?page=`. Create form takes
  kode/Aspek/posisi (1-10 dropdown) in one row, varian (optional) + nama in
  another. "Ubah" expands the same kind of nested panel as Aspek/Variabel
  Ukur, this time for `keterangan` (a textarea — some Indikator descriptions
  run to a full sentence, not worth cramming into an inline table cell).
  Browser-verified: confirmed search against real KB data (e.g. "extremely
  small" correctly narrowed 704 → 1), created a throwaway Indikator, edited
  its nama + keterangan, reloaded and re-searched to confirm persistence,
  then deleted it — zero console errors.
  **Indikator's edit panel gained an "Aturan Operator" sub-section,
  2026-08-08 (KM-E)** — the rule/operator builder. Below the existing
  kode/aspek/posisi/varian/nama/keterangan fields: a `rule_group_logic`
  select (AND/OR), a list of the Indikator's existing rules rendered via
  `formatRule()` (mis. `Middle zone height = "large"` or
  `d stem width > 1.5000× 5.2000`), each with its own delete button, and a
  small add-rule form. The form's fields are conditional on `rule_type`
  (`v-if`/`v-else` blocks, not hidden-but-present inputs) — picking
  "category" shows a category dropdown **populated from that variable's
  real `kategori` list** (`kategoriOptionsFor()`, reads `variableList`
  already loaded for the Variabel Ukur tab — no separate fetch); picking
  "comparison" shows operator/koefisien/compareMode, and `compareMode`
  itself toggles between a variable-B dropdown and a fixed-number input.
  Reuses `variableList` across two tabs is deliberate — it's the same data,
  no reason to fetch it twice. Browser-verified: added one rule of each
  type to a real Indikator, confirmed both display with correctly formatted
  text, changed `rule_group_logic` to AND and saved, reloaded the page and
  reopened the same Indikator to confirm both rules AND the group-logic
  choice persisted, then deleted both rules. Also confirmed (via the
  backend's own test suite, not re-proven through a UI click in this
  session) that deleting a Measurement Variable still referenced by a rule
  is blocked.
  **Gained a 6th tab, "Referensi Silang", 2026-08-08 (KM-F)** — same
  paginated-search pattern as the Indikator tab (280 rows). Table columns:
  Sumber (raw text + resolved source Indikator kode if known), Tujuan
  (target kode), Status (`matched`/`unmatched` badge, read-only — computed
  server-side, not editable), Aktif (a dedicated toggle button, the primary
  action for this tab, separate from full row edit). Create/edit forms use
  a new `GET /admin/knowledge/indikator-options` fetch (`indikatorOptions`,
  loaded once at mount) for the source-Indikator dropdown — the existing
  paginated `indikatorList` can't serve that, it only ever holds one page's
  worth of rows. **This tab is explicitly NOT the cascade-trigger UI**
  (checking Indikator A auto-suggesting B) — the intro text says so; that
  needs a real grafolog-facing checklist form to exist first (KM-G), this
  is purely the data-management layer underneath it. Browser-verified:
  confirmed search against real data via network-response-aware waits
  (learned from earlier KM tabs' flaky fixed-timeout scripts — this one
  waits on the actual debounced API response instead), created a row,
  toggled `aktif` off, edited its target kode, reloaded and re-searched to
  confirm **both** the toggle and the edit persisted, then deleted it.
  **`AdminTokensView` + `TokenWalletView` added 2026-08-07** (grafolog
  token system, see root `ROADMAP.md`'s "Inisiatif — Token Grafolog").
  `AdminTokensView` (`/admin/tokens`, `role: 'administrator'`) has two
  independent sections in one page — price per token
  (`GET/PUT /api/admin/token-price`) and tokens-required per tier
  (`GET/PUT /api/admin/token-costs/{tier}`) — each with its own draft/save/
  history, same structural pattern as `AdminPricingView` duplicated twice
  in one component rather than two separate routes, since an admin tuning
  the token economy needs both at once. `TokenWalletView`
  (`/token-saya`, `role: 'grafolog'`) shows `GET /api/tokens/wallet`'s
  balance + last 20 ledger transactions, plus a buy form (quantity +
  optional discount code previewed via `POST /api/tokens/preview`) that
  posts to `POST /api/tokens/purchase` and hard-redirects to DOKU's
  `payment_url` exactly like `OrderView` does — **same untested-past-503
  caveat applies**: the actual DOKU redirect has never been exercised
  (blocked on Commerce Fase C's sandbox credentials), only the clean-503
  failure path is verified. Nav link "Kelola Token" (administrator) /
  "Token Saya" (grafolog) + `CommandPalette.vue` entries.
  `AdminDiscountsView.vue`'s tier checkboxes gained a third option,
  `token`, so a discount code can be scoped to token purchases the same
  way it's scoped to comprehensive/master — no other change needed there,
  `applicable_tiers` was already a free-form array on the backend.
  `DashboardView.vue` needed **no changes** for the new "Sisa Token" KPI
  card — its `v-for="kpi in dashboard.kpi"` already renders whatever the
  API sends generically.
  **`AdminAnnouncementsView` added 2026-08-06** (Commerce Fase F) —
  `/admin/announcements`, `role: 'administrator'`. Create form (title,
  body, optional target-role checkboxes from a local `roleOptions` array —
  matches `Announcement`'s allowed role list on the backend, not derived
  from it, keep both in sync if roles ever change — optional start/end
  date) + a table with an activate/deactivate toggle
  (`PUT /api/admin/announcements/{id}`, full-edit endpoint but this view
  only ever sends `is_active`). Same admin-page pattern as
  `AdminDiscountsView`/`AdminPricingView`. Nav link "Pengumuman" +
  `CommandPalette.vue` entry. **`DashboardView.vue` changed 2026-08-06** —
  fetches `GET /api/announcements` after its existing dashboard-summary
  fetch (separate try/catch, non-fatal on failure — dashboard still
  renders if this call fails, same fallback philosophy as `LandingView`'s
  CMS fetch below). Visible announcements render as dismissible banners
  above the KPI cards; dismissal is a local `dismissedIds` ref, not
  persisted anywhere — the same announcement reappears next visit as long
  as it's still active. Don't add persistence for this without an explicit
  product decision; the admin UI's own copy states the current behavior on
  purpose.
  **`AdminContentView` added 2026-08-06** (Commerce Fase E), **expanded
  2026-08-07** — `/admin/content`, `role: 'administrator'`. Local `fields`
  array (now 21 entries) that **must match** `ContentBlock::EDITABLE_KEYS`
  on the backend — they're not derived from each other, if you add a field
  on one side add it on the other too. Each field has a `type`:
  `'text'`/`'textarea'` render a plain input/textarea (original pattern,
  save button per field); `'list-string'`/`'list-object'` (new) render a
  small **fixed-count** list editor instead — plain strings for
  `landing_compare_old`/`_new` (3 slots each), title+desc pairs for
  `landing_steps` (4) and `landing_honesty_points` (3). These serialize to
  JSON before `PUT` and deserialize on load (`JSON.parse` wrapped in
  try/catch — a parse failure silently falls back to an empty-but-correct-
  shaped array via `emptyListValue()`, never a crash). Matches
  `ContentBlock::LIST_KEYS` on the backend — see `guratan-api/CLAUDE.md`.
  **`LandingView.vue` rebuilt 2026-08-07** (from a single hero block into
  a full company-profile page — hero, cara-lama/cara-Guratan comparison,
  4-step "Cara Kerja", an interactive Sindrom/Aspek accordion explorer,
  pricing cards fed by the real `GET /api/pricing`, a "Kejujuran Kami"
  credibility section, 3-step signup flow, closing CTA band). Still
  follows the original fetch-with-fallback pattern — `GET /api/content`
  merged over a hardcoded defaults object kept in the component, list-type
  keys additionally `JSON.parse`'d over hardcoded array defaults — so the
  page is never blank or partially-shaped if the CMS is slow/down/returns
  malformed JSON. The Sindrom explorer's category/aspect labels
  (`sindromData`, e.g. "Ketegasan" for "Authoritarian") are **static data
  in the component, deliberately not wired to the `sindrom`/`aspek`
  tables** — they're softened public-marketing Indonesian copy, not a live
  reflection of the KB, and the real technical terms are unchanged
  everywhere else (grafolog scoring form, reports). Breaks out of the
  app's normal 960px `.app-main` column via a scoped negative-margin
  full-bleed technique (`margin: 0 calc(50% - 50vw)`) so its alternating
  section backgrounds run edge-to-edge — this only affects `LandingView`,
  every other route is still constrained by `.app-main` in `App.vue`.
  Does **not** duplicate a nav bar — `AppNavbar` is already global.
  **`OrderView` added 2026-08-06** (Commerce Fase D) — `/pesan`,
  `role: 'user'` (the plain-client role only; `auth.isClient` in
  `stores/auth.js`, mirrors `isGrafolog`/`isHr`/etc). Tier cards from
  `GET /api/pricing`, optional discount code applied via
  `POST /api/pricing/preview` for a live total before paying. "Bayar
  Sekarang" calls `POST /api/samples` then `POST /api/samples/{id}/payment`
  — two existing endpoints, no new backend endpoint for this view. On
  success it does a hard `window.location.href` redirect to DOKU's
  `payment_url` (leaves the SPA entirely, this is intentional — DOKU's
  checkout page isn't ours). **The actual DOKU redirect has never been
  tested** — no sandbox credentials yet (Commerce Fase C). What *is*
  verified: the failure path — with DOKU unconfigured,
  `PaymentController::store` returns a clean 503 (see
  `guratan-api/CLAUDE.md`'s "DOKU config errors are caught" note) and this
  view shows it as an inline message + toast, not a crash.
  **`AdminDiscountsView` added 2026-08-06** (Commerce Fase B) —
  `/admin/discounts`, `role: 'administrator'`. Create form (code auto-
  uppercases server-side, type percentage/fixed, optional tier checkboxes,
  optional quota/expiry) + a table with an activate/deactivate toggle
  (`PATCH /api/admin/discount-codes/{id}`, `is_active` only — there's no
  edit for value/quota after creation, see `guratan-api/CLAUDE.md`'s
  "Discount codes" section for why). Same admin-page pattern as the other
  admin views. Nav link "Kelola Diskon" + `CommandPalette.vue` entry.
  **`AdminPricingView` added 2026-08-06** (Commerce Fase A, see root
  `ROADMAP.md`'s "Inisiatif — Commerce & CMS") — `/admin/pricing`,
  `role: 'administrator'`. Shows the active price + change history per
  tier, inline edit backed by `GET/PUT /api/admin/pricing[/{tier}]`. Same
  admin-page pattern as `AdminUsersView`/`HrCandidatesView` (toast on
  success, reload list after mutation). Nav link "Kelola Harga" +
  `CommandPalette.vue` entry.
  **`HrCandidatesView` + `AssignedToMeView` added 2026-08-06** (MGA Fase
  06). `HrCandidatesView` (`/hr/candidates`, `role: 'hr'`): CSV upload form
  + a candidate table reusing `GET /api/samples` (already scoped to the HR
  user via `created_by` — no new list endpoint needed), with inline
  grafolog assignment (dropdown fed by `GET /api/grafologs`, posts to the
  existing assignment endpoint). `AssignedToMeView`
  (`/grafolog/ditugaskan`, `role: 'grafolog'`): lists every non-completed
  sample visible to the grafolog — both self-created and HR-assigned, since
  `GET /api/samples` now covers both — linking into
  `PortalGrafologView`'s new resume flow. **`PortalGrafologView` now
  accepts a `?sampleId=` query param**: on mount, if present, it fetches
  that sample and jumps straight to step 2 (skipping the client-lookup
  step), which is what makes an HR-assigned sample actually reachable —
  the original flow only ever populated `sample` via lookup-then-create.
  `stores/auth.js` gets `isHr`.
  **`AdminUsersView` added 2026-08-03** (MGA pivot Fase 05) — `/admin/users`,
  gated by `meta: { role: 'administrator' }` in the router (same pattern as
  `/portal-grafolog`'s `role: 'grafolog'`). Create-staff-account form +
  live table, backed by `GET/POST /api/admin/users`. Nav link "Kelola Staf"
  shows only for `auth.isAdministrator` (new computed in `stores/auth.js`,
  mirrors `isGrafolog`). **Browser-verified in Fase 05 and again in Fase
  06** (Playwright, ad-hoc via `npx playwright install chromium` — no
  project skill for this existed yet, worth generating one via
  `/run-skill-generator` if this becomes a recurring need): Fase 06's
  script drove the *entire* HR→assignment→grafolog-scoring loop across two
  browser contexts and screenshotted every step, zero console errors. Every
  view NOT touched by Fase 05/06 is still only build/lint-checked — say so
  explicitly if asked.
  **`DashboardView` added 2026-08-01** (MGA pivot Fase 03) — 4 KPI cards +
  5-item activity feed from `GET /api/dashboard`, now the post-login landing
  page (`/dashboard`; login/register/guestOnly-guard all redirect here
  instead of `riwayat`). `RiwayatView` is unchanged and still reachable via
  its own nav link — it's the detailed list, Dashboard is the summary; per
  the plan neither replaces the other. **`UploadView` and
  `HasilRapidView` were deleted 2026-08-01** (MGA pivot Fase 01, Rapid tier
  retired — the backend now rejects `tier: rapid` at creation, so the
  self-upload flow was dead code). Old rapid-tier reports are still viewable
  through the unrelated `RiwayatView` → `ReportView` path, which never
  depended on the deleted views.
- `src/components/report/`: `TraitBar`, `ReportDocument`.
- `src/components/scoring/`: `AspekRow`, `ScoreSelector`, `SindromAccordion`
  (all unchanged since 2026-07-26 — reused as-is, only repositioned),
  `AutoCalculationPanel` (added 2026-08-03, MGA Fase 04),
  **`MeasurementWorksheet` + `IndikatorChecklist` (added 2026-08-08, KM-G)**
  — see `PortalGrafologView`'s entry below for how they plug in.
- **Built 2026-07-27** (were missing as of 2026-07-26, now done):
  - `components/layout/AppNavbar.vue` — extracted verbatim from `App.vue`'s
    inline header (no behavior change), mounted there via `<AppNavbar />`.
    **Updated 2026-08-01**: "Unggah" link removed (led to the retired Rapid
    upload flow).
  - `components/upload/UploadDropzone.vue` — **deleted 2026-08-01** along
    with `UploadView`, its only consumer.
  - `components/shared/LoadingSpinner.vue` — wired into `RiwayatView`,
    `ReportView`, `DashboardView` (replacing plain "Memuat..." text).
    (Was also wired into `HasilRapidView` until that view was deleted
    2026-08-01 with the rest of the retired Rapid tier.)
  - `components/shared/ToastNotification.vue` + `src/composables/useToast.js`
    (tiny shared reactive array, not Pinia) — mounted once in `App.vue`.
    Real usage: `ReportView`'s PDF-download failure now shows a toast instead
    of overwriting the page's main error region.
  - `components/shared/ProgressTracker.vue` — wired into
    `PortalGrafologView`'s 3-step flow (Pilih Klien → Isi Skor → Laporan
    Selesai) as a visual step indicator.
  - **Not browser-tested visually** — verified via `npm run build`,
    `npm run lint`, and serving the dev server + curling the changed files
    (200 OK, no compile errors). No actual click-through in a real browser
    was done this session; if something looks off visually, that's why. This
    is a recurring gap across every UI change in this project so far — no
    browser automation tool has been available in any session — not
    specific to this component.
- **`PortalGrafologView`'s step 1 gained "daftarkan klien baru" 2026-08-07**:
  when `GET /api/users/lookup` 404s (client doesn't exist yet — this used
  to be a dead end), a button reveals an inline form (name + optional
  password) that calls the new `POST /api/clients`. On success the
  returned client is set directly into the existing `client` ref, so step
  1's tier picker and "Buat Sample" button work exactly as if the lookup
  had succeeded — no separate downstream code path. If no password was
  given, the server-generated one comes back as `generated_password` and
  is shown in a **persistent inline banner** (not a toast — a toast could
  disappear before the grafolog copies the password down) telling them to
  relay it to the client directly. See `guratan-api/CLAUDE.md`'s
  `POST /api/clients` entry for why this doesn't email an invite.
- **`PortalGrafologView`'s step 2 gained a "cara isi skor" mode toggle,
  2026-08-08 (KM-G)**: a `scoringMode` ref (`'manual'` default |
  `'worksheet'`) two radio buttons above the workspace. `'manual'` renders
  exactly what was already there (`SindromAccordion`, completely
  untouched). `'worksheet'` swaps the middle column for
  `MeasurementWorksheet` (input grid for the ~34 measurement variables,
  posts to `POST /api/samples/{id}/measurements`) stacked above
  `IndikatorChecklist` (fetches `GET /api/samples/{id}/checklist`,
  Sindrom→Aspek accordions of checkboxes; each checked-by-rule/cascade
  Indikator shows an "Auto"/"Terkait" badge plus the trigger's
  `keterangan_pemicu` text inline in italic — this is the KM plan's
  explicit "grafolog must see WHY" requirement). **Both modes write into
  the exact same `scores` ref** that already fed
  `AutoCalculationPanel`/preview/submit — `IndikatorChecklist` never talks
  to those directly, it only emits an `apply` event (from its own
  "Terapkan Skor Checklist ke Form" button) that `PortalGrafologView`
  merges into `scores`, at which point the pre-existing debounced preview
  watcher and the submit button behave identically to manual mode with
  zero new code path. **That button click is a deliberate, one-time
  hand-off, not a live sync** — `tallyPerAspek` on the backend returns a
  skor for every Aspek unconditionally (clamped to a floor of 1), so
  auto-applying it continuously would make the submit button look
  "complete" the instant the checklist loads, before the grafolog has
  actually gone through it. Checkbox clicks call
  `POST /api/samples/{id}/checklist/toggle`; when the backend responds
  with `requires_confirmation` (unchecking something that cascaded to
  still-checked targets), the component shows a plain `window.confirm()`
  listing the affected Indikator — no modal component exists in this
  codebase (same convention as every other KM view), and a yes/no with a
  short list didn't justify inventing one. Browser-verified end-to-end
  against real KB data — see `guratan-api/CLAUDE.md`'s KM-G section for
  the full scenario (real category rule, real cross-reference cascade,
  submitted through the unchanged scoring endpoint to a completed report).
- **`PortalGrafologView`'s step 2 refactored 2026-08-03** (MGA pivot Fase
  04): "Isi Skor" is now a 3-column Assessment Workspace (client/sample info
  | `SindromAccordion` form, unchanged | `AutoCalculationPanel`, live).
  `AutoCalculationPanel` is fed by a 500ms-debounced `watch(scores, ...)`
  calling the new `POST /api/samples/{id}/scores/preview` — see
  `guratan-api/CLAUDE.md`. Steps 1 (pick client) and 3 (done) are unchanged.
  **Deferred**: the plan's fuller idea of splitting this view into 3 routes
  (`/grafolog/clients`, `/grafolog/projects/new`, a standalone workspace
  route) — out of scope for this phase, `PortalGrafologView` still owns all
  3 steps as one component. Revisit if/when Fase 05 role work needs
  separate URLs for these steps.
- `src/stores/auth.js` (Pinia): holds `user`/`token` refs, persists both to
  `localStorage` under `guratan_user` / `guratan_token` on register/login,
  clears them on logout. **Confirmed using `localStorage` correctly** — a
  prior bug here is fixed.
- `src/lib/api.js`: axios instance, `baseURL` from `VITE_API_URL`, always
  sends `Accept: application/json`, attaches `Authorization: Bearer <token>`
  from `localStorage` via request interceptor, clears stored auth on a 401
  response. Because `Accept: application/json` is always sent, this client
  correctly gets JSON 401s from the API rather than tripping the
  `guratan-api` bug described in `guratan-api/CLAUDE.md` (unauthenticated +
  no Accept header → 500).
- `src/router/index.js` (vue-router): route guards check
  `meta.requiresAuth` (redirect to `login` with `?redirect=`),
  `meta.guestOnly` (redirect authenticated users to `dashboard` — was
  `riwayat` before the Fase 03 pivot), and `meta.role` (redirect if
  `auth.user.role` doesn't match, e.g. `/portal-grafolog` requires
  `role: 'grafolog'`; same pattern gates `/admin/users` → `administrator`,
  `/hr/candidates` → `hr`, `/grafolog/ditugaskan` → `grafolog`).
- `src/assets/base.css` + `main.css`: design tokens (`--color-ink`,
  `--color-seal`, `--color-sage`, `--color-gold` (new 2026-08-07, Master-tier
  accent), `--font-heading: Fraunces`, `--font-body: Inter`, `--font-accent:
  Caveat`) plus shared `.btn`/`.error`/form-input styles. Confirmed
  applied — this was a previously-reported bug, now fixed. **Palette
  repainted 2026-08-07** ("Kertas Berani" direction — chosen over a dark
  "night journal" alternative after comparing both in a throwaway mockup
  artifact, to read less "aged/stiff" for a younger audience): crisper
  off-white paper (`#fffcf7`/`#ffffff`, was a more yellowed cream), higher-
  saturation seal red and sage green, same four-hue family as before
  (ink/seal/sage/gold) just brightened — not a palette replacement. Dark
  mode and both `[data-theme]` overrides were updated to match; still only
  the base tokens are redefined per mode, no component-level dark CSS.
  **Dark mode added 2026-08-06** (MGA Fase 07): only the base color tokens
  are redefined for dark (`@media (prefers-color-scheme: dark)` for the OS
  default, `:root[data-theme='dark'/'light']` for an explicit override) —
  everything else (`--color-background`, `--color-surface`,
  `--color-primary`, ...) already derives from those via `var()`, so no
  component has (or should ever need) its own dark-mode CSS. If you add a
  new hardcoded color anywhere instead of a token, it silently breaks dark
  mode — grep for literal hex/rgb before assuming a new component is done.
  Theme state: `src/composables/useTheme.js` (module-level ref, same
  shared-singleton pattern as `useToast.js`, not Pinia), persisted to
  `localStorage['guratan_theme']`, applied by setting/removing
  `data-theme` on `<html>`. Toggle lives in `AppNavbar.vue`.
- **`CommandPalette.vue` added 2026-08-06** (MGA Fase 07,
  `components/shared/`) — Ctrl/Cmd+K opens a role-aware page-jump list
  (mirrors `AppNavbar`'s own `v-if` role checks — if you add a new
  role-gated route to the navbar, add it here too, they're not derived
  from a shared source) plus a "toggle theme" action. Own global `keydown`
  listener registered in the component itself (mounted once via `App.vue`
  — no store/composable needed since nothing else triggers it). Does
  **not** search entities (Projects, candidates, reports) by name/content
  — that would need a search endpoint that doesn't exist; deliberately
  scoped to page navigation only, per the original Fase 07 ask.

## Stack

Vue 3.5, vue-router 5, Pinia 4, axios 1.18, Vite 8. Lint: `eslint` +
`oxlint` (`npm run lint`), format via `prettier` (`npm run format`).

## API↔Web connection

- Base URL: `guratan-web/.env.development` → `VITE_API_URL`.
- CORS: `guratan-api/config/cors.php` reads `CORS_ALLOWED_ORIGINS` from
  `guratan-api/.env`, currently `http://localhost:5173` — matches Vite's
  default dev port. If you change the Vite dev port, update that env var too.
- Auth: Bearer token via Sanctum personal access tokens (not cookie-based SPA
  auth) — `supports_credentials` is correctly `false` on the API side for
  this reason.

## Not built yet

- No test setup found (no test runner in `package.json` scripts).
- `components/upload/` is intentionally absent (deleted 2026-08-01, Rapid
  tier retired) — don't recreate it without an explicit new user decision
  reversing that.
