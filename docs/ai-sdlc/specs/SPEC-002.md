# SPEC-002: Role-Based Access Control & Staff Telegram Whitelist

## 1. Metadata
- **Specification ID:** SPEC-002
- **Title:** RBAC, Web Authentication & Staff Telegram Whitelist
- **Source Intent:** [intent/rbac-authentication.md](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md)
- **Status:** Approved / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Web Authentication (Email & Password), RBAC Middleware/Policies, Staff Telegram User ID Whitelist

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-002-01 | [rbac-authentication.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L23) | Web Admin authentication via email + password (no mandatory 2FA TOTP) | `AuthenticationFeatureTest::test_user_can_login_with_valid_credentials` |
| REQ-002-02 | [rbac-authentication.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L24) | API requests on behalf of Telegram staff validated by matching registered `telegram_user_id` for that tenant | `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated` |
| REQ-002-03 | [rbac-authentication.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L25) | `owner` role has full permissions across inventory, finances, audit logs, staff management, settings | `AuthorizationOwnerPolicyTest::test_owner_can_access_all_resources` |
| REQ-002-04 | [rbac-authentication.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L26) | `staff` role has restricted permissions (create bookings, check availability, process returns, view daily schedule) | `AuthorizationStaffPolicyTest::test_staff_can_perform_operational_tasks` |
| REQ-002-05 | [rbac-authentication.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L27) | Unregistered or inactive Telegram user IDs rejected immediately with 401/403 | `TelegramWhitelistAuthTest::test_non_whitelisted_telegram_user_is_rejected` |
| REQ-002-06 | [rbac-authentication.md:L34](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L34) | Staff cannot access financial reports, tenant settings, or staff management | `AuthorizationStaffPolicyTest::test_staff_cannot_access_financials_or_settings` |
| REQ-002-07 | [rbac-authentication.md:L53](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L53) | Telegram user IDs managed in Web Admin dashboard by Owner per staff member | `TelegramWhitelistManagementTest::test_owner_can_manage_telegram_whitelist` |
| REQ-002-08 | [rbac-authentication.md:L29](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L29) | MVP supports platform `system_admin`, tenant `owner` and `staff` roles; custom permissions are deferred | `AuthorizationRoleTest::test_mvp_roles_system_admin_owner_staff` |
| REQ-002-09 | [rbac-authentication.md:L30](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L30) | Owners who forget their password contact the System Admin; self-service email reset is not available in MVP | `OwnerPasswordResetTest::test_owner_reset_requires_system_admin` |

---

## 3. Technical Contract & Schema

### 3.1 Users & Roles Contract
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY,
    tenant_id UUID REFERENCES tenants(id) ON DELETE CASCADE, -- NULL for platform system_admin
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'staff' CHECK (role IN ('system_admin', 'owner', 'staff')),
    telegram_user_id BIGINT UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    remember_token VARCHAR(100),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Authorization Matrix

| Action / Resource | System Admin | Owner Role | Staff Role | Unauthenticated |
|---|---|---|---|---|
| Manage Shops / Tenants | ALLOW | DENY (403) | DENY (403) | DENY (401) |
| Manage Subscriptions | ALLOW | DENY (403) | DENY (403) | DENY (401) |
| View Inventory | (Explicit context) | ALLOW | ALLOW | DENY (401) |
| Create / Edit Inventory | DENY | ALLOW | ALLOW | DENY (401) |
| Delete Inventory | DENY | ALLOW | DENY (403) | DENY (401) |
| Create Booking / Return | DENY | ALLOW | ALLOW | DENY (401) |
| View Financial Reports | DENY | ALLOW | DENY (403) | DENY (401) |
| View Audit Logs | (Platform logs) | ALLOW (Tenant) | DENY (403) | DENY (401) |
| Manage Staff & Telegram IDs | DENY | ALLOW | DENY (403) | DENY (401) |
| Edit Tenant Settings | DENY | ALLOW | DENY (403) | DENY (401) |

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-002.1: Web Login & Session Creation
- **Given:** A valid user with email `owner@shop.com` and password `secret123`.
- **When:** Login form is submitted with valid credentials.
- **Then:** User is authenticated into session and redirected to the Filament dashboard.
- **Verification Test:** `AuthenticationFeatureTest::test_successful_login_with_valid_credentials`

### AC-002.2: Telegram Staff Whitelist Verification
- **Given:** Telegram user ID `778899` is NOT registered in `users.telegram_user_id` for the tenant.
- **When:** An incoming n8n API request is received with `telegram_user_id: 778899`.
- **Then:** HTTP status is `401/403 Unauthorized` and request is rejected without processing.
- **Verification Test:** `TelegramWhitelistAuthTest::test_unauthorized_telegram_sender_rejected`

### AC-002.3: Whitelisted Telegram Staff Access
- **Given:** Telegram user ID `12345678` is registered to active staff member "Ahmad" in Tenant A.
- **When:** An API request is received with `telegram_user_id: 12345678`.
- **Then:** The request is authorized within Tenant A's context and linked to Ahmad.
- **Verification Test:** `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated`

### AC-002.4: Staff Forbidden from Financial Endpoints
- **Given:** An authenticated user with role `staff`.
- **When:** Requesting financial reports or accessing financial views in Filament.
- **Then:** Response status is `403 Forbidden`.
- **Verification Test:** `AuthorizationStaffPolicyTest::test_staff_forbidden_from_financial_reports`

### AC-002.5: Inactive Users Blocked
- **Given:** An inactive user (`is_active = false`).
- **When:** Attempting web login or passing their `telegram_user_id` via API.
- **Then:** Access is rejected.
- **Verification Test:** `AuthenticationFeatureTest::test_inactive_user_cannot_authenticate`
