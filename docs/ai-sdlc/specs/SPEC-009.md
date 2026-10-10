# SPEC-009: Shop and Owner Onboarding

## 1. Metadata
- **Specification ID:** SPEC-009
- **Title:** Atomic Shop Creation, Owner Provisioning, and Tenant Assignment
- **Source Intent:** [intent/shop-owner-onboarding.md](../../../intent/shop-owner-onboarding.md)
- **Status:** Approved / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** System Admin Filament workflow, tenant-owner association, staff tenant assignment, initial credentials, Telegram tenant context

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-009-01 | [shop-owner-onboarding.md:L24](../../../intent/shop-owner-onboarding.md#L24), [L25](../../../intent/shop-owner-onboarding.md#L25) | System Admin creates a shop and its owner account together; both persist or neither persists | `TenantOwnerOnboardingTest::test_shop_and_owner_are_created_atomically` |
| REQ-009-02 | [shop-owner-onboarding.md:L24](../../../intent/shop-owner-onboarding.md#L24) | Owner account is assigned to the newly created shop using its `tenant_id` | `TenantOwnerOnboardingTest::test_owner_is_bound_to_new_tenant` |
| REQ-009-03 | [shop-owner-onboarding.md:L26](../../../intent/shop-owner-onboarding.md#L26) | Initial owner password is set by the System Admin and stored as a hash | `TenantOwnerOnboardingTest::test_initial_owner_password_is_hashed` |
| REQ-009-04 | [shop-owner-onboarding.md:L27](../../../intent/shop-owner-onboarding.md#L27) | After login, the owner is scoped to their associated tenant without a separate assignment step | `TenantOwnerOnboardingTest::test_owner_login_uses_assigned_tenant_context` |
| REQ-009-05 | [shop-owner-onboarding.md:L28](../../../intent/shop-owner-onboarding.md#L28) | Owner-created staff inherit the owner's tenant; System Admin-created staff require an explicit tenant selection | `TenantStaffAssignmentTest::test_owner_staff_inherits_owner_tenant` and `TenantStaffAssignmentTest::test_system_admin_must_select_staff_tenant` |
| REQ-009-06 | [shop-owner-onboarding.md:L29](../../../intent/shop-owner-onboarding.md#L29), [L33](../../../intent/shop-owner-onboarding.md#L33) | Each shop has at most one active owner; only the System Admin can create a replacement after deactivation, and inactive owner records are retained | `TenantOwnerOnboardingTest::test_only_one_active_owner_per_shop` and `test_system_admin_can_replace_deactivated_owner` |
| REQ-009-07 | [shop-owner-onboarding.md:L29](../../../intent/shop-owner-onboarding.md#L29), [L30](../../../intent/shop-owner-onboarding.md#L30) | Telegram-authenticated staff requests use their assigned tenant, and a Telegram ID is not shared across shops in MVP | `TelegramWhitelistAuthTest::test_telegram_staff_request_uses_assigned_tenant` and `test_telegram_id_cannot_be_reused_across_shops` |
| REQ-009-08 | [shop-owner-onboarding.md:L31](../../../intent/shop-owner-onboarding.md#L31) | Slug is a unique readable shop identifier and is not used to associate or authorize a user | `TenantOwnerOnboardingTest::test_slug_does_not_determine_user_tenant` |
| REQ-009-09 | [shop-owner-onboarding.md:L33](../../../intent/shop-owner-onboarding.md#L33) | Shop Owners cannot create owner accounts or promote staff to owner | `TenantStaffAssignmentTest::test_owner_cannot_create_or_promote_owner` |

## 3. Technical Contract

- `tenants.id` remains the tenant's internal UUID and the value stored in `users.tenant_id`.
- `tenants.slug` remains unique and human-readable; it is not an authentication credential, tenant authorization boundary, or replacement for `tenant_id`.
- The account created during shop onboarding has the `owner` role.
- In MVP, each tenant has at most one active owner. Deactivated former owner accounts remain stored; only the System Admin may create a replacement, and only after the current owner has been deactivated.
- Shop Owners may create/manage staff, but cannot create another owner or promote a staff account to owner.
- Each owner account belongs to one shop, each Telegram ID is assigned to one shop only, and shops do not have branch records.
- Supporting one owner across multiple shops and multiple branches per shop is deferred until after MVP.
- Shop creation and owner creation must share one database transaction. Failure to persist either record must leave neither record committed.
- A Shop Owner may create staff only within the authenticated owner's tenant. The System Admin must choose a tenant when creating a staff account.
- The System Admin provides the initial password to the owner outside the application. Persist only the password hash; never expose it in logs or audit metadata.
- Telegram authentication resolves the staff account first and then establishes tenant context from that account's `tenant_id`.

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-009.1: Atomic Shop and Owner Creation
- **Given:** An authenticated System Admin submits valid shop details and owner credentials.
- **When:** The create-shop workflow completes.
- **Then:** Exactly one tenant and one owner are persisted, and the owner's `tenant_id` equals the tenant's UUID.
- **Verification Test:** `TenantOwnerOnboardingTest::test_shop_and_owner_are_created_atomically`

### AC-009.2: Roll Back When Owner Provisioning Fails
- **Given:** An authenticated System Admin submits new shop details with an owner email that violates the global email uniqueness constraint.
- **When:** The create-shop workflow attempts to persist both records.
- **Then:** The transaction rolls back and no tenant for that submission remains in the database.
- **Verification Test:** `TenantOwnerOnboardingTest::test_tenant_creation_rolls_back_when_owner_creation_fails`

### AC-009.3: Owner Login Uses Tenant Assignment
- **Given:** An active owner account is assigned to Tenant A and Tenant B contains separate data.
- **When:** The owner authenticates to the web dashboard.
- **Then:** The authenticated context resolves Tenant A from the owner's `tenant_id` and does not expose Tenant B's data.
- **Verification Test:** `TenantOwnerOnboardingTest::test_owner_login_uses_assigned_tenant_context`

### AC-009.4: Tenant-Safe Staff Creation
- **Given:** An owner assigned to Tenant A creates a staff account, or a System Admin creates a staff account.
- **When:** The account is saved.
- **Then:** Owner-created staff are assigned Tenant A automatically, while System Admin-created staff cannot be saved without an explicit tenant selection.
- **Verification Tests:** `TenantStaffAssignmentTest::test_owner_staff_inherits_owner_tenant` and `TenantStaffAssignmentTest::test_system_admin_must_select_staff_tenant`

### AC-009.5: Slug Is Not the User-Tenant Link
- **Given:** An owner and staff accounts are associated with a tenant by UUID.
- **When:** The tenant's slug is changed while maintaining its uniqueness.
- **Then:** The user `tenant_id` values and effective tenant access remain unchanged.
- **Verification Test:** `TenantOwnerOnboardingTest::test_slug_does_not_determine_user_tenant`

### AC-009.6: Telegram Request Uses Staff Tenant
- **Given:** An active staff member has a registered Telegram user ID and is assigned to Tenant A.
- **When:** A valid API request is authenticated using that Telegram user ID.
- **Then:** The request is processed with Tenant A context only.
- **Verification Test:** `TelegramWhitelistAuthTest::test_telegram_staff_request_uses_assigned_tenant`

### AC-009.7: One Active Owner and Admin-Only Replacement
- **Given:** Tenant A has an active owner.
- **When:** A Shop Owner attempts to create another owner or promote a staff account, or a System Admin attempts to create a replacement before deactivating the current owner.
- **Then:** The operation is rejected; after the System Admin deactivates the current owner, the System Admin can create one replacement while the prior account remains stored and inactive.
- **Verification Tests:** `TenantOwnerOnboardingTest::test_only_one_active_owner_per_shop`, `test_system_admin_can_replace_deactivated_owner`, and `TenantStaffAssignmentTest::test_owner_cannot_create_or_promote_owner`

## 5. Negative Boundaries

- The System Admin cannot complete the shop-onboarding flow with a tenant but no owner, or an owner with no tenant.
- An owner cannot assign a staff member to a different tenant by submitting a foreign `tenant_id`.
- A tenant cannot have more than one active owner; owners cannot create/promote owner accounts, and only the System Admin can replace a deactivated owner.
- Tenant access must not be inferred from or authorized by the slug.
- The plain-text initial password must not be persisted or logged.
- The System Admin's platform context must not accidentally scope tenant creation to an existing shop.

## 6. Deferred Scope and Decisions

- Multi-shop ownership and multiple branches per shop are deferred until after MVP.
- Telegram IDs are associated with one shop only in MVP. The current global uniqueness constraint on `users.telegram_user_id` matches this rule.
- Each shop has at most one active owner; a replacement can be provisioned only by the System Admin after deactivation of the current owner.
- This specification is approved based on the approved source intent and decisions recorded above.
