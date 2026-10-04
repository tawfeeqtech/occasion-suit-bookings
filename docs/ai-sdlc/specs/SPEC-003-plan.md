# PLAN-003: Arabic RTL Filament Dashboard, Inventory and Staff Management

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-003.md`
- Source status: Approved / Customized
- Approval: Approved

## Goal and Scope
Build the Laravel Filament admin panel in Arabic/RTL with tenant-scoped inventory management, staff account administration (including `telegram_user_id` assignment for whitelist checking), a filtered bookings list, manual booking creation, return & inspection interface, basic financial summaries, and tenant settings.

## External Workstream (Excluded)
- Telegram bot, interactive emulator/simulator, and audio handling (all handled exclusively in n8n).

## Constraints
- Complete Arabic (`ar`) interface with native RTL layout.
- Tenant scoping applied automatically to all Filament queries and forms.
- Role policies: Staff can view inventory, process bookings/returns, but cannot access financial reports, tenant settings, audit logs, or manage other staff accounts.
- Staff accounts include a `telegram_user_id` field to support the n8n whitelist match.

## Implementation Steps
1. Install and configure Filament panel (`admin`) with Arabic locale, RTL direction, and elegant typography (Cairo/Tajawal).
2. Configure tenant-aware panel navigation and role policies (`owner` vs `staff`).
3. Create `ItemResource` for inventory management (suits, shirts, shoes, accessories) with JSONB dynamic attributes.
4. Create `UserResource` for staff management: add/edit staff, activate/deactivate, and assign `telegram_user_id`.
5. Create `BookingResource`:
   - List view with date and status filters.
   - Manual booking creation form with client details and manual pricing.
   - Return and inspection action interface with item checklist, manual penalty assessment, and collateral release button.
6. Create `TenantSettings` page for buffer hours and shop currency.
7. Add financial dashboard widgets and read-only audit log resource (`AuditLogResource`).
8. Write tests for RTL rendering, Filament resources, authorization barriers, and staff telegram ID persistence.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-003.1 | Inventory form persists JSONB custom fields | `ItemResourceTest::test_can_create_item_with_dynamic_custom_fields` |
| AC-003.2 | Staff management includes `telegram_user_id` assignment | `UserResourceTest::test_owner_can_manage_staff_accounts` |
| AC-003.3 | Configure Arabic locale and RTL panel output | `AdminDashboardLocalizationTest::test_admin_html_has_rtl_and_arabic_locale` |
| AC-003.4 | Enforce resource policies on navigation and direct URLs | `AdminPanelAuthorizationTest::test_staff_cannot_view_restricted_resources` |