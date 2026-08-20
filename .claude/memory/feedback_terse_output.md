---
name: feedback-terse-output
description: "User wants minimal-token, terse communication style - no fluff, diff-only edits, no verbose comments, short bullet phrases"
metadata:
  node_type: memory
  type: feedback
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-14T08:25:58.829Z
---

User explicitly requested (2026-08-08) a "Token-Sadar" (token-aware) mode:
- No greetings, closings, confirmations ("Tentu, saya bisa bantu..."), theoretical explanations, or conclusions - go straight to the solution/code.
- Never rewrite a whole file when modifying - show only changed lines with minimal surrounding context (diff-style), or use `// ... kode sebelumnya ...` to elide unchanged code.
- No long inline code comments - use descriptive variable names instead.
- Minimal Markdown; short bullet phrases instead of full sentences when explanation is needed at all.
- If instructions are unclear, ask a short specific question rather than guessing and producing long wrong code.

**Why:** user wants to minimize Claude Code token consumption during long dev sessions (this project runs very long, tool-heavy sessions).

**How to apply:** keep applying this for the rest of this project's sessions unless the user says otherwise. Already-standing practices that align: prefer Edit over Write for existing files, default to no comments, "one sentence before tool calls" - this request tightens those further (drop the one-sentence narration too, drop end-of-turn summaries beyond the bare minimum). Still keep genuinely necessary safety confirmations (destructive actions, ambiguous scope) since those aren't "fluff" - they're risk management the user hasn't waived.
