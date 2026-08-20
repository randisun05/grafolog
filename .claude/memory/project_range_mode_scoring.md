---
name: project-range-mode-scoring
description: "2026-08-17: irregularity rules corrected to use range (max-min) not point value, measurement worksheet gained min/max input, report correction can reopen worksheet on completed samples, per-Indikator narasi (keterangan) now surfaces in reports"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-19T03:24:14.785Z
---

User clarified (2026-08-17) that the 20 irregularity thresholds from
2026-08-08 ([[project_km_system]]) all literally say "Range is more
than..." - "Range" meant the actual selisih (max observed value - min
observed value) for a variable across one handwriting sample, not a single
point reading. The original 28 seeded rules had compared a single nilai
reading directly, which was semantically wrong (though it shipped
tested/verified - the tests just verified the wrong interpretation).

**What changed:**
- measurement_readings gained nilai_min/nilai_max (nullable); nilai
  itself also became nullable (a reading can now be range-only).
- indikator_rules.variable_a_value_mode (nilai|range) - only
  variable_a needs this, since in every one of the user's 20 thresholds the
  right-hand side (variable_b/compare_value) is always a point value, never
  itself a range.
- All 28 existing IrregularityRuleSeeder rows retrofitted in place to
  variable_a_value_mode = 'range' (same updateOrCreate key, so this is
  a content migration, not new rows for those 28).
- **The 5 "Middle zone height irregular/regular" Indikator that were
  previously skipped as a probable source typo (self-referential
  comparison) are now understood to be valid** - range(MZH) compared
  against 1x point-value(MZH), i.e. the same variable used in two different
  modes. Added as real rules once the range mechanism existed to express
  this correctly. indikator_rules is now 106 rows (was 101).
- MeasurementController/ChecklistController no longer block
  status === 'completed' samples - needed so ReportCorrectionPanel can
  reopen the original measurement worksheet + checklist for an
  already-completed report, edit inputs, and only affect the live report
  once ScoringController::correct() is explicitly called (editing the
  worksheet alone never touches the frozen report data).
- Indikator.keterangan (per-Indikator narrative text, present in the KB
  since the original Excel import but never surfaced anywhere) is now
  attached to reports as aspek.indikator_terkait for every Aspek that has
  a checked Indikator - post-processing in ScoringController, KB
  narrative-generation logic (ScoringEngineService) itself untouched.
  No-op for manual-mode reports (no sample_indikator_checks rows exist).

362 backend tests (up from 355). Full technical detail in
guratan-api/CLAUDE.md's "Range-mode irregularity rules..." entry and
guratan-web/CLAUDE.md's matching ReportCorrectionPanel/ReportDocument
notes - this memory is the "why," those files are the "what/where."

**Verification method**: initially just live API calls + DB ground truth
(no browser tool available that session). A later session (same day,
2026-08-19) got Playwright working (ad-hoc npm install -g playwright,
browsers already cached from an earlier session) and browser-verified the
full flow for real: worksheet min/max entry -> auto-check with "Range ..."
reason -> report's indikator_terkait -> correction panel's worksheet
mode showing prefilled values. 0 console errors. All throwaway rows
deleted afterward. See [[project_cross_reference_unification]] for the
follow-on architecture change (cross-reference merged into
indikator_rules) done in that same later session.

**How to apply**: if a future irregularity/range-comparison rule request
comes in, the mechanism already exists - extend indikator_rules content
(new seeder or edit), don't re-derive the schema. If a report-correction
change is requested, remember both correction paths exist now (manual
1-10 form and worksheet mode) sharing one scores ref and one submit
endpoint - check which one the user means before assuming. See
[[project_report_editing]] for the correction feature's original scope and
[[feedback_scoring_engine_design]] for other scoring-engine corrections.
