# Corevisys POS (LaravelPOS) — Project README

**Last verified:** 2026-09-16
**Method:** every statement was read directly from source this pass; citations use `path:line`. Uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`.
**Related docs:** [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (deep current-state map), [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md) (multi-store readiness), [`THEME_REFERENCE.md`](docs/THEME_REFERENCE.md) (design system), [`MULTISTORE_FOLLOWUPS.md`](docs/MULTISTORE_FOLLOWUPS.md) (deferred work).

> Note: the project README lives here under `docs/`. There is no root-level `README.md` in this repository.

---

## 1. What this is

A server-rendered **Laravel 12 POS / retail ERP** for a business operating **multiple independent stores (branches/tenants)** in one database. It is a monolith: Blade + Alpine.js on the front end, Eloquent on MySQL/SQLite, no separate API/frontend app.

- Framework/runtime: Laravel `^12.0` on PHP `^8.2` ([`composer.json`](composer.json:12), [`composer.json`](composer.json:14)).
- Front end: Blade + **Tailwind CSS 4.1.18** + **Alpine.js 3** via **Vite 7** ([`package.json`](package.json:13), [`package.json`](package.json:19), [`package.json`](package.json:20)).
- PDF: `barryvdh/laravel-dompdf`; barcodes: `picqer/php-barcode-generator`; backups: `spatie/laravel-backup` ([`composer.json`](composer.json:13), [`composer.json`](composer.json:16), [`composer.json`](composer.json:17)).
- Charts: Chart.js vendored locally at `public/vendor/chart.umd.min.js` ([`app.blade.php`](resources/views/layouts/app.blade.php:19)).
- Tests: **Pest** on PHPUnit ([`composer.json`](composer.json:27), [`phpunit.xml`](phpunit.xml:11)).
- Scale: 59 controllers, 66 models, 143 migrations, 225 Blade views (180 under `module/`), 159 test files.

## 2. Requirements

- PHP `^8.2` with the usual Laravel extensions; `node`/`npm` for assets.
- A database. Default connection is `sqlite` unless `DB_CONNECTION` overrides; MySQL/MariaDB/PostgreSQL/SQLSrv connections are all defined ([`config/database.php`](config/database.php:19)). **Note:** the MySQL connection hardcodes the XAMPP dump path `C:/xampp/mysql/bin/` ([`config/database.php`](config/database.php:65)) — non-portable (ISSUE-6); override with `DUMP_BINARY_PATH` ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:28)).
- `composer` and `npm` install.

## 3. Install & run

The project ships a composer `setup` script that installs dependencies, copies `.env`, generates the key, migrates and builds assets ([`composer.json`](composer.json:46)):

```
composer run setup
```

Day-to-day development runs the web server, queue listener and Vite together via `concurrently` ([`composer.json`](composer.json:54)):

```
composer run dev
```

Database seeding (`fresh` installs) runs a fixed seeder chain — stores, admins, roles/permissions, **Owner/Developer accounts**, SMS templates/rules, master data ([`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php:27)).

**Seeded logins (all password `password`):**

| Login | Role | Source |
|---|---|---|
| `admin_dhaka` / `admin_ctg` / `admin_sylhet` | Branch Admin (one per store, `is_super_admin = false`) | [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:18), [`…:69`](database/seeders/AdminUserSeeder.php:69) |
| `owner@corevisys.com` | Owner (cross-store visibility, **not** a super admin) | [`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:39) |
| `developer@corevisys.com` | Developer (the only global super-admin account) | [`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:57) |

Password hash rounds are lowered only under test ([`phpunit.xml`](phpunit.xml:23)).

Scheduled work is defined in [`routes/console.php`](routes/console.php:12) (EMI reminders daily 09:00, SMS health check every 15 min, scheduled SMS campaigns every minute, scheduled auto-rules daily 09:00, DB backup/clean at 00:00/01:00). Run the scheduler with `php artisan schedule:work` (or cron) and a queue worker for the SMS jobs.

Optional app-level kill-switch: `ENFORCE_TOTAL_VALIDATION` (default `true`) gates server-side POS grand-total recompute rejection ([`config/sales.php`](config/sales.php:25), [`.env.example`](.env.example:20)).

## 4. Testing

```
php artisan test --parallel
```

- Suites: `Unit` + `Feature` ([`phpunit.xml`](phpunit.xml:7)); tests run on in-memory SQLite with `QUEUE_CONNECTION=sync` ([`phpunit.xml`](phpunit.xml:26), [`phpunit.xml`](phpunit.xml:29)).
- **Full run (2026-09-16):** `1346 passed (6479 assertions)`, 12 parallel processes, 149.92s. **Zero failures.**
- **MySQL gate (2026-09-16):** `16 skipped, 1330 passed (6392 assertions)`, 336.42s. Configured in [`phpunit.mysql.xml`](phpunit.mysql.xml:26) against a throwaway `laravelpos_test` database. The 16 self-contained SQLite concurrency/migration harnesses are **skipped** on MySQL by design via `skipUnlessSqlite()` ([`tests/Pest.php`](tests/Pest.php:91)) because they build their own SQLite files and spawn `php` workers.
- `tests/Unit` contains only `ExampleTest.php`; all real coverage is in `tests/Feature`.

## 5. Repository map

- `app/Http/Controllers/` — 59 controllers; `Concerns/{ImportsItems, ExportsReportData, ValidatesReportFilters}` for shared behaviour.
- `app/Models/` — 66 Eloquent models; `Models/Traits/StoreScoped.php` implements tenant scoping (44 models use it).
- `app/Services/` — `CodeGeneratorService` (per-store document numbering), `StoreContext` (acting store), `CustomerIdentityResolver` (shared customer identity), `ItemCreationService`, `ItemSerialValidationService`, `NavigationShortcutService`.
- `app/SMS/` — provider-abstraction SMS subsystem (`Providers/*`, `Services/*`, `DTOs`, `Helpers`).
- `app/Jobs/` — `DispatchCampaignJob`, `EmiReminderJob`, `SendSingleSmsJob`, `SmsHealthCheckJob`.
- `app/Console/Commands/` — `items:compare-stock-sources`, `sms:process-scheduled-rules`, `MigrateCustomerEmiData`.
- `app/Http/Middleware/` — `SetCurrentStore` (acting store), `EnsureUserHasStore`, `EnsureUserHasPermission`, `EnsureReportExport`.
- `routes/web.php` — all web routes; `routes/auth.php` — Breeze auth; `routes/console.php` — scheduler.
- `resources/views/` — Blade; `module/**` (180 files) holds every feature screen; `layouts/**`, `components/**` shared chrome.
- `database/migrations/` — 143 migrations; `database/seeders/` — demo/seed data.
- `scripts/` — one-off maintenance/verification PHP & PowerShell helpers (e.g. [`scripts/compute_migration_order.ps1`](scripts/compute_migration_order.ps1:1)).
- `tests/Feature/`, `tests/Unit/` — Pest tests (159 files).

## 6. Core concepts you must understand before changing code

### 6.1 Store (tenant) scoping — the foundational rule

Every operational table carries a `store_id`, and models use the `App\Models\Traits\StoreScoped` global scope, which filters reads strictly to the **acting store** whenever a user is authenticated — there is **no `store_id IS NULL` escape hatch** ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:14), [`…:23`](app/Models/Traits/StoreScoped.php:23)).

- The acting store is resolved by `current_store_id()`, which reads the `StoreContext` binding **first**, then the authenticated user's `store_id`, then `default_store_id()` ([`helpers.php`](app/Helpers/helpers.php:157)).
- The binding is set per request by `SetCurrentStore`, which is appended to the whole `web` group ([`bootstrap/app.php`](bootstrap/app.php:25)) and resolves session → user default, ignoring any session value the user is not allowed to act as ([`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:39), [`…:63`](app/Http/Middleware/SetCurrentStore.php:63)).
- `store_settings()` is a memoised per-store cache; use it instead of reading `db_store` directly ([`helpers.php`](app/Helpers/helpers.php:77)).
- `allStores()` / `withoutGlobalScope('store_id')` are the **sanctioned** bypasses for cross-store reads (consolidated reporting, the multi-store dashboard, per-store code generation, `CustomerIdentityResolver`). Keep them centralised ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:28), [`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:32), [`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)).
- **Branch admins are binary-bound to their own store**; only Owner/Developer are cross-store. A store-selector UI for the Owner is **not yet exposed** — see ISSUE-14 and [`MULTISTORE_FOLLOWUPS.md`](docs/MULTISTORE_FOLLOWUPS.md).

### 6.2 Authorization

Four complementary server-side layers:
1. `permission:<slug>` route middleware ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), alias in [`bootstrap/app.php`](bootstrap/app.php:16)) — the whole `reports/*` group ([`routes/web.php`](routes/web.php:364)) and purchase store/update ([`routes/web.php`](routes/web.php:192), [`…:197`](routes/web.php:197)).
2. Inline `if (auth()->check() && !auth()->user()->hasPermission('slug')) abort(403)` in controllers.
3. `Gate::authorize` policies for Users/Roles ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:47), `app/Policies/`).
4. FormRequest `authorize()` for validation-backed writes.

**Three-role identity model:** `User::isSuperAdmin()` reads only `db_roles.is_super_admin` ([`User.php`](app/Models/User.php:122)); `isOwner()` reads only `db_roles.is_owner` ([`User.php`](app/Models/User.php:134)); `canViewAllStores()` = either ([`User.php`](app/Models/User.php:144)); `hasPermission()` short-circuits **only** for a super admin ([`User.php`](app/Models/User.php:149)). Seeded roles: branch-admin `Super Admin` (ids 1-3, flag false), `Owner` (id 19, `is_owner = true`), `Developer` (id 20, the sole `is_super_admin = true`) ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:169), [`…:179`](database/seeders/RolePermissionSeeder.php:179)). Details in [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md) §3.

### 6.3 Document numbering

`CodeGeneratorService::generate(type, offset, storeId)` produces store-scoped, sequential codes with `lockForUpdate()` and a bounded retry, inside a transaction ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:33), [`…:42`](app/Services/CodeGeneratorService.php:42), [`…:206`](app/Services/CodeGeneratorService.php:206)). Prefixes come from `db_store.*_init`. **All entity types are sequential now, including `purchase_return`** (prefix `PR`) — the old `uniqid()` behaviour is gone ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167)).

### 6.4 Stock

Stock is materialised as `db_warehouseitems (warehouse_id, item_id, available_qty)`. `DbItem::availableStock(?int $warehouseId)` is the **single canonical read rule** (requested warehouse row → summed warehouse rows → `db_items.stock` fallback for warehouse-less items) ([`DbItem.php`](app/Models/DbItem.php:114)). `DbItem::syncGlobalStock()` is the single aggregation that keeps `db_items.stock == SUM(available_qty)` for warehouse-backed items and never zeroes warehouse-less ones ([`DbItem.php`](app/Models/DbItem.php:148)). **`db_items.stock` is intentionally retained**, not deprecated. Every read site (POS checkout, POS/adjustment/transfer search, items list/export, SMS low-stock) routes through the canonical rule.

### 6.5 SMS

Provider-abstracted. The provider is chosen from `db_store.sms_status` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)); **blacklist and duplicate-suppression are enforced per store** ([`SmsService.php`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:84)), and logs carry `store_id` ([`SmsService.php`](app/SMS/Services/SmsService.php:91)). A `DbSale` observer triggers the pipeline ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:50)). **Note:** `config('sms.sandbox')` and `config('sms.throttle.*')` are read but no `config/sms.php` exists, so those knobs are currently unreachable (ISSUE-12).

### 6.6 Reports & export

The whole `reports/*` group is gated by `permission:reports_view` and decorated by `report.export`, which turns any `/data` JSON into CSV/Excel/PDF/print when `?export=` is present — reusing the already store-scoped rows, so exports cannot leak another store's data ([`routes/web.php`](routes/web.php:364), [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:31), [`ExportsReportData.php`](app/Http/Controllers/Concerns/ExportsReportData.php:37)). All 24 report pages render ([`ReportPhase7RedesignTest.php`](tests/Feature/ReportPhase7RedesignTest.php:1)).

### 6.7 Activity log

`activity_logs` records login, logout and store-settings changes. It deliberately does **not** use `StoreScoped` (an implicit scope on an audit table can make genuine rows appear to vanish); callers opt in via `scopeForStore($id)`, and a Super Admin may omit it to read across stores ([`ActivityLog.php`](app/Models/ActivityLog.php:10), [`…:49`](app/Models/ActivityLog.php:49)). Rows are immutable — there is no `updated_at` ([`ActivityLog.php`](app/Models/ActivityLog.php:22)).

### 6.8 Shared customer identity (multi-store)

A person's **identity** is recognised across branches via `db_customer_identities` (globally unique `phone`), while each store keeps its own store-scoped `db_customers` row (dues/loyalty/history stay per store). Resolution is centralised in `App\Services\CustomerIdentityResolver` with phone normalisation ([`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29), [`…:78`](app/Services/CustomerIdentityResolver.php:78)).

## 7. Feature modules (present in code)

Sales/POS (incl. hold/resume, EMI, serial tracking & history, labels/PDF), Sales returns, Quotations (with convert-to-sale), Purchases (incl. returns, payments, barcode), Items/Services/Categories/Brands/Variants (incl. import, print labels), Warehouses, Stock adjustments & transfers, Accounts/ledger (accounts, money transfer, deposit, cash transactions, **two-step cash-drawer reconciliation**), Expenses & categories, Contacts (customers/suppliers, guarantees/guardians, attachments, advance, **shared identity**), Coupons (master + customer), Settings (store, languages, countries/states, tax, units, payment types, currency, SMTP), **Database backup**, Reporting (~24 reports incl. **Cash Flow** and **Cash Reconciliation**), SMS suite (send, templates, campaigns, logs, history, auto-rules, blacklist, settings), Global search, Docs, Legal pages, Sitemap, **Multi-Store Dashboard**, **Consolidated (Owner) reporting**, **Owner store-context switching (backend only)**.

## 8. Known limitations

- **Owner store-selector UI is missing** (ISSUE-14): controller + routes exist but nothing posts to them, so the Owner cannot switch the acting store from the UI.
- **Store settings ignore the acting store** (ISSUE-15): `resolveActingStore()` reads `auth()->user()->store_id` directly, so an Owner cannot configure a chosen branch.
- **`config/sms.php` does not exist** (ISSUE-12) though `config('sms.*')` is read — sandbox/throttle tuning is unreachable.
- **Duplicate migration timestamp** `2026_09_13_000003` on two migrations (ISSUE-13).
- **App layout loads no Font Awesome** though `app.js` builds an FA spinner glyph (ISSUE-9).
- **Public layout omits `app.js`** (ISSUE-7); **Windows-hardcoded MySQL dump path** (ISSUE-6).
- **EMI tables carry no `store_id`** (ISSUE-16); isolation is transitive via `db_sales`.
- **`db_customers.mobile` per-store uniqueness is validation-only**, not a DB index.

Full register with evidence: [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.6.

## 9. Where to look next

- Full current-state map & every known issue: [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md).
- What it would take to safely run multiple stores (now largely shipped): [`docs/MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md).
- Deferred/not-yet-done work: [`docs/MULTISTORE_FOLLOWUPS.md`](docs/MULTISTORE_FOLLOWUPS.md).
- Colours, typography, spacing, components: [`docs/THEME_REFERENCE.md`](docs/THEME_REFERENCE.md).
