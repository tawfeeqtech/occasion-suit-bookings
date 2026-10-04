# PLAN-008: Append-Only Audit Trail

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-008.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement Laravel-domain audit capture with actor and tenant attribution, append-only persistence, atomic failure behavior, and an Owner-only read/filter interface. The repository currently has no audit package or project audit model/tests.

## External Workstream (Excluded)
- Telegram-specific audit transport or event delivery. Laravel domain events such as a booking being created are audited here regardless of whether a future caller is web or Telegram; the integration itself is excluded.

## Open Questions and Constraints
- **Blocking persistence conflict:** `AGENTS.md` requires `spatie/laravel-activitylog`, while SPEC-008 specifies a custom `audit_logs` table. Decide whether to adapt the package's storage contract, use a custom activity model/table, or formally revise one contract before migrations.
- The SPEC's proposed Eloquent `update()`/`delete()` overrides do not stop mass query-builder changes, raw SQL, or database administration. The immutability guarantee needs a database-enforced strategy and defined privileged maintenance exception.
- Audit retention is indefinite for MVP; audit writes for sensitive mutations must share the parent transaction and fail the parent mutation if the audit insert fails.
- Audit viewer authorization depends on the final role/tenant model in SPEC-001/002.

## Implementation Steps
1. Resolve package/table compatibility and database-enforced immutability design; record the approved choice before creating the audit schema.
2. Add tenant-scoped audit storage with actor type/ID, action, entity, timestamp, metadata, and indexes; ensure system actions have explicit actor identity.
3. Implement a single audit-writing boundary for sensitive Laravel domain changes and connect booking, return, collateral, finance, inventory-state, and cleaning-release events as those services land.
4. Make audit writes participate in the parent transaction; propagate failures so the parent mutation rolls back instead of silently succeeding.
5. Add database-level protection against update/delete and enforce tenant isolation in audit queries.
6. Add a read-only Owner Filament resource with actor/action/date filters and no edit/delete actions; enforce access on direct URLs.
7. Add PostgreSQL tests for event completeness, failure atomicity, immutability including bulk/raw paths, actor attribution, and cross-tenant isolation.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-008.1 | Capture booking event, actor, entity, and metadata | `AuditLogEventsTest::test_booking_creation_records_audit_trail` |
| AC-008.2 | Enforce immutable record behavior | `AuditLogImmutabilityTest::test_model_throws_exception_on_update_or_delete` plus bulk/raw-path tests |
| AC-008.3 | Capture collateral release actor and exact timestamp | `AuditLogEventsTest::test_id_release_triggers_audit_entry` |
| AC-008.4 | Scope audit viewer queries to tenant | `AuditLogIsolationTest::test_owner_only_sees_own_tenant_audit_logs` |
| REQ-008-03/05/06 | Read-only filters and Staff denial | `AuditLogResourceTest`, `AuditLogAuthorizationTest`, `AuditLogSearchTest` |
| Negative boundary | Audit insert failure aborts sensitive mutation | Proposed `AuditLogFailureAtomicityTest` |

## Test List
- Proposed tests: `AuditLogEventsTest`, `AuditLogStructureTest`, `AuditLogImmutabilityTest`, `AuditLogIsolationTest`, `AuditLogAuthorizationTest`, `AuditLogSearchTest`, and `AuditLogFailureAtomicityTest`.
- Verify no Filament write actions exist and that raw/bulk mutation attempts are rejected by the database guard, not only the model.
- Current repository has no audit tests. Run focused tests, then `php artisan test --compact` against PostgreSQL.

## Rollback and Recovery
Back up PostgreSQL before audit schema changes and verify restore. Retain audit history indefinitely; do not roll back by dropping populated audit tables. If an event contract is wrong, introduce a forward migration or new event format while preserving old records. Test the database guard and parent-transaction rollback before production rollout.