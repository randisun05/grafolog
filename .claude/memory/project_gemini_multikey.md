---
name: project-gemini-multikey
description: "2026-10-14: Gemini LLM provider with pool of up to 10 API keys, failover to next available key on rate limit"
metadata:
  node_type: memory
  type: project
  modified: 2026-10-14T00:00:00.000Z
---

User asked for Gemini API support with up to 10 keys, switching to the next
available key when one hits its limit. Built GeminiLlmProvider + GeminiKeyPool
(details in guratan-api/CLAUDE.md "Gemini multi-key"). Still only used through
NarasiCacheService (per aspek/level generic KB text, never user data) - the
no-live-LLM-per-report principle is unchanged.

Key decision: when every key fails, throw LlmUnavailableException and serve
source text WITHOUT caching, so translations retry later instead of English
text being cached as 'id'.

**How to apply:** keys live only in .env (GEMINI_API_KEY_1..10), never commit
them. Translation of KB narasi to Indonesian was deferred by the user; running
`narasi:pregenerate` with LLM_PROVIDER=gemini is how it would be done once they
approve - results should be reviewed before launch.
