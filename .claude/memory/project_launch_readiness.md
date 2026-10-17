---
name: project-launch-readiness
description: "2026-10-03: full role simulation for public launch; fixed grafolog self-register hole, B2C photo+admin-assign flow, supervisor QA review, Master consultation, password reset, PDP consent, DOKU webhook bugs, per-role dashboards"
metadata:
  node_type: memory
  type: project
  modified: 2026-10-03T15:30:00.000Z
---

User asked whether Guratan is ready to go public; I ran an end-to-end API +
Playwright simulation of every role. Verdict then: not ready. User said
"DOKU belum saya lanjutkan, yang lain perbaiki" and chose (AskUserQuestion):
B2C orders assigned by admin; Master consultation = grafolog sets time +
https meeting link; Supervisor = QA review gate before client/HR sees a
report; Indonesian narasi translation deferred.

Built (details: guratan-api/CLAUDE.md "Kesiapan publik", guratan-web/CLAUDE.md,
ROADMAP "Kesiapan Publik"): public register is user-only; consent fields
(UU PDP); private sample_images + audit; /admin/orders; review_status on
reports (approve/request_revision, edits send back to review, email on
approval); sample_consultations; forgot/reset password with its own named
rate limiter; DOKU paid-is-final + 503 on HTTP errors; role dashboards;
Sanctum 30-day expiry; oxlint pinned for npm ci. 401 backend tests;
re-simulation 43/43.

**How to apply:** don't reopen grafolog self-registration or let any edit
bypass review_status. Remaining launch blockers are user-owned: DOKU creds,
legal review of legal/*.md placeholders, narasi translation (KB is English
with "He"), production deploy/SMTP/cron/queue, retention & refund policy.
Simulation scripts lived in session scratchpad (not in repo).
