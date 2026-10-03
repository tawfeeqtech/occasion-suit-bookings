# SPEC-002: Role-Based Access Control & Authentication

## 1. Metadata
- **Specification ID:** SPEC-002
- **Title:** RBAC, 2FA Authentication & Telegram Whitelist
- **Source Intent:** [intent/rbac-authentication.md](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Fortify Auth, TOTP 2FA, RBAC Middleware/Policies, Telegram Bot Auth Middleware

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-002-01 | [rbac-authentication.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L23) | Web Admin authentication via email + password with optional TOTP 2FA | `AuthenticationFeatureTest::test_user_can_login_with_valid_credentials` |
| REQ-002-02 | [rbac-authentication.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L23) | 2FA challenge required when TOTP enabled for user | `TwoFactorAuthenticationTest::test_2fa_challenge_prompted_when_enabled` |
| REQ-002-03 | [rbac-authentication.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L24) | Telegram bot access authenticated against tenant whitelist by `telegram_user_id` | `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated` |
| REQ-002-04 | [rbac-authentication.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L25) | `owner` role has full permissions across inventory, finances, audit logs, staff management, settings | `AuthorizationOwnerPolicyTest::test_owner_can_access_all_resources` |
| REQ-002-05 | [rbac-authentication.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L26) | `staff` role has restricted permissions (create bookings, check availability, process returns, view daily schedule) | `AuthorizationStaffPolicyTest::test_staff_can_perform_operational_tasks` |
| REQ-002-06 | [rbac-authentication.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L27) | Non-whitelisted Telegram requests rejected immediately with 401/authorization error | `TelegramWhitelistAuthTest::test_non_whitelisted_telegram_user_is_rejected` |
| REQ-002-07 | [rbac-authentication.md:L34](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L34) | Staff cannot access financial reports, tenant settings, or staff management | `AuthorizationStaffPolicyTest::test_staff_cannot_access_financials_or_settings` |
| REQ-002-08 | [rbac-authentication.md:L53](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L53) | Telegram whitelist CRUD managed from the Web Admin dashboard by Owner | `TelegramWhitelistManagementTest::test_owner_can_manage_telegram_whitelist` |
| REQ-002-09 | [rbac-authentication.md:L29](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L29) | MVP supports only `owner` and `staff`; custom staff roles and permissions are deferred beyond MVP | `AuthorizationRoleTest::test_mvp_accepts_only_owner_and_staff_roles` |
| REQ-002-10 | [rbac-authentication.md:L30](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L30) | Owners who forget their password contact the System Admin; self-service email reset is not available in MVP | `OwnerPasswordResetTest::test_owner_reset_requires_system_admin` |

---

## 3. Technical Contract & Schema

### 3.1 Users & Roles Contract
```sql
CREATE TABLE users (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL DEFAULT 'staff' CHECK (role IN ('owner', 'staff')), -- MVP roles only
    two_factor_secret TEXT,
    two_factor_recovery_codes TEXT,
    two_factor_confirmed_at TIMESTAMPTZ,
    telegram_user_id BIGINT UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Authorization Matrix

| Action / Resource | Owner Role | Staff Role | Unauthenticated |
|---|---|---|---|
| View Inventory | ALLOW | ALLOW | DENY (401) |
| Create / Edit Inventory | ALLOW | ALLOW | DENY (401) |
| Delete Inventory | ALLOW | DENY (403) | DENY (401) |
| Create Booking / Return | ALLOW | ALLOW | DENY (401) |
| View Financial Reports | ALLOW | DENY (403) | DENY (401) |
| View Audit Logs | ALLOW | DENY (403) | DENY (401) |
| Manage Staff & Telegram Whitelist | ALLOW | DENY (403) | DENY (401) |
| Edit Tenant Settings | ALLOW | DENY (403) | DENY (401) |

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-002.1: Fortify Login & Session Creation
- **Given:** A valid user with email `owner@shop.com` and password `secret123`.
- **When:** `POST /login` is submitted with valid credentials.
- **Then:** HTTP status is `200` (or `302 Redirect` to dashboard) and user is authenticated into session.
- **Verification Test:** `AuthenticationFeatureTest::test_successful_login_with_valid_credentials`

### AC-002.2: 2FA TOTP Enforcement
- **Given:** A user with `two_factor_confirmed_at` populated.
- **When:** User completes password login.
- **Then:** User is redirected to `/two-factor-challenge` and full dashboard access is blocked until valid TOTP code is provided.
- **Verification Test:** `TwoFactorAuthenticationTest::test_2fa_challenge_enforced_before_dashboard_access`

### AC-002.3: Telegram Whitelist Verification
- **Given:** Telegram user ID `778899` is NOT present in `users.telegram_user_id` for the tenant.
- **When:** An incoming Telegram webhook or bot request is received with sender ID `778899`.
- **Then:** HTTP status is `401 Unauthorized` and message payload is not forwarded to AI or booking pipeline.
- **Verification Test:** `TelegramWhitelistAuthTest::test_unauthorized_telegram_sender_rejected`

### AC-002.4: Staff Forbidden from Financial Endpoints
- **Given:** An authenticated user with role `staff`.
- **When:** Requesting `GET /api/v1/reports/financial` or accessing financial views in Filament.
- **Then:** Response status is `403 Forbidden`.
- **Verification Test:** `AuthorizationStaffPolicyTest::test_staff_forbidden_from_financial_reports`

### AC-002.5: Fixed MVP Roles
- **Given:** A user account is created or its role is changed.
- **When:** A role other than `owner` or `staff` is submitted.
- **Then:** The role is rejected; custom staff roles and permissions are deferred beyond MVP.
- **Verification Test:** `AuthorizationRoleTest::test_mvp_accepts_only_owner_and_staff_roles`

### AC-002.6: Owner Password Reset Through System Admin
- **Given:** An owner has forgotten their password.
- **When:** They request help from the System Admin to reset it.
- **Then:** The reset is handled through the System Admin; no self-service email reset flow is offered in MVP.
- **Verification Test:** `OwnerPasswordResetTest::test_owner_reset_requires_system_admin`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- Inactive users (`is_active = false`) MUST NOT be able to log in or execute Telegram bot commands ([rbac-authentication.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L33)).
- Staff MUST NOT see aggregate revenue, profit, or outstanding shop financial summaries ([rbac-authentication.md:L32](file:///d:/mind-ai/occasion-suit-bookings/intent/rbac-authentication.md#L32)).
- Bot messages from groups or channels MUST be rejected; only direct messages (1-on-1 DM) are supported ([AGENTS.md:L16](file:///d:/mind-ai/occasion-suit-bookings/AGENTS.md#L16)).

---

## 6. Resolved Decisions

1. **MVP Roles:** Only `owner` and `staff` are supported. Custom staff roles and granular permissions are deferred beyond MVP.
2. **Owner Password Reset:** The owner contacts the System Admin for a reset. Self-service email password reset is not part of MVP.
