# PLAN-001: Multi-Tenant SaaS Architecture & Data Isolation

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-001.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement shared-database tenant isolation in the Laravel application: PostgreSQL schema, tenant context resolution for authenticated web users, tenant-aware Eloquent models, and authorization that prevents cross-tenant access. The repository is currently a Laravel skeleton; the tenancy models, domain tables, and relevant tests are not present yet.

## External Workstream (Excluded)
- Telegram tenant resolution from the Telegram whitelist. This plan only covers web requests resolved from the authenticated user's tenant.

## Open Questions and Constraints
- `AGENTS.md` requires `stancl/tenancy`, while the SPEC calls for a `BelongsToTenant` trait and a shared-database `TenantScope`. Confirm the installed package's Laravel 13 compatibility and decide how it will support this exact tenancy model before implementation; do not assume database-per-tenant behavior.
- PostgreSQL is mandatory. Current default setup/test assumptions must be changed to a PostgreSQL test database; SQLite is not an acceptable substitute for JSONB or row-lock behavior.
- Schema rollback after tenant data exists is destructive. Define backup and restore procedures before production migrations.

## Implementation Steps
1. Confirm PHP/Laravel and PostgreSQL environment, package compatibility, and a repeatable PostgreSQL test configuration. Completion: a clean test database can run migrations and tests without SQLite.
2. Add the central `Tenant` schema/model and tenant-scoped domain-table migration conventions, UUID keys, foreign keys, indexes, JSONB settings, and factories. Include `tenant_id` on every tenant-owned table.
3. Implement authenticated web tenant context, a shared `BelongsToTenant`/`TenantScope` convention, and automatic tenant assignment on model creation. Define and tightly constrain any explicit scope bypass.
4. Apply tenant-scoped route binding and policies to tenant resources. Resolve web tenant from the authenticated user's `tenant_id`; never accept a tenant ID from untrusted request input as authority.
5. Add PostgreSQL-backed feature tests for automatic tenant binding, query isolation, cross-tenant route access, and scope bypass safeguards.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-001.1 | Assign the authenticated tenant when creating a tenant-owned model | `TenantAutoFillTest::test_model_automatically_sets_tenant_id_on_create` |
| AC-001.2 | Apply tenant scope to Eloquent reads | `TenantIsolationFeatureTest::test_cannot_retrieve_foreign_tenant_records` |
| AC-001.3 | Scope route binding and resource authorization | `TenantRouteProtectionTest::test_foreign_tenant_resource_returns_404` |
| AC-001.4 | Verify generated queries retain tenant constraints | `TenantQueryIntegrityTest::test_query_sql_always_contains_tenant_where_clause` |
| REQ-001-02, REQ-001-05 | Tenant columns, indexes, UUIDs, and JSONB settings | `TenantSchemaIntegrityTest`, `TenantSettingsTest` |

## Test List
- Proposed feature tests: `TenantAutoFillTest`, `TenantIsolationFeatureTest`, `TenantRouteProtectionTest`, and `TenantSettingsTest`.
- Proposed PostgreSQL schema/query tests: `TenantSchemaIntegrityTest` and `TenantQueryIntegrityTest`.
- The named SPEC tests are proposed; the current repository contains only the Laravel example feature test, not these tenant tests.
- Run `php artisan test --compact` against PostgreSQL after implementation.

## Rollback and Recovery
Before production, take a verified PostgreSQL backup and test restoration. Additive migrations can be rolled back only before tenant data is accepted. Once populated, prefer a forward fix or restore to a separately verified database; do not run a destructive rollback that drops tenant data.