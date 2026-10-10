---
name: booking-management
description: Create, confirm, and manage suit rental bookings. Use when staff needs to create a new booking, check availability, modify an existing booking, or handle booking conflicts. Covers the full booking lifecycle from voice input to database commit, including dynamic bundle assembly and payment recording.
---

# Booking Management

## Overview

Core booking workflow for SuitRent SaaS. Bookings are created via Telegram voice notes or manual web input, checked for availability, confirmed by staff, then committed to the database with row-level locking.

## Booking Creation Workflow

1. **Receive input** — Arabic voice note (Telegram) or manual form (web)
2. **Extract data** — Groq Whisper STT → GPT-4o-mini entity extraction → structured JSON
3. **Validate required fields** — `customer_name`, `customer_phone`, `pickup_date`, `return_date` are mandatory
4. **Check availability** — Call `POST /api/v1/availability/check` with item IDs and date range
5. **Present summary** — Show formatted card with all parsed fields + availability status
6. **Await confirmation** — Staff taps [Confirm & Save] or [Edit / Cancel]
7. **Commit booking** — `POST /api/v1/bookings` within ACID transaction with `SELECT FOR UPDATE` row locking

## Required Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `customer_name` | string | Yes | Full name |
| `customer_phone` | string | Yes | Mobile number |
| `customer_id_number` | string | No | National ID (text only, never store images) |
| `pickup_date` | datetime | Yes | When customer picks up |
| `event_date` | datetime | No | Wedding/event date |
| `return_date` | datetime | Yes | Expected return |
| `total_fee` | decimal | Yes | Base + alterations + accessories |
| `advance_paid` | decimal | Yes | Partial payment (not refundable) |
| `payment_method` | string | Yes | cash, palpay, jawwal_pay, bank_transfer |
| `alterations_notes` | text | No | Tailoring details |

## Dynamic Bundle Structure

Bookings contain a primary item (suit) plus optional accessories:

```json
{
  "items": [
    { "item_id": "uuid", "is_primary": true, "size": "40" },
    { "item_id": "uuid", "type": "shirt", "size": "36", "color": "white" },
    { "item_id": "uuid", "type": "shoes", "size": "42", "color": "brown" },
    { "item_id": "uuid", "type": "belt", "color": "camel" },
    { "item_id": "uuid", "type": "tie", "color": "beige" }
  ]
}
```

## Availability Check Logic

Before confirming any booking:

1. Query `bookings` table for overlapping date ranges on requested items
2. Query `item_maintenance` for active cleaning buffers
3. If conflict → return conflicting booking details + available alternatives
4. If available → proceed to confirmation

## Gotchas

- **Never auto-fill missing critical data.** If `return_date` or `customer_phone` is missing, ask the staff explicitly.
- **Advance payment is NOT refundable.** It is part of the total rental fee, not a separate deposit.
- **Row locking is mandatory.** Always use `SELECT ... FOR UPDATE` when checking + creating bookings to prevent race conditions.
- **Tenant scoping is automatic.** All queries include `WHERE tenant_id = current_tenant` via global scope — never bypass it.
- **Response time target:** < 5 seconds from voice note to confirmation card.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/availability/check` | Check item availability for date range |
| POST | `/api/v1/bookings` | Create booking (ACID transaction) |
| GET | `/api/v1/bookings` | List bookings (tenant-scoped) |
| GET | `/api/v1/bookings/today` | Today's reservations |
| POST | `/api/v1/bookings/{id}/return` | Process return |
| POST | `/api/v1/bookings/{id}/collateral/release` | Release National ID |

## References

- [Booking schema details](references/booking-schema.md)
- [Availability check algorithm](references/availability-algorithm.md)
