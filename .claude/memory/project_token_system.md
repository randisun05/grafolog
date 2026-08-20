---
name: project-token-system
description: "2026-08-07: grafolog token system for report generation - complete, gated off until admin sets a real price/cost in /admin/tokens"
metadata:
  node_type: memory
  type: project
  originSessionId: f2ad2120-ae2a-49c8-bc59-5eacf0508ec8
  modified: 2026-08-06T19:44:31.642Z
---

User asked for a token system for grafolog: admin sets the token cost
manually, every successful report generation deducts tokens, every token
purchase adds tokens. Built 2026-08-07 as a single batch (not phased like
[[project_commerce_initiative]]) since the pieces are tightly coupled -
a gate with no purchase path is useless, and vice versa.

3 business decisions confirmed via AskUserQuestion before building:
1. Grafolog buy tokens themselves via DOKU (real payment), not an admin
   manual top-up. Reuses the same DOKU integration Commerce Fase D
   already built for client checkout.
2. Token consumption per report is admin-configurable and can change over
   time (not hardcoded), same as pricing. Discount codes were explicitly
   requested to also apply to token purchases.
3. Insufficient balance -> blocked with 402, mirroring the existing
   client payment-gate pattern in ScoringController.

Architecture (guratan-api@ccd43f3+52af6b7, guratan-web@74590e2+ce6dcea):
- TokenCost (per tier) / TokenPrice (per-token Rupiah) - exact same
  history-preserving pattern as PricingPlan::setPriceFor(). Both
  tables start empty on purpose - TokenCost::activeTokensFor()
  returning null is treated as 0 tokens (no gate) in
  ScoringController::submit. This is the safety mechanism that let the
  feature ship without instantly blocking every existing grafolog (who
  all start with token_balance = 0) - the gate stays off until an
  admin deliberately sets a real cost via /admin/tokens.
- TokenWalletService is the only code allowed to touch
  User::token_balance. credit()/debit() lock the user row and write
  one immutable token_ledger_entries row (with balance_after
  snapshotted) in the same transaction, so the cached balance and the
  audit ledger can't drift under concurrent requests. debit() throws
  the 402 the user asked for, called from inside
  ScoringController::submit's existing transaction so a
  blocked-for-tokens report is never half-created.
- Token purchases reuse the DOKU integration instead of a parallel
  payment system: DokuService::createCheckout() was refactored to stop
  type-hinting the Payment model (now takes invoice/amount/currency as
  plain args) so both Payment and the new TokenPurchase share it.
  DOKU only has one Notification URL (external config, not per-request),
  so PaymentController::notification now dispatches by invoice_number
  prefix (INV- for samples, TOKEN- for token purchases) to whichever
  table owns it - same double-credit guard pattern as discount usage.
- DiscountCode::applicable_tiers now accepts 'token' as a value
  alongside comprehensive/master - zero changes to
  isValidFor()/amountOff(), per decision #2 above.
- DashboardController's grafolog KPI list gained token_balance -
  needed no frontend template change since DashboardView.vue's KPI
  cards already render generically from the API's array.
- Frontend: /admin/tokens (price + per-tier cost, each with its own
  history) and /token-saya (balance, buy form with discount preview,
  transaction history) - same structural pattern as
  AdminPricingView/OrderView.

190 tests passing (38 new, up from 152). Live-verified against the real
dev database, not just the test suite: admin set price/cost through the
actual browser, dashboard showed the new KPI, the buy-token flow failed
cleanly on unconfigured DOKU (503, same as OrderView's already-accepted
verification depth), and the full gate lifecycle was confirmed via direct
API calls - blocked at balance 0, credited (via
TokenWalletService::credit() directly since no real DOKU webhook is
possible without sandbox credentials), then a submit succeeded and
deducted exactly the configured amount with the ledger entry correctly
linked to the resulting report. All test data was cleaned up afterward and
token_prices/token_costs were reset back to inactive, so the gate
stays off for every other grafolog account in the dev database until an
admin deliberately configures real numbers.

What's NOT done / still open:
- Admin has not entered a real token price or per-tier cost yet - the
  feature is fully built but inert (/admin/tokens shows "Belum diatur")
  until they do.
- The actual DOKU payment redirect for token purchases has never been
  tested end-to-end - same blocker as [[project_commerce_initiative]]'s
  Fase C (no real DOKU sandbox credentials yet). Only the graceful-503
  failure path is verified.

How to apply: if the user asks "why isn't the token gate blocking
anyone," the answer is that token_costs/token_prices are intentionally
empty by default - check /admin/tokens before assuming a bug. If DOKU
sandbox credentials land (resolving Commerce Fase C), the token purchase
redirect becomes testable at the same time as the sample-checkout
redirect - they share DokuService, so there's no separate integration
step needed for tokens specifically.

See [[project_commerce_initiative]] for the DOKU/discount-code
infrastructure this reuses, [[project_mga_pivot]] for the platform this
sits on top of.
