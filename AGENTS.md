# AGENTS.md — SuitRent SaaS

## Project Overview

Multi-tenant SaaS for wedding suit rental shops. Internal-only (no customer-facing portal). Staff use a Telegram bot (Arabic voice notes) to create bookings; the Shop Owner uses a web dashboard for inventory, financials, and analytics; the System Admin manages the platform-level tenants and subscriptions.

**Stack:** Laravel 13 + Filament (Arabic/RTL) + PostgreSQL + n8n (self-hosted) + Groq Whisper + OpenAI GPT-4o-mini

## Critical Architecture Decisions

- **Multi-tenancy from day one.** Every table has `tenant_id`. All Eloquent queries use a global scope. Never write a query without tenant scoping.
- **PostgreSQL only** (not SQLite). Row-level locking (`SELECT ... FOR UPDATE`) prevents double-booking. JSONB columns store dynamic accessory bundles.
- **No payment gateway integration.** Payments are manual record-keeping only (cash, PalPay, Jawwal Pay, bank transfer).
- **No customer-facing features.** Tenant users are either the Shop Owner or Staff; the System Admin is a separate platform-level role.
- **National ID cards are never scanned/stored.** Only a status flag (`held`/`released`) + text notes.
- **Telegram bot is 1-on-1 DM only.** Auth via `user_id` whitelist per tenant. No groups/channels.

## Required Packages (install before coding)

```bash
composer require filament/filament
composer require stancl/tenancy          # Multi-tenancy
```

## Key Commands

```bash
# Setup
composer install
php artisan key:generate
php artisan migrate --force

# Run
php artisan serve                  # Web dashboard
php artisan queue:work             # Required for async jobs
php artisan schedule:work          # Required for buffer expiry checks

# Test
php artisan test                   # Full suite
php artisan test --filter=Booking  # Single test class

# Code style
./vendor/bin/pint                  # Laravel Pint (auto-format)
```

## Database

- **Driver:** PostgreSQL (update `.env`: `DB_CONNECTION=pgsql`)
- **JSONB** on `items.custom_fields` and `bookings.alterations_notes` for dynamic per-tenant schemas
- **Row locking** in `BookingService` — never rely on application-level checks alone

## Telegram Bot Pipeline (n8n)

```
Voice note → Groq Whisper (whisper-large-v3) → Arabic text
  → GPT-4o-mini (json_schema) → structured JSON
  → Laravel API: availability check
  → Telegram: summary card [Confirm & Save] / [Edit / Cancel]
  → Laravel API: POST /bookings (ACID transaction)
```

- n8n runs on the same VPS, calls Laravel internal API
- Fallback LLM chain: GPT-4o-mini → Claude 3.5 Haiku → Groq Llama 3
- Target: < 5 seconds from voice note to confirmation card

## Roles & Permissions

The project uses exactly three role categories, each with a distinct scope:

- **Shop Owner**: tenant-level owner for one shop. Full control over inventory, financials, staff management, settings, and Telegram whitelist for that shop.
- **Staff**: tenant-level operator. Creates bookings, checks availability, processes returns, and views the daily schedule for that shop only.
- **System Admin**: platform-level administrator. Manages tenants, subscriptions, and the central platform dashboard; does not manage a shop's daily operations.

| Role | Scope | Capabilities |
|---|---|---|
| **Shop Owner** | Single tenant/shop | Full admin: inventory, financials, reports, staff management, Telegram whitelist, settings |
| **Staff** | Single tenant/shop | Create bookings (voice/text), view availability, process returns, view daily schedule |
| **System Admin** | Platform-wide | Manage shops, subscriptions, tenant records, and central platform administration |

## Non-Negotiable Constraints

- 100% database transaction locking — zero double-bookings
- 99%+ uptime during peak hours (12:00–20:00)
- < 5s Telegram voice-to-card response
- Tenant data isolation at the database level (not just UI)
- All UI text in Arabic with RTL layout (Filament supports this natively)

## Environment Variables (VPS / n8n & Laravel)

```env
# VPS / n8n Environment (AI & Telegram Orchestration)
GROQ_API_KEY=
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
TELEGRAM_BOT_TOKEN=
N8N_WEBHOOK_URL=

# Laravel Environment
APP_ENV=production
DB_CONNECTION=pgsql
```

## Directory Structure (Planned)

```
app/
  Models/           # Tenant-scoped models (Item, Booking, BookingItem, CollateralRecord)
  Services/         # BookingService, AvailabilityService, ReturnService, AuditService
  Http/Controllers/ # API controllers (n8n webhook, availability, bookings)
  Filament/         # Admin panel resources
database/
  migrations/       # All tables with tenant_id
  seeders/          # Demo data for pilot shop
n8n/                # Workflow JSON exports (version-controlled)
docker/             # Docker Compose for VPS deployment
```

## What This Is NOT

- Not a customer-facing booking site
- Not a payment processor
- Not a native mobile app (MVP is Telegram + web only)
- Not a generic Laravel app — multi-tenancy and Arabic/RTL are core requirements

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.3. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If a frontend change doesn't show in the UI or you get a "Unable to locate file in Vite manifest" error, run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This project uses PHPUnit. Create tests with `php artisan make:test --phpunit {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/phpunit` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
