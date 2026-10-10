---
name: cleaning-buffer
description: Manage automated cleaning and maintenance buffers between rentals. Use when items are returned and need cleaning time, when checking if an item is available for booking, or when configuring buffer durations. Covers the full buffer lifecycle from activation to expiry, including manual overrides for maintenance.
---

# Cleaning Buffer Management

## Overview

After a rental return, items enter a cleaning/maintenance buffer period during which they cannot be booked. This ensures suits are properly cleaned, inspected, and prepared before the next customer.

## Buffer Lifecycle

```
Item Returned (clean_pass)
    ↓
item_maintenance record created
    ↓
Item status → 'cleaning'
expected_ready_at = NOW() + buffer_hours
    ↓
[Buffer Period]
    ↓
Laravel Scheduler checks every 15 min
    ↓
expected_ready_at <= NOW()
    ↓
Item status → 'available'
```

## Buffer Configuration

| Setting | Location | Default | Description |
|---|---|---|---|
| `buffer_hours` | `tenants.settings` | 48 | Hours between return and next availability |
| `auto_mark_ready` | `tenants.settings` | true | Automatically mark item available after buffer |

## Buffer Activation Triggers

1. **Return processed** — All items marked `clean_pass` → buffer starts
2. **Manual activation** — Owner/staff manually sets item to `maintenance`
3. **Damage repair** — Item sent for repair → buffer with custom duration

## Availability Check Integration

When checking item availability, the system must:

1. Check `item.status` — if `cleaning` or `maintenance`, item is unavailable
2. Check `item_maintenance.expected_ready_at` — if in future, item is unavailable
3. Return the date when item will be available

## Manual Override

### Extend Buffer (Maintenance)
- Staff can extend buffer for repairs
- Set `item_maintenance.status = 'maintenance'`
- Set new `expected_ready_at`
- Item remains unavailable until new date

### Force Available
- Owner can manually mark item as available
- Use with caution — only for items that don't need cleaning
- Creates audit log entry with `actor_id` and reason

## Scheduler Configuration

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule): void
{
    $schedule->command('items:update-buffer-status')
        ->everyFifteenMinutes()
        ->withoutOverlapping();
}
```

```php
// app/Console/Commands/UpdateBufferStatus.php
public function handle(): void
{
    $expired = ItemMaintenance::where('status', 'cleaning')
        ->where('expected_ready_at', '<=', now())
        ->get();

    foreach ($expired as $maintenance) {
        $maintenance->update(['actual_ready_at' => now()]);
        $maintenance->item->update(['status' => 'available']);
    }
}
```

## Gotchas

- **Buffer is mandatory.** Items cannot go from `booked` directly to `available`.
- **Scheduler must be running.** Laravel Scheduler requires cron entry: `* * * * * php /path/to/artisan schedule:run`.
- **Buffer hours are per-tenant.** Different shops can have different buffer durations.
- **Force-available is audited.** Manual overrides are logged with reason.
- **Buffer affects availability API.** The availability check must always consider active buffers.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/items/{id}/availability` | Check item availability with buffer info |
| POST | `/api/v1/items/{id}/maintenance` | Manually activate maintenance buffer |
| PUT | `/api/v1/items/{id}/maintenance` | Update maintenance buffer |
| POST | `/api/v1/items/{id}/force-available` | Force item available (owner only) |
