# SPEC-003: Web Admin Dashboard (Filament Arabic/RTL)

## 1. Metadata
- **Specification ID:** SPEC-003
- **Title:** Arabic RTL Web Admin Dashboard & Inventory Management
- **Source Intent:** [intent/web-admin-dashboard.md](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Filament PHP v3/v4 Panel, RTL Direction, Arabic Localization, Inventory CRUD & Custom Fields

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-003-01 | [web-admin-dashboard.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L23) | Dashboard rendered in Arabic (`ar`) with native Right-to-Left (RTL) layout | `AdminDashboardLocalizationTest::test_admin_panel_is_rtl_and_arabic_locale` |
| REQ-003-02 | [web-admin-dashboard.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L24) | Inventory CRUD with categories (`suit`, `shirt`, `shoes`, `belt`, `tie`, `vest`, `lapel_pin`) and status states | `ItemResourceTest::test_owner_can_crud_inventory_items` |
| REQ-003-03 | [web-admin-dashboard.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L24) | Dynamic accessory bundles and custom fields stored in `items.custom_fields` JSONB | `ItemCustomFieldsTest::test_item_custom_fields_saved_in_jsonb` |
| REQ-003-04 | [web-admin-dashboard.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L25) | Staff management: create, update, activate/deactivate, assign roles and `telegram_user_id` | `UserResourceTest::test_owner_can_manage_staff_accounts` |
| REQ-003-05 | [web-admin-dashboard.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L26) | MVP bookings list with status badges, date filters, and customer details; interactive calendar deferred as P1 | `BookingResourceTest::test_bookings_list_and_filters` |
| REQ-003-06 | [web-admin-dashboard.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L27) | Basic financial summaries: daily/monthly revenue, outstanding receivables, and collected penalties | `FinancialReportResourceTest::test_financial_metrics_calculation` |
| REQ-003-07 | [web-admin-dashboard.md:L28](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L28) | Tenant settings management: buffer duration (hours), base pricing rules, and Telegram whitelist | `TenantSettingsResourceTest::test_owner_can_update_shop_settings` |
| REQ-003-08 | [web-admin-dashboard.md:L29](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L29) | Audit log viewer resource (read-only) with actor, event, and timestamp filters | `AuditLogResourceTest::test_audit_logs_are_viewable_and_filterable` |

---

## 3. Technical Contract & Schema

### 3.1 Items Table Contract
```sql
CREATE TABLE items (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    category VARCHAR(100) NOT NULL, -- 'suit' | 'shirt' | 'shoes' | 'belt' | 'tie' | 'vest' | 'lapel_pin'
    size VARCHAR(50),
    color VARCHAR(100),
    status VARCHAR(50) NOT NULL DEFAULT 'available', -- 'available' | 'booked' | 'cleaning' | 'maintenance' | 'retired'
    custom_fields JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Filament Configuration Contract
- Panel ID: `admin`
- Locale: `ar`
- Direction: `rtl`
- Fonts: Cairo or Tajawal (Google Fonts)
- Theme: Rich dark/light mode with customized primary colors.

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-003.1: Inventory Item Creation with Custom Fields
- **Given:** An owner creating a suit with size "48", color "Navy Blue", and custom field `{"lapel_style": "peak", "fabric": "wool"}`.
- **When:** Form is submitted via Filament `CreateItem` action.
- **Then:** Item record exists in DB with `custom_fields` stored properly as valid JSON.
- **Verification Test:** `ItemResourceTest::test_can_create_item_with_dynamic_custom_fields`

### AC-003.2: Item Status State Transitions
- **Given:** An item currently in status `cleaning`.
- **When:** An admin attempts to change status to `available` or extend `maintenance`.
- **Then:** Status is updated and an activity log entry is automatically recorded.
- **Verification Test:** `ItemStateTransitionTest::test_item_status_transition_records_audit`

### AC-003.3: RTL & Arabic Locale Enforcement
- **Given:** An authenticated request to the admin panel URL `/admin`.
- **When:** Response HTML is rendered.
- **Then:** HTML tag contains `dir="rtl"` and `lang="ar"`, with zero English labels in standard navigation.
- **Verification Test:** `AdminDashboardLocalizationTest::test_admin_html_has_rtl_and_arabic_locale`

### AC-003.4: Staff Access Restriction in Filament
- **Given:** A user with role `staff` logged into the Filament panel.
- **When:** Navigating to Financial Reports, Tenant Settings, or Audit Logs.
- **Then:** Navigation links are hidden, and direct URL access returns HTTP `403 Forbidden`.
- **Verification Test:** `AdminPanelAuthorizationTest::test_staff_cannot_view_restricted_resources`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- No English strings displayed on primary customer/staff flows ([web-admin-dashboard.md:L36](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L36)).
- Settings updates MUST NOT corrupt or drop unedited keys in `tenants.settings` JSONB ([web-admin-dashboard.md:L37](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L37)).
- No hardcoded CSS overrides that break mobile responsiveness ([web-admin-dashboard.md:L42](file:///d:/mind-ai/occasion-suit-bookings/intent/web-admin-dashboard.md#L42)).

---

## 6. Resolved Decisions

1. **Booking Calendar:** The MVP includes a bookings list with date filters. The interactive calendar is deferred as P1, consistent with [the MVP scope](../../mvp-scope.md).
2. **Advanced Reports:** The MVP includes basic financial summaries. Graphical charts and Excel export are deferred until after MVP, consistent with [the MVP scope](../../mvp-scope.md).
