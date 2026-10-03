# Product Requirement Document (PRD)
## SuitRent SaaS — Wedding Suit Rental Management System

---

## 1. Executive Summary

| Field | Detail |
|---|---|
| **Product Name** | SuitRent SaaS |
| **Version** | MVP v1.0 |
| **Problem** | Manual reservation conflicts, buffer-time scheduling errors, and tedious data entry for wedding suit rental shops |
| **Solution** | Voice-driven, multi-tenant SaaS booking system with automated availability checks, dynamic bundle management, and financial tracking |
| **Target Users** | System Admin, Shop Owner, and Staff (internal only) |
| **Platforms** | Telegram bot (Staff) + responsive web dashboard (Shop Owner and System Admin) |
| **Timeline** | 3–4 weeks to pilot MVP |
| **Budget** | <$30/month infrastructure |

---

## 2. User Stories

### 2.1 Shop Owner

| ID | User Story | Priority |
|---|---|---|
| US-O-01 | As a shop owner, I want to log in to the web dashboard with email/password and optional 2FA so that only authorized users access my shop's data | P0 |
| US-O-02 | As a shop owner, I want to manage staff accounts and assign roles (staff vs. shop owner) so that I control who can perform which actions | P0 |
| US-O-03 | As a shop owner, I want to configure my shop's suit inventory with dynamic bundles (jacket, trousers, shirt, vest, shoes, belt, tie, lapel pin) and custom fields so that the system matches my shop's unique catalog | P0 |
| US-O-04 | As a shop owner, I want to set configurable cleaning/buffer turnaround times per item so that suits cannot be rebooked before they are ready | P0 |
| US-O-05 | As a shop owner, I want to view financial reports (revenue, outstanding balances, deposits collected) so that I can track business performance | P1 |
| US-O-06 | As a shop owner, I want to manage the Telegram whitelist (add/remove staff user_ids) so that only authorized staff can use the bot | P0 |
| US-O-07 | As a shop owner, I want to view a calendar of all bookings, returns, and item availability so that I can plan operations | P1 |
| US-O-08 | As a shop owner, I want to configure custom pricing rules (base rental, alterations, accessories) so that the system calculates totals correctly | P1 |

### 2.2 System Admin

| ID | User Story | Priority |
|---|---|---|
| US-PA-01 | As a System Admin, I want to manage shop records and their subscription records from a central platform dashboard so that I can administer tenants independently of shop-owner accounts | P0 |
| US-PA-02 | As a System Admin, I want each shop's subscription to use a fixed monthly fee so that subscription pricing is consistent per shop; the fee amount remains to be determined | P0 |

### 2.3 Staff Member (Operator)

| ID | User Story | Priority |
|---|---|---|
| US-S-01 | As a staff member, I want to send an Arabic voice note to the Telegram bot with all booking details so that I can create a reservation without manual data entry | P0 |
| US-S-02 | As a staff member, I want the bot to parse my voice note, extract structured data, and show me a confirmation card so that I can verify accuracy before saving | P0 |
| US-S-03 | As a staff member, I want the bot to check availability in real-time and warn me of conflicts before I confirm so that double-bookings are prevented | P0 |
| US-S-04 | As a staff member, I want to edit or cancel a parsed booking from the confirmation card so that I can correct mistakes before committing | P0 |
| US-S-05 | As a staff member, I want to view today's and upcoming reservations so that I can prepare for pickups and returns | P1 |
| US-S-06 | As a staff member, I want to process a return with a multi-item checklist inspection so that I can verify all items are accounted for | P0 |
| US-S-07 | As a staff member, I want to record damage/missing items and assess penalty fees so that the customer is charged appropriately | P0 |
| US-S-08 | As a staff member, I want to release the customer's National ID only after all conditions are met (clean pass + fees settled) so that the shop is protected | P0 |
| US-S-09 | As a staff member, I want to record partial payments and track remaining balances so that I know what is still owed | P1 |
| US-S-10 | As a staff member, I want to be rejected by the bot if my Telegram user_id is not whitelisted so that unauthorized users cannot access the system | P0 |

### 2.4 System (Automated)

| ID | User Story | Priority |
|---|---|---|
| US-A-01 | As the system, I want to automatically lock items for booked dates using database transactions so that double-booking is impossible | P0 |
| US-A-02 | As the system, I want to automatically switch returned items to Cleaning/Maintenance status for the configured buffer period so that they cannot be rebooked prematurely | P0 |
| US-A-03 | As the system, I want to flag overdue bookings and optionally apply late penalty fees so that staff are alerted | P1 |
| US-A-04 | As the system, I want to log all critical actions (booking created, return processed, ID released) to an audit trail so that accountability is maintained | P0 |
| US-A-05 | As the system, I want to enforce tenant-level data isolation on every query so that one shop can never see another shop's data | P0 |

---

## 3. Acceptance Criteria (Gherkin Syntax)

### 3.1 Authentication & Authorization

```gherkin
Feature: Web Admin Authentication

  Scenario: Shop Owner logs in with valid credentials
    Given the Shop Owner is on the login page
    When they enter a valid email and password
    Then they are redirected to the dashboard
    And their tenant_id is resolved from their account

  Scenario: Shop Owner enables 2FA
    Given the Shop Owner is logged in
    When they navigate to security settings and enable 2FA
    Then a QR code is displayed for authenticator app setup
    And subsequent logins require a valid TOTP code

  Scenario: Invalid login attempt
    Given the Shop Owner is on the login page
    When they enter an invalid email or password
    Then an error message is displayed
    And no session is created

Feature: Telegram Bot Whitelist Authentication

  Scenario: Whitelisted staff sends a voice note
    Given a Telegram user with user_id "12345" is whitelisted under tenant "shop_a"
    When they send a voice note to the bot
    Then the system resolves tenant_id "shop_a"
    And processes the message within that tenant's context

  Scenario: Non-whitelisted user sends a message
    Given a Telegram user with user_id "99999" is NOT whitelisted
    When they send a message to the bot
    Then the bot replies with an authorization error
    And no data is processed or returned

Feature: System Admin Platform Dashboard

  Scenario: System Admin manages shops and subscriptions
    Given an authenticated System Admin
    When they open the central platform dashboard
    Then they can view and manage shop records and their subscription records
    And subscription pricing is recorded as a fixed monthly fee per shop
    And the Shop Owner cannot access other shops' records through this dashboard
```

### 3.2 Voice-Driven Booking Flow

```gherkin
Feature: Voice Note to Booking

  Scenario: Successful voice booking with complete data
    Given a whitelisted staff member sends an Arabic voice note containing:
      | Field          | Value                    |
      | Customer Name  | أحمد محمد               |
      | Phone          | 0599123456               |
      | Suit           | Classic Black Size 40    |
      | Shirt          | Size 36 White            |
      | Shoes          | Size 42 Brown            |
      | Belt           | Camel Leather            |
      | Pickup Date    | 2026-10-15              |
      | Return Date    | 2026-10-18              |
      | Advance Paid   | 200 ILS                  |
      | Payment Method | Cash                     |
    When the system processes the voice note
    Then Groq Whisper transcribes the audio to Arabic text
    And GPT-4o-mini extracts structured JSON matching the tenant's schema
    And the system checks availability for the suit and accessories
    And the bot replies with a formatted summary card
    And the card shows [Confirm & Save] and [Edit / Cancel] buttons

  Scenario: Staff confirms the booking
    Given the bot has displayed a booking summary card
    When the staff member taps [Confirm & Save]
    Then the booking is committed to the database within an ACID transaction
    And the suit and all accessories are locked for the selected dates
    And an audit log entry is created with the staff member's identity
    And the bot replies with a success confirmation

  Scenario: Staff edits the booking
    Given the bot has displayed a booking summary card
    When the staff member taps [Edit / Cancel]
    Then the bot prompts for the specific field to correct
    And the corrected data is re-validated
    And a new summary card is displayed

  Scenario: Missing critical data in voice note
    Given a voice note is missing the return date
    When the LLM extracts the structured data
    Then the bot replies with a specific prompt: "Please provide the expected return date"
    And no booking is created until the missing data is supplied

  Scenario: Availability conflict detected
    Given a voice note requests Suit "Classic Black Size 40" for 2026-10-15 to 2026-10-18
    And that suit is already booked for 2026-10-14 to 2026-10-17
    When the system performs the availability pre-check
    Then the bot replies with a conflict warning showing the overlapping booking
    And the [Confirm & Save] button is disabled or requires override confirmation
```

### 3.3 Return & Inspection Workflow

```gherkin
Feature: Return Processing

  Scenario: Clean pass return
    Given a booking exists with status "Active"
    And the customer returns all items on the expected return date
    When the staff member processes the return and marks all items as "Clean Pass"
    Then the booking status changes to "Completed"
    And the system prompts to release the National ID
    And the staff confirms ID release
    And the customer's collateral status is updated to "ID Released"
    And any remaining balance is flagged for collection

  Scenario: Damage detected on return
    Given a booking exists with status "Active"
    And the customer returns items with a torn jacket
    When the staff member marks the jacket as "Damaged"
    Then the system prompts for penalty/replacement fee assessment
    And the staff enters the penalty amount
    And the customer's collateral status remains "ID Held"
    And the booking status changes to "Damage Pending"

  Scenario: Penalty fee settled and ID released
    Given a booking has status "Damage Pending" with an outstanding penalty
    When the customer pays the penalty fee
    And the staff records the payment
    Then the system prompts to release the National ID
    And the staff confirms ID release
    And the customer's collateral status is updated to "ID Released"
    And the booking status changes to "Completed"

  Scenario: Missing accessory on return
    Given a booking includes a belt and lapel pin
    And the customer returns the suit but not the belt
    When the staff marks the belt as "Missing"
    Then the system prompts for replacement fee assessment
    And the customer's collateral status remains "ID Held"
    And the booking status changes to "Damage Pending"

  Scenario: Late return flagged
    Given a booking has expected return date "2026-10-18"
    And the customer returns items on "2026-10-20"
    When the staff processes the return
    Then the system flags the booking as "Overdue"
    And the overdue days are calculated
    And if late penalty is configured, the penalty fee is auto-applied
```

### 3.4 Automated Buffer & Availability

```gherkin
Feature: Cleaning Buffer Management

  Scenario: Item enters cleaning buffer after return
    Given a suit is returned and marked "Clean Pass"
    And the tenant's cleaning buffer is configured to 48 hours
    When the return is processed
    Then the suit status changes to "Cleaning / Maintenance"
    And the suit is unavailable for new bookings until 48 hours have elapsed
    And the system sets a scheduled task to mark it "Ready" after the buffer

  Scenario: Attempt to book item in cleaning buffer
    Given a suit is in "Cleaning / Maintenance" status
    When a new booking requests that suit
    Then the availability check returns "Unavailable"
    And the bot displays the date when the suit will be ready

  Scenario: Buffer period expires
    Given a suit entered "Cleaning / Maintenance" at "2026-10-18 14:00"
    And the buffer is 48 hours
    When the current time reaches "2026-10-20 14:00"
    Then the suit status automatically changes to "Ready / Available"
    And it can be booked for new reservations
```

### 3.5 Multi-Tenancy & Data Isolation

```gherkin
Feature: Tenant Isolation

  Scenario: Staff from Shop A cannot see Shop B's data
    Given a staff member belongs to tenant "shop_a"
    When they query bookings via the Telegram bot
    Then only bookings belonging to "shop_a" are returned
    And no data from "shop_b" is accessible

  Scenario: Owner cannot access another tenant's dashboard
    Given an owner is logged in under tenant "shop_a"
    When they attempt to access a URL with tenant_id "shop_b"
    Then the request is rejected with a 403 Forbidden error
    And no data from "shop_b" is displayed

  Scenario: Database-level tenant scoping
    Given a query is executed for any table with tenant_id
    Then the query automatically includes WHERE tenant_id = current_tenant
    And no application code can bypass this filter
```

### 3.6 Audit Logging

```gherkin
Feature: Audit Trail

  Scenario: Booking creation is logged
    Given a staff member confirms a booking via the Telegram bot
    When the booking is committed
    Then an audit log entry is created containing:
      | Field         | Value                  |
      | timestamp     | 2026-09-30T14:30:00Z   |
      | actor_type    | staff                  |
      | actor_id      | telegram:12345         |
      | tenant_id     | shop_a                 |
      | action        | booking.created        |
      | entity_type   | booking                |
      | entity_id     | booking_001            |
      | metadata      | {summary of booking}   |

  Scenario: Return processing is logged
    Given a staff member processes a return
    When the return is committed
    Then an audit log entry is created with action "return.processed"
    And the entry includes the inspection results

  Scenario: ID release is logged
    Given a staff member releases a customer's National ID
    When the release is confirmed
    Then an audit log entry is created with action "collateral.id_released"
    And the entry includes the staff member's identity and timestamp
```

### 3.7 Performance & Reliability

```gherkin
Feature: System Performance

  Scenario: Voice note to confirmation card under 5 seconds
    Given a whitelisted staff member sends a voice note
    When the system processes the entire pipeline (STT + LLM + availability check)
    Then the confirmation card is displayed within 5 seconds

  Scenario: Concurrent booking attempts do not double-book
    Given two staff members attempt to book the same suit for overlapping dates simultaneously
    When both requests reach the database
    Then only one transaction commits successfully
    And the other receives a conflict error
    And no double-booking exists in the database

  Scenario: System uptime during peak hours
    Given the system is in production
    When measured during peak shop hours (12:00–20:00)
    Then uptime is 99% or higher
    And no unplanned downtime exceeds 5 minutes
```

---

## 4. Technical Architecture Recommendation

### 4.1 High-Level Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                              │
│  ┌─────────────────────┐    ┌─────────────────────────────────┐  │
│  │   Telegram Bot      │    │   Web Admin Dashboard           │  │
│  │   (Staff UI)       │    │   (Owner/Admin UI)              │  │
│  │   1-on-1 DM only    │    │   Responsive Arabic/RTL         │  │
│  └────────┬────────────┘    └──────────────┬──────────────────┘  │
└───────────┼────────────────────────────────┼─────────────────────┘
            │                                │
            ▼                                ▼
┌─────────────────────────────────────────────────────────────────┐
│                     ORCHESTRATION LAYER                          │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                    Self-Hosted n8n                          ││
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────────┐  ││
│  │  │ Telegram      │  │ Webhook      │  │ HTTP Request     │  ││
│  │  │ Webhook       │  │ Receiver     │  │ → Laravel API    │  ││
│  │  └──────┬───────┘  └──────┬───────┘  └──────────────────┘  ││
│  │         │                 │                                  ││
│  │         ▼                 ▼                                  ││
│  │  ┌──────────────────────────────────────────────────────┐   ││
│  │  │              AI Processing Pipeline                  │   ││
│  │  │  1. Audio → Groq Whisper (whisper-large-v3)          │   ││
│  │  │  2. Arabic Text → GPT-4o-mini (structured JSON)      │   ││
│  │  │  3. Fallback: Claude 3.5 Haiku / Groq Llama 3        │   ││
│  │  └──────────────────────────────────────────────────────┘   ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                      API / BUSINESS LOGIC LAYER                  │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                  Laravel + Filament                        ││
│  │                                                             ││
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────────────┐ ││
│  │  │ Filament    │  │ Laravel     │  │ Laravel Fortify     │ ││
│  │  │ Admin Panel │  │ REST API    │  │ (Auth + 2FA)        │ ││
│  │  │ (RTL/Arabic)│  │ (Business   │  │                     │ ││
│  │  │             │  │  Logic)     │  │                     │ ││
│  │  └─────────────┘  └──────┬──────┘  └─────────────────────┘ ││
│  │                          │                                  ││
│  │  ┌───────────────────────┴───────────────────────────────┐  ││
│  │  │              Core Domain Services                      │  ││
│  │  │  • BookingService (ACID transactions, row locking)    │  ││
│  │  │  • AvailabilityService (conflict detection + buffer)  │  ││
│  │  │  • InventoryService (dynamic bundles, custom fields)  │  ││
│  │  │  • ReturnService (inspection, penalties, collateral)  │  ││
│  │  │  • AuditService (activity logging)                    │  ││
│  │  │  • TenantService (multi-tenancy scoping)              │  ││
│  │  └───────────────────────────────────────────────────────┘  ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                        DATA LAYER                                │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                     PostgreSQL                               ││
│  │                                                             ││
│  │  • Row-level locking (SELECT ... FOR UPDATE)                ││
│  │  • JSONB columns for dynamic accessory configurations      ││
│  │  • tenant_id column on all tables (global scope)            ││
│  │  • Audit log table (append-only)                            ││
│  │  • Scheduled tasks (pg_cron / Laravel Scheduler)             ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
            │
            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     INFRASTRUCTURE LAYER                         │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │              Single Linux VPS (Docker)                      ││
│  │                                                             ││
│  │  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌───────────────┐  ││
│  │  │ Nginx   │  │ Laravel │  │  n8n    │  │ PostgreSQL    │  ││
│  │  │ Reverse │  │  PHP-FPM│  │         │  │               │  ││
│  │  │ Proxy   │  │         │  │         │  │               │  ││
│  │  └─────────┘  └─────────┘  └─────────┘  └───────────────┘  ││
│  │                                                             ││
│  │  External APIs:                                             ││
│  │  • Groq API (Whisper STT)                                   ││
│  │  • OpenAI API (GPT-4o-mini)                                 ││
│  │  • Telegram Bot API                                         ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
```

### 4.2 Database Schema (Core Tables)

```sql
-- Multi-tenancy core
CREATE TABLE tenants (
    id UUID PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    settings JSONB DEFAULT '{}',        -- buffer hours, pricing rules, etc.
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Users & Auth
CREATE TABLE users (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(50) NOT NULL,          -- 'owner' | 'staff'
    two_factor_secret VARCHAR(255),
    telegram_user_id BIGINT,            -- nullable, for bot whitelist
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Inventory: Suits & Items
CREATE TABLE items (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    name VARCHAR(255) NOT NULL,         -- "Classic Black Suit"
    category VARCHAR(100) NOT NULL,     -- 'suit' | 'shirt' | 'shoes' | 'belt' | 'tie' | 'vest' | 'lapel_pin'
    size VARCHAR(50),                   -- "40", "36", "42"
    color VARCHAR(100),
    status VARCHAR(50) DEFAULT 'available', -- 'available' | 'booked' | 'cleaning' | 'maintenance' | 'retired'
    custom_fields JSONB DEFAULT '{}',   -- dynamic per-tenant fields
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Bookings
CREATE TABLE bookings (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    customer_name VARCHAR(255) NOT NULL,
    customer_phone VARCHAR(50) NOT NULL,
    customer_id_number VARCHAR(100),    -- National ID number (text only, no images)
    pickup_date TIMESTAMPTZ NOT NULL,
    event_date TIMESTAMPTZ,             -- optional wedding/event date
    return_date TIMESTAMPTZ NOT NULL,
    status VARCHAR(50) DEFAULT 'active',   -- 'active' | 'completed' | 'overdue' | 'damage_pending' | 'cancelled'
    total_fee DECIMAL(10,2) NOT NULL,
    advance_paid DECIMAL(10,2) DEFAULT 0,
    remaining_balance DECIMAL(10,2) GENERATED ALWAYS AS (total_fee - advance_paid) STORED,
    payment_method VARCHAR(100),        -- 'cash' | 'palpay' | 'jawwal_pay' | 'bank_transfer'
    alterations_notes TEXT,
    created_by UUID REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Booking Items (dynamic bundles)
CREATE TABLE booking_items (
    id UUID PRIMARY KEY,
    booking_id UUID NOT NULL REFERENCES bookings(id) ON DELETE CASCADE,
    item_id UUID NOT NULL REFERENCES items(id),
    is_primary BOOLEAN DEFAULT FALSE,   -- true for main suit
    alteration_notes TEXT,
    return_status VARCHAR(50),          -- 'clean_pass' | 'damaged' | 'missing' | NULL
    penalty_fee DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Collateral Tracking
CREATE TABLE collateral_records (
    id UUID PRIMARY KEY,
    booking_id UUID NOT NULL REFERENCES bookings(id),
    document_type VARCHAR(100) NOT NULL DEFAULT 'national_id',
    status VARCHAR(50) NOT NULL DEFAULT 'held',  -- 'held' | 'released'
    notes TEXT,
    held_at TIMESTAMPTZ DEFAULT NOW(),
    released_at TIMESTAMPTZ,
    released_by UUID REFERENCES users(id)
);

-- Cleaning Buffer Tracking
CREATE TABLE item_maintenance (
    id UUID PRIMARY KEY,
    item_id UUID NOT NULL REFERENCES items(id),
    booking_id UUID REFERENCES bookings(id),
    status VARCHAR(50) NOT NULL,        -- 'cleaning' | 'maintenance'
    started_at TIMESTAMPTZ DEFAULT NOW(),
    expected_ready_at TIMESTAMPTZ NOT NULL,
    actual_ready_at TIMESTAMPTZ
);

-- Audit Log (append-only)
CREATE TABLE audit_logs (
    id UUID PRIMARY KEY,
    tenant_id UUID NOT NULL REFERENCES tenants(id),
    actor_type VARCHAR(50) NOT NULL,    -- 'user' | 'system'
    actor_id VARCHAR(255),              -- user UUID or 'system'
    action VARCHAR(100) NOT NULL,       -- 'booking.created', 'return.processed', etc.
    entity_type VARCHAR(100) NOT NULL,
    entity_id UUID,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for performance
CREATE INDEX idx_items_tenant_status ON items(tenant_id, status);
CREATE INDEX idx_bookings_tenant_dates ON bookings(tenant_id, pickup_date, return_date);
CREATE INDEX idx_booking_items_booking ON booking_items(booking_id);
CREATE INDEX idx_audit_logs_tenant_created ON audit_logs(tenant_id, created_at DESC);
```

### 4.3 Key API Endpoints (Laravel REST API)

| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/telegram/webhook` | n8n → Laravel: receive parsed booking data |
| POST | `/api/v1/availability/check` | Check item availability for date range |
| POST | `/api/v1/bookings` | Create booking (ACID transaction) |
| GET | `/api/v1/bookings` | List bookings (tenant-scoped) |
| GET | `/api/v1/bookings/today` | Today's reservations |
| POST | `/api/v1/bookings/{id}/return` | Process return with inspection |
| POST | `/api/v1/bookings/{id}/collateral/release` | Release National ID |
| GET | `/api/v1/items` | List inventory |
| POST | `/api/v1/items` | Create item |
| GET | `/api/v1/reports/financial` | Financial reports |
| GET | `/api/v1/audit-logs` | Audit trail |

### 4.4 n8n Workflow Design

```
[Telegram Webhook] 
    → [Download Voice File from Telegram]
    → [Groq Whisper API: Audio → Arabic Text]
    → [GPT-4o-mini: Text → Structured JSON (json_schema)]
    → [Laravel API: POST /availability/check]
    → [IF conflict → Telegram: Send conflict warning]
    → [ELSE → Telegram: Send summary card with inline buttons]
        → [Button: Confirm & Save]
            → [Laravel API: POST /bookings]
            → [Telegram: Send success confirmation]
        → [Button: Edit / Cancel]
            → [Telegram: Prompt for correction]
```

### 4.5 Security Architecture

| Layer | Mechanism |
|---|---|
| **Transport** | HTTPS/TLS everywhere (Nginx + Let's Encrypt) |
| **Web Auth** | Laravel Fortify (email/password + optional TOTP 2FA) |
| **Bot Auth** | Telegram user_id whitelist per tenant |
| **API Auth** | Laravel Sanctum tokens (scoped per tenant) |
| **Data Isolation** | Global scope on all Eloquent models (`tenant_id`) |
| **Database** | Row-level locking (`SELECT FOR UPDATE`) for booking conflicts |
| **Audit** | Append-only `audit_logs` table |
| **Secrets** | Environment variables / Docker secrets (never in code) |
| **Backups** | Daily PostgreSQL dump to object storage |

### 4.6 Deployment Architecture (Docker Compose)

```yaml
version: '3.8'
services:
  nginx:
    image: nginx:alpine
    ports: ["80:80", "443:443"]
    volumes: ["./nginx.conf:/etc/nginx/nginx.conf", "./ssl:/etc/nginx/ssl"]
    
  laravel:
    build: ./laravel
    environment:
      - DB_HOST=postgres
      - DB_DATABASE=suitrent
      - GROQ_API_KEY=${GROQ_API_KEY}
      - OPENAI_API_KEY=${OPENAI_API_KEY}
    depends_on: [postgres]
    
  n8n:
    image: n8nio/n8n
    ports: ["5678:5678"]
    environment:
      - WEBHOOK_URL=https://your-domain.com/
    depends_on: [laravel]
    
  postgres:
    image: postgres:16-alpine
    environment:
      - POSTGRES_DB=suitrent
      - POSTGRES_USER=suitrent
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    volumes: ["postgres_data:/var/lib/postgresql/data"]
    
volumes:
  postgres_data:
```

### 4.7 Cost Estimate (Monthly)

| Component | Provider | Est. Cost |
|---|---|---|
| Linux VPS (2 vCPU, 4GB RAM) | Hetzner/DigitalOcean | $10–20 |
| Groq Whisper API | Groq | $2–5 |
| OpenAI GPT-4o-mini | OpenAI | $1–5 |
| Domain + SSL | Cloudflare/Namecheap | $1–2 |
| **Total** | | **$14–32** |

---

## 5. MVP Scope vs. Future Phases

### MVP (Weeks 1–4) — Pilot Shop
- [x] Multi-tenant architecture (1 active pilot shop)
- [x] Web admin dashboard (Arabic/RTL, Filament)
- [x] Telegram bot with voice-to-booking pipeline
- [x] Dynamic item catalog with custom fields
- [x] Booking creation with availability check
- [x] Return processing with inspection checklist
- [x] Collateral (National ID) tracking
- [x] Automated cleaning buffer
- [x] Basic financial tracking (payments, balances)
- [x] Audit logging
- [x] RBAC (owner vs. staff)

### Phase 2 (Weeks 5–8) — Public SaaS Onboarding
- [ ] Self-service tenant registration
- [ ] Onboarding wizard (shop setup, inventory import)
- [ ] Advanced financial reports & analytics
- [ ] Calendar view with drag-and-drop
- [ ] SMS/WhatsApp notifications to customers
- [ ] Multi-language support (English)
- [ ] Subscription billing integration
- [ ] Data export (CSV/Excel)

### Phase 3 (Future) — Scale
- [ ] Mobile app (Flutter/React Native)
- [ ] Customer-facing booking portal
- [ ] Payment gateway integration (PalPay, Jawwal Pay)
- [ ] AI-powered demand forecasting
- [ ] Multi-location support
- [ ] API marketplace for third-party integrations

---

## 6. Risks & Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Arabic dialect accuracy in Whisper | High | Use `whisper-large-v3` (best Arabic support); allow staff to edit before confirm |
| GPT-4o-mini hallucination on entity extraction | Medium | JSON schema enforcement + human confirmation card before commit |
| VPS single point of failure | Medium | Daily automated backups; migrate to cloud HA after pilot |
| Telegram API rate limits | Low | Queue processing in n8n; batch webhook handling |
| Scope creep in MVP | High | Strict Phase 1 feature freeze; Phase 2 backlog separate |

---

**Document Version:** 1.0
**Date:** 2026-09-30
**Status:** Ready for Development Kickoff
