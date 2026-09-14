# Corevisys POS (LaravelPOS) — Project README

**Last verified:** 2026-09-13
**Method:** every statement was read directly from source; citations use `path:line`. Uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`. Prior audit history has been superseded and is no longer preserved in this document.
**Related docs:** [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (deep current-state map), [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md) (multi-store readiness), [`THEME_REFERENCE.md`](docs/THEME_REFERENCE.md) (design system).

---

## 1. What this is

A server-rendered **Laravel 12 POS / retail ERP** for a single business that may operate **multiple independent stores (branches/tenants)** in one database. It is a monolith: Blade views + Alpine.js on the front end, Eloquent + MySQL/SQLite on the back end, no separate API/frontend app.

- Framework/runtime: Laravel `^12.0` on PHP `^8.2` ([`composer.json`](composer.json:12), [`composer.json`](composer.json:14)).
- Front end: Blade + **Tailwind CSS 4.1.18** + **Alpine.js 3** via **Vite 7** ([`package.json`](package.json:13), [`package.json`](package.json:19), [`package.json`](package.json:20)).
- PDF: `barryvdh/laravel-dompdf`; barcodes: `picqer/php-barcode-generator`; backups: `spatie/laravel-backup` ([`composer.json`](composer.json:13), [`composer.json`](composer.json:16), [`composer.json`](composer.json:17)).
- Charts: Chart.js vendored locally at [`public/vendor/chart.umd.min.js`](public/vendor/chart.umd.min.js:1).
- Tests: **Pest** on PHPUnit ([`composer.json`](composer.json:27), [`phpunit.xml`](phpunit.xml:11)).

## 2. Requirements

- PHP `^8.2` with the usual Laravel extensions; `node`/`npm` for assets.
- A database. Default connection is `sqlite` unless `DB_CONNECTION` overrides; MySQL, MariaDB, PostgreSQL and SQLSrv connections are all defined ([`config/database.php`](config/database.php:19)). **Note:** the MySQL connection hardcodes the XAMPP dump path `C:/xampp/mysql/bin/` ([`config/database.php`](config/database.php:65)) — non-portable (ISSUE-6).
- `composer` and `npm` install.

## 3. Install & run

The project ships a composer `setup` script that installs dependencies, copies `.env`, generates the key, migrates and builds assets ([`composer.json`](composer.json:46)):

```
composer run setup
```

Day-to-day development uses concurrently to run the web server, queue listener and Vite together ([`composer.json`](composer.json:54)):

```
composer run dev
```

Database seeding (`fresh` installs) runs a fixed seeder chain — stores, admins, roles/permissions, SMS templates/rules, master data ([`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php:27)).

Seeded logins (password `password`): `admin_dhaka`, `admin_ctg`, `admin_sylhet` — one per store ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:27)). Hash rounds are lowered only under test ([`phpunit.xml`](phpunit.xml:23)).

Scheduled work is defined in [`routes/console.php`](routes/console.php:12) (EMI reminders, SMS health check, scheduled SMS campaigns, scheduled auto-rules, DB backup/clean). Run the scheduler with `php artisan schedule:work` (or cron) and a queue worker for the SMS jobs.

## 4. Testing

```
php artisan test
```

- Suites: `Unit` + `Feature` ([`phpunit.xml`](phpunit.xml:7)); tests run on in-memory SQLite with `QUEUE_CONNECTION=sync` ([`phpunit.xml`](phpunit.xml:26), [`phpunit.xml`](phpunit.xml:29)).
- **Full run (2026-09-13):** `15 failed, 1285 passed (6283 assertions)`, 158.73s.
- The 15 failures are **all** concurrency/race tests (parallel PHP workers vs. file-backed SQLite) plus 3 deterministic `node --check` failures on inline Blade JS. None are ordinary functional failures. Full list & rationale: [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.6. `[INFERENCE]` the race failures are a harness/SQLite-locking artefact; re-run against MySQL to confirm before treating them as product defects.

## 5. Repository map

- `app/Http/Controllers/` — ~65 controllers; `Concerns/{ImportsItems, ExportsReportData, ValidatesReportFilters}` for shared behaviour.
- `app/Models/` — ~67 Eloquent models; `Models/Traits/StoreScoped.php` implements tenant scoping.
- `app/Services/` — `CodeGeneratorService` (per-store document numbering), `ItemCreationService`, `ItemSerialValidationService`, `NavigationShortcutService`.
- `app/SMS/` — provider-abstraction SMS subsystem (`Providers/*`, `Services/*`, `DTOs`, `Helpers`).
- `app/Jobs/` — `DispatchCampaignJob`, `EmiReminderJob`, `SendSingleSmsJob`, `SmsHealthCheckJob`.
- `app/Http/Middleware/` — `EnsureUserHasStore`, `EnsureUserHasPermission`, `EnsureReportExport`.
- `routes/web.php` — all web routes; `routes/auth.php` — Breeze auth; `routes/console.php` — scheduler.
- `resources/views/` — Blade; `module/**` holds every feature screen; `layouts/**`, `components/**` shared chrome.
- `database/migrations/` — schema history; `database/seeders/` — demo/seed data.
- `scripts/` — one-off maintenance/verification PHP & PowerShell helpers (e.g. [`scripts/compute_migration_order.ps1`](scripts/compute_migration_order.ps1:1)).
- `tests/Feature/`, `tests/Unit/` — Pest tests.

## 6. Core concepts you must understand before changing code

### 6.1 Store (tenant) scoping — the foundational rule
Every operational table carries a `store_id`, and models use the `App\Models\Traits\StoreScoped` global scope, which filters reads strictly to the **acting store** whenever a user is authenticated — there is no `store_id IS NULL` escape hatch ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:9), [`…:23`](app/Models/Traits/StoreScoped.php:9)).

- The acting store is resolved by `current_store_id()` = the authenticated user's `store_id`, else `default_store_id()` ([`helpers.php`](app/Helpers/helpers.php:152)).
- `store_settings()` is a memoised per-store cache; use it instead of reading `db_store` directly ([`helpers.php`](app/Helpers/helpers.php:77)).
- `allStores()` / `withoutGlobalScope('store_id')` are the **sanctioned** bypasses for cross-store reads (super-admin dashboard, per-store code generation). Keep them centralised ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:28), [`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)).
- **There is currently no store-switcher** — a user is bound to exactly one store (see §8 and the gap analysis).

### 6.2 Authorization
Two layers, both server-side:
1. `permission:<slug>` route middleware ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), alias in [`bootstrap/app.php`](bootstrap/app.php:16)) — used for the whole `reports/*` group and select purchase routes ([`routes/web.php`](routes/web.php:351), [`routes/web.php`](routes/web.php:179)).
2. Inline `if (auth()->check() && !auth()->user()->hasPermission('slug')) abort(403)` in controllers, plus `Gate::authorize` policies for Users/Roles ([`UserPolicy.php`](app/Policies/UserPolicy.php:46), [`RolePolicy.php`](app/Policies/RolePolicy.php:43)).

Super-admin is the explicit **`db_roles.is_super_admin`** flag ([`User.php`](app/Models/User.php:121)), not a role name or id. **Caveat:** the flag was backfilled true for all three per-store "Super Admin" roles ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36)) — see ISSUE-8. The agreed target model is three roles: **Branch Admin** (own branch only — the flag is to be cleared), **Owner** (all stores + consolidated reports, a normal named role, not a bypass-everything super-admin) and **Developer** (separate system/maintenance account, never used as the Owner login). Details in [`MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md).

### 6.3 Document numbering
`CodeGeneratorService::generate(type, offset, storeId)` produces store-scoped, sequential codes with `lockForUpdate()` and a bounded retry, inside a transaction ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:33), [`…:207`](app/Services/CodeGeneratorService.php:207)). Prefixes come from `db_store.*_init`. Exception: `purchase_return` still uses `uniqid()` (ISSUE-10).

### 6.4 Stock
Stock is materialised as `db_warehouseitems (warehouse_id, item_id, available_qty)`, unique per `(warehouse_id, item_id)`. `db_items.stock` is a legacy denormalised aggregate that is still dual-written in several flows (ISSUE-1) — treat the warehouse row as authoritative.

### 6.5 SMS
Provider-abstracted. The provider is chosen from `db_store.sms_status` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)); blacklist and duplicate-suppression are enforced **per store** ([`SmsService.php`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:71)). A `DbSale` observer triggers the pipeline ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:43)).

### 6.6 Reports & export
The whole `reports/*` group is gated by `permission:reports_view` and decorated by `report.export`, which turns any `/data` JSON into CSV/Excel/PDF/print when `?export=` is present — reusing the already store-scoped rows, so exports cannot leak another store's data ([`routes/web.php`](routes/web.php:351), [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:31), [`ExportsReportData.php`](app/Http/Controllers/Concerns/ExportsReportData.php:37)).

### 6.7 Activity log
`activity_logs` records login, logout and store-settings changes ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:31), [`ActivityLog.php`](app/Models/ActivityLog.php:17)). It deliberately does **not** use `StoreScoped`; callers opt in via `forStore($id)` ([`ActivityLog.php`](app/Models/ActivityLog.php:49)).

## 7. Feature modules (present in code)

Sales/POS (incl. hold/resume, EMI, serial tracking), Sales returns, Quotations (with convert-to-sale), Purchases (incl. returns, payments, barcode), Items/Services/Categories/Brands/Variants (incl. serial history, labels/PDF, import), Warehouses, Stock adjustments & transfers, Accounts/ledger (accounts, money transfer, deposit, cash transactions, **cash-drawer reconciliation**), Expenses & categories, Contacts (customers/suppliers, guarantees/guardians, attachments, advance), Coupons (master + customer), Settings (store, languages, countries/states, tax, units, payment types, currency, SMTP), **Database backup**, Reporting (~25 reports incl. **Cash Flow** and **Cash Reconciliation**), SMS suite (send, templates, campaigns, logs, history, auto-rules, **blacklist**, settings), Global search, Docs, Legal pages, Sitemap, Super-admin **Multi-Store Dashboard**.

## 8. Known limitations (see gap analysis for detail)

- **No store switcher / acting-store context** — the single biggest multi-store blocker ([`helpers.php`](app/Helpers/helpers.php:152)).
- Three per-store admins are globally privileged via the `is_super_admin` backfill (ISSUE-8) — to be replaced by the Branch Admin / Owner / Developer role model.
- Dual stock source of truth persists (ISSUE-1).
- Invalid inline JS on items-list / transfer-create / adjustment-create views (ISSUE-2).
- Orphan view `module/items/import_services.blade.php` (ISSUE-3); `to_store_id` columns are **confirmed dead code** and safe to remove (ISSUE-4).
- 12 concurrency tests fail in this environment (harness suspected); 3 deterministic JS failures.
- Windows-hardcoded MySQL dump path (ISSUE-6); public layout omits `app.js` (ISSUE-7); app layout loads no Font Awesome though `app.js` builds FA spinner markup (ISSUE-9).

## 9. Where to look next

- Full current-state map & every known issue: [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md).
- What it would take to safely run multiple stores: [`docs/MULTISTORE_GAP_ANALYSIS.md`](docs/MULTISTORE_GAP_ANALYSIS.md).
- Colours, typography, spacing, components: [`docs/THEME_REFERENCE.md`](docs/THEME_REFERENCE.md).
