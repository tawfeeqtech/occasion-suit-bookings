# SPEC-004: Laravel Booking Backend & Availability API for n8n

## 1. Metadata
- **Specification ID:** SPEC-004
- **Title:** Laravel Booking Backend, Concurrency Locking & Availability API for n8n
- **Source Intent:** [intent/voice-booking-telegram.md](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md)
- **Status:** Approved / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Availability Service & API, Booking Transaction with `SELECT ... FOR UPDATE` Row Locking, Manual Pricing, Collateral Status Management

---

## 2. Workstream Boundaries (Explicit Division)

| Responsibility | Handled By |
|---|---|
| **Telegram Voice/Text Ingestion & Summary Cards** | ❌ **n8n Workflow** (External, not in Laravel) |
| **Whisper Arabic Audio Transcription & GPT Structured Extraction** | ❌ **n8n Workflow** (External, not in Laravel) |
| **Availability Checking Endpoint** (`POST /api/v1/availability/check`) | ✅ **Laravel REST API** |
| **Booking Creation Endpoint** (`POST /api/v1/bookings`) | ✅ **Laravel REST API** |
| **Today's Bookings Endpoint** (`GET /api/v1/bookings/today`) | ✅ **Laravel REST API** |
| **ACID Transaction & Concurrency Prevention** (`SELECT ... FOR UPDATE`) | ✅ **Laravel BookingService & PostgreSQL** |
| **Manual Pricing Recording & Collateral Initial State** | ✅ **Laravel Database & Models** |

---

## 3. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-004-01 | [voice-booking-telegram.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L25) | Real-time item availability check endpoint `POST /api/v1/availability/check` considering buffer hours | `AvailabilityServiceTest::test_detects_unavailable_and_buffer_items` |
| REQ-004-02 | [voice-booking-telegram.md:L27-28](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L27-L28) | ACID transaction with row-level locking (`SELECT ... FOR UPDATE`) preventing double-booking | `BookingConcurrencyTest::test_simultaneous_booking_requests_prevent_double_booking` |
| REQ-004-03 | User Specification | Manual pricing: total fee, advance paid, and remaining balance recorded manually as provided | `BookingServiceTest::test_manual_pricing_persisted_accurately` |
| REQ-004-04 | [AGENTS.md:L15](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L15) | National ID cards are never scanned/stored; only `held`/`released` status record created | `BookingServiceTest::test_creates_held_collateral_record_without_id_scan` |
| REQ-004-05 | [voice-booking-telegram.md:L35](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L35) | Attempting to book an item with date overlap or active cleaning buffer is rejected (409 Conflict) | `BookingServiceTest::test_cannot_book_overlapping_dates` |
| REQ-004-06 | User Specification | API requests authenticate via tenant staff `telegram_user_id` header or payload | `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated` |

---

## 4. Technical Contract & Schemas

### 4.1 Bookings Table Schema
```sql
CREATE TABLE bookings (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    booking_number VARCHAR(50) NOT NULL,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    pickup_date DATE NOT NULL,
    event_date DATE,
    return_date DATE NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- 'active' | 'completed' | 'overdue' | 'damage_pending' | 'cancelled'
    total_fee DECIMAL(10,2) NOT NULL,
    advance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method VARCHAR(100) NOT NULL, -- 'cash' | 'palpay' | 'jawwal_pay' | 'bank_transfer'
    alterations_notes TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_bookings_tenant_dates ON bookings (tenant_id, pickup_date, return_date);
```

### 4.2 Booking Items Schema
```sql
CREATE TABLE booking_items (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    item_id UUID NOT NULL REFERENCES items(id) ON DELETE RESTRICT,
    rental_price DECIMAL(10,2) DEFAULT 0.00,
    inspection_status VARCHAR(50) NOT NULL DEFAULT 'clean_pass', -- 'clean_pass' | 'damaged' | 'missing'
    penalty_amount DECIMAL(10,2) DEFAULT 0.00,
    damage_notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_booking_items_tenant_item ON booking_items (tenant_id, item_id);
```

### 4.3 Collateral Records Schema
```sql
CREATE TABLE collateral_records (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    status VARCHAR(50) NOT NULL DEFAULT 'held', -- 'held' | 'released'
    notes TEXT,
    held_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    released_at TIMESTAMPTZ,
    released_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

## 5. API Endpoints Contract (for n8n)

### 5.1 Check Availability
- **POST** `/api/v1/availability/check`
- **Headers:** `X-Telegram-User-Id: <BIGINT>`
- **Request Body:**
```json
{
  "item_ids": ["uuid-1", "uuid-2"],
  "pickup_date": "2026-10-15",
  "return_date": "2026-10-18"
}
```
- **Response:**
```json
{
  "available": true,
  "items": [
    {"id": "uuid-1", "status": "available", "conflict": null},
    {"id": "uuid-2", "status": "available", "conflict": null}
  ]
}
```

### 5.2 Create Booking
- **POST** `/api/v1/bookings`
- **Headers:** `X-Telegram-User-Id: <BIGINT>`
- **Request Body:**
```json
{
  "customer_name": "محمد علي",
  "customer_phone": "0599123456",
  "pickup_date": "2026-10-15",
  "event_date": "2026-10-16",
  "return_date": "2026-10-18",
  "total_fee": 350.00,
  "advance_paid": 100.00,
  "payment_method": "cash",
  "alterations_notes": "تقصير البنطال 2 سم",
  "item_ids": ["uuid-1", "uuid-2"]
}
```
- **Response (201 Created):**
```json
{
  "success": true,
  "booking_id": "uuid-booking",
  "booking_number": "BK-202610-0001",
  "remaining_balance": 250.00,
  "collateral_status": "held"
}
```

---

## 6. Acceptance Criteria
- Zero double-booking concurrency bugs guaranteed via `SELECT ... FOR UPDATE` sorting item IDs.
- Clean separation: absolutely no external LLM or Telegram bot runtime code inside Laravel.
