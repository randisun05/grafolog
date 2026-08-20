---
name: project-cross-reference-unification
description: "2026-08-19: cross-reference cascade (KM-F) merged into indikator_rules as a 3rd rule_type ('indikator_checked') - old table dropped, admin tab removed, chains can now be multi-hop, inclusion-only (exclusion logic explicitly rejected)"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-19T04:32:23.523Z
---

User asked why "checking Indikator A cascades to checking Indikator B"
(cross-reference, [[project_km_system]]'s KM-F) was a SEPARATE mechanism
from the measurement/irregularity rule engine (indikator_rules), when
conceptually both are just "criteria that auto-check an Indikator."
Proposed unifying them into one system. Confirmed via AskUserQuestion
before touching the live scoring engine (2 architecture decisions, not
guessed):

1. **Dependency chains may be multi-hop** (A->B->C, not just the old
   cascade's single-hop-only restriction).
2. **Full migration** - drop the old indikator_cross_reference table
   entirely, don't run both systems side by side.

**What changed:**
- indikator_rules gained a 3rd rule_type: indikator_checked, with a
  new depends_on_indikator_id FK (cascadeOnDelete). variable_a_id
  became nullable (this rule type doesn't use a measurement variable at
  all).
- ChecklistEngineService::evaluateSample() rewritten from "one pass +
  separate single-hop cascade pass" to a **fixed-point iteration** (loops
  until no row changes, capped at N iterations where N = Indikator count).
  This is provably safe to terminate: an indikator_checked rule can only
  return true/null, never false - "not checked yet" isn't evidence
  of "will never be checked" - so non-manual Indikator status is monotonic
  (can only go false->true within one evaluation, never reversed). Two
  mutually-dependent Indikator (A depends on B, B depends on A) stabilize
  at "both unchecked" rather than looping forever - verified by a
  dedicated test, not just reasoned about.
- **sumber badge semantics preserved**: auto vs cascade is now
  chosen from the *winning rule's type* rather than from which table it
  came from, so IndikatorChecklist.vue's existing "Auto"/"Terkait"
  badges needed zero frontend changes. One real behavior change: cascade
  rows are now live-reconciled every evaluateSample() call just like
  auto rows (previously frozen permanently once created, like manual)
  - this follows from treating cascade and auto as "the same kind of
  thing" now. Consequence: toggle()'s force-uncheck path now also
  freezes the target as sumber='manual', not just checked=false -
  otherwise a still-valid OR-sibling rule could silently re-check it,
  undoing the grafolog's explicit decision.
- **Data migration**: a one-time migration converted the 257
  active+matched indikator_cross_reference rows (in the real dev DB, at
  the moment the migration actually ran) into indikator_rules rows, then
  dropped the old table plus the now-redundant
  sample_indikator_checks.cross_reference_id column (cascade rows are
  identified via the same rule_id every other rule type uses).
  GrafologiKnowledgeSeeder::seedCrossReference() was retrofitted to
  write directly into indikator_rules from the JSON source going
  forward, so a fresh install/test run gets the same 257 relationships
  without depending on that one-time migration.
- **Deliberate trade-off, not a regression**: KM-F's aktif flag used to
  be explicitly protected from being overwritten by a reseed. That concept
  is gone now - "deactivating" a relationship means deleting its
  indikator_rules row, and a reseed *will* recreate it if it's still in
  the source JSON. This matches how the other 3 content seeders
  (Irregularity/CategoryMatch/VariableEquality) already behave - not a new
  risk class, just consistency. Locked in by a test that asserts this
  exact recreate-on-reseed behavior.
- **Admin UI**: the "Referensi Silang" tab (KM-F) is gone - 7 tabs back
  down to 6. Authoring these relationships now happens in the Indikator
  tab's existing "Aturan Operator" section (a 3rd rule-type option).
  ConceptMapController (Peta Konsep / KM-H) was rewritten to read from
  indikator_rules, but its **response shape was kept byte-identical**
  (cross_ref_count, referensi_keluar, referensi_masuk), so
  ConceptMapExplorer.vue needed zero frontend changes at all.

361 backend tests (down net from 355 despite adding new ones, because the
9-test IndikatorCrossReferenceControllerTest file was deleted along with
the feature it tested). Full technical detail in guratan-api/CLAUDE.md's
"Unifikasi cross-reference ke indikator_rules" and guratan-web/CLAUDE.md's
updated AdminKnowledgeView note.

**Verification**: browser-verified via Playwright against real KB data -
confirmed the old tab is gone, added and deleted a real indikator_checked
rule through the new UI location, and checked Peta Konsep for a real
Indikator from the migrated data (not synthetic) showing its correct 3
outgoing relationships with connector lines. 0 console errors both times.
indikator_rules count confirmed back to the correct baseline (363) after
cleanup.

**Exclusion logic decided AGAINST, 2026-08-19** (not deferred - closed):
"if A checked -> B un-checked" was discussed on 2026-08-08
([[project_range_mode_scoring]]) as a possible extension, but user
explicitly confirmed it's not needed ("tidak perlu ada reverse seperti
itu"). Don't build this or re-raise it as a pending item - the
indikator_checked rule type stays inclusion-only by design.

**How to apply**: if the user references "Referensi Silang" or KM-F by
name, that UI/table no longer exists - point them to the Indikator tab's
rule builder instead. If a future request wants deeper Indikator-to-
Indikator logic (AND across multiple dependencies, negation), the
fixed-point engine and rule_group_logic already support AND/OR
combination with other rule types - check ChecklistEngineService before
assuming new schema is needed.
