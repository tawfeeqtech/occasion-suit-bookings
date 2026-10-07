# PLAN-005: Cleaning Buffer and Maintenance Lifecycle

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-005.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement Laravel maintenance records and state transitions, tenant-configured cleaning buffers, availability checks, a scheduled buffer-release command, and readiness data for the dashboard. The application currently has no item, booking, maintenance, or scheduling domain code.

## External Workstream (Excluded)
- Telegram status notifications, n8n jobs, and other bot messaging. No automatic ready notification is part of the Laravel MVP scope.

## Open Questions and Constraints
- SPEC-005 depends on item, booking, tenant settings, and audit logging foundations from SPEC-001, SPEC-003, SPEC-004, and SPEC-008.
- Confirm the canonical timezone used for tenant settings, persisted timestamps, and ready-at API output before implementation.
- A due cleaning record must not make an item available if a newer maintenance extension or another active maintenance record exists.

## Implementation Steps
1. Add tenant-scoped maintenance schema/model, status transitions, foreign keys, and the composite ready-work index from the SPEC.
2. Implement return-to-cleaning behavior using the tenant's single `buffer_hours` setting and persist `expected_ready_at` consistently.
3. Extend availability logic to return a conflict and exact readiness timestamp during cleaning/maintenance; also calculate readiness directly so delayed scheduling cannot cause stale blocking.
4. Implement the release command (`php artisan buffer:release-clean-items`) to process only due cleaning rows, transition item and maintenance state atomically, and attribute system audit events explicitly.
5. Register the 15-minute schedule using project conventions and prevent duplicate/overlapping releases safely.
6. Expose readiness values to the dashboard resource and add tests for time boundaries, extensions, tenant settings, and scheduler behavior.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-005.1 | Create maintenance row and calculate tenant buffer | `ReturnServiceTest::test_item_maintenance_record_created_with_48h_offset` |
| AC-005.2 | Block pickup until item is ready | `AvailabilityServiceTest::test_booking_rejected_when_pickup_before_ready_date` |
| AC-005.3 | Release due rows and item state through Artisan command | `ReleaseCleaningBuffersCommandTest::test_artisan_command_releases_due_items` |
| AC-005.4 | Extend readiness and move state to maintenance | `ItemMaintenanceTest::test_extend_maintenance_updates_expected_ready_at` |
| AC-005.5 | Apply one buffer duration to all item categories | `TenantSettingsTest::test_one_buffer_duration_applies_to_all_items` |
| AC-005.6 | Show readiness without emitting notification | `ItemReadinessDashboardTest::test_ready_time_is_visible_without_notification` |

## Test List
- Proposed tests: `ItemMaintenanceTest`, `AvailabilityServiceTest`, `ReleaseCleaningBuffersCommandTest`, `TenantSettingsTest`, and `ItemReadinessDashboardTest`.
- Use framework time-travel/frozen-clock support for exact boundaries and test that extended or non-cleaning records are not released.
- Current repository has no domain tests. Run the focused tests and then `php artisan test --compact` against PostgreSQL.

## Rollback and Recovery
Use additive maintenance migrations with verified PostgreSQL backup before production. Do not roll back after maintenance history is populated if doing so would orphan item states; use a forward migration to correct transitions. If the scheduled command misfires, rerun it safely after confirming it is idempotent and tenant-scoped.