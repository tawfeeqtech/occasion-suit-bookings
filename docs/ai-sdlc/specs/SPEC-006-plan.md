# PLAN-006: Return Inspection, Penalties, and Collateral Release

## Status
- Source SPEC: `docs/ai-sdlc/specs/SPEC-006.md`
- Source status: Approved / Specification
- Approval: Approved

## Goal and Scope
Implement Laravel return inspection for all items on a booking, manual damage/missing penalties, settlement and owner waiver rules, collateral held/released state transitions, and atomic audit integration. The repository currently has no booking return service, collateral model, or domain tests.

## External Workstream (Excluded)
- Telegram return commands, voice-based inspection, n8n workflows, and external notifications. Return entry is through Laravel API/admin application surfaces only.

## Open Questions and Constraints
- SPEC-006's payload requires a manual `penalty_reason`, but the listed schema only has `penalty_fee`; the schema also lacks an explicit waiver representation. Decide whether these belong on booking-item assessment fields or a separate penalty/waiver record before migrations.
- SPEC-008 audit persistence must be established before return/waiver/release transactions can satisfy the requirement that audit failure rolls back the parent change.
- Never store ID-card scans/images or raw ID numbers. Collateral is a status record with permitted text notes only, per `AGENTS.md`.
- Return processing depends on the item/booking schema and cleaning lifecycle from SPEC-004 and SPEC-005.

## Implementation Steps
1. Resolve penalty reason and waiver persistence, and confirm the approved collateral data contract.
2. Add tenant-scoped return/penalty/collateral schema and model relationships without storing prohibited identity-card data.
3. Implement return validation requiring a result for every booking item; validate status, manual penalty amount/reason, and payment data.
4. Implement a transactional return service that locks booking/items, records inspections and settlements, sends clean items into SPEC-005 cleaning lifecycle, and sets `damage_pending` when penalties remain unresolved.
5. Implement a single collateral-release guard: no unpaid rental balance or unwaived penalty; require Owner authorization for waiver and record actor/reason/timestamp.
6. Connect required immutable audit events and Filament/API surfaces; ensure audit-write failure aborts the domain transaction.
7. Add feature tests for complete/partial returns, clean/damaged/missing states, unpaid balances, waiver permissions, tenant isolation, and audit atomicity.

## Acceptance Criteria and Test Mapping
| Acceptance criterion | Planned change | Test or verification |
|---|---|---|
| AC-006.1 | Complete clean return, cleaning transition, settlement, and authorized release | `ReturnServiceTest::test_clean_pass_return_completes_booking_and_releases_collateral` |
| AC-006.2 | Block release for unpaid penalty without Owner waiver | `CollateralGuardTest::test_release_collateral_blocked_when_penalty_unpaid` |
| AC-006.3 | Require inspection outcome for every booking item | `ReturnValidationTest::test_incomplete_item_checklist_rejected` |
| AC-006.4 | Persist manually entered penalty amount and reason | `DamageAssessmentTest::test_manual_penalty_amount_and_reason_are_recorded` |
| AC-006.5 | Keep refusal pending, then permit audited Owner waiver after settlement | `CollateralReleaseTest::test_refusal_requires_owner_decision_before_release` |
| REQ-006-08 | Record processing/waiver/release actor and time | `AuditTrailIntegrationTest::test_return_and_id_release_logged_to_audit` |

## Test List
- Proposed tests: `ReturnProcessFeatureTest`, `ReturnValidationTest`, `ReturnServiceTest`, `DamageAssessmentTest`, `CollateralGuardTest`, `CollateralReleaseTest`, and `AuditTrailIntegrationTest`.
- Cover direct endpoint access by Staff/Owner and verify failed return or audit writes leave all related records unchanged.
- Current repository has no return tests. Run focused tests, then `php artisan test --compact` against PostgreSQL.

## Rollback and Recovery
Back up the database before adding penalty/waiver fields or tables. Keep return assessments and payments as history; after real returns exist, prefer a forward correction over dropping the fields or deleting records. Verify a failed transaction leaves booking, inspection, settlement, collateral, and audit state unchanged.