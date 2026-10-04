# PLAN-002: Laravel Authentication, RBAC & Staff Telegram Matching

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-002.md`
- Source status: Approved / Customized
- Approval: Approved

## Goal and Scope
Implement Laravel web authentication (email + password only, no 2FA TOTP), role-based authorization policies for Shop Owner and Staff, and Telegram staff verification by matching `telegram_user_id` against tenant staff records.

## External Workstream (Excluded)
- Telegram bot, Telegram Bot API webhook, interactive emulator/simulator, and audio handling (all handled exclusively in n8n).

## Constraints
- Password-only authentication for Web Admin / Filament.
- Staff telegram ID matching middleware for API endpoints.
- Role checks: `owner` (full access), `staff` (operational only; financials and settings forbidden).
- Active flag checking: inactive users cannot log in or authenticate via API.

## Implementation Steps
1. Create `users` migration with UUID, `tenant_id`, `role`, `telegram_user_id`, `is_active`.
2. Configure authentication guards and password hashing.
3. Implement `TelegramStaffMiddleware` for API routes to validate `telegram_user_id` and attach current tenant/staff context.
4. Implement authorization policies (`BookingPolicy`, `ReportPolicy`, `UserPolicy`, `ItemPolicy`) to restrict Staff from financials, settings, and staff deletion.
5. Create Filament resources for user/staff management including assigning `telegram_user_id`.
6. Add unit and feature tests: `AuthenticationFeatureTest`, `TelegramWhitelistAuthTest`, `AuthorizationStaffPolicyTest`.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-002.1 | Email/password login into Filament | `AuthenticationFeatureTest::test_successful_login_with_valid_credentials` |
| AC-002.2 | Reject unknown telegram user ID | `TelegramWhitelistAuthTest::test_unauthorized_telegram_sender_rejected` |
| AC-002.3 | Authorize known staff telegram user ID | `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated` |
| AC-002.4 | Deny staff access to financials | `AuthorizationStaffPolicyTest::test_staff_forbidden_from_financial_reports` |
| AC-002.5 | Reject inactive accounts | `AuthenticationFeatureTest::test_inactive_user_cannot_authenticate` |