# PLAN-010: Inventory Item Creation API for n8n

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-010.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Expose a tenant-scoped REST API endpoint `POST /api/v1/items` within the Laravel application allowing automated ingestion of rental inventory items from an external n8n workflow node.

In-scope work includes request validation with flexible Arabic support and smart default values, tenant context binding via the existing `telegram.staff` middleware, JSON response formatting via `ItemResource`, audit logging, and automated test coverage.

## External Workstream (Excluded)
- n8n workflow configuration, node execution, and HTTP Request node setup.
- External Excel file updates, Google Sheets synchronization, and spreadsheet row tracking.
- Groq Whisper speech-to-text processing and OpenAI GPT-4o-mini structured entity extraction in n8n.
- Telegram bot webhook triggers and direct chat interactions.

## Open Questions and Constraints
- Source SPEC is currently Draft (approval pending).
- All requests must authenticate via `X-Telegram-User-Id` (or `telegram_user_id`) matching an active user in `users`.
- Items are strictly scoped to the authenticated user's `tenant_id`; cross-tenant item creation is strictly prohibited.
- `items.name` is the only mandatory input parameter. To maintain database schema constraints (`NOT NULL` on `category`, `size`, `color`), the application layer must provide default fallback values (`category`: "عام", `size`: "غير محدد", `color`: "غير محدد", `rental_price`: `0.00`, `status`: `"available"`) when these fields are omitted from the request payload.
- External Excel writing remains the sole responsibility of the n8n workflow.
- Scope boundary decisions resolved:
  - Bulk/Batch import is explicitly deferred to a future iteration; current scope handles single-item creation per request.
  - No notifications are sent to the Shop Owner upon staff item creation; system auditing via `audit_logs` is sufficient.

## Implementation Steps

1. **Create Form Request for Item Creation Validation.**
   - Likely files: `app/Http/Requests/Api/V1/CreateItemRequest.php`
   - Purpose: Validate incoming JSON payload (`name` required, `category`, `size`, `color`, `rental_price`, `status`, `custom_fields` optional). Provide default values for omitted optional fields.
   - Completion check: Validation unit/feature assertions confirm missing `name` returns 422, while omission of optional fields applies defaults.

2. **Implement Item Controller Store Method and Route Registration.**
   - Likely files: `routes/api.php`, `app/Http/Controllers/Api/V1/ItemController.php`
   - Purpose: Register `POST /items` inside the existing `telegram.staff` middleware group. Implement `store(CreateItemRequest $request)` to persist the item using `TenantContext::getTenantId()`, record an audit log entry via `AuditService`, and return an `ItemResource` with HTTP 201.
   - Completion check: Route list shows `POST api/v1/items` mapped to `ItemController@store`.

3. **Integrate Audit Logging.**
   - Likely files: `app/Http/Controllers/Api/V1/ItemController.php`, `app/Services/AuditService.php`
   - Purpose: Record an audit event `item_created` capturing the creator's user ID, tenant ID, and item details.
   - Completion check: Creating an item generates a matching record in `audit_logs`.

4. **Add Comprehensive Feature Tests.**
   - Likely files: `tests/Feature/ItemApiCreationTest.php`
   - Purpose: Verify all acceptance criteria (AC-010.1 through AC-010.6), including smart defaults, Arabic values, tenant scoping, validation rejections, and unauthorized requests.
   - Completion check: All tests in `tests/Feature/ItemApiCreationTest.php` pass cleanly with `php artisan test --filter=ItemApiCreationTest`.

5. **Run Lint and Regression Checks.**
   - Likely files: All modified and added files.
   - Purpose: Run `./vendor/bin/pint --format agent` and execute full API test suite.
   - Completion check: Clean formatting and zero regressions in existing tests.

## Acceptance Criteria and Test Mapping

| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-010.1: Minimal item creation with smart defaults | Add `store` endpoint and fallback defaults | `ItemApiCreationTest::test_item_created_successfully_with_only_name_and_defaults` |
| AC-010.2: Full item creation with custom fields | Save full attributes & JSONB `custom_fields` | `ItemApiCreationTest::test_item_created_with_full_arabic_details` |
| AC-010.3: Tenant scoping enforcement | Bind to `TenantContext::getTenantId()` | `ItemApiCreationTest::test_item_persisted_with_authenticated_tenant_id` |
| AC-010.4: Name validation failure | FormRequest requires non-empty `name` | `ItemApiCreationTest::test_validation_error_when_name_is_missing` |
| AC-010.5: Unauthorized access rejection | Protected by `telegram.staff` middleware | `ItemApiCreationTest::test_unauthorized_telegram_user_cannot_create_item` |
| AC-010.6: Audit log recording | Trigger audit event upon item persistence | `ItemApiCreationTest::test_audit_log_recorded_upon_item_creation` |

## Test List
- **Proposed Tests to Add (`tests/Feature/ItemApiCreationTest.php`):**
  - `test_authenticated_staff_can_access_create_item_endpoint`
  - `test_item_created_successfully_with_only_name_and_defaults`
  - `test_item_created_with_full_arabic_details`
  - `test_item_persisted_with_authenticated_tenant_id`
  - `test_validation_error_when_name_is_missing`
  - `test_unauthorized_telegram_user_cannot_create_item`
  - `test_deactivated_staff_cannot_create_item`
  - `test_audit_log_recorded_upon_item_creation`
- **Existing Regression Tests to Run:**
  - `tests/Feature/TelegramWhitelistAuthTest.php`
  - `tests/Feature/TenantIsolationFeatureTest.php`

## Rollback and Recovery
- All created item records and audit logs are safely tied to `tenant_id` and have UUID primary keys.
- If this endpoint needs to be rolled back before deployment, remove the `POST /items` route from `routes/api.php` and `CreateItemRequest.php`. Existing items and bookings in PostgreSQL remain untouched and unaffected.
