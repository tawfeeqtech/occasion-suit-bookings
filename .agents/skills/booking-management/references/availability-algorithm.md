# Availability Check Algorithm

## Pseudocode

```sql
-- Check for overlapping bookings
SELECT b.id, b.customer_name, b.pickup_date, b.return_date
FROM bookings b
JOIN booking_items bi ON bi.booking_id = b.id
WHERE b.tenant_id = :tenant_id
  AND bi.item_id = :item_id
  AND b.status IN ('active', 'overdue')
  AND b.pickup_date < :requested_return_date
  AND b.return_date > :requested_pickup_date
FOR UPDATE;

-- Check for active cleaning buffers
SELECT m.id, m.started_at, m.expected_ready_at
FROM item_maintenance m
WHERE m.tenant_id = :tenant_id
  AND m.item_id = :item_id
  AND m.status = 'cleaning'
  AND m.expected_ready_at > NOW();
```

## Conflict Response

If conflict detected, return:

```json
{
  "available": false,
  "conflicts": [
    {
      "item_id": "uuid",
      "item_name": "Classic Black Suit",
      "conflicting_booking_id": "uuid",
      "conflicting_dates": {
        "pickup": "2026-10-14T10:00:00Z",
        "return": "2026-10-17T18:00:00Z"
      },
      "available_from": "2026-10-17T18:00:00Z"
    }
  ]
}
```

## Buffer Configuration

- Buffer hours are stored in `tenants.settings.buffer_hours` (default: 48)
- Buffer starts when return is processed with `clean_pass` status
- Item becomes available when `expected_ready_at` passes
- Laravel Scheduler runs every 15 minutes to update item statuses
