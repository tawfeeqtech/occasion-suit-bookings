# PLAN-004: Laravel Booking and Availability Backend for n8n

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-004.md`
- Source status: Approved / Customized
- Approval: Approved

## Goal and Scope
Implement the Laravel-owned booking backend: request validation, availability checking endpoint, booking persistence in an ACID transaction with deterministic PostgreSQL row-level locking (`SELECT ... FOR UPDATE`), manual pricing, and collateral status management.

## External Workstream (Excluded)
- Telegram bot webhook, Whisper audio transcription, GPT/Claude extraction, Telegram inline keyboards, and user DM handling (all managed via n8n).

## Constraints
- Manual pricing (total_fee, advance_paid, remaining_balance) without automated forced formula.
- Row-locking items in deterministic ID order to avoid deadlocks.
- Collateral record stores only status (`held`/`released`) and notes (no national ID scans/numbers).
- `booking_items` includes `tenant_id` for multi-tenant integrity.

## Implementation Steps
1. Create migrations for `bookings`, `booking_items`, and `collateral_records` with UUIDs, foreign keys, and indexes.
2. Implement `AvailabilityService` calculating calendar overlaps and tenant cleaning buffers.
3. Expose `POST /api/v1/availability/check` and `GET /api/v1/bookings/today`.
4. Implement `BookingService` with atomic database transactions:
   - Sort requested `item_ids` to eliminate deadlocks.
   - Lock item rows (`SELECT ... FOR UPDATE`).
   - Re-check availability within the lock.
   - Persist booking, booking items, and create initial `held` collateral record.
5. Expose `POST /api/v1/bookings` protected by `telegram_user_id` staff matching.
6. Write feature and concurrency tests verifying simultaneous overlapping requests reject race conditions.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| REQ-004-01 | Availability service checks overlaps & cleaning buffer | `AvailabilityServiceTest::test_detects_unavailable_and_buffer_items` |
| REQ-004-02 | Row locking prevents double-booking | `BookingConcurrencyTest::test_simultaneous_booking_requests_prevent_double_booking` |
| REQ-004-03 | Manual pricing saved accurately | `BookingServiceTest::test_manual_pricing_persisted_accurately` |
| REQ-004-04 | Collateral status held created without ID scan | `BookingServiceTest::test_creates_held_collateral_record_without_id_scan` |
| REQ-004-05 | Reject overlapping dates with 409 | `BookingServiceTest::test_cannot_book_overlapping_dates` |
| REQ-004-06 | Whitelist authentication via header/payload | `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated` |