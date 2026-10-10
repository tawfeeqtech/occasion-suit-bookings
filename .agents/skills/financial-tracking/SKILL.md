---
name: financial-tracking
description: Track rental payments, balances, penalties, and generate financial reports. Use when recording payments, calculating remaining balances, assessing penalty fees, or generating daily/weekly/monthly financial reports. Covers all financial operations including cash, PalPay, Jawwal Pay, and bank transfer recording.
---

# Financial Tracking

## Overview

Financial tracking covers all monetary transactions: rental fees, advance payments, penalty fees, and balance collection. The system records payments manually — no payment gateway integration.

## Payment Methods

| Method | Code | Notes |
|---|---|---|
| Cash | `cash` | In-store cash payment |
| PalPay | `palpay` | Palestinian mobile payment |
| Jawwal Pay | `jawwal_pay` | Mobile wallet |
| Bank Transfer | `bank_transfer` | Direct bank deposit |

## Financial Flow

```
Booking Created
    ↓
total_fee calculated (base + alterations + accessories)
    ↓
advance_paid recorded (partial payment)
    ↓
remaining_balance = total_fee - advance_paid
    ↓
Return Processed
    ↓
IF damage/missing → penalty_fee assessed
    ↓
remaining_balance + penalty_fee collected
    ↓
Booking Completed (all fees settled)
```

## Key Financial Rules

1. **Advance payment is NOT refundable.** It is part of the total rental fee.
2. **No cash deposit is held.** The National ID is the sole collateral.
3. **Penalty fees are manually assessed** by staff based on damage severity.
4. **Remaining balance is collected at return** (or written off by owner).
5. **All payments are recorded** with method, amount, timestamp, and staff identity.

## Financial Reports

### Daily Report
- Total bookings created
- Total revenue (advance payments collected)
- Total penalties collected
- Total remaining balances

### Weekly/Monthly Report
- Revenue by payment method
- Revenue by item category
- Outstanding balances (aging)
- Penalty fees collected
- Top rented items

## Report API

| Endpoint | Description |
|---|---|
| `GET /api/v1/reports/financial?period=daily` | Daily financial summary |
| `GET /api/v1/reports/financial?period=weekly` | Weekly summary |
| `GET /api/v1/reports/financial?period=monthly` | Monthly summary |
| `GET /api/v1/reports/financial?start_date=&end_date=` | Custom date range |

## Gotchas

- **Never delete financial records.** All transactions are immutable.
- **Remaining balance is auto-calculated.** `remaining_balance = total_fee - advance_paid` (generated column).
- **Penalty fees are per-item.** Each damaged/missing item has its own penalty fee.
- **All financial actions are logged** to the audit trail.
- **Reports are tenant-scoped.** Owners only see their own shop's financial data.

## Database Columns

| Table | Column | Type | Description |
|---|---|---|---|
| bookings | total_fee | DECIMAL(10,2) | Total rental fee |
| bookings | advance_paid | DECIMAL(10,2) | Amount paid upfront |
| bookings | remaining_balance | DECIMAL(10,2) | Auto-calculated balance |
| bookings | payment_method | VARCHAR(100) | Payment method |
| booking_items | penalty_fee | DECIMAL(10,2) | Per-item penalty |
