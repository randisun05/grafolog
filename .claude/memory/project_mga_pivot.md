---
name: project-mga-pivot
description: "2026-08-01 decision: Guratan pivots into 'Master Graphology Assistant' — 5-role B2B-heavy platform, Project entity, 7-phase roadmap"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-07T13:38:50.549Z
---

User requested a large refactor (not rebuild) from "Guratan" (B2C-leaning,
2 roles: user/grafolog) into "Master Graphology Assistant" — a
project-based, 5-role platform (Administrator, Grafolog, Supervisor,
HR/Company, Client) with new IA: Dashboard → Project → Assessment → Report.

**Why:** explicit user-driven strategic pivot toward B2B, delivered as a
structured brief asking me to act as PO + UX + Architect simultaneously.
This **supersedes** the earlier "B2B is post-MVP, don't build it yet"
principle in [[project_overview]] — it's the user's own explicit new call,
not scope drift. Don't push back citing the old MVP-first principle; it no
longer applies to B2B specifically (still applies to not over-building
speculative features beyond what's now in scope).

**How to apply:** [[reference_roadmap]] (ROADMAP.md at project root) has a
"Pivot — Master Graphology Assistant" section with the full 7-phase roadmap
and 3 locked product decisions. Check that section before assuming Guratan's
old 2-role/no-Project shape is still current.

**3 product decisions locked 2026-08-01** (don't re-ask, don't silently
reverse):
1. Rapid tier (free/CV) is retired — see the "Superseded" note in
   [[feedback_guratan_principles]] item 2. Old Rapid samples/reports stay
   viewable; no new Rapid uploads.
2. Project : Assessment = 1 : many (one Project, e.g. an HR recruitment
   batch, holds multiple Assessments/candidates). Not 1:1.
3. Administrator/Supervisor accounts: seeded at bootstrap, then an
   already-logged-in Administrator creates further Admin/Supervisor accounts
   via an in-app panel. No public self-registration for these roles.

**Process note:** before touching any code for this refactor, git was
initialized in both repos (neither had version control before) with a
baseline commit — `guratan-api@9dcca3d`, `guratan-web@c30d5e2` — specifically
so this large refactor has rollback points per phase. If asked why git
exists now when it didn't before, this is why.

**Roadmap phases** (full detail in ROADMAP.md, don't duplicate here since
it drifts fast during active work):
01 navigation/menu (frontend only, no migration) → 02 Project entity
migration (the load-bearing one — everything above depends on this table's
shape) → 03 role-based dashboards → 04 Assessment Workspace 3-panel
(highest UX-regression risk, reuses existing scoring components) → 05
Administrator/Supervisor roles + auth middleware → 06 HR entities
(Company/Candidate/Assignment/Billing — mostly new code, not refactor) → 07
polish (command palette, dark mode).

**Fase 01 done 2026-08-01** (`guratan-api@ccc59ca`, `guratan-web@44de157`):
Rapid tier creation blocked at `StoreSampleRequest` (422 on `tier: rapid`),
dead controller/view code removed (`SampleController::generatePlaceholderRapidReport`,
`UploadView`, `HasilRapidView`, `UploadDropzone`). Old rapid data untouched.

**Fase 02 done 2026-08-01** (`guratan-api@7f25426`): `App\Models\Project`
added — `projects` table (`name` nullable, `source` enum
grafolog/hr/client, `created_by`), `handwriting_samples.project_id`
(nullable FK). **Important nuance**: `source` is a separate axis from
`tier` — don't confuse them. `tier` (comprehensive/master) stays exactly as
before and still drives DOKU pricing; `source` only records who initiated
the project. `SampleController::store` creates exactly one `Project` per
new sample (schema supports many samples per project for Fase 06's HR
bulk-candidate case, but no UI adds a second sample to an existing project
yet — that's still ahead). Backfill verified against the real dev DB before
running tests: 8 pre-existing samples → 8 projects (6 grafolog, 2 client),
zero left with a null `project_id`. 49/49 tests passing.

**Process precedent for Fase 03+**: before any schema-changing phase, check
the real dev DB state first (`php artisan migrate:status`, inspect row
counts) — the backfill logic for Fase 02 was verified against actual data,
not just the sqlite test DB, before trusting it. Keep doing that for
Fase 04+ and beyond.

**Fase 03 done 2026-08-01** (`guratan-api@8d5dce2`, `guratan-web@2fe7362`):
`GET /api/dashboard` (role-aware KPI + 5-item activity feed) +
`DashboardView.vue`, now the post-login landing page. Scope was narrowed
from the original wireframe on purpose — no chart, no quick-actions, user
agreed to defer those. `RiwayatView` was **not** replaced, it's still there
as the detailed list; Dashboard is the new summary layer on top.

**No browser tool available in this environment** — UI changes (Fase 01
navbar edit, Fase 03 DashboardView, Fase 04 Assessment Workspace) were
verified via `npm run build`, `npm run lint`, and real HTTP round-trips
(register -> login -> call the real endpoint with the real token) to prove
API contracts, but never actually click-rendered in a browser. Say so
explicitly if asked whether the UI was visually verified — it wasn't,
every time, until the user confirms otherwise or a browser tool becomes
available.

**Fase 04 done 2026-08-03** (`guratan-api@bfcf44a`, `guratan-web@8439ea6`):
`POST /api/samples/{sample}/scores/preview` (`ScoringController::preview`)
reuses `ScoringEngineService::generate()` completely unchanged — it already
tolerated partial input, so no service code needed to change, only a new
controller action + lightweight `PreviewScoresRequest` (no completeness
check, unlike `SubmitScoresRequest`). Frontend: `PortalGrafologView`'s "Isi
Skor" step became a 3-column Assessment Workspace (info | existing
`SindromAccordion` form, untouched | new `AutoCalculationPanel`, live via a
500ms-debounced watcher). `SindromAccordion`/`AspekRow`/`ScoreSelector`
were **not modified** — only repositioned, per the user's explicit
reuse-first instruction. 56/56 tests passing; additionally verified via a
real end-to-end HTTP chain (register grafolog+client -> create sample ->
call preview with partial scores) against the live dev DB, confirming
correct grouped/averaged output and zero rows persisted.

**Deferred from Fase 04 on purpose** (flagged in `guratan-web/CLAUDE.md`,
not forgotten): splitting `PortalGrafologView` into 3 separate routes
(`/grafolog/clients`, `/grafolog/projects/new`, a standalone workspace
route) as the original plan envisioned. Current `PortalGrafologView` still
owns all 3 steps as one component. Revisit only if Fase 05 role work
actually needs separate URLs for these steps — don't do it speculatively.

**Recurring dev-environment gotcha, hit again during Fase 04 verification**:
MySQL (`mysqld`) was not running mid-session even though it had been
running earlier in the same conversation — don't assume it's still up
just because it was checked once. When starting it manually
(`mysqld.exe --defaults-file=...`), Laragon's own watchdog may have
already respawned it in parallel, producing two `mysqld.exe` processes;
only kill the one NOT bound to port 3306 (check via
`Get-NetTCPConnection -OwningProcess <pid>` first) — same
verify-before-kill discipline used earlier in this project for duplicate
php.exe/node.exe dev-server processes.

**Same gotcha got much worse during Fase 05** (2026-08-03): `mysqld` died
completely silently multiple times in one session — no crash log, no
warning, sometimes minutes after being confirmed up — most likely because
free RAM on this machine was observed as low as ~4GB with the full dev
stack running (php, node/vite, VSCode's PHP language servers, the user's
own browser). Plain shell `&`/`disown` backgrounding was also observed
letting `php artisan serve` die silently between tool calls with zero log
output. **Fix that actually stuck**: use the harness's own background-task
launcher (the `run_in_background` option) for `mysqld`/`php artisan
serve`/`npm run dev` instead of shell-level backgrounding, and always poll
for readiness (`until curl ... ; do sleep 1; done` / repeated `mysql -e
"SELECT 1"`) rather than a fixed `sleep` — cold MySQL InnoDB init alone can
take ~30s. Full detail in `guratan-api/CLAUDE.md`'s "Local dev environment
note".

**Fase 05 done 2026-08-03** (`guratan-api@5681f9a`, `guratan-web@2c0ea8d`):
`users.role` expanded to 4 values (user/grafolog/administrator/supervisor)
via a driver-aware migration (raw SQL for real MySQL, since doctrine/dbal
still isn't installed; `Schema::table()->change()` for the sqlite test DB,
which Laravel handles natively there). New generic `role:<role>` middleware
(`EnsureUserHasRole`) for future role-gated routes — old
`isGrafolog()`-based inline checks elsewhere were deliberately left alone.
Provisioning matches the locked decision exactly: `AdministratorSeeder`
creates one bootstrap admin from `ADMIN_EMAIL`/`ADMIN_PASSWORD` (skips if
unset), then `POST /api/admin/users` lets a logged-in Administrator create
further admin/supervisor/grafolog accounts; public register still rejects
those two roles (test-enforced). Frontend: `/admin/users` (`AdminUsersView`),
gated the same way `/portal-grafolog` is gated for grafolog. 64/64 backend
tests passing.

**Deferred from Fase 05 on purpose**: the full admin panel (Users done,
but Master Data editor and cross-project Reports view are not built), and
all Supervisor-specific functionality (a review queue was mentioned in the
original plan, but what a supervisor actually reviews was never decided —
the role exists and is assignable, nothing else). Don't build supervisor
screens speculatively.

**First real browser verification in this project's history**, done for
this phase's `AdminUsersView`: no `chromium-cli` available in this
session, so used Playwright directly (`npx playwright install chromium`,
then a throwaway `.mjs` script in the scratchpad dir using
`chromium.launch()` — NOT `_electron`, this is a regular web app not
Electron). Confirmed via screenshot + `console --errors`-equivalent
(page.on('console')/'pageerror' listeners): login as bootstrap admin
worked, `/admin/users` rendered the form+table, creating a grafolog
account showed a real success toast and the new row appeared, zero
console/page errors. If this kind of verification becomes routine, it's
worth running `/run-skill-generator` to turn the ad-hoc script into a real
project skill (`.claude/skills/run-*`) — wasn't done this time since it
was a one-off. **Reused successfully again in Fase 06** (two browser
contexts, full HR→assignment→grafolog loop) — still ad-hoc, still worth
formalizing into a skill if a third use case comes up.

**Fase 06 done 2026-08-06** (`guratan-api@992dc62`, `guratan-web@189e2c1`)
— the biggest MGA phase, mostly new backend, not refactor. Adds the last
of the 5 planned roles:

- `hr` added to `users.role` (now all 5 roles from the original plan
  exist: administrator/grafolog/supervisor/hr/user). `companies` table +
  `users.company_id`. HR provisioning reuses Fase 05's exact mechanism —
  `POST /api/admin/users` with `role: hr` now also requires `company_id`
  (company created first via `POST /api/admin/companies`, administrator-
  only). No new provisioning pattern was invented.
- **Key design decision, don't re-litigate**: "Candidate" is NOT a new
  table. A candidate is a regular `User` (`role: user`, `company_id` set).
  This was the highest-leverage reuse call in the whole MGA pivot — it
  means CSV-imported candidates flow through `Project`,
  `HandwritingSample`, `ScoringEngineService`, `ReportController`,
  `ReportView.vue`, PDF export, and email notifications with **zero**
  changes to any of them.
- `CandidateImportController` (`POST /api/hr/candidates/import`): CSV
  parsed with native `fgetcsv`, no new composer/npm dependency. Creates
  one `Project` (`source: hr`) + one `HandwritingSample` per row, all-or-
  nothing (one bad row fails the whole import, nothing partial commits).
- `assignments` table (`App\Models\Assignment`, one row per sample) is the
  mechanism that separates "HR owns this candidate" (`created_by`) from
  "this grafolog does the work" (`assignment.grafolog_id`) — these can now
  be different people, which the original Fase 01-05 flow never needed
  (the grafolog who created a sample was always the one who scored it).
- **Authorization pattern**: rather than rewrite the existing
  `created_by === user.id` checks scattered across `ScoringController`/
  `SampleController`/`ReportController`, added two `HandwritingSample`
  helper methods — `isScorableBy()` and `isViewableBy()` — that OR the old
  check with a new assignment check, and swapped the controllers to call
  them. Zero behavior change for the pre-Fase-06 flow, assignment access
  is purely additive. This is the pattern to reach for again if a 6th
  access path is ever needed — don't hand-roll another inline OR chain.
- Frontend: `HrCandidatesView` (upload + inline assign), `AssignedToMeView`
  (grafolog's "things to score" list, covers both self-created and
  HR-assigned since the backend query already unions both), and
  `PortalGrafologView` gained a `?sampleId=` resume path — without that,
  an HR-assigned sample would have been created and assignable but
  literally unreachable in the UI, since the only way `sample` ever got
  populated before was the lookup-then-create step.
- 92/92 backend tests. Verified twice end-to-end: a pure-HTTP curl chain
  (admin→company→HR→CSV import→assign→grafolog submits→report generated,
  assignment auto-flips to completed) and a full Playwright browser run
  across two login contexts (HR and grafolog) with a screenshot at every
  step, zero console errors.

**Deferred from Fase 06 on purpose**: Billing/Subscription for company
plans (no pricing model has been decided — this needs a business decision
from the user, not a technical one; don't build a `Subscription` table
speculatively when asked to continue the roadmap). Also still not built:
admin-facing Master Data editor, cross-project Reports view, and anything
Supervisor-specific (unchanged from Fase 05 — role exists, nothing uses
it).

**Fase 07 done 2026-08-06** (`guratan-web@f7a707c` — pure frontend, no
backend commit this phase): dark mode + command palette, the last of the
original 7-phase roadmap. Dark mode redefines only the base color tokens
(`--color-ink/paper/seal/sage` + soft/dark variants) — every derived token
(`--color-background`, `--color-primary`, ...) already referenced those
via `var()`, so the whole app repainted correctly with zero per-component
dark rules needed. Three-way (system/light/dark) via `useTheme.js`
(module-level ref + localStorage, same pattern as `useToast.js`).
Command palette (`Ctrl/Cmd+K`) is page-navigation only — a role-aware list
mirroring `AppNavbar`'s own `v-if` checks, not entity search (no search
endpoint exists for Projects/candidates/reports; don't assume ⌘K reaches
those without building that first). Verified with a full Playwright run:
toggled dark mode, confirmed the `data-theme` attribute and visual repaint
via screenshot, reloaded to confirm localStorage persistence, opened the
palette, filtered "staf", hit Enter, landed on `/admin/users` with the
palette auto-closed, reopened and confirmed Escape also closes it. Zero
console errors.

**THE 7-PHASE MGA ROADMAP IS NOW FULLY COMPLETE (2026-08-01 → 2026-08-06,
all in one continuous work session).** If a future session is asked "what's
left on the MGA pivot" or similar, the honest answer is: the phased
roadmap itself is done — what remains is the stuff that was deliberately
deferred at each phase (listed below), which was never scheduled work, it
was explicitly out-of-scope pending a further decision or request:

1. **Billing/Subscription for company plans** (Fase 06) — blocked on a
   pricing-model decision from the user, not technical.
2. **Admin Master Data editor + cross-project Reports view** (mentioned in
   the original Fase 05 plan, never built — Fase 05 only shipped user
   management).
3. **Supervisor role has zero functionality** (Fase 05) — assignable, no
   review queue, no dedicated view. What a supervisor actually reviews was
   never decided.
4. **`PortalGrafologView` is still one component doing all 3 steps**
   (Fase 04 deferred the plan's idea of splitting it into 3 routes:
   `/grafolog/clients`, `/grafolog/projects/new`, a standalone workspace).
5. **Rapid tier's CV/automated scoring** — not deferred, actually retired
   (Fase 01, see [[feedback_guratan_principles]]'s superseded note). Don't
   confuse this with the others; it's not "not yet built", it's "decided
   against."

Before picking any of these up, don't just start building — confirm
which one the user actually wants next, since #1 and #3 both still need a
product decision before code makes sense, not just an engineering pass.

**Full planning artifact** (sitemap, wireframes, per-role menu tables,
component keep/remove/new audit, mermaid user flows) was published as a
Claude Artifact during the session that made these decisions — not saved to
the repo. If deep visual/structural detail is needed and the artifact link
is stale, regenerate from ROADMAP.md's phase descriptions plus the actual
current code rather than assuming the old artifact content still applies.

**Post-roadmap gap closed 2026-08-07** (`guratan-api@3d92be9`,
`guratan-web@cb2f186`): user asked "is there a feature for grafolog to
register a client" — verified there wasn't (confirmed via code, not
assumed): `GET /api/users/lookup` only found *existing* clients, 404'd
with no path forward otherwise, and `AdminUserController` explicitly
excludes `role: user` by design comment ("stays via public
`/auth/register`"). Added `POST /api/clients` (grafolog-only) so a
walk-in client who hasn't self-registered can be created on the spot from
`PortalGrafologView`'s step 1. Deliberately does **not** reuse
`AuthController::register` (would swap the grafolog's own Sanctum session
for the new client's) and does **not** email an invite (`MAIL_MAILER` is
still `log`, no real SMTP — see [[project_overview]]) — instead an
optional password field, or a server-generated one shown once in the
response for the grafolog to relay directly. This is a small gap-fix, not
a new roadmap phase; doesn't reopen or extend the 7-phase roadmap above.

See [[project_overview]] for pre-pivot product context (tiers/pricing
mostly still apply), [[project_status]] for pre-pivot MVP build state
(scoring engine, auth, payment backend — none of this is being rebuilt,
only wrapped/reorganized).
