---
name: telegram-voice-processing
description: Process Arabic voice notes from Telegram for booking creation. Use when staff sends voice messages to the Telegram bot, needs speech-to-text conversion, entity extraction, or booking confirmation card generation. Covers the full pipeline from audio receipt to structured booking data with Groq Whisper and GPT-4o-mini.
---

# Telegram Voice Processing

## Overview

Staff send Arabic voice notes to the Telegram bot. The system transcribes audio to text, extracts structured booking data, checks availability, and presents a confirmation card — all within 5 seconds.

## Pipeline

```
[Telegram Webhook] → [Download Audio] → [Groq Whisper STT] → [GPT-4o-mini Extraction] → [Availability Check] → [Confirmation Card]
```

## Step-by-Step

### 1. Receive Voice Note
- Telegram webhook receives voice message
- Download audio file from Telegram Bot API
- Validate sender `user_id` against tenant whitelist

### 2. Speech-to-Text (Groq Whisper)
- Model: `whisper-large-v3`
- Language: Arabic (auto-detect dialect)
- Output: Arabic text transcript

### 3. Entity Extraction (GPT-4o-mini)
- Input: Arabic text transcript
- Output: Structured JSON matching tenant's schema
- Use `json_schema` enforcement for reliable output
- Fallback chain: GPT-4o-mini → Claude 3.5 Haiku → Groq Llama 3

### 4. Availability Check
- Call `POST /api/v1/availability/check` with extracted items + dates
- If conflict → return warning with conflicting booking details
- If available → proceed to confirmation

### 5. Confirmation Card
- Format extracted data as readable Arabic summary
- Include inline buttons: [Confirm & Save] [Edit / Cancel]
- If missing critical data → prompt for specific missing field

## Entity Extraction Schema

```json
{
  "type": "object",
  "properties": {
    "customer_name": { "type": "string" },
    "customer_phone": { "type": "string" },
    "suit_name": { "type": "string" },
    "suit_size": { "type": "string" },
    "accessories": {
      "type": "array",
      "items": {
        "type": "object",
        "properties": {
          "type": { "type": "string" },
          "size": { "type": "string" },
          "color": { "type": "string" }
        }
      }
    },
    "alterations": { "type": "string" },
    "pickup_date": { "type": "string", "format": "date" },
    "return_date": { "type": "string", "format": "date" },
    "advance_paid": { "type": "number" },
    "payment_method": { "type": "string" }
  },
  "required": ["customer_name", "customer_phone", "pickup_date", "return_date"]
}
```

## Confirmation Card Format

```
📋 حجز جديد

👤 العميل: أحمد محمد
📱 الهاتف: 0599123456
👔 البدلة: Classic Black Size 40
👕 القميص: Size 36 White
👞 الحذاء: Size 42 Brown
🗓️ الاستلام: 2026-10-15
🗓️ الإرجاع: 2026-10-18
💰 المدفوع: 200 ILS (نقدي)

✅ جميع العناصر متوفرة

[تأكيد وحفظ]  [تعديل / إلغاء]
```

## Gotchas

- **Never guess missing data.** If `return_date` is missing, ask specifically: "من فضلك أدخل تاريخ الإرجاع المتوقع".
- **Arabic dialect handling.** Whisper-large-v3 handles most Palestinian/Levantine dialects well, but staff can edit before confirming.
- **Response time target:** < 5 seconds end-to-end. If STT + LLM exceeds 4 seconds, send "جاري المعالجة..." immediately.
- **Voice notes up to 60 seconds.** Longer notes should be split or rejected.
- **Whitelist check first.** Reject non-whitelisted `user_id` immediately without processing audio.

## Error Handling

| Error | Response |
|---|---|
| Non-whitelisted user | "عذراً، غير مصرح لك باستخدام هذا البوت" |
| Audio too long | "الرسالة الصوتية طويلة جداً. الحد الأقصى 60 ثانية" |
| STT failure | "لم أتمكن من فهم الصوت. حاول مرة أخرى أو اكتب التفاصيل" |
| LLM extraction failure | "لم أتمكن من استخراج البيانات. اكتب التفاصيل يدوياً" |
| Missing critical data | "من فضلك أدخل [الحقل الناقص]" |
