# SPEC-005: Automated Cleaning Buffer Management

## 1. Metadata
- **Specification ID:** SPEC-005
- **Title:** Automated Cleaning Buffer & Maintenance Lifecycle
- **Source Intent:** [intent/cleaning-buffer-management.md](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** `item_maintenance` Table, Availability Engine Buffer Calculations, Scheduled Console Command (`app:release-cleaning-buffers`)

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-005-01 | [cleaning-buffer-management.md:L22](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L22) | Clean-pass returned items transition immediately to `cleaning` status | `ReturnServiceTest::test_returned_item_enters_cleaning_status` |
| REQ-005-02 | [cleaning-buffer-management.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L23) | `expected_ready_at` is calculated using the tenant's configured `buffer_hours` | `ItemMaintenanceTest::test_calculates_expected_ready_time_from_tenant_settings` |
| REQ-005-03 | [cleaning-buffer-management.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L24) | Any attempt to reserve an item during its cleaning buffer is blocked | `AvailabilityServiceTest::test_item_cannot_be_booked_during_buffer_period` |
| REQ-005-04 | [cleaning-buffer-management.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L25) | Background scheduled task automatically transitions items from `cleaning` to `available` when `now() >= expected_ready_at` | `ReleaseCleaningBuffersCommandTest::test_expired_buffer_items_auto_release_to_available` |
| REQ-005-05 | [cleaning-buffer-management.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L26) | Staff/Owner can manually extend maintenance duration if repairs are needed | `ItemMaintenanceTest::test_can_manually_extend_maintenance_duration` |
| REQ-005-06 | [cleaning-buffer-management.md:L38](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L38) | Availability failure responses return the exact datetime when the item becomes ready | `AvailabilityCheckApiTest::test_conflict_response_includes_ready_timestamp` |
| REQ-005-07 | [cleaning-buffer-management.md:L40](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L40) | Buffer hours can be modified per tenant via settings | `TenantSettingsTest::test_can_update_buffer_hours_setting` |
| REQ-005-08 | [cleaning-buffer-management.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L27) | A single tenant-level cleaning duration applies to all items; category-specific overrides are not supported in MVP | `TenantSettingsTest::test_one_buffer_duration_applies_to_all_items` |
| REQ-005-09 | [cleaning-buffer-management.md:L28](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L28) | The dashboard displays item status and expected readiness time; no automatic ready notification is sent in MVP | `ItemReadinessDashboardTest::test_ready_time_is_visible_without_notification` |

---

## 3. Technical Contract & Schema

### 3.1 Item Maintenance Schema
```sql
CREATE TABLE item_maintenance (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    item_id UUID NOT NULL REFERENCES items(id) ON DELETE CASCADE,
    booking_id UUID REFERENCES bookings(id),
    status VARCHAR(50) NOT NULL DEFAULT 'cleaning', -- 'cleaning' | 'maintenance' | 'completed'
    started_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expected_ready_at TIMESTAMPTZ NOT NULL,
    actual_ready_at TIMESTAMPTZ,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_item_maintenance_ready ON item_maintenance(tenant_id, status, expected_ready_at);
```

### 3.2 Scheduled Worker Contract
- **Command:** `php artisan app:release-cleaning-buffers`
- **Schedule:** Runs every 15 minutes (`schedule->command(...)->everyFifteenMinutes()`).
- **Operation:**
  1. Queries all `item_maintenance` records where `status = 'cleaning'` and `expected_ready_at <= NOW()`.
  2. Updates `item_maintenance.status = 'completed'` and `actual_ready_at = NOW()`.
  3. Updates associated `items.status = 'available'`.
  4. Records activity in `audit_logs` (`actor: system`, `action: buffer.released`).

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-005.1: Automatic Maintenance Record on Return
- **Given:** A booking with an active suit and tenant setting `buffer_hours = 48`.
- **When:** Return is submitted on `2026-10-10 12:00:00`.
- **Then:** `item_maintenance` record is inserted with `status = 'cleaning'` and `expected_ready_at = '2026-10-12 12:00:00'`.
- **Verification Test:** `ReturnServiceTest::test_item_maintenance_record_created_with_48h_offset`

### AC-005.2: Rejection of Booking During Active Buffer
- **Given:** Item is in `cleaning` status with `expected_ready_at = 2026-10-12 12:00:00`.
- **When:** Booking is requested for pickup on `2026-10-11`.
- **Then:** Validation fails with message: `"العنصر قيد التنظيف والتعقيم حتى 2026-10-12 12:00"`.
- **Verification Test:** `AvailabilityServiceTest::test_booking_rejected_when_pickup_before_ready_date`

### AC-005.3: Automated Buffer Expiry Execution
- **Given:** 3 items with `status = 'cleaning'` where `expected_ready_at` is 1 hour in the past.
- **When:** `ReleaseCleaningBuffersCommand` is executed via Artisan.
- **Then:** All 3 items are updated to `status = 'available'` and their maintenance records marked `completed`.
- **Verification Test:** `ReleaseCleaningBuffersCommandTest::test_artisan_command_releases_due_items`

### AC-005.4: Manual Extension of Maintenance
- **Given:** An item in `cleaning` requiring fabric stitching.
- **When:** Staff calls `POST /api/v1/items/{id}/extend-maintenance` adding 24 hours.
- **Then:** `expected_ready_at` is pushed forward by 24 hours and status changed to `maintenance`.
- **Verification Test:** `ItemMaintenanceTest::test_extend_maintenance_updates_expected_ready_at`

### AC-005.5: Single Tenant-Level Buffer Duration
- **Given:** A tenant has configured `buffer_hours = 48`.
- **When:** Any item category enters cleaning after return.
- **Then:** The same 48-hour duration is applied; category-specific buffer overrides are not supported in MVP.
- **Verification Test:** `TenantSettingsTest::test_one_buffer_duration_applies_to_all_items`

### AC-005.6: Readiness Visible Without Automatic Notification
- **Given:** An item has an `expected_ready_at` value.
- **When:** An owner or staff member views the item in the dashboard.
- **Then:** Its current status and expected readiness time are visible, and no automatic notification is sent when it becomes available.
- **Verification Test:** `ItemReadinessDashboardTest::test_ready_time_is_visible_without_notification`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- No item in `cleaning` or `maintenance` may be forced into an active booking by staff bypass ([cleaning-buffer-management.md:L32](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L32)).
- Expired buffer items MUST NOT remain locked indefinitely if the scheduled worker fails; fallback check in `AvailabilityService` must verify real-time timestamp even if cron was delayed ([cleaning-buffer-management.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/cleaning-buffer-management.md#L33)).

---

## 6. Resolved Decisions

1. **Cleaning Duration:** A single tenant-level `buffer_hours` setting applies to all items in MVP. Category-specific overrides are not supported; individual items may be extended manually when maintenance is needed.
2. **Readiness Notification:** The dashboard shows the item status and expected readiness time. Automatic notifications are not included in MVP.
