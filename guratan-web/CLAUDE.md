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
  **Gained a 7th tab, "Peta Konsep", 2026-08-08 (KM-H)** — the final KM
  phase, purely read-only exploration (no create/edit/delete anywhere on
  this tab; that's still the other 6 tabs' job). Renders a new
  `src/components/admin/ConceptMapExplorer.vue`: 3 side-by-side columns
  (Sindrom → Aspek → Indikator, each level fetched only once its parent is
  clicked — `GET /admin/knowledge/concept-map`, then `.../aspek/{id}`,
  then `.../indikator/{id}`) plus a 4th "Relasi" detail panel. Indikator
  nodes show small badges ("N aturan", "N referensi") from `rules_count`/
  `cross_ref_count` so which ones are worth clicking is visible before
  drilling in. The detail panel lists the selected Indikator's operator
  rules (reads like `formatRule()` in the Indikator tab, duplicated
  locally rather than shared since this is a read-only display string, not
  form state) and its cross-references in **both directions** — "Referensi
  Silang Keluar" (this one triggers) and "Direreferensikan Oleh" (who
  triggers this one), the latter not shown anywhere else in the app.
  Clicking a relation chip calls `jumpToIndikator()`, which drives the
  SAME column selection state the user clicking through the columns would
  — the map genuinely navigates via its own cross-links, not just static
  text. **SVG connector lines between the selected node in each column**
  (a `<path>` per adjacent-column pair, quadratic Bezier computed from
  `getBoundingClientRect()` of template refs relative to the container,
  recomputed on every selection change and on window resize) are what
  make this read as an actual map rather than 3 unrelated list boxes —
  this is the one place in the app doing manual DOM-rect layout math,
  everything else here is plain CSS. No graph library was added; the
  layout is simple enough (max 3 edges visible at once) that pulling in
  d3/cytoscape/vis-network wasn't justified. Browser-verified against
  real KB data — see `guratan-api/CLAUDE.md`'s KM-H section for the full
  scenario (real rule + 3 real outgoing cross-references on `02-8a`,
  jump-via-chip confirmed, 2 connector lines rendered, zero console
  errors). **Fixed post-review, 2026-08-08**: `selectAspek()` didn't clear
  the previous Aspek's `aspekDetail` before awaiting the new fetch, so the
  prior Aspek's Indikator buttons stayed visible and clickable during the
  loading window, producing a Relasi panel inconsistent with the
  visibly-selected column. Fixed by clearing `aspekDetail` synchronously
  before the request starts.
  **"Referensi Silang" tab REMOVED 2026-08-19** (cross-reference unified
  into `indikator_rules`, see `guratan-api/CLAUDE.md`'s "Unifikasi cross-
  reference ke indikator_rules") — back to 6 tabs. Creating/managing these
  relationships now happens in the Indikator tab's existing "Aturan
  Operator" sub-section: a 3rd `rule_type` option, "Indikator Lain
  Tercentang", with a `depends_on_indikator_id` dropdown (reuses the same
  `indikatorOptions`/`loadIndikatorOptions()` fetch this removed tab used
  for its source dropdown — kept, not deleted). `formatRule()` gained a
  branch rendering it as "Tercentang jika Indikator {kode} tercentang".
  Browser-verified: added a real rule this way, confirmed it lists and
  deletes correctly, confirmed the tab itself is gone from the tab bar.
  **Gained a new 7th tab, "Kombinasi Temuan", 2026-08-22** — new component
  `components/admin/KombinasiTemuanManager.vue` (self-contained, own
  `<style scoped>`, same pattern as `ConceptMapExplorer.vue` rather than
  growing the already-1300+-line `AdminKnowledgeView.vue` further). Create
  form (nama/logika_gabung AND-OR/teks_interpretasi) + list with inline
  expand-to-edit (same convention as every other KM tab), each item's
  expanded panel has a nested "Syarat" builder: level dropdown
  (indikator/aspek/sindrom) drives which target dropdown shows
  (`indikatorOptions` from the existing `/admin/knowledge/indikator-options`
  endpoint reused as-is; `aspekOptions`/`sindromOptions` are its own fetches
  of the existing `/admin/knowledge/aspek` and `/admin/knowledge/sindrom`
  list endpoints — both already unpaginated, fine to reuse for a dropdown)
  and which `kondisi` options show (tercentang/tidak_tercentang for
  Indikator; low/medium/high/very_high — the same 4-bucket narasi_level
  already used elsewhere — for Aspek/Sindrom). See `guratan-api/CLAUDE.md`'s
  "Kombinasi Temuan" for the backend half and why this is a genuinely
  different mechanism from the cascade rule above (produces a NEW
  interpretation from a combination of conditions, not just extending the
  same evidence to another Indikator). `ReportDocument.vue` gained a
  "Pola Kombinasi Ditemukan" section rendering `data.kombinasi_ditemukan`
  (only present when a report has at least one match) at the end of the
  document, styled with its own gold-accent left border to read as a
  distinct finding type from the per-Aspek narasi above it.
  **Gained an 8th tab, "Topik", 2026-08-22** — pure tagging infrastructure
  (see `guratan-api/CLAUDE.md`'s "Topik (kategorisasi)" for why this ships
  with no consumer feature yet). Inline in `AdminKnowledgeView.vue` (not
  its own component — CRUD is as simple as the Sindrom tab: nama +
  optional deskripsi, no nested builder). The Aspek tab's existing edit
  panel gained a checkbox multi-select ("Topik") wired to
  `PUT /admin/knowledge/aspek/{id}/topik` (`aspekTopikSelection` ref,
  separate `saveAspekTopik()` call — deliberately NOT folded into the
  existing `saveAspek()`/`aspekEditForm`, since it's its own sync
  endpoint on the backend, not a plain field). `KombinasiTemuanManager.vue`
  got the identical widget for the same reason. Both list views show a
  small chip of tagged Topik names next to the existing count badges when
  any are tagged, so tags are visible without opening the edit panel.
  **Below is the original (now-removed) tab's history, kept for context:**
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
- `src/components/report/`: `TraitBar`, `ReportDocument`, plus 2 new ones
  added 2026-08-08 (report koreksi/edit, see `guratan-api/CLAUDE.md`'s
  "Koreksi laporan & riwayat versi" for the backend half):
  - **`ReportCorrectionPanel.vue`** - toggled by a "Koreksi Skor" button on
    `ReportView`. Reuses `SindromAccordion`/`AspekRow`/`ScoreSelector`
    UNCHANGED (fetches `GET /sindrom` itself, pre-fills `scores` from the
    report's existing `aspek_scores` prop) - same form as the original
    scoring flow, just pointed at `POST /samples/{id}/scores/correct`
    instead of `/scores`, with an added optional "alasan koreksi" textarea.
    **Gained a mode toggle 2026-08-17** (`correctionMode`, `'manual'` default
    | `'worksheet'`) mirroring `PortalGrafologView`'s step 2 exactly: worksheet
    mode swaps `SindromAccordion` for `MeasurementWorksheet` +
    `IndikatorChecklist` (both reused unchanged), `IndikatorChecklist`'s
    `apply` event merges into the same `scores` ref manual mode uses, so
    `buildSkorPayload()`/`submitCorrection()` needed zero changes. This is
    what lets a grafolog re-open the original measurement worksheet for an
    already-completed sample - `MeasurementController`/`ChecklistController`
    no longer block `status === 'completed'` (see
    `guratan-api/CLAUDE.md`'s 2026-08-17 entry) specifically to support this.
  - **`ReportRevisionHistory.vue`** - collapsible "Riwayat Perubahan"
    section, lazy-loads `GET /reports/{id}/revisions` on first open.
    Clicking a revision lazy-loads and renders its frozen snapshot using
    `ReportDocument` itself (read-only, `editable` not passed) - the same
    component doubles as both the live report view and the historical
    snapshot viewer.
  - **`ReportDocument.vue` gained an `editable` prop + inline edit**: each
    Aspek's narasi gets an "Edit narasi" link when `editable` is true
    (grafolog viewing their own report); clicking swaps it for a textarea,
    saves via `PATCH /reports/{id}/aspek/{kode}/narasi`. A manually-edited
    Aspek shows a "✏️ diedit manual" badge, driven by the API's
    `narasi_diedit_manual` flag - this flag (and the override text itself)
    disappears automatically the moment the report is regenerated via a
    score correction, so the badge is a reliable "this text is NOT what
    the KB would currently generate" signal, not just "was ever edited."
  - **`ReportView.vue`**: `report.aspek_scores` — **the API returns
    Eloquent relations in snake_case** (`aspekScores` relation method →
    `aspek_scores` JSON key), a real bug caught during browser
    verification (the correction panel silently never rendered because
    the template checked `report.aspekScores`, which is always
    `undefined`). If you add a new relation-backed field to a Vue
    component, check the actual JSON key Laravel serializes, don't assume
    it matches the PHP relation method's camelCase name.
  - Browser-verified end-to-end against real KB data (40 real aspek, not
    a test fixture): narasi override → badge appears → full score
    correction via clicking real score buttons in the reused
    `ScoreSelector` UI (not a direct API call) → response confirms new
    scores → ground-truth DB check confirms the override flag was cleared
    and revision history recorded both changes correctly.
  - **`ReportDocument.vue` gained `aspek.indikator_terkait` rendering,
    2026-08-17**: a small list under each Aspek's narasi (kode/nama/
    keterangan per checked Indikator) when the API includes it - see
    `guratan-api/CLAUDE.md`'s "per-Indikator narasi" entry. Renders nothing
    when the key is absent (manual-mode reports never have it), no
    `v-if` branching needed beyond the list's own `v-if="...?.length"`.
    Correction-panel's worksheet mode (above) is what actually changes
    this list's contents on an existing report.
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
  posts to `POST /api/samples/{id}/measurements`; **each variable gained
  optional min/max fields 2026-08-17** alongside the original point-value
  input, for irregularity rules that compare a selisih/range rather than a
  single reading — see `guratan-api/CLAUDE.md`'s range-mode entry) stacked above
  `IndikatorChecklist` (fetches `GET /api/samples/{id}/checklist`, ALL
  Sindrom→Aspek→Indikator rendered flat/expanded — **no accordion**, see
  the 2026-08-08 UX-feedback note below for why; each checked-by-rule/
  cascade Indikator shows an "Auto"/"Terkait" badge plus the trigger's
  `keterangan_pemicu` text inline in italic — this is the KM plan's
  explicit "grafolog must see WHY" requirement). **Both modes write into
  the exact same `scores` ref** that already fed
  `AutoCalculationPanel`/preview/submit — `IndikatorChecklist` never talks
  to those directly, it only emits an `apply` event (from its own
  "Terapkan Skor Checklist ke Form" button) that `PortalGrafologView`
  merges into `scores`, at which point the pre-existing debounced preview
  watcher and the submit button behave identically to manual mode with
  zero new code path. **That button click is a deliberate, one-time
  hand-off, not a live sync**, and — since a 2026-08-08 post-review fix,
  see below — it's also **disabled until a single "Saya sudah meninjau
  seluruh checklist di atas" checkbox is ticked** (`reviewedAcknowledged`).
  Checkbox clicks call `POST /api/samples/{id}/checklist/toggle`; when the
  backend responds with `requires_confirmation` (unchecking something
  that cascaded to still-checked targets), the component shows a plain
  `window.confirm()` listing the affected Indikator — no modal component
  exists in this codebase (same convention as every other KM view), and a
  yes/no with a short list didn't justify inventing one.
  `IndikatorChecklist` exposes its `load()` method via `defineExpose()`;
  `PortalGrafologView` holds a template ref to it and calls `.load()`
  whenever `MeasurementWorksheet` emits `saved` — so a newly-saved
  measurement's auto-checks appear immediately, no manual "Muat Ulang"
  click needed (see the 2026-08-08 UX-feedback note below). Browser-
  verified end-to-end against real KB data — see `guratan-api/CLAUDE.md`'s
  KM-G section for the full scenario (real category rule, real
  cross-reference cascade, submitted through the unchanged scoring
  endpoint to a completed report).
- **UX feedback, 2026-08-08** — user tested the worksheet flow and
  reported two things: entering a measurement didn't seem to auto-check
  anything (investigated live against their real sample - the checklist
  engine itself worked correctly, the actual cause was `indikator_rules`
  being empty in the dev DB, i.e. no administrator had authored any rules
  yet through the KM-E rule builder, not a code bug), and the
  per-Sindrom accordion added friction ("tidak perlu drawdown, langsung
  saja keluar semua"). Two changes followed: `IndikatorChecklist`'s
  accordion (`expandedSindrom`/`toggleExpand`) was removed entirely — all
  Sindrom now render as plain section headers with their Aspek/Indikator
  always visible, no click-to-expand — and the review-gate from the
  post-review fix above was simplified from "opened every Sindrom
  section" (which depended on the now-removed accordion) to the single
  acknowledgment checkbox described above. Separately,
  `MeasurementWorksheet`'s `saved` event (previously emitted but nothing
  listened to it) was wired up so the checklist auto-refreshes right
  after saving, addressing the "doesn't seem to auto-check" confusion
  even once rules do exist. Browser-verified: confirmed zero accordion
  toggle buttons render, and confirmed a real rule-matching measurement
  auto-checked its Indikator (with the correct "Auto" badge) immediately
  after clicking "Simpan Hasil Ukur", with no manual reload step.
- **Fixed post-review, 2026-08-08** — a review of the freshly-built KM-G/
  KM-H work found 2 frontend bugs (verified individually before fixing,
  alongside 5 backend ones documented in `guratan-api/CLAUDE.md`):
  - **`applyTally()` used to write a skor for all 40 Aspek unconditionally**
    (`tallyPerAspek` floors an untouched Aspek to skor 1, not 0) — so one
    click of "Terapkan Skor Checklist ke Form" made the submit button
    look complete even if the grafolog had reviewed only 2 of 40 Aspek.
    Originally fixed by requiring every Sindrom's accordion to have been
    opened at least once before the apply button enabled; that mechanism
    was replaced by the single `reviewedAcknowledged` checkbox once the
    accordion itself was removed (see the UX-feedback note above) — same
    intent (a real gate where there was none before, not a perfect
    guarantee the grafolog read every row), simpler mechanism.
  - **Declining the cascade-uncheck confirm dialog was silently broken** —
    `postToggle()`'s `also_uncheck_cascaded: []` looked identical whether
    the grafolog had declined or hadn't been asked yet (both are an empty
    array, and an empty array is truthy in JS so it was always sent),
    so clicking Cancel just re-triggered the same prompt and the checkbox
    snapped back to checked. Fixed by always sending an explicit
    `confirmed: true` on the follow-up call (see
    `guratan-api/CLAUDE.md`'s matching backend fix) regardless of the
    grafolog's answer — an empty cascade list now unambiguously means
    "confirmed, nothing to cascade," not "undecided."
  Both browser-verified with Playwright against real KB data: confirmed
  the apply button stays disabled after opening only 1 of 8 Sindrom with
  the correct "1/8" hint, and confirmed declining the cascade prompt
  (auto-dismissed dialog = Cancel) leaves the source Indikator unchecked
  while the cascaded target stays checked — the exact scenario that used
  to fail.
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
- **`NarasiTerpaduPanel.vue` added 2026-08-22** (`components/report/`,
  see `guratan-api/CLAUDE.md`'s "Narasi terpadu (laporan klien)" for the
  full backend picture) — bahasa dropdown (id/en), "Generate Draft AI"
  button (`POST /reports/{id}/narasi-terpadu/generate`), textarea, "Simpan
  sebagai Draft"/"Tandai Final" buttons (`PATCH /reports/{id}/narasi-terpadu`,
  the latter behind a `window.confirm()` since it immediately unlocks client
  visibility). **`ReportView.vue` now branches entirely on `auth.isClient`**:
  a client renders `report.narasi_terpadu` as a plain flowing document (the
  API response for that role never includes `data`/`aspek_scores` at all —
  nothing to hide client-side, it's just not there); everyone else
  (grafolog/admin/hr) gets `NarasiTerpaduPanel` plus the pre-existing
  breakdown (`ReportCorrectionPanel`/`ReportDocument`/`ReportRevisionHistory`)
  now under an explicit "Data Pengukuran (Internal)" heading. `narasi_status`
  badge (belum_dibuat/draft/final) drives a small colored pill in the panel.
  `ReportRevisionHistory.vue` gained a branch rendering the new
  `edit_narasi_terpadu` revision jenis as plain text (`revisionData[r.id]
  .narasi_terpadu`) instead of feeding it through `ReportDocument` (whose
  `{sindrom: [...]}` shape doesn't match this revision type's snapshot).
  **Gained a 4th status, `generating`, 2026-08-22** (generate moved to a
  queue job backend-side, see `guratan-api/CLAUDE.md`'s "Narasi terpadu -
  optimalisasi") — while that status is active, the panel disables its
  controls and polls `GET /reports/{id}` every 4s (plain `setInterval`,
  cleared `onUnmounted` and whenever the status leaves `generating` via a
  `watch`), no WebSocket/broadcasting needed given how infrequently this
  fires (one grafolog click, not real traffic). A 409 from `/generate`
  (dedup-guard — data unchanged since the last successful generate) is
  caught and turned into a `window.confirm()`; confirming resends the same
  request with `force: true`. `narasiGenerationError` prop (new) renders
  inline when a background generate failed, so the panel isn't just stuck
  looking idle with no explanation.

- **`PortalGrafologView` gained a token-balance warning, 2026-08-23** (see
  `guratan-api/CLAUDE.md`'s "UX gaps per persona" for the full picture of
  all 3 fixes across end user/grafolog/B2B). Loads `GET /tokens/wallet`
  once on mount (`wallet` ref, fails silently — non-fatal, the form still
  works without it), computes `tokensRequired`/`insufficientTokens` off the
  new `wallet.costs[tier]` field. A ⚠ banner + "Beli token" link
  (`RouterLink to="/token-saya"`) appears in step 1 (right after picking a
  tier, before creating the sample) and again in step 2 (alongside the
  existing "progres tidak tersimpan otomatis" warning) — the point is
  catching this BEFORE a grafolog spends time filling all 40 aspek, not
  just at the existing 402 on submit.
- **`RiwayatView.vue` gained client-aware status display, 2026-08-23** — for
  `auth.isClient`, the status badge now reads `report.narasi_status`
  (final → "Selesai", else → "Diproses") instead of `report.status`
  (breakdown-internal completion) — see the matching backend fix in
  `guratan-api/CLAUDE.md`. Staff (`auth.isClient === false`) unaffected,
  still shows `report.status` as before.

- **`AdminUsersView.vue` expanded + `AdminAuditLogView.vue` added, 2026-08-23**
  (see `guratan-api/CLAUDE.md`'s "Gap management ditutup" for the full
  backend picture and why — a user question "is management complete?" led
  to a real audit). The user table gained Perusahaan/Status columns and an
  "Ubah" button (staff accounts only — client accounts have their own flow
  and don't get this button) that expands an inline edit panel below the
  row (same expand-row convention as every KM tab — no modal component
  exists in this codebase): name/email/role, a company dropdown (shown only
  for role `hr`), an `is_active` checkbox (disabled when editing your own
  account, matching the backend's self-deactivation guard), and an optional
  password-reset pair. A brand-new **"Perusahaan" section** (create form +
  table with an activate/deactivate toggle) was added to the same page —
  this actually closes a bigger gap than originally scoped: `POST
  /api/admin/companies` existed since MGA Fase 06 but had **never had a
  frontend caller at all**, so an HR account (which requires `company_id`)
  could not really be created through the app; the company dropdown in the
  create-HR form now genuinely has options instead of being unreachable.
  **`AdminAuditLogView.vue`** (new, `/admin/audit-logs`, nav link "Log
  Audit" + `CommandPalette.vue` entry) — read-only paginated table
  (aksi/target/actor/IP/waktu) with a 400ms-debounced aksi search and a
  date-range filter, pagination pattern copied from the Indikator tab in
  `AdminKnowledgeView.vue`. First time any of the ~45 `AuditLog::record()`
  call sites across the backend are readable through the app at all.

- **Notification bell added to `AppNavbar.vue`, 2026-08-23** (see
  `guratan-api/CLAUDE.md`'s "Notifikasi/Pengumuman/Promo" for the full
  picture) — replaces `DashboardView.vue`'s old session-local dismiss
  banner entirely (that code is gone, not kept alongside). New
  `composables/useNotifications.js` (module-level singleton, same pattern
  as `useTheme.js`/`useToast.js`) holds `notifications`/`unreadCount`
  shared across the app since `AppNavbar` is mounted once globally. A bell
  icon (reuses `.app-navbar__theme`'s icon-button styling) shows a red
  badge with the unread count, opens a dropdown panel on click (closed by
  a document-level outside-click listener), and calls `markAllRead()` the
  moment it opens — one action, not per-item toggling, kept deliberately
  simple. `AdminAnnouncementsView.vue`'s intro copy updated to describe
  the new bell-based behavior instead of the removed dashboard banner.

- **`AdminUsersView.vue`'s Perusahaan table gained 4 stat columns,
  2026-08-23** (B2B Fase 1 — see `guratan-api/CLAUDE.md`'s matching
  entry) — HR/Kandidat/Selesai/Rata-rata Durasi, sourced from
  `CompanyController::index()`'s extended response. `toggleCompanyActive()`'s
  existing `Object.assign(company, data)` safely leaves these new fields
  untouched on a status toggle (the PATCH response doesn't include them,
  and `Object.assign` only overwrites keys present in its source).

- **`ReportView.vue` gained a Topik segment filter for HR, 2026-08-23**
  (B2B Fase 2 — see `guratan-api/CLAUDE.md`'s matching entry). When
  `auth.isHr` and at least one `Topik` exists (`GET /topik`), a "Filter
  Segmen Topik" checkbox panel appears above `ReportDocument`. Selecting
  1+ topics calls `GET /reports/{id}/segmen?topik_ids[]=...` and swaps
  `ReportDocument`'s `:data` to the filtered result (also forces
  `:editable="false"` while a filter is active — editing narasi against a
  filtered subset doesn't make sense, editing always happens against the
  full breakdown). Deselecting all topics reverts to `report.data`
  (`segmentedData` ref reset to `null`) — pure client-side view state, no
  server-side data is ever touched by this filter.

- **`AdminUsersView.vue`'s Perusahaan table gained a Kontrak column +
  expand panel, 2026-08-23** (B2B Fase 3 — see `guratan-api/CLAUDE.md`'s
  matching entry). Same expand-row convention as the existing staff edit
  panel on the same page: a "Kontrak" button toggles a panel below the
  company row showing contract history (`company.contracts`, eager-loaded
  from Fase 1's extended `CompanyController::index()`) plus a small
  create form (judul/status/tanggal mulai-berakhir/nilai opsional/catatan
  bebas). The main row's badge shows the latest contract's status
  (`badge--contract-draft/aktif/dihentikan`), plus a separate "Kadaluarsa"
  badge computed purely client-side when `status === 'aktif'` but
  `berakhir_at` has already passed — a display signal only, never writes
  back to the record.

- **3 halaman publik baru + footer global, 2026-08-30** (perlindungan
  data simpel + dukungan pelanggan — see `guratan-api/CLAUDE.md`'s
  matching entry for the 6 new `ContentBlock` keys behind these pages):
  `PrivacyPolicyView.vue` (`/kebijakan-privasi`), `TermsOfServiceView.vue`
  (`/ketentuan-layanan`), `HelpView.vue` (`/bantuan`) — all fully public
  routes, no `meta.requiresAuth`/`meta.role` (same as the landing page).
  Each fetches `GET /content` on mount for the handful of dynamic fields
  it needs (`support_*` for Help, `legal_entity_name`/
  `legal_contact_email` for the two legal pages) and otherwise renders
  static Indonesian copy inline in the component — these are NOT driven
  by `AdminContentView.vue`'s generic field-list rendering, they're
  purpose-built pages that happen to read a few CMS values, same
  fetch-with-graceful-fallback pattern as `LandingView.vue` (empty
  `try/catch`, page still renders fully minus the dynamic bits). New
  `components/layout/AppFooter.vue` (mounted once in `App.vue`, after
  `<main>`) links to all 3 pages and shows a copyright line with
  `legal_entity_name` appended only when non-empty. Pasal 2 "Bukan Alat
  Diagnosis Klinis" in `TermsOfServiceView.vue` was kept close to the
  original `legal/terms-of-service.md` draft's own instruction not to
  weaken this section for friendlier marketing language. **No AI-
  disclosure banner/section was added anywhere** — explicit user
  instruction, don't add one without a new decision. **No self-service
  data export/delete UI** — the Kebijakan Privasi's "Hak Anda" section
  points to `/bantuan` instead. Browser-verified: footer links resolve,
  both legal pages hide their Kontak section entirely when
  `legal_entity_name` is empty (default state), then after an admin fills
  the 6 new fields via `/admin/content`, footer/`/bantuan`/
  `/kebijakan-privasi` all reflect the new values on next navigation with
  zero console errors.

- **Google Analytics (GA4) wired up, 2026-08-30** — new
  `src/lib/analytics.js` (no npm dependency added, hand-rolled `gtag.js`
  injection, same minimal-dependency philosophy as `useTheme.js`/
  `useToast.js`): `initAnalytics()` called once from `main.js` after
  mount; `trackPageview()` called from a new `router.afterEach()` hook
  in `router/index.js` (pageviews must be sent manually per-navigation
  in a SPA — `gtag('config', ..., { send_page_view: false })` disables
  GA's own automatic pageview on script load). **Both functions are a
  total no-op when `VITE_GA_MEASUREMENT_ID` is empty** — no script tag
  injected, `window.gtag` never defined — so this ships safely with no
  real Measurement ID configured yet. New env var
  `VITE_GA_MEASUREMENT_ID` added to `.env.development` (empty by
  default, same "prepare the placeholder, don't invent the value"
  pattern as `legal_entity_name` in `guratan-api/CLAUDE.md`'s ContentBlock
  entry — an admin fills the real GA4 Measurement ID later; production
  needs its own env var set at deploy time, no `.env.production` exists
  in this repo). `PrivacyPolicyView.vue`'s "Berbagi Data ke Pihak Ketiga"
  section gained a bullet disclosing Google Analytics usage. **No
  cookie-consent banner was built** — explicit user instruction was
  "pasang saja" (just install it), no consent flow requested; revisit if
  a legal review later requires one. Browser-verified: confirmed zero
  GA script tags / `window.gtag === undefined` with the empty default
  key (true no-op, not just visually inert), and confirmed the privacy
  policy page renders the new disclosure text.

- **Grafolog registration moved to a verification flow, 2026-09-02** (see
  `guratan-api/CLAUDE.md`'s "Pendaftaran grafolog lewat verifikasi data"
  for the backend half). `RegisterView.vue` **lost its role dropdown** —
  it now only registers `role: user`, with a link to a new page for
  aspiring grafolog instead. New `RegisterGrafologView.vue`
  (`/daftar-grafolog`, public, `guestOnly`): biodata + a required document
  upload (certificate/membership card/whatever), posted as **`FormData`**
  (this codebase's first multipart form submission — every other POST is
  plain JSON via the shared `api` axios instance, which sets no
  `Content-Type` override so the browser fills in the multipart boundary
  itself with zero changes needed to `src/lib/api.js`). Unlike
  `RegisterView.vue`/`auth.register()`, submitting here does **not**
  authenticate you or redirect anywhere — the response carries no token,
  just a confirmation message, since the application sits in `pending`
  until an administrator reviews it.
  New `AdminGrafologApplicationsView.vue` (`/admin/grafolog-applications`,
  nav link "Verifikasi Grafolog" + `CommandPalette.vue` entry) — status
  filter (pending/approved/rejected/semua) + paginated list, same
  expand-row convention as every other admin table in this app (no modal
  component exists here). Expanding a row shows the applicant's phone/
  notes/review history, a "Lihat Bukti Profesi" button that fetches the
  document as a blob (`responseType: 'blob'`, same pattern as
  `ReportView.vue`'s PDF download) and opens it in a new tab via
  `window.open(URL.createObjectURL(...))`, and — only while `status ===
  'pending'` — Approve/Reject buttons. Reject has an inline optional-note
  text input rather than `window.prompt()` (no precedent for `prompt()`
  anywhere in this codebase; `IndikatorChecklist.vue` uses `confirm()` for
  a yes/no, this needed free text so it got its own inline field instead).
  Browser-verified end-to-end: confirmed `/register` no longer shows a
  role select, submitted a real application with an actual uploaded PNG
  (no token/redirect afterward), confirmed a second submission with the
  same email while the first was still pending was rejected, logged in as
  admin and opened the uploaded document from a fresh blob tab, approved
  the application, then logged in as the newly-created grafolog account
  successfully with the exact password submitted at application time.

- **Laporan/Rekap admin — Fase 1, 2026-09-03** (see `guratan-api/CLAUDE.md`'s
  matching entry for the full 4-phase plan and the backend half). New
  `src/lib/downloadBlob.js` — tiny shared helper (`downloadBlob(blob,
  filename)`, creates an object URL, clicks a hidden `<a download>`,
  revokes the URL) extracted because the exact same blob-download
  sequence that `ReportView.vue`'s PDF button already used inline is now
  needed 4+ times across the new recap pages (Fase 1's 2 pages, Fase 2's
  2 more) — worth a shared function past that point, per this codebase's
  own "3rd+ use" extraction bar (see `avgTurnaroundDaysFor()` in
  `guratan-api/CLAUDE.md`'s matching entry for the same reasoning applied
  backend-side). `AdminRecapUsersView.vue` (`/admin/recap/users`) and
  `AdminRecapGrafologView.vue` (`/admin/recap/grafolog`) — filter bar +
  paginated table + "Export CSV" button, scaffolding copied from
  `AdminAuditLogView.vue` (400ms-debounced search, date-range inputs,
  prev/next pager) with extra filters (role/status/company dropdown for
  Users; status only for Grafolog, since that page is always
  `role=grafolog`). The company filter dropdown reuses the exact
  `api.get('/admin/companies')` → `data.data` pattern already used by
  `AdminUsersView.vue`'s HR-creation form, not a new fetch convention.
  Export button calls the matching `/export` endpoint with
  `responseType: 'blob'` and the same filter params currently applied on
  screen (so what downloads always matches what's visible), then
  `downloadBlob()`. Nav links "Rekap Pengguna"/"Rekap Grafolog" +
  `CommandPalette.vue` entries. Browser-verified: filtered by role and by
  search text and confirmed the right seeded users surfaced, clicked
  Export CSV on both pages and confirmed a real file downloaded each
  time (not just a click with no result), 0 console errors.

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
