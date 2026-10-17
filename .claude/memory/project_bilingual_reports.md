---
name: project-bilingual-reports
description: "2026-10-15: reports in Indonesian + English; AI draft translation of 40 aspek narasi in terjemahan_id.json, per-language PDF/web/narasi edits"
metadata:
  node_type: memory
  type: project
  modified: 2026-10-15T18:00:00.000Z
---

User asked for an Indonesian version so reports can be downloaded in 2
languages. The KB had no Indonesian text at all, so I (Claude) translated all
40 aspek (nama, keterangan_umum, 4 narasi levels; ~28k words) + 8 sindrom names
into database/seeders/data/terjemahan_id.json, seeded into *_id columns by
TerjemahanIdSeeder. Marked DRAFT - must be reviewed by a grafolog (editable in
admin KB Aspek tab, EN/ID side by side).

Report data stores narasi_en + narasi_id; ReportLocalizer serves ?lang=id|en
for web and PDF; manual narasi edits are per language. Details in
guratan-api/CLAUDE.md "Laporan dua bahasa".

**How to apply:** if the user asks about translation quality, the draft is
mine - point them to the admin KB to correct, and fix wording in the JSON too
so reseeds keep it. Indikator keterangan (704) still English-only. Source
anomalies found while translating (aspek 08 levels look shifted, 09 missing
medium/low, 21 low duplicated paragraphs, 37 high truncated sentence) were
translated as-is and reported to the user - don't silently "fix" them.
