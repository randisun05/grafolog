---
name: project-report-editing
description: "2026-08-08: Report correction/manual-edit feature for Guratan - #2 of a 4-part idea (score correction+regen, manual narasi edit, topic categorization, client chat) built; categorization and chat still pending, chat conflicts with the 'no live LLM' principle and needs its own product discussion"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-09T05:17:55.377Z
---

User asked (2026-08-08) about developing the report feature further, which
turned out to be 4 distinct ideas bundled together:

1. **Score correction + regeneration** after a report is already completed.
2. **Manual narasi text edit** directly, bypassing the KB.
3. **Topic categorization** of Indikator/Sindrom (career, love, personality,
   etc.) so a future feature can look up relevant content by topic.
4. **Interactive client chat** grounded in the report, to discuss results.

I broke these down and recommended starting with #3 (categorization,
smallest/safest, no LLM involved) since it's groundwork #4 would need
anyway. **User explicitly chose to do #2 first instead** ("#2 dl") - #1 and
#2 turned out to be one combined feature once I asked clarifying questions
(user wanted BOTH score-correction-triggers-regeneration AND a separate
direct-manual-text-edit capability, not just one or the other).

**#3 (categorization) and #4 (chat) are NOT built.** #4 in particular
should NOT be started without a dedicated product conversation first - it
directly conflicts with the hard constraint already locked into this
project ("LLM dipakai seminimal & se-defensif mungkin, cache bukan
panggilan live per-user, demi biaya DAN privasi" - see
[[feedback_guratan_principles]]). A chat feature is inherently live,
per-user, per-message LLM calls that can't be cached the way
NarasiCacheService caches narasi (which is identical for everyone in the
same score band) - real cost and privacy implications (sending a full
psychological profile to an LLM API repeatedly) that need explicit
sign-off, not something to build incrementally alongside other features.

**What got built for #1+#2 (2026-08-08, see guratan-api/CLAUDE.md's
"Koreksi laporan & riwayat versi" and guratan-web/CLAUDE.md's
src/components/report/ entry for full technical detail):**

- Two design decisions confirmed via AskUserQuestion before building
  (correction is a sensitive, production-data feature - didn't want to
  guess): (a) build BOTH score-correction+regen AND direct manual-text-
  override as separate features, not just one; (b) the owning grafolog can
  correct without extra approval (no Supervisor gate - Supervisor still
  has no function anywhere in this app), and old versions are preserved
  for audit (not silently overwritten) - matches this project's existing
  "sensitive psychological data needs audit log" principle.
- report_revisions table is the SINGLE audit mechanism for both features
  (not two separate systems) - snapshots PersonalityReport.data before
  any change, tagged koreksi_skor or edit_manual.
- ScoringController::correct() is the literal inverse of submit() -
  submit() blocks completed samples, correct() requires that state.
  Reuses the same full-40-aspek validation. Does NOT re-charge tokens
  (correcting an already-paid-for report, not generating a new one).
- Manual narasi override lives directly in the report's JSON data (not a
  separate table) with a narasi_diedit_manual flag - and that flag/text
  is DELIBERATELY wiped the moment a score correction regenerates the
  report from the KB. This was a real design call: KB-derived content is
  trusted over a possibly-now-stale manual override once the underlying
  scores change.
- Real bug caught during browser verification (not by tests, which used
  the correct field name from the start): the frontend referenced
  report.aspekScores (camelCase, matching the Eloquent relation method
  name) but Laravel actually serializes relations as snake_case
  (aspek_scores), so the correction panel silently never rendered. Fixed
  in ReportView.vue. If a future relation-backed field goes missing
  silently in the Vue layer, check snake_case vs camelCase first.
- 355 backend tests (up from 341, KM system's final count).

**How to apply**: if the user references "koreksi laporan" or asks why a
report can/can't be edited after completion, this is the feature. If asked
to build the categorization or chat pieces, treat them as genuinely new
work needing their own scoping - don't assume prior context covers them,
and flag the chat feature's conflict with the no-live-LLM principle before
writing any code for it.
