# SPEC-006: Return & Inspection Workflow

## 1. Metadata
- **Specification ID:** SPEC-006
- **Title:** Return Inspection Checklist, Damage Penalties & Collateral Release
- **Source Intent:** [intent/return-inspection-workflow.md](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** `ReturnService`, Inspection Checklist Form, Penalty Calculations, Collateral State Transition Guards

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-006-01 | [return-inspection-workflow.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L23) | Return flow displays all associated bundle items for checklist verification | `ReturnProcessFeatureTest::test_return_screen_lists_all_booking_items` |
| REQ-006-02 | [return-inspection-workflow.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L24) | Every item must be inspected with explicit status: `clean_pass`, `damaged`, or `missing` | `ReturnValidationTest::test_all_items_must_have_inspection_status` |
| REQ-006-03 | [return-inspection-workflow.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L25) | When all items are clean pass and balance settled, system prompts and permits ID release | `CollateralReleaseTest::test_clean_return_allows_id_release` |
| REQ-006-04 | [return-inspection-workflow.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L26) | Damaged/missing items require a manually entered penalty and reason; booking transitions to `damage_pending` and ID stays `held` | `DamageAssessmentTest::test_damaged_item_sets_damage_pending_and_holds_id` |
| REQ-006-05 | [return-inspection-workflow.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L27) | Once the penalty is paid and other balances are settled, booking transitions to `completed` and ID can be released | `DamageAssessmentTest::test_settling_penalty_allows_id_release` |
| REQ-006-06 | [return-inspection-workflow.md:L28](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L28) | Clean returned items automatically trigger entry into cleaning buffer | `ReturnServiceTest::test_clean_return_triggers_cleaning_buffer` |
| REQ-006-07 | [return-inspection-workflow.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L33) | ID release is blocked while rental balances or unwaived penalties remain unpaid | `CollateralGuardTest::test_cannot_release_id_with_outstanding_balance_or_penalties` |
| REQ-006-08 | [return-inspection-workflow.md:L41](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L41) | Return processing, penalty decisions, waivers, and ID release record audit entries with actor and timestamp | `AuditTrailIntegrationTest::test_return_and_id_release_logged_to_audit` |
| REQ-006-09 | [return-inspection-workflow.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L26) | Staff manually enters the penalty amount and reason for each damaged or missing item; no default catalog is assumed | `DamageAssessmentTest::test_manual_penalty_amount_and_reason_are_recorded` |
| REQ-006-10 | [return-inspection-workflow.md:L29](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L29) | On refusal, booking remains `damage_pending` and ID remains `held` until payment or an owner-approved, audited waiver | `CollateralReleaseTest::test_refusal_requires_owner_decision_before_release` |

---

## 3. Technical Contract & Schema

### 3.1 Collateral Records Schema
```sql
CREATE TABLE collateral_records (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    document_type VARCHAR(100) NOT NULL DEFAULT 'national_id',
    status VARCHAR(50) NOT NULL DEFAULT 'held', -- 'held' | 'released'
    notes TEXT,
    held_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    released_at TIMESTAMPTZ,
    released_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Return Inspection Payload
```json
{
  "items": [
    {
      "booking_item_id": "uuid-1",
      "return_status": "clean_pass",
      "notes": null
    },
    {
      "booking_item_id": "uuid-2",
      "return_status": "damaged",
      "penalty_fee": 150.00,
      "penalty_reason": "Estimated repair cost",
      "notes": "تمزق في الكم الأيمن للجاكيت"
    }
  ],
  "remaining_balance_collected": 100.00,
  "payment_method": "cash",
  "release_collateral": false
}
```

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-006.1: Clean Pass Return Execution
- **Given:** A booking with 3 items, total fee 500, advance paid 200 (balance 300).
- **When:** Return is submitted marking all 3 items `clean_pass`, remaining 300 collected, and `release_collateral = true`.
- **Then:**
  - Booking status becomes `completed`.
  - All 3 items transition to `cleaning` buffer.
  - Collateral record status becomes `released` with `released_by = auth()->id()`.
- **Verification Test:** `ReturnServiceTest::test_clean_pass_return_completes_booking_and_releases_collateral`

### AC-006.2: Damaged Item Enforces Held ID Guard
- **Given:** A booking where 1 item is marked `damaged` with penalty fee 200.
- **When:** An API request or staff attempts to call `POST /api/v1/bookings/{id}/collateral/release` before the 200 penalty is paid and without an owner-approved waiver.
- **Then:** Request fails with HTTP `422 Unprocessable Content` and error message: `"لا يمكن تسليم بطاقة الهوية قبل سداد الغرامة المستحقة"`.
- **Verification Test:** `CollateralGuardTest::test_release_collateral_blocked_when_penalty_unpaid`

### AC-006.3: Partial Return Validation
- **Given:** A booking with 4 items.
- **When:** Return payload includes inspection results for only 3 items.
- **Then:** Validation fails with error: `"يجب تحديد حالة جميع عناصر الحجز لإتمام الإرجاع"`.
- **Verification Test:** `ReturnValidationTest::test_incomplete_item_checklist_rejected`

### AC-006.4: Manual Penalty Assessment
- **Given:** An item is marked `damaged` or `missing` during inspection.
- **When:** Staff enters the penalty amount and the reason for the assessment.
- **Then:** Both values are recorded with the return; no fixed or suggested amount is applied automatically.
- **Verification Test:** `DamageAssessmentTest::test_manual_penalty_amount_and_reason_are_recorded`

### AC-006.5: Customer Refusal and Owner Waiver
- **Given:** A customer refuses to pay an assessed penalty.
- **When:** The return is recorded before the owner makes a decision.
- **Then:** The booking remains `damage_pending`, the collateral remains `held`, and the incident is recorded for owner review.
- **When:** The owner approves a waiver and no rental balance remains unpaid.
- **Then:** The waiver is recorded in the audit log, the penalty is marked waived, the booking can be completed, and the collateral can be released.
- **Verification Test:** `CollateralReleaseTest::test_refusal_requires_owner_decision_before_release`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- The system MUST NOT release an ID while a rental balance is unpaid or a penalty remains unpaid without an owner-approved, audited waiver ([return-inspection-workflow.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L33)).
- The system MUST NOT refund deposits as cash collateral; advance payments are strictly credited against rental charges ([return-inspection-workflow.md:L52](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L52)).
- National ID numbers or details must not be wiped upon release; only `status` updates to `released` with timestamp and user ID ([return-inspection-workflow.md:L41](file:///d:/mind-ai/occasion-suit-bookings/intent/return-inspection-workflow.md#L41)).

---

## 6. Resolved Decisions

1. **Penalty Amount:** Staff enters the amount and reason manually for each damaged or missing item. No fixed catalog or suggested default amounts are assumed in MVP.
2. **Customer Refusal:** The booking remains `damage_pending` and the ID remains `held` until payment or a documented owner-approved waiver. Any other unpaid rental balance still blocks ID release.
