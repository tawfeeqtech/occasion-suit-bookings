# SPEC-007: Financial Tracking & Reporting

## 1. Metadata
- **Specification ID:** SPEC-007
- **Title:** Financial Record-Keeping, Balances & Revenue Reporting
- **Source Intent:** [intent/financial-tracking.md](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Booking Financial Calculations, Manual Payment Methods, Revenue Aggregations & Daily Reports

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-007-01 | [financial-tracking.md:L22](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L22) | Every booking records `total_fee`, `advance_paid`, and computed `remaining_balance` | `BookingFinancialsTest::test_booking_records_amounts_and_calculates_balance` |
| REQ-007-02 | [financial-tracking.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L23) | Supported manual payment methods: `cash`, `palpay`, `jawwal_pay`, `bank_transfer` | `BookingPaymentMethodTest::test_validates_allowed_manual_payment_methods` |
| REQ-007-03 | [financial-tracking.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L24) | Collection of outstanding balance or penalty assessment on return | `ReturnFinancialSettlementTest::test_records_final_payment_and_penalties_on_return` |
| REQ-007-04 | [financial-tracking.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L25) | Basic daily/weekly/monthly tabular financial reports aggregate revenue, receivables, and penalties; charts and Excel exports are deferred beyond MVP | `FinancialReportingServiceTest::test_financial_report_aggregates_metrics_correctly` |
| REQ-007-05 | [financial-tracking.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L26) | Advance payments treated as earned deposits, strictly non-refundable | `BookingFinancialsTest::test_advances_are_credited_against_total_fee` |
| REQ-007-06 | [financial-tracking.md:L32](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L32) | Any financial adjustment or manual discount records an immutable audit log entry | `FinancialAuditLogTest::test_financial_adjustments_logged_to_audit` |
| REQ-007-07 | [financial-tracking.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L33) | Staff users are strictly forbidden from viewing aggregate financial reports | `FinancialAuthorizationTest::test_staff_blocked_from_revenue_endpoints` |
| REQ-007-08 | [financial-tracking.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/financial-tracking.md#L27) | Each tenant uses one configurable currency for its transactions and reports, defaulting to ILS; mixed currencies and conversion are out of MVP scope | `FinancialCurrencyTest::test_one_configured_currency_per_tenant` |

---

## 3. Technical Contract & Data Model

### 3.1 Financial Formulas
- $\text{Remaining Balance} = \text{total\_fee} - \text{advance\_paid}$
- $\text{Total Receivable at Return} = \text{remaining\_balance} + \sum(\text{penalty\_fee})$
- In PostgreSQL:
```sql
ALTER TABLE bookings 
    ADD COLUMN remaining_balance DECIMAL(10,2) 
    GENERATED ALWAYS AS (total_fee - advance_paid) STORED;
```

### 3.2 Payments Ledger Table (Normative Contract)
```sql
CREATE TABLE booking_payments (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    amount DECIMAL(10,2) NOT NULL,
    type VARCHAR(50) NOT NULL, -- 'advance' | 'final_payment' | 'penalty'
    method VARCHAR(50) NOT NULL, -- 'cash' | 'palpay' | 'jawwal_pay' | 'bank_transfer'
    reference_number VARCHAR(100),
    recorded_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-007.1: Zero Inconsistencies in Remaining Balance
- **Given:** A booking created with `total_fee = 450.00` and `advance_paid = 150.00`.
- **When:** Saved to the database.
- **Then:** `remaining_balance` is exactly `300.00`.
- **Verification Test:** `BookingFinancialsTest::test_stored_remaining_balance_is_exact`

### AC-007.2: Daily Financial Report Accuracy
- **Given:** Tenant has 3 bookings today:
  - Booking 1: advance 200 cash
  - Booking 2: advance 100 PalPay
  - Booking 3: return with 150 cash balance + 50 penalty
- **When:** `FinancialReportService::getDailySummary(today)` is called.
- **Then:** Total collected revenue is 500.00 (cash: 400.00, palpay: 100.00), penalties collected: 50.00.
- **Verification Test:** `FinancialReportingServiceTest::test_daily_report_calculation`

### AC-007.3: Invalid Payment Method Rejected
- **Given:** A booking payload with `payment_method = 'credit_card_stripe'`.
- **When:** Submitted to API or web form.
- **Then:** Validation fails with HTTP `422 Unprocessable Content` and error message restricting to approved manual options.
- **Verification Test:** `BookingPaymentMethodTest::test_unsupported_payment_method_rejected`

### AC-007.4: Single Currency Per Tenant
- **Given:** A tenant has `tenants.settings->currency` configured, defaulting to `ILS`.
- **When:** Payments are recorded and financial reports are generated for that tenant.
- **Then:** All amounts use that tenant's configured currency; mixed-currency transactions and currency conversion are not supported in MVP.
- **Verification Test:** `FinancialCurrencyTest::test_one_configured_currency_per_tenant`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- NO payment gateway integration (Stripe, PayPal, PalPay API); all transactions are strictly manual cash/wallet ledger entries ([AGENTS.md:L13](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L13)).
- Negative total fees or negative advance payments MUST be rejected at schema and validator level.
- Advances MUST NOT exceed `total_fee`.

---

## 6. Resolved Decisions

1. **Report Format:** MVP provides basic tabular financial reports. Graphical charts and Excel exports are deferred until after MVP.
2. **Currency:** Each tenant uses one configurable currency, defaulting to ILS. Mixed currencies and conversion within a tenant are not supported in MVP.
