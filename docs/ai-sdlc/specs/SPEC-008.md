# SPEC-008: Audit Logging & Traceability

## 1. Metadata
- **Specification ID:** SPEC-008
- **Title:** Append-Only Audit Trail & Activity Logging
- **Source Intent:** [intent/audit-logging.md](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** `spatie/laravel-activitylog`, Immutable Database Schema, Actor Attribution, Owner Audit Viewer

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-008-01 | [audit-logging.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L23) | Automatically logs sensitive actions: `booking.created`, `booking.updated`, `return.processed`, `collateral.released`, `penalty.settled` | `AuditLogEventsTest::test_critical_domain_events_trigger_audit_log` |
| REQ-008-02 | [audit-logging.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L24) | Every audit entry captures timestamp, actor type, actor ID, action name, entity ID, and metadata | `AuditLogStructureTest::test_audit_entry_contains_all_required_attributes` |
| REQ-008-03 | [audit-logging.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L25) | Audit log viewable and filterable by actor, action type, and date range in Admin Dashboard | `AuditLogResourceTest::test_audit_logs_table_filters` |
| REQ-008-04 | [audit-logging.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L26) | Audit logs are strictly append-only; update and delete operations are prohibited | `AuditLogImmutabilityTest::test_audit_logs_cannot_be_updated_or_deleted` |
| REQ-008-05 | [audit-logging.md:L32](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L32) | Staff role users are forbidden from viewing or accessing audit logs | `AuditLogAuthorizationTest::test_staff_cannot_view_audit_logs` |
| REQ-008-06 | [audit-logging.md:L37](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L37) | Querying audit logs by user name or date range returns accurate results | `AuditLogSearchTest::test_search_by_actor_and_date` |

---

## 3. Technical Contract & Schema

### 3.1 Audit Logs Schema
```sql
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    actor_type VARCHAR(50) NOT NULL, -- 'user' | 'system'
    actor_id VARCHAR(255) NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id UUID,
    metadata JSONB DEFAULT '{}',
    ip_address VARCHAR(45),
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX idx_audit_logs_tenant_created ON audit_logs(tenant_id, created_at DESC);
CREATE INDEX idx_audit_logs_actor ON audit_logs(tenant_id, actor_id);
```

### 3.2 Immutability Guard (Eloquent Model)
The `AuditLog` model MUST override `delete()` and `update()` methods:
```php
public function update(array $attributes = [], array $options = []): bool
{
    throw new \RuntimeException('Audit logs are immutable and cannot be updated.');
}

public function delete(): ?bool
{
    throw new \RuntimeException('Audit logs are immutable and cannot be deleted.');
}
```

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-008.1: Automatic Booking Creation Audit Record
- **Given:** A staff member with user ID `staff-uuid-1` confirms a booking via Telegram or web.
- **When:** Booking is persisted to database.
- **Then:** An `audit_logs` entry exists with `action = 'booking.created'`, `entity_type = 'Booking'`, `actor_id = 'staff-uuid-1'`, and metadata containing customer name and item count.
- **Verification Test:** `AuditLogEventsTest::test_booking_creation_records_audit_trail`

### AC-008.2: Immutability Enforcement
- **Given:** An existing audit log entry in the database.
- **When:** An Eloquent call `AuditLog::first()->update(['action' => 'modified'])` or `delete()` is made.
- **Then:** A `RuntimeException` is thrown and the record remains unchanged in the database.
- **Verification Test:** `AuditLogImmutabilityTest::test_model_throws_exception_on_update_or_delete`

### AC-008.3: Collateral Release Audit Capture
- **Given:** Staff releases National ID for booking `B-100`.
- **When:** `CollateralService::release()` executes.
- **Then:** An audit log entry is saved with `action = 'collateral.id_released'` including staff identity and exact timestamp.
- **Verification Test:** `AuditLogEventsTest::test_id_release_triggers_audit_entry`

### AC-008.4: Multi-Tenant Audit Isolation
- **Given:** Audit logs exist for `Tenant_A` and `Tenant_B`.
- **When:** Owner of `Tenant_A` views audit logs in Filament.
- **Then:** Only records matching `tenant_id = Tenant_A` are retrieved.
- **Verification Test:** `AuditLogIsolationTest::test_owner_only_sees_own_tenant_audit_logs`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- No UI controls (delete buttons, edit forms) shall exist for audit logs in Filament ([audit-logging.md:L31](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L31)).
- System operations (e.g. cleaning buffer release) MUST NOT be logged as anonymous; they must explicitly record `actor_type = 'system'` and `actor_id = 'scheduler'` ([audit-logging.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L24)).
- Audit logging failures MUST NOT silently fail; if the audit trail write fails, the parent transaction should roll back to prevent unlogged mutations ([audit-logging.md:L30](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md#L30)).

---

## 6. Resolved Decisions

- **Data Retention Policy:** Audit logs are retained indefinitely in MVP v1.0 and remain append-only. If storage pressure becomes significant later, a separate archive/pruning policy may be introduced in a future phase; it is not an MVP requirement.
- **Real-Time High-Penalty Alerts:** Real-time notifications for high-penalty events are out of scope for MVP. The owner reviews risk and penalty activity through the admin dashboard and daily financial reports instead.

These decisions are now resolved in [intent/audit-logging.md](file:///d:/mind-ai/occasion-suit-bookings/intent/audit-logging.md) and are considered final for MVP scope.
