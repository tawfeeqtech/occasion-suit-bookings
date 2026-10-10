---
name: return-processing
description: Process suit rental returns with multi-item inspection checklists. Use when a customer returns rented items, staff needs to inspect items for damage, assess penalty fees, release collateral (National ID), or handle late returns. Covers the full return workflow from inspection to cleaning buffer activation.
---

# Return Processing

## Overview

When a customer returns rented items, staff must inspect each item, assess any damage, handle financial settlement, and release collateral. The system enforces that National ID is only returned after all conditions are met.

## Return Workflow

1. **Select active booking** — Staff chooses the booking to process return
2. **Display item checklist** — System shows all items in the booking
3. **Inspect each item** — Staff marks each as: `clean_pass`, `damaged`, or `missing`
4. **Assess penalties** — For damaged/missing items, staff enters penalty fee
5. **Financial settlement** — Collect remaining balance + penalties
6. **Release collateral** — Only if no outstanding penalties
7. **Activate cleaning buffer** — Item enters cleaning/maintenance status

## Return Status Logic

| Condition | Booking Status | Collateral Status | Action |
|---|---|---|---|
| All items clean_pass | Completed | Release ID | Collect remaining balance |
| Any item damaged | Damage Pending | Hold ID | Assess penalty, collect payment |
| Any item missing | Damage Pending | Hold ID | Assess replacement fee |
| Return date exceeded | Overdue | Hold ID | Apply late penalty (if configured) |

## Collateral Release Rules

**National ID is released ONLY when:**
- All items are marked `clean_pass`
- All penalty fees are paid
- Remaining balance is collected (or written off by owner)

**National ID is HELD when:**
- Any item is damaged or missing
- Any penalty fee is unpaid
- Booking is overdue with unpaid late fees

## Penalty Assessment

- Penalty fees are **manually assessed** by staff based on damage severity
- Common penalties: tear repair, burn damage, missing accessory replacement
- Penalty amount is recorded per item in `booking_items.penalty_fee`
- Total penalty = SUM of all item penalties

## Late Return Handling

- System compares `return_date` with actual return datetime
- If overdue: booking status → `overdue`
- Late penalty is **configurable** per tenant (flat fee or daily rate)
- Late penalty is auto-applied if tenant setting `auto_late_penalty` is true

## Cleaning Buffer Activation

After successful return (all items clean_pass):

1. Create `item_maintenance` record with status `cleaning`
2. Set `expected_ready_at = NOW() + buffer_hours`
3. Update item status to `cleaning`
4. Laravel Scheduler marks item `available` when buffer expires

## Gotchas

- **Never release National ID if any penalty is unpaid.** The system must block release until all fees are settled.
- **No refundable cash deposit exists.** The advance payment is part of the total fee — never refund it.
- **Never store images of National ID cards.** Only store status flag (`held`/`released`) and text notes.
- **Every return action must be logged** to the audit trail with staff identity.
- **Cleaning buffer is mandatory.** Items cannot be rebooked until buffer expires.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/bookings/{id}/return` | Process return with inspection results |
| POST | `/api/v1/bookings/{id}/collateral/release` | Release National ID |
| GET | `/api/v1/bookings/{id}` | Get booking details with items |
