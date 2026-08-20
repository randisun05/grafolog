---
name: reference-roadmap
description: "Where the ordered Guratan/MGA work backlog lives — ROADMAP.md at project root, checkbox-tracked, Fase 0-7 all complete as of 2026-08-06"
metadata: 
  node_type: memory
  type: reference
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-06T10:05:51.888Z
---

The ordered task list lives in `d:\Project\grafolog\ROADMAP.md`, created
2026-07-26. Original pre-pivot phases (all complete):
- Fase 0: quick finishers (SMTP wiring, a 500-vs-401 auth bug)
- Fase 1: MVP hardening (controller test coverage, missing frontend
  components)
- Fase 2: production readiness (payment gateway, ToS/Privacy Policy,
  deployment)
- Fase 3: partially absorbed into the MGA pivot below (see
  [[feedback_guratan_principles]] for what got retired vs. carried forward)

**2026-08-01 the project pivoted to "Master Graphology Assistant" (MGA)** —
see [[project_mga_pivot]] for full detail. ROADMAP.md grew a second
"Pivot — Master Graphology Assistant" section with its own Fase 01-07
(different numbering scheme from the Fase 0-3 above, don't conflate them).
**All 7 MGA phases were completed 2026-08-01 → 2026-08-06.** What's left is
only the specific items each phase deliberately deferred (Billing/
Subscription, admin Master Data/cross-project Reports, Supervisor
functionality, PortalGrafologView route-splitting) — listed in full,
with why each was skipped, in [[project_mga_pivot]].

**Why:** the user asked for a persistent ordered work list to follow across
sessions, not an ephemeral todo.
**How to apply:** when the user asks "what's next," the phased roadmap
itself is done — check [[project_mga_pivot]]'s deferred-items list first
rather than assuming there's a queued next phase. Re-read ROADMAP.md
directly if unsure, don't trust this summary's snapshot for anything
time-sensitive.

See [[project-status]] for the pre-pivot code-verified state, [[project_mga_pivot]] for everything since.
