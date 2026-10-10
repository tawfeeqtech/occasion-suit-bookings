---
name: inventory-management
description: Manage suit rental inventory including items, categories, sizes, colors, and dynamic custom fields. Use when adding new items, updating item status, configuring custom fields per tenant, or managing item categories (suits, shirts, shoes, belts, ties, vests, lapel pins). Covers the full inventory lifecycle from creation to retirement.
---

# Inventory Management

## Overview

Inventory consists of rental items (suits and accessories) that can be booked by customers. Each item belongs to a tenant and has a dynamic schema supporting custom fields per shop.

## Item Categories

| Category | Description | Typical Fields |
|---|---|---|
| `suit` | Main suit (jacket + trousers) | size, color, brand, material |
| `shirt` | Dress shirt | size, color, sleeve_length |
| `shoes` | Dress shoes | size, color, style |
| `belt` | Leather belt | color, material, width |
| `tie` | Necktie | color, pattern, material |
| `vest` | Waistcoat | size, color, material |
| `lapel_pin` | Lapel accessory | style, material |

## Item Lifecycle

```
available → booked → cleaning → available
                ↓
           maintenance → available
                ↓
            retired (permanent)
```

## Item Status Definitions

| Status | Meaning | Can Book? |
|---|---|---|
| `available` | Ready for booking | Yes |
| `booked` | Currently rented out | No |
| `cleaning` | Post-return cleaning buffer | No |
| `maintenance` | Under repair/alteration | No |
| `retired` | Permanently out of service | No |

## Dynamic Custom Fields

Each tenant can define custom fields for items. Stored in `items.custom_fields` as JSONB:

```json
{
  "brand": "Hugo Boss",
  "material": "Wool",
  "purchase_date": "2025-03-15",
  "condition_notes": "Excellent"
}
```

## Item CRUD Operations

### Create Item
- Required: `name`, `category`, `tenant_id`
- Optional: `size`, `color`, `custom_fields`
- Default status: `available`

### Update Item
- Can update: `name`, `size`, `color`, `custom_fields`, `status`
- Cannot update: `tenant_id` (immutable after creation)

### Retire Item
- Set status to `retired`
- Item no longer appears in availability searches
- Historical bookings retain item reference

## Gotchas

- **Tenant scoping is automatic.** All item queries include `WHERE tenant_id = current_tenant`.
- **Status transitions are enforced.** Cannot go from `booked` directly to `available` — must go through `cleaning` first.
- **Custom fields are tenant-specific.** Field definitions are stored in `tenants.settings.item_fields`.
- **Item names should be descriptive.** Include brand + style + color for easy identification (e.g., "Classic Black Suit - Hugo Boss").

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| GET | `/api/v1/items` | List inventory (filterable by category, status) |
| POST | `/api/v1/items` | Create new item |
| GET | `/api/v1/items/{id}` | Get item details |
| PUT | `/api/v1/items/{id}` | Update item |
| DELETE | `/api/v1/items/{id}` | Retire item (soft delete) |
