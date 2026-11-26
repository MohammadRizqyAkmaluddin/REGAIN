# RE:GAIN — Architecture & Implementation Specification

## 1. System Overview

RE:GAIN is a personal finance and smoking cessation companion built as a **Modular Monolith** backend (Laravel 13) paired with a decoupled **Mobile-First PWA** frontend (Nuxt 4 + Vue 3).

Core Philosophy:
> "Every cigarette you don't buy is money you keep for your future."

## 2. Technology Stack

| Layer | Technology |
| --- | --- |
| Frontend | Nuxt 4, Vue 3 Composition API, TypeScript, Tailwind CSS 4, Pinia, VueUse |
| Charts & Offline | Apache ECharts (`vue-echarts`), Dexie.js (IndexedDB), `@vite-pwa/nuxt`, Workbox |
| Backend | Laravel 13, PHP 8.4, Laravel Sanctum (API Tokens), Minishlink Web Push PHP |
| Data & Queue | MySQL 8.4, Redis 7 (`predis`), Laravel Queue, Laravel Scheduler |
| Infrastructure | Docker, Docker Compose, Nginx, Ubuntu VPS, HTTPS |

## 3. Domain Separation & Financial Integrity

RE:GAIN enforces strict separation between **Actual Finances** and **Estimated Smoking Savings**:

1. **Actual Net Worth**
   $$\text{Actual Net Worth} = \sum \text{Opening Balance} + \sum \text{Actual Income} - \sum \text{Actual Expenses}$$
   - All monetary columns use `DECIMAL(15, 2)` in MySQL.
   - All financial calculations are performed exclusively on the Laravel backend using `bcmath` / scaled integer arithmetic.
   - Frontend formatting (`Intl.NumberFormat('id-ID')`) is strictly for presentation.
2. **Estimated Smoking Savings**
   - Computed from the configured smoking profile (packs per week, price per pack, quit date, and relapse events).
   - **Never** inserted into `transactions`, **never** counted as actual income, and **never** added to actual net worth.
   - Can be **virtually allocated** to `financial_goals` via `allocated_smoking_savings` without altering actual account balances.

## 4. Database Schema

Normalized relational tables:
- `users`: User identity and Sanctum authentication.
- `accounts`: Actual financial accounts (`opening_balance`, `currency`, `is_default`).
- `categories` & `subcategories`: Configurable income & expense taxonomy (system defaults + user-customizable).
- `transactions`: Actual income/expense ledger (`type`, `amount`, `category_id`, `subcategory_id`, `account_id`, `description`, `transaction_date`, `transaction_time`, `client_uuid` for offline idempotency).
- `recurring_incomes`: Recurring salary / income rules (`amount`, `day_of_month`, `next_run_date`, `is_active`).
- `recurring_executions`: Idempotency ledger with unique constraint `(recurring_income_id, execution_period)` preventing duplicate monthly executions.
- `smoking_profiles`: Quit configuration (`quit_date`, `packs_per_week`, `price_per_pack`, `cigarettes_per_pack`).
- `smoking_savings`: Daily/periodic computed savings snapshots.
- `smoking_events`: Non-judgmental milestone & relapse log (`event_type`, `event_date`, `packs_smoked`, `notes`) preserving all historical progress.
- `financial_goals`: User savings targets (`target_amount`, `current_actual_allocation`, `allocated_smoking_savings`, `target_date`, `status`).
- `push_subscriptions`: Web Push endpoints & VAPID keys (`endpoint_hash`, `public_key`, `auth_token`, `content_encoding`).
- `notification_logs`: Audit trail & deduplication/cooldown tracking for motivational push notifications.
- `app_settings`: Per-user preferences (theme, timezone `Asia/Jakarta`, notification active hours, daily cap, cooldown minutes).

## 5. API Architecture

RESTful JSON API under `/api/v1` protected by `auth:sanctum` (except auth register/login and health check):

- `POST /api/v1/auth/register`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`, `GET /api/v1/auth/me`
- `GET /api/v1/dashboard` — Consolidated net worth, monthly income/expense, smoking summary, chart datasets, recent transactions
- `GET/POST/PUT/DELETE /api/v1/accounts`
- `GET/POST/PUT/DELETE /api/v1/categories` & `/subcategories`
- `GET/POST/PUT/DELETE /api/v1/transactions` & `POST /api/v1/transactions/sync` (offline batch sync with `client_uuid` deduplication)
- `GET/POST/PUT/DELETE /api/v1/recurring-incomes` & `POST /api/v1/recurring-incomes/run`
- `GET/PUT /api/v1/smoking/profile`, `GET /api/v1/smoking/summary`, `GET /api/v1/smoking/reports`, `POST /api/v1/smoking/relapse`
- `GET/POST/PUT/DELETE /api/v1/goals` & `POST /api/v1/goals/{goal}/allocate`
- `GET /api/v1/push/vapid-public-key`, `POST/DELETE /api/v1/push/subscriptions`, `GET /api/v1/notifications`, `POST /api/v1/notifications/test`
- `GET/PUT /api/v1/settings`

## 6. Background Processing & Scheduling

- **Laravel Scheduler**:
  - `regain:process-recurring-incomes` — Runs hourly (idempotent by `YYYY-MM` execution period in `Asia/Jakarta` timezone).
  - `regain:schedule-motivational-pushes` — Runs every 15 minutes; evaluates user active hours, daily max cap (default 3), cooldown window, and random probability before dispatching `SendMotivationalPushJob`.
  - `regain:snapshot-smoking-savings` — Runs daily at 00:05 `Asia/Jakarta` to record daily smoking savings snapshots.
- **Laravel Queue (Redis)**:
  - Processes Web Push delivery asynchronously via `minishlink/web-push`.

## 7. PWA & Offline Strategy

- **Installable PWA**: Web App Manifest (`display: standalone`, Indonesian labels, dark theme color `#09090b`).
- **Custom Service Worker** (`injectManifest` via `@vite-pwa/nuxt` + Workbox):
  - Precaches app shell and static assets only.
  - Uses `NetworkOnly` for `/api/*` endpoints so sensitive financial data is never cached in HTTP/SW caches.
  - Handles `push` and `notificationclick` events for background motivational notifications.
- **Offline Queue (Dexie.js)**:
  - Stores pending transactions with a client-generated UUID and `status: 'pending' | 'syncing' | 'failed'`.
  - Explicitly labels unsynced items as **"Belum disinkronkan"** until confirmed by the server.
  - Automatically syncs when connectivity returns.
