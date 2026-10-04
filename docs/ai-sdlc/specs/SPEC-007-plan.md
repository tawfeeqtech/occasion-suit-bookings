# PLAN-007: Financial Record-Keeping and Reports

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-007.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement Laravel-side booking balances, manual payment recording, tenant currency handling, financial summaries, and Owner-only report access. No payment processing or provider integration is included. The repository currently has no booking finance models, payment ledger, reporting service, or finance tests.

## External Workstream (Excluded)
- Payment gateways, payment APIs, online checkout, and Telegram/n8n collection workflows. Payments are manually recorded by authorized staff/owners inside the Laravel application.

## Open Questions and Constraints
- **Blocking accounting-source decision:** `booking_payments` is described as optional, but accurate daily revenue by payment method and settled penalties require dated payment events. Decide whether every advance, balance, and penalty collection must be a ledger entry or whether `bookings` remains the source of truth before implementing reports.
- **Balance representation:** The SPEC suggests a PostgreSQL generated `remaining_balance` column while the main contract also describes it as computed. Confirm whether to persist a generated column or derive it in the model/query; do not duplicate mutable values.
- Enforce non-negative amounts and `advance_paid <= total_fee`; use exact decimal arithmetic, not floating point.
- Keep one configured currency per tenant (default ILS), with no conversion or mixed currency.

## Implementation Steps
1. Resolve the canonical payment ledger and remaining-balance representation; define how existing booking and return records map to finance entries.
2. Add tenant-scoped schema/models/validation for approved payment entries, methods, amounts, references, and actor attribution. Do not add gateway credentials or network calls.
3. Implement balance and settlement calculations as a single domain source used by booking creation and return processing.
4. Implement tenant-scoped daily/weekly/monthly tabular aggregations for collected amounts, receivables, and collected penalties; document date boundary/timezone rules.
5. Enforce Owner-only access in policies and Filament/API surfaces, including direct URL requests.
6. Add feature tests for arithmetic, method validation, ledger totals, currency, negative inputs, tenant isolation, and Staff denial.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-007.1 | Persist/derive exact remaining balance from canonical amounts | `BookingFinancialsTest::test_stored_remaining_balance_is_exact` |
| AC-007.2 | Aggregate tenant's collected payments and penalties by period/method | `FinancialReportingServiceTest::test_daily_report_calculation` |
| AC-007.3 | Validate only supported manual payment methods | `BookingPaymentMethodTest::test_unsupported_payment_method_rejected` |
| AC-007.4 | Apply one tenant currency to entries and reports | `FinancialCurrencyTest::test_one_configured_currency_per_tenant` |
| REQ-007-06/07 | Log adjustments and deny Staff access | `FinancialAuditLogTest`, `FinancialAuthorizationTest` |

## Test List
- Proposed tests: `BookingFinancialsTest`, `BookingPaymentMethodTest`, `ReturnFinancialSettlementTest`, `FinancialReportingServiceTest`, `FinancialCurrencyTest`, `FinancialAuditLogTest`, and `FinancialAuthorizationTest`.
- Include boundary cases: zero amounts, negative amounts, advance greater than total, mixed tenant currency, day/month boundary, and penalty assessed but not collected.
- Current repository has no finance tests. Run focused tests, then `php artisan test --compact` against PostgreSQL.

## Rollback and Recovery
Back up PostgreSQL before adding or backfilling ledger data. Never discard payment history or silently rewrite recorded amounts; correct mistakes with audited adjustment/void entries after an approved policy exists. Roll back additive schema only before payment entries are recorded; otherwise use forward migrations and reconcile totals.