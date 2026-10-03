# SPEC-004: Voice-Driven Booking via Telegram

## 1. Metadata
- **Specification ID:** SPEC-004
- **Title:** Voice-Driven Booking Pipeline via Telegram & n8n
- **Source Intent:** [intent/voice-booking-telegram.md](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** n8n Webhook, Groq Whisper STT, GPT-4o-mini Extraction, Availability API, Booking Transaction Locking (`SELECT ... FOR UPDATE`)

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-004-01 | [voice-booking-telegram.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L23) | Speech-to-text conversion of Arabic voice note via Groq Whisper (`whisper-large-v3`) | `SpeechToTextPipelineTest::test_whisper_stt_transcription_integration` |
| REQ-004-02 | [voice-booking-telegram.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L24) | Structured JSON extraction with strict schema validation via GPT-4o-mini | `VoiceExtractionParserTest::test_extracts_structured_booking_json` |
| REQ-004-03 | [voice-booking-telegram.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L25) | Real-time item availability pre-check endpoint `POST /api/v1/availability/check` | `AvailabilityServiceTest::test_detects_unavailable_and_buffer_items` |
| REQ-004-04 | [voice-booking-telegram.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L26) | Telegram summary card rendered with inline buttons `[Confirm & Save]` and `[Edit / Cancel]` | `TelegramResponseFormatterTest::test_summary_card_inline_keyboard` |
| REQ-004-05 | [voice-booking-telegram.md:L27-28](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L27-L28) | ACID transaction with row-level locking (`SELECT ... FOR UPDATE`) on confirm | `BookingServiceTest::test_booking_creation_locks_items_preventing_race_condition` |
| REQ-004-06 | [voice-booking-telegram.md:L34](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L34) | No booking is committed to DB without explicit confirmation button press | `BookingApiTest::test_unconfirmed_booking_payload_does_not_persist` |
| REQ-004-07 | [voice-booking-telegram.md:L35](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L35) | Attempting to confirm an item with date overlap or buffer overlap is blocked | `BookingServiceTest::test_cannot_book_overlapping_dates` |
| REQ-004-08 | [voice-booking-telegram.md:L36](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L36) | Telegram webhook enforces tenant whitelist; non-whitelisted users rejected immediately | `TelegramWebhookAuthTest::test_non_whitelisted_user_rejected_before_processing` |
| REQ-004-09 | [voice-booking-telegram.md:L52](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L52) | Bot ignores all messages from group chats or channels; 1-on-1 DM only | `TelegramWebhookAuthTest::test_group_messages_ignored` |
| REQ-004-10 | [voice-booking-telegram.md:L29](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L29) | Low-confidence or noisy audio prompts the staff member to re-record or send text; no booking is saved before staff confirmation | `VoiceAudioFallbackTest::test_low_confidence_requests_re_recording_or_text` |
| REQ-004-11 | [voice-booking-telegram.md:L30](file:///d:/mind-ai/occasion-suit-bookings/intent/voice-booking-telegram.md#L30) | MVP speech recognition supports Palestinian colloquial Arabic and Modern Standard Arabic; other dialects are deferred | `SpeechToTextDialectTest::test_supports_palestinian_arabic_and_msa` |
| REQ-004-12 | [AGENTS.md:L65](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L65) | On model failure, n8n tries GPT-4o-mini, then Claude 3.5 Haiku, then Groq Llama 3 | `LLMFallbackTest::test_uses_configured_fallback_order` |

---

## 3. Technical Contract & Schemas

### 3.1 Bookings Table Schema
```sql
CREATE TABLE bookings (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    customer_id_number VARCHAR(100),
    pickup_date TIMESTAMPTZ NOT NULL,
    event_date TIMESTAMPTZ,
    return_date TIMESTAMPTZ NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active', -- 'active' | 'completed' | 'overdue' | 'damage_pending' | 'cancelled'
    total_fee DECIMAL(10,2) NOT NULL,
    advance_paid DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_method VARCHAR(100) NOT NULL, -- 'cash' | 'palpay' | 'jawwal_pay' | 'bank_transfer'
    alterations_notes TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Booking Items Schema
```sql
CREATE TABLE booking_items (
    id UUID PRIMARY KEY,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    item_id UUID NOT NULL REFERENCES items(id),
    is_primary BOOLEAN DEFAULT FALSE,
    alteration_notes TEXT,
    return_status VARCHAR(50), -- 'clean_pass' | 'damaged' | 'missing' | NULL
    penalty_fee DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.3 Extracted JSON Schema (Contract between LLM and Laravel)
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["customer_name", "customer_phone", "pickup_date", "return_date", "items", "advance_paid", "payment_method"],
  "properties": {
    "customer_name": { "type": "string" },
    "customer_phone": { "type": "string" },
    "pickup_date": { "type": "string", "format": "date" },
    "return_date": { "type": "string", "format": "date" },
    "items": {
      "type": "array",
      "items": {
        "type": "object",
        "required": ["category"],
        "properties": {
          "category": { "type": "string" },
          "item_id": { "type": "string" },
          "size": { "type": "string" },
          "color": { "type": "string" },
          "alterations": { "type": "string" }
        }
      }
    },
    "total_fee": { "type": "number" },
    "advance_paid": { "type": "number" },
    "payment_method": { "type": "string", "enum": ["cash", "palpay", "jawwal_pay", "bank_transfer"] }
  }
}
```

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-004.1: Availability Pre-Check API
- **Given:** Item `item_1` has an active booking from `2026-10-10` to `2026-10-15` with a 48h buffer (ready on `2026-10-17`).
- **When:** `POST /api/v1/availability/check` is called for `item_1` with date range `2026-10-16` to `2026-10-20`.
- **Then:** Response status is `200 OK` with payload `{"available": false, "reason": "item_in_buffer", "ready_at": "2026-10-17T00:00:00Z"}`.
- **Verification Test:** `AvailabilityCheckApiTest::test_availability_fails_during_cleaning_buffer`

### AC-004.2: Zero Double-Booking via Row-Level Locking
- **Given:** Item `item_2` is available for `2026-11-01` to `2026-11-05`.
- **When:** Two concurrent requests simultaneously attempt `POST /api/v1/bookings` requesting `item_2` for those exact dates.
- **Then:** Exactly one request succeeds with HTTP `201 Created`; the second request fails with HTTP `409 Conflict` (or database lock exception handled gracefully).
- **Verification Test:** `BookingConcurrencyTest::test_simultaneous_booking_requests_prevent_double_booking`

### AC-004.3: Missing Critical Data Interception
- **Given:** An extracted JSON payload missing `return_date`.
- **When:** Processed by the booking validation layer.
- **Then:** System responds with HTTP `422 Unprocessable Content` and structured error message identifying the missing field: `["حقل تاريخ الإرجاع مطلوب لإتمام الحجز"]`.
- **Verification Test:** `BookingValidationTest::test_missing_dates_fails_validation_with_arabic_error`

### AC-004.4: Collateral Record Creation on Booking
- **Given:** A valid booking confirmation payload with National ID number.
- **When:** `BookingService::createBooking()` executes successfully.
- **Then:** A record in `collateral_records` is created with status `held`, associated with the newly created booking ID.
- **Verification Test:** `BookingServiceTest::test_creates_held_collateral_record_on_booking`

### AC-004.5: Low-Confidence Audio Recovery
- **Given:** A noisy voice note or an extraction result with low confidence or missing required fields.
- **When:** The bot evaluates the transcription and extracted booking data.
- **Then:** It asks the staff member to re-record or provide the details as text, and does not save a booking before staff review and confirmation.
- **Verification Test:** `VoiceAudioFallbackTest::test_low_confidence_requests_re_recording_or_text`

### AC-004.6: Supported Arabic Dialects
- **Given:** A representative booking voice note in Palestinian colloquial Arabic or Modern Standard Arabic.
- **When:** The note is processed by the speech-to-text pipeline.
- **Then:** The transcription is passed to the extraction step; dialects beyond these two are outside MVP scope.
- **Verification Test:** `SpeechToTextDialectTest::test_supports_palestinian_arabic_and_msa`

### AC-004.7: LLM Provider Fallback
- **Given:** The primary LLM request fails.
- **When:** The n8n workflow handles the provider error.
- **Then:** It tries Claude 3.5 Haiku, then Groq Llama 3, and returns a clarification/retry response if all providers fail.
- **Verification Test:** `LLMFallbackTest::test_uses_configured_fallback_order`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- No audio files stored permanently on disk after processing (privacy & disk space limits) ([AGENTS.md:L15](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L15)).
- The system MUST NOT commit a booking using application-level checks without `SELECT ... FOR UPDATE` row locks ([AGENTS.md:L52](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L52)).
- National ID card images/photos MUST NEVER be accepted, uploaded, or stored; text numbers only ([AGENTS.md:L15](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L15)).

---

## 6. Resolved Decisions

1. **Audio Quality:** For noisy or low-confidence audio, the bot asks the staff member to re-record or provide the details as text. It must not guess or save a booking before staff review and confirmation.
2. **Arabic Dialects:** MVP supports Palestinian colloquial Arabic and Modern Standard Arabic. Other dialects are deferred.
3. **LLM Fallback:** On provider failure, n8n tries GPT-4o-mini, then Claude 3.5 Haiku, then Groq Llama 3; if all fail, it asks the staff member to retry or clarify.
