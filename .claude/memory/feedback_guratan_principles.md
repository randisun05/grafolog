---
name: feedback-guratan-principles
description: "Hard constraints the user stated for building guratan-api — LLM caching, no CV yet, fix data quality at source, security is mandatory"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 9355ddff-ff22-468b-8c0a-a63f0a0e28f8
  modified: 2026-08-01T14:24:09.804Z
---

Four constraints stated up front for all future work on guratan-api:

1. **Never call an LLM per-report.** All report narrative must go through `NarasiCacheService`, reading from the `narasi_cache` table — not calling an LLM provider directly at report-generation time.
   **Why:** cost/latency control and consistency; the per-report "connective sentence" LLM idea is explicitly deferred (see [[project-status]]), not a green light to add live LLM calls elsewhere.
   **How to apply:** if a task seems to need dynamic narrative generation, route it through the cache service or flag the gap — don't wire up `ApiLlmProvider`/`SelfHostedLlmProvider` live without the user asking.

2. **Don't build the Rapid tier's CV/image scoring.** It's explicitly postponed post-MVP.
   **How to apply:** photo upload (`SampleController`) can exist, but its scoring should be a placeholder/manual stand-in, not real computer vision.
   **Superseded 2026-08-01**: user decided to retire the Rapid tier entirely
   as part of the "Master Graphology Assistant" pivot (see
   [[project_status]]) — not just postpone CV, but stop offering new Rapid
   uploads at all (old ones stay viewable). Don't build Rapid CV under either
   the old or new framing.

3. **Data-quality issues in the knowledge base get fixed at the JSON source**, not patched in the seeder.
   **Why:** keeps `database/seeders/data/grafologi_knowledge_base.json` as the single source of truth; seeder-level patches would silently diverge from it.
   **How to apply:** if a new duplicate/malformed kode turns up, edit the JSON file and re-seed, don't add special-case logic to `GrafologiKnowledgeSeeder`.

4. **Security and validation are mandatory, not optional**, because the data is sensitive psychological/personality data.
   **How to apply:** rate limiting, Form Request validation, and audit logging are required before anything is public-facing — treat requests to skip these as something to push back on.
