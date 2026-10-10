# Booking Schema Reference

## Database Tables

### `bookings`

| Column | Type | Description |
|---|---|---|
| id | UUID | Primary key |
| tenant_id | UUID | Tenant foreign key |
| customer_name | VARCHAR(255) | Customer full name |
| customer_phone | VARCHAR(50) | Customer mobile |
| customer_id_number | VARCHAR(100) | National ID (text only) |
| pickup_date | TIMESTAMPTZ | Pickup datetime |
| event_date | TIMESTAMPTZ | Optional event date |
| return_date | TIMESTAMPTZ | Expected return datetime |
| status | VARCHAR(50) | active, completed, overdue, damage_pending, cancelled |
| total_fee | DECIMAL(10,2) | Total rental fee |
| advance_paid | DECIMAL(10,2) | Amount paid upfront |
| remaining_balance | DECIMAL(10,2) | Generated: total_fee - advance_paid |
| payment_method | VARCHAR(100) | cash, palpay, jawwal_pay, bank_transfer |
| alterations_notes | TEXT | Tailoring details |
| created_by | UUID | User who created the booking |

### `booking_items`

| Column | Type | Description |
|---|---|---|
| id | UUID | Primary key |
| booking_id | UUID | Foreign key to bookings |
| item_id | UUID | Foreign key to items |
| is_primary | BOOLEAN | True for main suit |
| alteration_notes | TEXT | Per-item alteration notes |
| return_status | VARCHAR(50) | clean_pass, damaged, missing |
| penalty_fee | DECIMAL(10,2) | Penalty for damage/missing |

### `collateral_records`

| Column | Type | Description |
|---|---|---|
| id | UUID | Primary key |
| booking_id | UUID | Foreign key to bookings |
| document_type | VARCHAR(100) | Default: national_id |
| status | VARCHAR(50) | held, released |
| notes | TEXT | Operational notes |
| held_at | TIMESTAMPTZ | When ID was held |
| released_at | TIMESTAMPTZ | When ID was returned |
| released_by | UUID | User who released it |
