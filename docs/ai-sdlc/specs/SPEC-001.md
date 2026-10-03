# SPEC-001: Multi-Tenant SaaS Architecture

## 1. Metadata
- **Specification ID:** SPEC-001
- **Title:** Multi-Tenant SaaS Architecture & Data Isolation
- **Source Intent:** [intent/multi-tenant-saas.md](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md)
- **Status:** Draft / Specification
- **Target Version:** MVP v1.0
- **Architectural Scope:** Database Scoping, Tenancy Middleware, Eloquent Global Scope

---

## 2. Intent Traceability Matrix

| Requirement ID | Intent Line Reference | Description | Automated Test Mapping |
|---|---|---|---|
| REQ-001-01 | [multi-tenant-saas.md:L23](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L23) | Every tenant has a unique identifier (`tenant_id` UUID) | `TenantModelTest::test_tenant_has_unique_uuid` |
| REQ-001-02 | [multi-tenant-saas.md:L24](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L24) | Every database table (except central `tenants`) contains `tenant_id` column | `TenantSchemaIntegrityTest::test_all_domain_tables_have_tenant_id_column` |
| REQ-001-03 | [multi-tenant-saas.md:L25](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L25) | Every query automatically applies a global tenant filter (`WHERE tenant_id = ?`) | `TenantGlobalScopeTest::test_queries_automatically_scoped_to_current_tenant` |
| REQ-001-04 | [multi-tenant-saas.md:L26](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L26) | Requests attempting to access cross-tenant resources by altering URLs/IDs return 403/404 | `TenantIsolationFeatureTest::test_user_cannot_access_other_tenant_resource` |
| REQ-001-05 | [multi-tenant-saas.md:L27](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L27) | Each tenant has isolated configurable settings stored in JSONB | `TenantSettingsTest::test_tenant_settings_jsonb_isolation` |
| REQ-001-06 | [multi-tenant-saas.md:L31-L32](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L31-L32) | Staff/Owner in Tenant A cannot see any items, bookings, or records of Tenant B | `TenantDataLeakageTest::test_zero_data_leakage_between_tenants` |
| REQ-001-07 | [multi-tenant-saas.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L33) | Scoping cannot be bypassed in application code unless explicitly requested by super-admin commands | `TenantGlobalScopeTest::test_global_scope_cannot_be_unintentionally_bypassed` |

---

## 3. Database Schema & Architecture Contract

### 3.1 Tenants Table
```sql
CREATE TABLE tenants (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    settings JSONB DEFAULT '{"buffer_hours": 48, "currency": "ILS"}',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

### 3.2 Tenant Scoped Tables Contract
All domain tables (`users`, `items`, `bookings`, `booking_items`, `collateral_records`, `item_maintenance`, `audit_logs`) MUST:
1. Define foreign key: `tenant_id UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE`.
2. Apply index: `INDEX idx_{table}_tenant_id (tenant_id)`.
3. Use `BelongsToTenant` trait applying `TenantScope`.

---

## 4. Acceptance Criteria (Automated Test Specifications)

### AC-001.1: Automated Model Creation Tenant Binding
- **Given:** An authenticated request with active tenant `tenant_A`.
- **When:** An Eloquent model (e.g. `Item` or `Booking`) is created without specifying `tenant_id`.
- **Then:** `tenant_id` is automatically set to `tenant_A->id` before persisting.
- **Verification Test:** `TenantAutoFillTest::test_model_automatically_sets_tenant_id_on_create`

### AC-001.2: Cross-Tenant Data Leakage Prevention
- **Given:** Two tenants exist: `Tenant_A` with 5 items and `Tenant_B` with 3 items.
- **When:** A user belonging to `Tenant_A` executes `Item::all()`.
- **Then:** Exactly 5 items are returned, and none of `Tenant_B`'s item IDs exist in the collection.
- **Verification Test:** `TenantIsolationFeatureTest::test_cannot_retrieve_foreign_tenant_records`

### AC-001.3: URL Tampering Protection
- **Given:** An item with ID `item_B_uuid` belongs to `Tenant_B`.
- **When:** A user authenticated under `Tenant_A` requests `GET /api/v1/items/{item_B_uuid}` or views it in dashboard.
- **Then:** The response status is HTTP `404 Not Found` (or `403 Forbidden`).
- **Verification Test:** `TenantRouteProtectionTest::test_foreign_tenant_resource_returns_404`

### AC-001.4: Database Level Row Scoping Enforcement
- **Given:** A direct Eloquent query builder execution `Booking::where('status', 'active')->get()`.
- **When:** Inspected via `toSql()`.
- **Then:** The generated SQL contains `WHERE bookings.tenant_id = ?`.
- **Verification Test:** `TenantQueryIntegrityTest::test_query_sql_always_contains_tenant_where_clause`

---

## 5. Negative Boundaries ("What Should NOT Happen")
- Staff or Owner from shop A MUST NOT see, modify, or delete any record belonging to shop B ([multi-tenant-saas.md:L31](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L31)).
- No shared inventory or bundle pools across tenants ([multi-tenant-saas.md:L32](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L32)).
- Application controllers must not manually concatenate tenant IDs in where clauses; global scope must enforce it transparently ([multi-tenant-saas.md:L33](file:///d:/mind-ai/occasion-suit-bookings/intent/multi-tenant-saas.md#L33)).

---

## 6. Resolved Decisions

1. **Central Super-Admin Subscription Dashboard:**
    - Include a system-admin-only dashboard in MVP to manage shops and their subscriptions.
    - The initial rollout remains one pilot shop ([MVP scope](../../../mvp-scope.md)); this limits the rollout, not the availability of internal admin tools.
    - Subscription pricing is a fixed monthly fee per shop. The fee amount is not yet specified.
2. **Tenant Resolution Mechanism:**
    - Web Admin requests resolve the tenant from the authenticated user's `tenant_id`.
    - Telegram bot requests resolve the tenant from the authorized user's `whitelist.tenant_id`.
