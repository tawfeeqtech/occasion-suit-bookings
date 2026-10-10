# PLAN-009: Shop and Owner Onboarding

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-009.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement the Laravel/Filament flow for creating a shop together with its owner, authenticating the owner into the tenant attached to their account, and assigning staff safely to a shop.

The MVP supports at most one active owner per shop, with owner replacement restricted to the System Admin after deactivating the current owner. Each owner account and Telegram user ID belongs to one shop only. Multi-shop ownership and multiple branches per shop remain out of scope. The slug is a unique readable identifier only; `tenant_id` remains the authorization and data-isolation relationship.

## External Workstream (Excluded)
- Telegram bot commands, Telegram UI or shop-selection flow, n8n workflows, audio/speech recognition, and LLM orchestration.
- Laravel will continue accepting the registered Telegram user ID on API requests and derive tenant context from its assigned user account.

## Open Questions and Constraints
- Source SPEC and implementation plan are approved; implementation remains pending.
- `users.email` is globally unique. Shop creation must fail atomically if the owner email is already taken.
- Each tenant may have at most one active `owner`. A deactivated former owner record is retained; only the System Admin may create a replacement, and only after the former owner is inactive. Shop Owners may not create owner accounts or promote staff.
- Enforce the active-owner invariant in PostgreSQL and in the Filament workflow, so concurrent requests and non-UI writes cannot create two active owners. Before adding the constraint, inspect existing data and stop for an explicit repair decision if duplicate active owners are found; never auto-deactivate accounts.
- `users.telegram_user_id` currently has a global unique constraint. This matches the MVP rule that one Telegram ID cannot be assigned across shops; do not weaken or migrate this constraint for this work.
- `users.tenant_id` is nullable for platform users. Shop creation must explicitly set the new owner's `tenant_id` to the newly persisted tenant UUID and must not inherit a tenant from request context.
- Owner-created staff must always inherit the authenticated owner's tenant. System Admin-created staff must explicitly select a valid tenant. Never trust a submitted `tenant_id` from an owner.
- Store the initial password only as a hash and do not include it in logs or audit metadata. The System Admin provides it to the owner outside the application.
- No branch entity or multi-shop owner relationship is to be introduced for MVP.

## Implementation Steps
1. **Extend the System Admin shop creation form.**
   - Likely files: `app/Filament/Resources/Tenants/Schemas/TenantForm.php`, `app/Filament/Resources/Tenants/Pages/CreateTenant.php`.
   - Add required owner name, globally unique email, and initial password inputs to the create-shop workflow. Keep owner credentials out of the tenant edit form and tenant listing.
   - Confirm that validation errors are returned to the form and that the create action remains System Admin-only.
   - **Completion check:** Filament form tests verify required fields, invalid/duplicate owner email handling, and access restrictions.
2. **Persist the tenant and owner atomically.**
   - Likely files: `app/Filament/Resources/Tenants/Pages/CreateTenant.php`; introduce a narrowly scoped action/service only if needed to keep the persistence transaction independently testable.
   - Wrap tenant and owner persistence in one database transaction. Create the owner with role `owner`, explicit `tenant_id` equal to the new tenant ID, and the existing password-hashing convention. If either insert fails, roll back both.
   - Add a PostgreSQL partial unique index for active owner rows by `tenant_id` (`role = 'owner' AND is_active = true`) after checking existing data. Retain inactive owner rows for history.
   - Ensure System Admin context cannot accidentally assign the owner to an already active tenant. Do not add secrets to logs or audit metadata.
   - **Completion check:** feature tests prove both records commit together, the owner references the created tenant, and failure to create the owner leaves no tenant behind.
3. **Make staff tenant assignment actor-aware and server-enforced.**
   - Likely files: `app/Filament/Resources/Users/Schemas/UserForm.php`, `app/Filament/Resources/Users/UserResource.php`, `app/Filament/Resources/Users/Pages/CreateUser.php`, and `app/Filament/Resources/Users/Pages/EditUser.php`.
   - For an owner, expose staff creation/management only; force the authenticated owner's `tenant_id`, including on edits, and reject attempts to submit another tenant's ID or create/promote an owner.
   - For the System Admin, provide an explicit required shop selector for staff creation and validate the selected tenant server-side. Allow owner replacement only after deactivating the current owner; the database constraint is authoritative. Do not infer tenant from slug.
   - Preserve staff-management authorization and review user queries so owners see only their own shop's users while System Admin access remains platform-wide.
   - **Completion check:** feature tests cover owner-created staff tenant assignment, rejection of cross-tenant assignment, and required valid tenant selection for System Admin-created staff.
4. **Verify owner authentication and tenant isolation.**
   - Likely files: `tests/Feature/TenantOwnerOnboardingTest.php` (new), `tests/Feature/TenantIsolationFeatureTest.php` (existing).
   - Authenticate using the created owner's credentials and verify the request/dashboard context is derived from the owner's `tenant_id`, with no access to another tenant's records.
   - Verify changing the shop slug does not change the owner's tenant relationship or effective access.
   - **Completion check:** focused feature tests assert login result, associated tenant, and cross-tenant isolation through observable application behavior.
5. **Verify Telegram assignment against the MVP rule.**
   - Likely files: `tests/Feature/TelegramWhitelistAuthTest.php` (existing), `app/Http/Middleware/TelegramStaffAuth.php` (change only if tests expose a gap).
   - Keep middleware tenant resolution based on the single matched user record. Test that an active registered staff ID scopes API access to its assigned shop and that the same ID cannot be assigned to a second shop under the existing database constraint.
   - **Completion check:** Telegram feature tests confirm tenant-scoped results and global duplicate rejection; no n8n or bot changes are included.
6. **Run focused regression checks.**
   - Run new onboarding/staff-assignment tests and existing `TenantAutoFillTest`, `TenantIsolationFeatureTest`, and `TelegramWhitelistAuthTest`.
   - Run `git diff --check`; if any PHP files changed, run the project's required Pint command and re-run the focused tests.
   - **Completion check:** all focused tests pass against the configured PostgreSQL test database and formatting checks are clean.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-009.1: Shop and owner are created atomically and owner `tenant_id` is set to that shop | Required owner fields and a single transaction in the create-shop operation | Proposed `TenantOwnerOnboardingTest::test_shop_and_owner_are_created_atomically` and `test_owner_is_bound_to_new_tenant` |
| AC-009.2: Owner creation failure rolls back tenant creation | Let persistence exceptions fail the transaction; surface form validation errors for expected uniqueness failures | Proposed `TenantOwnerOnboardingTest::test_tenant_creation_rolls_back_when_owner_creation_fails` |
| AC-009.3: Owner login derives tenant context from the associated account | Authenticate the newly created owner and verify tenant-scoped access | Proposed `TenantOwnerOnboardingTest::test_owner_login_uses_assigned_tenant_context` |
| AC-009.4: Owner and System Admin staff creation are safely tenant-scoped | Force owner tenant assignment; require System Admin tenant selection and validate it on save | Proposed `TenantStaffAssignmentTest::test_owner_staff_inherits_owner_tenant`, `test_owner_cannot_assign_staff_to_another_tenant`, and `test_system_admin_must_select_staff_tenant` |
| AC-009.5: Slug is not an authorization or user association key | Keep UUID-based `tenant_id` relationships and validate slug changes do not affect scope | Proposed `TenantOwnerOnboardingTest::test_slug_does_not_determine_user_tenant` |
| AC-009.6: Telegram requests use the assigned staff tenant and Telegram IDs are not reused across shops | Preserve middleware lookup and existing global unique constraint | Existing `TelegramWhitelistAuthTest::test_whitelisted_telegram_user_is_authenticated`; proposed `test_telegram_id_cannot_be_reused_across_shops` |
| AC-009.7: At most one active owner per shop; System Admin-only replacement after deactivation | Add database uniqueness guard and actor-specific UI authorization; retain inactive owner rows | Proposed `TenantOwnerOnboardingTest::test_only_one_active_owner_per_shop`, `test_system_admin_can_replace_deactivated_owner`, and `TenantStaffAssignmentTest::test_owner_cannot_create_or_promote_owner` |

## Test List
- **Existing focused tests:**
  - `tests/Feature/TenantAutoFillTest.php`
  - `tests/Feature/TenantIsolationFeatureTest.php`
  - `tests/Feature/TelegramWhitelistAuthTest.php`
- **Proposed tests to add:**
  - `tests/Feature/TenantOwnerOnboardingTest.php`
  - `tests/Feature/TenantStaffAssignmentTest.php`
  - Add database-level active-owner uniqueness coverage; do not rely only on form validation.
  - Add Telegram duplicate-assignment coverage to `TelegramWhitelistAuthTest.php`.
- **Suggested commands:**
  - `php artisan test --compact tests/Feature/TenantOwnerOnboardingTest.php tests/Feature/TenantStaffAssignmentTest.php`
  - `php artisan test --compact tests/Feature/TenantAutoFillTest.php tests/Feature/TenantIsolationFeatureTest.php tests/Feature/TelegramWhitelistAuthTest.php`

## Rollback and Recovery
- The current tenant UUID relationship and global unique Telegram ID constraint already support the MVP contract. A new migration is required for the active-owner uniqueness constraint.
- Before adding the partial unique index, inspect production data for tenants with multiple active owners. If any exist, stop deployment and obtain an explicit owner-selection/deactivation decision; do not silently deactivate or delete accounts.
- If the application change must be reverted, revert the Filament flow and keep already created tenant/owner records intact; do not delete shop data as part of code rollback.
- The database transaction prevents partial tenant/owner creation. If the application needs rollback after the unique index is deployed, remove the index only through a reviewed migration after confirming the owner policy; preserve all owner account rows.
