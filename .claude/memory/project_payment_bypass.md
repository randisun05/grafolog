---
name: Payment bypass (test mode)
description: 2026-10-17 — PAYMENT_BYPASS makes checkout/token purchase instantly paid without DOKU; never in production
type: project
---
User has no DOKU account yet and wants manual testing (2026-10-17). `PaymentMode::bypassed()`:
PAYMENT_BYPASS=true, or unset + DOKU_CLIENT_ID empty; forced off when APP_ENV=production.
Placeholder DOKU_CLIENT_ID values defeat auto-detect → tell user to set PAYMENT_BYPASS=true.

**How to apply:** when DOKU sandbox creds arrive, remind user to remove PAYMENT_BYPASS and test the real redirect + webhook (Commerce Fase C still untested).
