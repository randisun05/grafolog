---
name: project-kb-content-gaps
description: "Known gaps in the grafologi_knowledge_base.json indikator.keterangan field - tracked pending user discussion, don't fill in without asking"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-17T05:35:21.399Z
---

Tracking table for known content gaps in Indikator.keterangan (per-
indikator interpretation text, see [[project_range_mode_scoring]] for why
this field now matters - it surfaces in reports as aspek.indikator_terkait
for checked Indikator).

**Resolved (2026-08-17):** 35-1b and 35-7b were empty - user supplied
the text (same as their a-variant siblings 35-1a/35-7a, a common
pattern where a/b/c variants at the same posisi share one interpretation).
Filled directly in grafologi_knowledge_base.json (source, not the
seeder) and re-seeded into dev DB.

**Deferred, pending discussion - do NOT fill without asking:** 30 more
empty keterangan rows, all in **Aspek 35 "Fears"**, all -dupN suffixed
codes representing degree/intensity variants of one measured posisi (e.g.
posisi 7a "d-stem width" has base variant 35-7a = "narrow" with real
text, then 35-7a-dup2 = "moderate", -dup3 = "broad", etc. - all empty).
Full list of 30 kodes and their base variants is reproducible via:
Indikator::whereNull('keterangan')->orWhere('keterangan','')->get() (all
30 will be under Aspek 35 once 35-1b/35-7b stay fixed).

**Why:** unlike 35-1b/35-7b (a straightforward "same text as sibling"
answer), these need genuinely distinct text per intensity level (e.g.
"narrow" vs "extremely broad" d-stem width plausibly mean different
things), which the user needs to look up/write themselves - not something
to guess or copy from the base variant. User explicitly asked to set this
aside as a task for later ("tunda dulu, masukkan ke task yang perlu
didiskusikan"), 2026-08-17.

**How to apply**: if the user brings up Aspek 35, "Fears", KB completeness,
or these specific kodes again, this is the context - don't re-derive the
list from scratch, and don't fill any of the 30 in without the user
supplying the actual text (same rule that applied to 35-1b/35-7b).
Scoring itself is unaffected either way (posisi-level tally doesn't care
which degree-variant is checked) - this only affects whether a report
shows an interpretation paragraph or just kode+nama for that specific
checked Indikator.
