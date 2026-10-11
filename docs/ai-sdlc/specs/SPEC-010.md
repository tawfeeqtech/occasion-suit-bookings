# SPEC-010: Inventory Item Creation API for n8n

## 1. Metadata
- **Specification ID:** SPEC-010
- **Title:** Inventory Item Ingestion via Tenant-Scoped API for n8n Automation
- **Source Intent:** [intent/inventory-api-creation.md](../../../intent/inventory-api-creation.md)
- **Status:** Approved / Specification
- **Target Version:** MVP v1.1
- **Architectural Scope:** API endpoint `POST /api/v1/items`, Request Validation, Telegram Staff Auth Middleware, Tenant Isolation, Audit Logging, JSON Resources

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-010-01 | [inventory-api-creation.md:L25](../../../intent/inventory-api-creation.md#L25) | Expose standard REST API route `POST /api/v1/items` under tenant staff middleware | `ItemApiCreationTest::test_authenticated_staff_can_access_create_item_endpoint` |
| REQ-010-02 | [inventory-api-creation.md:L26](../../../intent/inventory-api-creation.md#L26), [L41](../../../intent/inventory-api-creation.md#L41) | Authenticate requests via `X-Telegram-User-Id` (or `telegram_user_id`), rejecting unregistered or inactive accounts | `ItemApiCreationTest::test_unauthorized_telegram_user_cannot_create_item` and `test_deactivated_staff_cannot_create_item` |
| REQ-010-03 | [inventory-api-creation.md:L26](../../../intent/inventory-api-creation.md#L26), [L42](../../../intent/inventory-api-creation.md#L42) | Enforce tenant isolation by assigning the item to the authenticated user's `tenant_id` automatically | `ItemApiCreationTest::test_item_persisted_with_authenticated_tenant_id` |
| REQ-010-04 | [inventory-api-creation.md:L28](../../../intent/inventory-api-creation.md#L28), [L50](../../../intent/inventory-api-creation.md#L50) | Require `name` field; return 422 with validation errors if missing or empty | `ItemApiCreationTest::test_validation_error_when_name_is_missing` |
| REQ-010-05 | [inventory-api-creation.md:L29-L35](../../../intent/inventory-api-creation.md#L29-L35), [L43](../../../intent/inventory-api-creation.md#L43), [L48](../../../intent/inventory-api-creation.md#L48) | Provide smart defaults for omitted fields (`category`: 'عام', `size`: 'غير محدد', `color`: 'غير محدد', `rental_price`: 0.00, `status`: 'available') | `ItemApiCreationTest::test_item_created_successfully_with_only_name_and_defaults` |
| REQ-010-06 | [inventory-api-creation.md:L30](../../../intent/inventory-api-creation.md#L30), [L49](../../../intent/inventory-api-creation.md#L49) | Accept flexible string inputs including Arabic characters for `category`, `size`, `color` | `ItemApiCreationTest::test_item_created_with_full_arabic_details` |
| REQ-010-07 | [inventory-api-creation.md:L35](../../../intent/inventory-api-creation.md#L35) | Store arbitrary metadata in PostgreSQL JSONB `custom_fields` (e.g., `item_code`, deposit, notes) | `ItemApiCreationTest::test_item_persists_custom_fields_json` |
| REQ-010-08 | [inventory-api-creation.md:L36](../../../intent/inventory-api-creation.md#L36), [L48](../../../intent/inventory-api-creation.md#L48) | Return HTTP 201 Created with JSON representation transformed through `ItemResource` | `ItemApiCreationTest::test_successful_creation_returns_201_with_item_resource` |
| REQ-010-09 | [inventory-api-creation.md:L37](../../../intent/inventory-api-creation.md#L37), [L52](../../../intent/inventory-api-creation.md#L52) | Record an append-only audit log entry for the item creation event linking user, tenant, and item ID | `ItemApiCreationTest::test_audit_log_recorded_upon_item_creation` |
| REQ-010-10 | [inventory-api-creation.md:L44](../../../intent/inventory-api-creation.md#L44) | Exclude external Excel manipulation or file handling from Laravel responsibility | N/A (Architectural boundary constraint) |
| REQ-010-11 | [inventory-api-creation.md:L58](../../../intent/inventory-api-creation.md#L58) | Support single-item creation only in current scope; bulk/batch import is deferred to future releases | N/A (Scope boundary constraint) |
| REQ-010-12 | [inventory-api-creation.md:L59](../../../intent/inventory-api-creation.md#L59) | No immediate notifications sent to owner on staff item creation; rely solely on audit logging | N/A (Design boundary constraint) |

## 3. Technical Contract

- **Endpoint:** `POST /api/v1/items`
- **Middleware:** `telegram.staff` (inheriting existing `TelegramStaffAuth` logic: header `X-Telegram-User-Id` or request body `telegram_user_id`).
- **Request Headers:**
  - `Accept: application/json`
  - `Content-Type: application/json`
  - `X-Telegram-User-Id: <string|integer>`
- **Request Payload Schema (JSON):**
  - `name`: string, required, min: 1, max: 255
  - `category`: string, optional, max: 100, default: `"عام"`
  - `size`: string, optional, max: 50, default: `"غير محدد"`
  - `color`: string, optional, max: 50, default: `"غير محدد"`
  - `rental_price`: numeric, min: 0, optional, default: `0.00`
  - `status`: string, optional, in: `['available', 'booked', 'out_with_customer', 'cleaning', 'maintenance']`, default: `"available"`
  - `custom_fields`: object / associative array, optional, default: `null`
- **Response Schemas:**
  - **Success (201 Created):**
    ```json
    {
      "data": {
        "id": "uuid",
        "name": "بدلة كحلي ملكي",
        "category": "بدلة",
        "size": "50",
        "color": "كحلي",
        "status": "available",
        "rental_price": 250,
        "custom_fields": {
          "item_code": "SUIT-NVY-50",
          "excel_row": 15
        },
        "created_at": "2026-10-11T10:00:00.000000Z",
        "updated_at": "2026-10-11T10:00:00.000000Z"
      }
    }
    ```
  - **Validation Error (422 Unprocessable Content):**
    ```json
    {
      "message": "The name field is required.",
      "errors": {
        "name": [
          "The name field is required."
        ]
      }
    }
    ```
  - **Unauthorized (401 Unauthorized):**
    ```json
    {
      "error": "Unauthorized telegram user",
      "message": "The provided Telegram user ID is not registered in the system."
    }
    ```

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-010.1: Minimal Item Creation with Smart Defaults
- **Given:** An authenticated staff member or owner sends `POST /api/v1/items` with only `{ "name": "بدلة زفاف بيضاء" }`.
- **When:** The request is processed.
- **Then:** The item is saved with `category = 'عام'`, `size = 'غير محدد'`, `color = 'غير محدد'`, `rental_price = 0.00`, `status = 'available'`, and `tenant_id` matching the authenticated user.
- **HTTP Status:** 201 Created
- **Verification Test:** `ItemApiCreationTest::test_item_created_successfully_with_only_name_and_defaults`

### AC-010.2: Full Item Creation with Custom Fields
- **Given:** A valid request containing `name`, Arabic `category`, `size`, `color`, `rental_price`, and `custom_fields` object.
- **When:** The request is processed.
- **Then:** All fields and JSONB `custom_fields` are persisted accurately in PostgreSQL.
- **HTTP Status:** 201 Created
- **Verification Test:** `ItemApiCreationTest::test_item_created_with_full_arabic_details`

### AC-010.3: Tenant Scoping Enforcement
- **Given:** Staff A belonging to Tenant 1 sends a creation request.
- **When:** The item is persisted.
- **Then:** The new item's `tenant_id` is strictly Tenant 1, and it is invisible to queries scoped to Tenant 2.
- **Verification Test:** `ItemApiCreationTest::test_item_persisted_with_authenticated_tenant_id`

### AC-010.4: Name Validation Failure
- **Given:** An authenticated request omitting `name` or sending empty `name`.
- **When:** Request validation runs.
- **Then:** Request fails validation without persisting any record.
- **HTTP Status:** 422 Unprocessable Content
- **Verification Test:** `ItemApiCreationTest::test_validation_error_when_name_is_missing`

### AC-010.5: Unauthorized Access Rejection
- **Given:** An incoming request without `X-Telegram-User-Id` or with an unregistered Telegram ID.
- **When:** The `telegram.staff` middleware intercepts the request.
- **Then:** Request is rejected immediately.
- **HTTP Status:** 401 Unauthorized
- **Verification Test:** `ItemApiCreationTest::test_unauthorized_telegram_user_cannot_create_item`

### AC-010.6: Audit Log Recording
- **Given:** An item is successfully created via the API.
- **When:** The transaction completes.
- **Then:** An `AuditLog` row exists with `action = 'item_created'`, recording the actor ID, tenant ID, and item UUID in `auditable_id`.
- **Verification Test:** `ItemApiCreationTest::test_audit_log_recorded_upon_item_creation`
