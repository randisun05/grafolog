---
name: Matriks keterkaitan & ciri bersama
description: 2026-10-16 — tab Matriks admin KB, ciri bersama (indikator bernama sama tersinkron), fix latch kaskade, korelasi empiris
type: project
---
Built 2026-10-16 after research artifact (https://claude.ai/artifact/2Uay898mXSvsZGMMhuod6M).
User said "kerjakan semua" to all 4 proposals.

- Checklist engine now least-fixed-point: cascade/ciri_bersama rebuilt from scratch each evaluate (fixed latch where B stayed checked after A cleared). Kept cascade candidates on confirmed uncheck → frozen manual.
- Ciri bersama ON by default (`CHECKLIST_CIRI_BERSAMA`), sumber enum 'ciri_bersama'. Changes NEW report scores. Grafolog has NOT yet confirmed same-name = identical sign — open item.
- `/admin/knowledge/matrix` + `/matrix/empiris?min_n=` ; frontend KnowledgeMatrixExplorer.vue.
- Key KB facts: 363 rules (106 measurement, 257 indikator_checked all forward-only), 16/34 variables used, 308/704 indikator have rules, 160 same-name groups, 224 unlinked pairs.

**Why:** source Excel wrote cross-refs one direction only; score depended on click order.
**How to apply:** don't add reverse indikator_checked rules to "fix" direction — causes latching; ciri bersama handles it.
