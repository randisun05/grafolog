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
  `AssignedToMeView`, `HrCandidatesView`, `NotFoundView`.
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
  `AutoCalculationPanel` (added 2026-08-03, MGA Fase 04).
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
  `--color-seal`, `--color-sage`, `--font-heading: Fraunces`, `--font-body:
  Inter`, `--font-accent: Caveat`) plus shared `.btn`/`.error`/form-input
  styles. Confirmed applied — this was a previously-reported bug, now fixed.
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
