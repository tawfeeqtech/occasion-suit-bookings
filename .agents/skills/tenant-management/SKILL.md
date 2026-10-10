---
name: tenant-management
description: Manage SaaS tenants, users, roles, and Telegram whitelist. Use when creating new shop accounts, managing staff access, configuring tenant settings, or handling user authentication. Covers multi-tenant operations including tenant isolation, role assignment, and bot access control.
---

# Tenant Management

## Overview

SuitRent is a multi-tenant SaaS. Each shop is a tenant with isolated data, users, and settings. This skill covers tenant lifecycle, user management, and access control.

## Tenant Structure

```
Tenant (Shop)
├── Users (owner + staff)
├── Items (inventory)
├── Bookings
├── Collateral Records
├── Item Maintenance
├── Audit Logs
└── Settings (buffer_hours, pricing_rules, item_fields)
```

## User Roles

| Role | Capabilities |
|---|---|
| `owner` | Full access: manage users, view reports, configure settings, manage whitelist, view audit logs |
| `staff` | Limited: create bookings, view availability, process returns, view daily schedule |

## User Management

### Create User
- Required: `name`, `email`, `password`, `role`, `tenant_id`
- Optional: `telegram_user_id` (for bot access)
- Email must be unique across all tenants

### Assign Telegram Access
- Owner adds staff's `telegram_user_id` to whitelist
- Whitelist entry: `user_id`, `tenant_id`, `telegram_user_id`, `is_active`
- Non-whitelisted users are rejected by the bot immediately

### Deactivate User
- Set `is_active = false`
- User cannot log in to web or use bot
- Historical audit logs retain user reference

## Tenant Settings

| Setting | Type | Default | Description |
|---|---|---|---|
| `buffer_hours` | integer | 48 | Cleaning buffer duration |
| `auto_late_penalty` | boolean | false | Auto-apply late fees |
| `late_penalty_amount` | decimal | 0 | Flat fee or daily rate |
| `item_fields` | JSONB | {} | Custom field definitions |
| `currency` | string | ILS | Display currency |

## Authentication

### Web Dashboard
- Email + password login
- Optional 2FA (TOTP via Laravel Fortify)
- Session-based authentication
- All pages protected by role middleware

### Telegram Bot
- 1-on-1 private chat only
- `user_id` whitelist validation
- No groups or channels
- Bot rejects non-whitelisted users immediately

## Gotchas

- **Tenant isolation is non-negotiable.** Every query must include `WHERE tenant_id = current_tenant`.
- **Email is globally unique.** Same email cannot be used across different tenants.
- **Telegram user_id is per-tenant.** Same person can be whitelisted in multiple shops.
- **Deactivating a user does not delete their audit logs.** Historical records are preserved.
- **2FA is optional but recommended** for owner accounts.

## API Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| POST | `/api/v1/tenants` | Create new tenant (owner registration) |
| GET | `/api/v1/tenants/{id}` | Get tenant details |
| PUT | `/api/v1/tenants/{id}/settings` | Update tenant settings |
| GET | `/api/v1/tenants/{id}/users` | List tenant users |
| POST | `/api/v1/tenants/{id}/users` | Create user |
| PUT | `/api/v1/tenants/{id}/users/{userId}` | Update user |
| DELETE | `/api/v1/tenants/{id}/users/{userId}` | Deactivate user |
| POST | `/api/v1/tenants/{id}/whitelist` | Add Telegram whitelist entry |
| DELETE | `/api/v1/tenants/{id}/whitelist/{entryId}` | Remove whitelist entry |
