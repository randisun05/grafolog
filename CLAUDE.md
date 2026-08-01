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

## Structure (actual, as of 2026-07-26)

- `src/views/`: all 9 originally-planned views exist — `LandingView`,
  `LoginView`, `RegisterView`, `UploadView`, `HasilRapidView`, `RiwayatView`,
  `ReportView`, `PortalGrafologView`, `NotFoundView`.
- `src/components/report/`: `TraitBar`, `ReportDocument`.
- `src/components/scoring/`: `AspekRow`, `ScoreSelector`, `SindromAccordion`.
- **Built 2026-07-27** (were missing as of 2026-07-26, now done):
  - `components/layout/AppNavbar.vue` — extracted verbatim from `App.vue`'s
    inline header (no behavior change), mounted there via `<AppNavbar />`.
  - `components/upload/UploadDropzone.vue` — drag-and-drop file picker with
    preview, `v-model="file"`; replaces the old plain `<input type="file">`
    in `UploadView`.
  - `components/shared/LoadingSpinner.vue` — wired into `RiwayatView`,
    `ReportView`, `HasilRapidView` (replacing plain "Memuat..." text).
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
    was done this session; if something looks off visually, that's why.
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
  `meta.guestOnly` (redirect authenticated users to `riwayat`), and
  `meta.role` (redirect if `auth.user.role` doesn't match, e.g.
  `/portal-grafolog` requires `role: 'grafolog'`).
- `src/assets/base.css` + `main.css`: design tokens (`--color-ink`,
  `--color-seal`, `--color-sage`, `--font-heading: Fraunces`, `--font-body:
  Inter`, `--font-accent: Caveat`) plus shared `.btn`/`.error`/form-input
  styles. Confirmed applied — this was a previously-reported bug, now fixed.

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

- `components/layout/`, `components/upload/`, `components/shared/` (see
  above).
- No test setup found (no test runner in `package.json` scripts).
