# Laravel 12 Multi-Tenant SaaS Starter

Production-minded SaaS starter built with Laravel 12, Inertia + React, PostgreSQL, Redis queues, tenant isolation, billing primitives, usage metering, admin tooling, API keys, and centralized audit logging.

## Core Features

- Multi-tenant organizations with UUID primary keys
- Membership + RBAC (`owner`, `admin`, `member`)
- Tenant resolution middleware with session context
- Plan and entitlement system (Free / Pro / Enterprise)
- Stripe/Cashier subscription sync primitives (webhook-driven local plan sync)
- Internal usage metering + monthly aggregation
- API key auth for organization-scoped API access
- Per-tenant API rate limiting
- Idempotency and retry-safety primitives
- Failed-domain-event diagnostics
- Platform admin console (suspension + impersonation context)
- Centralized audit logging with tenant/platform visibility

## Architecture Summary

- **Tenant boundary:** `organizations` + `organization_user` pivot
- **Current tenant resolution:** `ResolveOrganizationFromSession` middleware
- **Write guard:** `EnsureOrganizationCanWrite` middleware (suspension-aware)
- **Entitlements:** `EntitlementService` using plan limits JSON
- **Usage pipeline:** `UsageRecorder` + `UsageAggregator` + `ProcessUsageEvent`
- **Billing sync:** `StripePlanSyncService` + `StripeWebhookController`
- **Audit trail:** `AuditLogger` + `audit_logs` query surfaces
- **Reliability:** idempotency keys + failed domain events + diagnostics UI

## Tech Stack

- PHP 8.2+
- Laravel 12
- Inertia + React + Tailwind
- PostgreSQL (default app DB)
- Redis (queue/cache in non-test environments)
- PHPUnit for automated tests

## Prerequisites

- PHP 8.2+
- Composer 2.x
- Node.js `20.19+` (or `22.12+`) and npm
- PostgreSQL 14+
- Redis 7+

## Local Setup (Non-Docker)

1. Install dependencies:
   - `composer install`
   - `npm install`
2. Create environment file:
   - `cp .env.example .env`
3. Configure required env values:
   - app URL, DB credentials, Redis connection
   - Stripe placeholders (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`)
   - `SUPER_ADMIN_EMAILS`
4. Generate app key:
   - `php artisan key:generate`
5. Run migrations + seed plans:
   - `php artisan migrate`
   - `php artisan db:seed`
6. Start app:
   - `composer dev`

## Local Setup (Laravel Sail)

If you prefer Dockerized local development:

1. Install Sail dependencies:
   - `composer install`
2. Initialize Sail stack with PostgreSQL + Redis:
   - `php artisan sail:install --with=pgsql,redis`
3. Start containers:
   - `./vendor/bin/sail up -d`
4. Initialize app:
   - `./vendor/bin/sail artisan key:generate`
   - `./vendor/bin/sail artisan migrate --seed`
5. Start frontend:
   - `./vendor/bin/sail npm install`
   - `./vendor/bin/sail npm run dev`

## Testing

This project uses PHPUnit with SQLite in-memory defaults for speed.

- Run tests:
  - `composer test`
- Coverage (requires Xdebug or PCOV):
  - `composer test:coverage`

Optional testing env template: `.env.testing.example`.

## Code Quality

- Format code:
  - `composer format`
- Check formatting:
  - `composer lint`
- Static analysis (Larastan/PHPStan):
  - `composer analyse`
- Run all quality gates:
  - `composer quality`

Config files:

- `pint.json`
- `phpstan.neon`

## Frontend Validation

- Production build:
  - `npm run build`
- CI-friendly build command:
  - `npm run build:ci`

## CI

GitHub Actions workflow (`.github/workflows/tests.yml`) runs on push and pull request:

- Composer install
- `.env` setup + app key generation
- Migrations
- Frontend dependency install + production build
- Pint check
- PHPStan analysis
- PHPUnit test suite

## Validate CI Locally

Run the same checks as CI:

1. `composer install`
2. `npm ci`
3. `cp .env.example .env`
4. `php artisan key:generate`
5. `php artisan migrate`
6. `composer lint`
7. `composer analyse`
8. `composer test`
9. `npm run build:ci`

## Environment Variables (Key Ones)

### Core

- `APP_*`
- `DB_*`
- `REDIS_*`
- `QUEUE_CONNECTION`
- `CACHE_STORE`

### Tenancy / Admin / Platform

- `SUPER_ADMIN_EMAILS`
- `SUSPENSION_BLOCK_WRITES`
- `SEED_DEMO_DATA`

### Billing

- `STRIPE_KEY`
- `STRIPE_SECRET`
- `STRIPE_WEBHOOK_SECRET`

### Platform Tunables

- `PLATFORM_ORG_SUMMARY_CACHE_TTL`
- `PLATFORM_ORG_ACCESS_CACHE_TTL`
- `PLATFORM_USER_ORGS_CACHE_TTL`
- `PLATFORM_PLAN_LIMITS_CACHE_TTL`
- `PLATFORM_USAGE_MONTHLY_CACHE_TTL`
- `PLATFORM_MEMBER_COUNT_CACHE_TTL`
- `ORG_API_RATE_LIMIT_PER_MINUTE`
- `MEMBER_INVITE_RATE_LIMIT_PER_MINUTE`
- `API_KEY_CREATE_RATE_LIMIT_PER_MINUTE`
- `BILLING_CHECKOUT_RATE_LIMIT_PER_MINUTE`
- `PROCESS_USAGE_EVENT_TRIES`
- `PROCESS_USAGE_EVENT_TIMEOUT`
- `PROCESS_USAGE_EVENT_BACKOFF`

## Demo Data

Plans are seeded by default via `PlanSeeder`.

To also seed demo tenant/users/usage data:

1. Set `SEED_DEMO_DATA=true` in `.env`
2. Run:
   - `php artisan db:seed`

Or seed directly:

- `php artisan db:seed --class=DemoDataSeeder`
- `php artisan db:seed --class=UsageEventDemoSeeder`

Demo credentials (all password: `password`):

- `owner@example.com`
- `admin@example.com`
- `member@example.com`

If you want demo admin console access, set:

- `SUPER_ADMIN_EMAILS=admin@example.com`

## High-Level Flow Notes

- **Tenancy:** authenticated requests resolve an active organization from session
- **Billing sync:** Stripe webhook events map Stripe price IDs to local plans
- **Entitlements:** plan limits gate writes (members, usage, etc.)
- **Usage metering:** usage events are recorded transactionally and aggregated monthly
- **Audit logging:** meaningful write/security/billing actions are centrally logged

## Screenshots (Placeholders)

- Tenant Dashboard
- Members & RBAC
- Billing
- Usage
- Admin Console
- System Health / Diagnostics
