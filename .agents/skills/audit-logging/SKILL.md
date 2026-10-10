---
name: audit-logging
description: Track and query all system activities for accountability and debugging. Use when investigating who performed an action, tracking booking changes, monitoring staff activity, or generating compliance reports. Covers audit log creation, querying, and retention policies.
---

# Audit Logging

## Overview

Every critical action in the system is logged to an append-only audit trail. Logs capture who did what, when, and on which entity. Only owners can view audit logs.

## Logged Actions

| Action | Entity Type | Trigger |
|---|---|---|
| `booking.created` | booking | Staff confirms booking |
| `booking.updated` | booking | Staff modifies booking |
| `booking.cancelled` | booking | Staff cancels booking |
| `return.processed` | booking | Staff processes return |
| `collateral.id_released` | collateral | Staff releases National ID |
| `collateral.id_held` | collateral | Booking created with ID |
| `item.created` | item | Owner adds inventory item |
| `item.updated` | item | Owner modifies item |
| `item.retired` | item | Owner retires item |
| `user.created` | user | Owner creates user account |
| `user.updated` | user | Owner modifies user |
| `user.deactivated` | user | Owner deactivates user |
| `payment.recorded` | booking | Staff records payment |
| `penalty.assessed` | booking_item | Staff assesses damage penalty |

## Audit Log Schema

```sql
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL,
    actor_type VARCHAR(50) NOT NULL,    -- 'user' | 'system'
    actor_id VARCHAR(255),              -- user UUID or 'system'
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id UUID,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

## Querying Audit Logs

### By User
```sql
SELECT * FROM audit_logs
WHERE tenant_id = :tenant_id
  AND actor_id = :user_id
ORDER BY created_at DESC;
```

### By Action
```sql
SELECT * FROM audit_logs
WHERE tenant_id = :tenant_id
  AND action = 'booking.created'
  AND created_at >= :start_date
ORDER BY created_at DESC;
```

### By Entity
```sql
SELECT * FROM audit_logs
WHERE tenant_id = :tenant_id
  AND entity_type = 'booking'
  AND entity_id = :booking_id
ORDER BY created_at ASC;
```

## Retention Policy

- **MVP:** Logs retained indefinitely (single VPS, limited storage)
- **Phase 2:** Implement log rotation (archive logs older than 1 year)
- **Phase 3:** Configurable retention per tenant

## Gotchas

- **Logs are append-only.** Never update or delete audit log entries.
- **Only owners can view logs.** Staff cannot access audit trail.
- **Actor is captured automatically.** Use `Auth::id()` or `system` for automated actions.
- **Metadata is JSONB.** Store relevant context (old values, new values, IP address).
- **High-volume actions** (like availability checks) should NOT be logged — only state-changing actions.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/audit-logs` | List audit logs (filterable) |
| GET | `/api/v1/audit-logs?actor_id=` | Filter by user |
| GET | `/api/v1/audit-logs?action=` | Filter by action |
| GET | `/api/v1/audit-logs?entity_type=&entity_id=` | Filter by entity |
| GET | `/api/v1/audit-logs?start_date=&end_date=` | Filter by date range |
