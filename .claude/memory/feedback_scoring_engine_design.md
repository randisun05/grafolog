---
name: feedback-scoring-engine-design
description: "Corrected (2026-07-26, code-verified) design of ScoringEngineService — very_high IS used via a separate 4-way narasi_level split; deskriptif_lookup is dead code, not used as a band ringkasan"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9355ddff-ff22-468b-8c0a-a63f0a0e28f8
  modified: 2026-07-26T03:26:24.516Z
---

**This corrects a prior version of this memory (2026-07-25) that turned out
to be wrong when checked against the actual code on 2026-07-26.** The old
version was written before/without seeing the final implementation; don't
trust design memories written before a feature shipped over the shipped code.

Verified against `guratan-api/app/Services/Scoring/ScoringEngineService.php`
and its test (`tests/Unit/ScoringEngineServiceTest.php`), both read directly,
plus `php artisan test` passing:

1. **`narasi_very_high` IS used**, contrary to the old note. The engine
   computes `narasi_level` via a standalone static method,
   `ScoringEngineService::narasiLevelUntukSkor(int $skor): string`, entirely
   independent of `scoring_rule_band`:
   `skor 1-3 → low, 4-6 → medium, 7-8 → high, 9-10 → very_high`. This is a
   real 4-bucket split with test coverage
   (`test_narasi_level_mapping_covers_all_buckets`). `NarasiCacheService`
   is called with this `narasi_level`, so `narasi_very_high` text from the
   source JSON does get surfaced for scores 9-10.
2. **`band_label`** (Nilai Rendah/Sedang/Tinggi) is a *separate* value, still
   the old 3-way split via `ScoringRuleBand::labelUntukSkor($skor,
   $polaritas)`. So a single aspek score produces two independent
   classifications: a 3-way display band and a 4-way narrative intensity
   level. Don't conflate them.
3. **`deskriptif_lookup` / `DeskriptifLookup` model is NOT used as a "band
   ringkasan."** That was the old memory's second claim and it's also wrong
   — grepping `app/` for `DeskriptifLookup` only finds the model's own class
   definition, nothing references it. It's dead code / an unimplemented idea,
   not part of the current design.
4. **Output shape** of `generate(array $skorPerAspek): array` is grouped by
   sindrom, not flat:
   `{ sindrom: [{ id, kode_romawi, nama, polaritas, catatan_polaritas,
   rata_rata_skor, band_label_rata_rata, aspek: [{ kode, nama, skor,
   band_label, narasi_level, narasi }] }] }`. The old memory's claimed shape
   (`aspek_id, kode, nama, sindrom_id, skor, band_label, level, ringkasan,
   narasi` as a flat list) does not match reality — don't rely on it.

**Why:** believing the stale design would have led to reimplementing
`very_high` handling that already exists, or wiring `DeskriptifLookup` based
on a design that was abandoned.
**How to apply:** if extending report structure (e.g. `ReportController` /
frontend `ReportView`/`ReportDocument`), consume `generate()`'s actual
sindrom-grouped shape above. If a real grafolog/expert wants `narasi_level`
buckets changed, that's an app-layer threshold change in
`narasiLevelUntukSkor()`, not a data-source gap (unlike what the old memory
assumed). Before trusting *this* memory in a future session, spot-check it
against `guratan-api/CLAUDE.md` and the actual service file — memories about
shipped code can go stale exactly like this one did.

See [[project-status]] for overall build state.
