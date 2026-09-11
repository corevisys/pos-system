# PROJECT KNOWLEDGE BASE — Corevisys POS (LaravelPOS)

**Document date:** 2026-09-11
**Audited revision:** working tree as present in this repository (no prior documentation assumed).
**Method:** every statement below was read directly from source. File citations use `path:line`.
Anything not fully verified is explicitly marked `[UNVERIFIED]`, and inferences are marked `[INFERENCE]`.

> Scope note: this is a **cold-start** document. It assumes the reader has never seen this codebase.

---

## A.1 Tech Stack & Dependencies

### Backend

| Item | Value | Evidence |
|---|---|---|
| Framework | Laravel `^12.0` | [`composer.json`](composer.json:14) |
| PHP runtime | `^8.2` | [`composer.json`](composer.json:12) |
| PDF generation | `barryvdh/laravel-dompdf` `^3.1` | [`composer.json`](composer.json:13) |
| Barcode generation | `picqer/php-barcode-generator` `^3.2` | [`composer.json`](composer.json:16) |
| Backups | `spatie/laravel-backup` `^9.3` | [`composer.json`](composer.json:17) |
| REPL | `laravel/tinker` `^2.10.1` | [`composer.json`](composer.json:15) |
| Dev/test | `pestphp/pest` `^3.8` + `pest-plugin-laravel` `^3.2`, `laravel/breeze` `^2.3`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `mockery`, `nunomaduro/collision` | [`composer.json`](composer.json:19) |
| Autoloaded global helper | `app/Helpers/helpers.php` via `autoload.files` | [`composer.json`](composer.json:36) |

### Frontend

| Item | Value | Evidence |
|---|---|---|
| Build tool | Vite `^7.0.7` + `laravel-vite-plugin` `^2.0.0` | [`package.json`](package.json:17) |
| CSS framework | Tailwind CSS `4.1.18` (v4 CSS-first config) | [`package.json`](package.json:19) |
| Tailwind plugins | `@tailwindcss/vite`, `@tailwindcss/forms`, `@tailwindcss/typography` | [`package.json`](package.json:10) |
| JS interactivity | Alpine.js `^3.4.2` + `@alpinejs/collapse` `^3.17.0` | [`package.json`](package.json:13) |
| HTTP client | axios `^1.11.0` | [`package.json`](package.json:15) |
| Charts | **Chart.js via public CDN** | [`resources/views/layouts/app.blade.php`](resources/views/layouts/app.blade.php:19) |
| Fonts | Google Fonts (`Plus Jakarta Sans`; guest/public layouts also load `Hind Siliguri`, `Inter`) | [`layouts/app.blade.php`](resources/views/layouts/app.blade.php:21), [`layouts/guest.blade.php`](resources/views/layouts/guest.blade.php:14), [`layouts/public.blade.php`](resources/views/layouts/public.blade.php:13) |

**Asset-loading inconsistency (surfaced as a conflict):**
- `layouts/app.blade.php` loads BOTH `@vite(['resources/css/app.css','resources/js/app.js'])` **and** Chart.js from `cdn.jsdelivr.net` ([`app.blade.php`](resources/views/layouts/app.blade.php:17)).
- `layouts/guest.blade.php` loads `@vite(['resources/css/app.css','resources/js/app.js'])` ([`guest.blade.php`](resources/views/layouts/guest.blade.php:20)).
- `layouts/public.blade.php` loads only `@vite('resources/css/app.css')` — **no `app.js`** ([`public.blade.php`](resources/views/layouts/public.blade.php:19)). Any Alpine directives on public pages therefore depend on a per-page script include; this is a potential silent bug source.
- Font Awesome is referenced by JS-generated markup ([`resources/js/app.js`](resources/js/app.js:50)) but its `<link>` was not located in the three layouts; `[UNVERIFIED]` whether it is loaded globally.

### Database

- Default connection is `sqlite` unless `DB_CONNECTION` overrides; MySQL/MariaDB/PgSQL/SQLSrv connections are all defined ([`config/database.php`](config/database.php:19)).
- MySQL connection hardcodes `dump.dump_binary_path => 'C:/xampp/mysql/bin/'` ([`config/database.php`](config/database.php:65)).
- Test suite forces `sqlite` `:memory:` ([`phpunit.xml`](phpunit.xml:26)).

### App-specific config

- `config/sales.php` defines a single kill-switch `sales.enforce_total_validation` (env `ENFORCE_TOTAL_VALIDATION`, default `true`) that gates server-side grand-total recompute rejection ([`config/sales.php`](config/sales.php:25)).

---

## A.2 Design System

### Design tokens (`resources/css/app.css`)

Tokens are declared in the Tailwind v4 `@theme` block ([`resources/css/app.css`](resources/css/app.css:7)):

| Token group | Tokens |
|---|---|
| Brand primary | `--color-primary` `#4f46e5`, `--color-primary-hover`, `--color-primary-light`, full `--color-primary-50…900` ramp, `--primary-rgb` |
| Surfaces | `--color-navy`, `--color-card`, `--color-background`, `--color-border`, `--color-border-light` |
| Text | `--color-text-primary`, `--color-text-secondary`, `--color-text-muted` |
| Status | `--color-danger[-hover|-light]`, `--color-warning[-light]`, `--color-success[-light]` |
| Shadows | `--shadow-card`, `--shadow-card-hover`, `--shadow-dropdown`, `--shadow-modal` |
| Radius | `--radius-card` `0.75rem`, `--radius-input`, `--radius-button` |
| Dark mode | `--color-dark-bg`, `--color-dark-card`, `--color-dark-border`, `--color-dark-text` |
| Font | `--font-sans` = Plus Jakarta Sans → Hind Siliguri → Figtree → sans-serif |

Dark mode uses a class variant `@custom-variant dark (&:where(.dark, .dark *))` ([`app.css`](resources/css/app.css:5)), toggled from localStorage in the layout ([`app.blade.php`](resources/views/layouts/app.blade.php:3)).

Utilities: `scrollbar-hide` is a project `@utility` ([`app.css`](resources/css/app.css:74)); `[x-cloak]{display:none!important}` base rule ([`app.css`](resources/css/app.css:68)).

### Reusable Blade components (inventory)

All under `resources/views/components/`:

| Component | Purpose |
|---|---|
| `application-logo`, `seo-meta`, `public-layout` | branding / SEO |
| `badge`, `card`, `stat-card`, `table` | display primitives |
| `primary-button`, `secondary-button`, `danger-button`, `icon-button` | buttons |
| `text-input`, `textarea`, `select`, `input-label`, `input-error`, `searchable-select` | form controls |
| `modal`, `dropdown`, `dropdown-link`, `nav-link`, `responsive-nav-link` | navigation/overlays |
| `notification-toast`, `sidebar-tooltip`, `keyboard-shortcuts`, `keyboard-shortcuts-modal` | UX helpers |
| `auth-session-status` | auth feedback |

Prop lists are read from each component's `@props`; `[UNVERIFIED]` per-component prop tables were not exhaustively extracted in this pass.

### Legacy / out-of-system markup

- A large inline Tailwind utility vocabulary (`text-[10px]`, `tracking-widest`, `!important`-prefixed classes) is used directly across module views rather than via components (e.g. [`module/users/roles_list.blade.php`](resources/views/module/users/roles_list.blade.php:139)). This is legacy-by-convention, not a formal component system.
- `resources/views/module/items/import_services.blade.php` exists as a view but the Import Services route was removed (see PermissionSeeder comment "[`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:44)`") → orphan view.

---

## A.3 Data & Financial Architecture

### A.3.1 Store-scoping mechanism (foundational)

Two mechanisms exist:

1. **Eloquent global scope trait** `App\Models\Traits\StoreScoped` — adds a global scope on `store_id` that filters to `current_store_id()` **OR `store_id IS NULL`**, *only when a user is authenticated* ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:7)). It exposes `scopeAllStores()` = `withoutGlobalScope('store_id')`.
2. **Helper functions** in `app/Helpers/helpers.php`:
   - `current_store_id()` → authenticated user's `store_id`, else `default_store_id()` ([`helpers.php`](app/Helpers/helpers.php:152)).
   - `default_store_id()` → first `db_store` row, else `1` ([`helpers.php`](app/Helpers/helpers.php:24)).
   - `store_settings()` → memoized `DbStore` row keyed per store id (fixes a prior cross-store cache leak) ([`helpers.php`](app/Helpers/helpers.php:77)).
   - `store_scoped_cached_list()` → per-store cache key `prefix_s{storeId}` ([`helpers.php`](app/Helpers/helpers.php:177)).

**Conflict #1 — nullable leak in the global scope.** Because `StoreScoped` also matches `store_id IS NULL`, any row with a NULL store_id is visible to *every* store. The migration `2026_09_11_000001` enforces `NOT NULL` on 57 tables to close this ([`add_not_null_store_id_to_store_scoped_tables.php`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21)), but that migration **aborts if any NULL exists** and does not backfill ([`…:95`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:95)).

**Conflict #2 — "Super Admin" is a per-store role, not a global one.** `User::isSuperAdmin()` returns true when `role_id === 1` **or** `role_name === 'Super Admin'` ([`User.php`](app/Models/User.php:101)). The `AdminUserSeeder` creates **three** "Super Admin" roles, one per store, with role ids 1, 2, 3 ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:18)). Therefore the Store-2 and Store-3 admins are `role_id = 2` / `3` but still pass `isSuperAdmin()` via the `role_name` check. Consequences: (a) they bypass `EnsureUserHasStore` ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:30)); (b) they bypass every `hasPermission()` gate because `hasPermission()` short-circuits for super admins ([`User.php`](app/Models/User.php:108)). This directly enables the multi-store header-bypass analysed in Deliverable B.

### A.3.2 Table inventory by domain

All tables prefixed `db_`, `ac_`, `sms_` or plain. Column sets below are from the `create_*` migrations.

**Store & identity**
| Table | Key columns | store_id? | StoreScoped? |
|---|---|---|---|
| `db_store` | `store_code`, `store_name`, `gst_no`, `vat_no`, `pan_no`, all `*_init` prefixes, `currency_id`, `language_id`, `timezone`, `smtp_*`, `decimals`, `qty_decimals` | is the store | n/a ([`DbStore.php`](app/Models/DbStore.php:8)) |
| `users` | `store_id`, `role_id`, `role_name`, `username`, `email` (global unique) | yes, nullable→NOT NULL by 2026_09_11 | no trait ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)) |
| `db_roles` | `store_id`, `role_name`, `status` | yes | no trait |
| `db_permissions` | `store_id`, `role_id`, `permissions` (JSON array) | yes | no trait ([`DbPermission.php`](app/Models/DbPermission.php:17)) |

**Items / stock**
| Table | store_id? | Traits |
|---|---|---|
| `db_items` (`item_code` globally unique — `uq_db_items_item_code`) | yes | StoreScoped ([`DbItem.php`](app/Models/DbItem.php:10)) |
| `db_item_serials` (unique `(item_id, serial_number)` `uq_db_item_serials_item_serial`) | yes | StoreScoped ([`DbItemSerial.php`](app/Models/DbItemSerial.php:10)) |
| `db_category`, `db_brands`, `db_variants` | yes | StoreScoped; per-store uniques `(store_id, name)`/`(store_id, code)` ([`…unique_per_store migration`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44)) |
| `db_warehouse` (unique `(store_id, warehouse_name)`) | yes | StoreScoped ([`DbWarehouse.php`](app/Models/DbWarehouse.php:11)) |
| `db_warehouseitems` (unique `(warehouse_id, item_id)` `uq_warehouse_item`) | yes | StoreScoped ([`DbWarehouseItem.php`](app/Models/DbWarehouseItem.php:11)) |
| `db_stockadjustment`, `db_stockadjustmentitems` | yes | StoreScoped |
| `db_stocktransfer`, `db_stocktransferitems` | yes (+ legacy `to_store_id`) | StoreScoped ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:16)) |

**Sales**
`db_sales`, `db_salesitems`, `db_salespayments`, `db_salesreturn`, `db_salesitemsreturn`, `db_salespaymentsreturn`, `db_hold`, `db_holditems`, `db_quotation`, `db_quotationitems` — all with `store_id`, all StoreScoped. `db_sales` carries `warehouse_id`, `sales_code`, `reference_no`, `coupon_id`, `coupon_amt`, `customer_previous_due`, `customer_total_due` ([`db_sales` migration](database/migrations/2026_02_07_090938_create_db_sales_table.php:15)).

**Purchases**
`db_purchase`, `db_purchaseitems`, `db_purchasepayments`, `db_purchasereturn`, `db_purchaseitemsreturn`, `db_purchasepaymentsreturn` — all `store_id`, all StoreScoped.

**Accounts / Ledger**
| Table | Key columns | store_id? |
|---|---|---|
| `ac_accounts` | `account_code` (unique `(store_id, account_code)`), `system_key` (unique `(store_id, system_key)`), `parent_id`, `balance`, `is_system` | yes ([`AcAccount.php`](app/Models/AcAccount.php:11)) |
| `ac_transactions` | `debit_account_id`, `credit_account_id`, `debit_amt`, `credit_amt`, `ref_*` FK-ish columns for sales/purchase/expense/transfer/deposit | yes ([`AcTransaction.php`](app/Models/AcTransaction.php:11)) |
| `ac_moneytransfer` | `transfer_code` (unique `(store_id, transfer_code)`), `delete_bit` | yes |
| `ac_moneydeposits` | `deposit_date`, `delete_bit` | yes |
| `cash_drawer_reconciliations` | `reconciliation_code` (**global** unique), `store_id` default 1, `warehouse_id`, `account_id`, `status` | yes ([`…reconciliations migration`](database/migrations/2026_08_24_000001_create_cash_drawer_reconciliations_table.php:16)) |

**Expenses** `db_expense`, `db_expense_category` — `store_id`, StoreScoped, soft-delete via `delete_bit`.

**Contacts** `db_customers` (SoftDeletes + StoreScoped), `db_suppliers` (SoftDeletes + StoreScoped, unique `(store_id, mobile)` / `(store_id, email)`).

**Tax / settings** `db_tax`, `db_tax`-adjacent `db_units`, `db_paymenttypes`, `db_states`, `db_country`, `db_currency`, `db_languages` — mostly `store_id` + StoreScoped, **except** `db_country`, `db_currency`, `db_languages` which are global reference tables.

**Messaging (SMS)** — **mostly NOT store-scoped:**
| Table | store_id? | Evidence |
|---|---|---|
| `db_smsapi` | yes | [`DbSmsapi.php`](app/Models/DbSmsapi.php:10) |
| `db_smstemplates` | yes | [`DbSmsTemplate.php`](app/Models/DbSmsTemplate.php:12) |
| `db_fivemojo` | yes (column) — **model does NOT use StoreScoped** | [`DbFivemojo` migration](database/migrations/2026_02_07_090714_create_db_fivemojo_table.php:16); provider queries it explicitly ([`FiveMojoSMSProvider.php`](app/SMS/Providers/FiveMojoSMSProvider.php:18)) |
| `sms_campaigns` | **no** | [`sms_campaigns migration`](database/migrations/2026_02_23_110001_create_sms_campaigns_table.php:14) |
| `sms_logs` | `[UNVERIFIED]` (migration not read in full) | — |
| `sms_auto_rules` | `[UNVERIFIED]` | — |
| `sms_blacklists` | **no**; `phone` is globally unique | [`sms_blacklists migration`](database/migrations/2026_02_23_110004_create_sms_blacklists_table.php:16) |
| `sms_daily_stats` | **no**; `date` is globally unique | [`sms_daily_stats migration`](database/migrations/2026_02_23_110005_create_sms_daily_stats_table.php:16) |

**EMI** `db_emi_sale`, `db_emi_schedule` (from `2026_02_18_052015_create_db_emi_tables.php`) — `[UNVERIFIED]` whether they carry `store_id`; the migration was not read in full in this pass.

**Other** `seo_meta` (`page_key` unique), `db_emailtemplates`, `db_shippingaddress`, `db_bankdetails`, `db_subscription`, `db_package`, and payment-gateway tables (`db_paypal`, `db_stripe`, `db_instamojo`, `db_twilio`) all carry a `store_id` column.

### A.3.3 Verified money & stock flows

**Flow 1 — POS Sale → Payment → Ledger → Stock** (`PosController::store`).
1. Warehouse must exist & be active ([`PosController.php`](app/Http/Controllers/PosController.php:653)).
2. Warehouse stock availability checked, serial validation, then `DbSale` created with `store_id`, `warehouse_id`, `sales_code` ([`PosController.php`](app/Http/Controllers/PosController.php:402)).
3. Per-line: `db_warehouseitems.available_qty` decremented ([`PosController.php`](app/Http/Controllers/PosController.php:466)).
4. Per payment with `account_id`: `AcTransaction::create([...'store_id'=>$sale->store_id...])` ([`PosController.php`](app/Http/Controllers/PosController.php:507)).
5. SMS observer fires on `DbSale` created ([`SMSObserver.php`](app/Observers/SMSObserver.php:13)).

**Flow 2 — Purchase → Stock → Payment/Payable → Ledger** (`PurchaseController`).
- Stock up: `DbItem::increment('stock')` **and** `db_warehouseitems` upsert ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:256)).
- Payments and payable both post `AcTransaction` rows ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:433), [`…:455`](app/Http/Controllers/PurchaseController.php:455)).
- Returns reverse stock and post reversal rows ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:1170)).

**Flow 3 — Stock Transfer (warehouse→warehouse, same store).** Decrements source and increments destination `db_warehouseitems`; reassigns serials' `warehouse_id` ([`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:201)). The legacy `to_store_id` column is **not referenced anywhere in controllers/models' fillable** — confirmed absent from the model fillable and from any controller write; inter-**store** transfer is therefore **not implemented** (`to_store_id` is a dormant column).

**Flow 4 — Money Transfer / Deposit → Ledger.** `TransferController` writes paired debit/credit `AcTransaction` rows and reversal rows on edit/delete ([`TransferController.php`](app/Http/Controllers/TransferController.php:221)).

**Flow 5 — Cash Drawer Reconciliation → Ledger adjustment.** Open/close flow computes expected cash and, if variance posted and user has `cash_reconciliation_adjust`, writes overage/shortage `AcTransaction` rows ([`CashReconciliationController.php`](app/Http/Controllers/CashReconciliationController.php:361), [`…:573`](app/Http/Controllers/CashReconciliationController.php:573)).

**Flow 6 — Quotation → Sale conversion.** `QuotationController::convertToSale` creates a `DbSale` from quotation items ([`QuotationController.php`](app/Http/Controllers/QuotationController.php:610)).

**Flow 7 — Customer Advance.** `AdvanceController` posts `AcTransaction` for the advance and for reversals ([`AdvanceController.php`](app/Http/Controllers/AdvanceController.php:95), [`…:217`](app/Http/Controllers/AdvanceController.php:217)).

**Flow 8 — SMS pipeline.** `DbSale` observer → `SmsTriggerService::trigger('InvoiceCreated', sale)` → `RuleResolverService` (cache key includes store) → `SmsService` resolves provider per `store_id` → provider reads credentials scoped by `store_id` ([`SmsTriggerService.php`](app/SMS/Services/SmsTriggerService.php:17), [`RuleResolverService.php`](app/SMS/Services/RuleResolverService.php:16), [`SmsService.php`](app/SMS/Services/SmsService.php:61)).

### A.3.4 Code generation & numbering (multi-store collision surface)

`CodeGeneratorService::generate()` builds codes as `prefix + zero-padded (max('id')+offset)` — note **`max('id')` is global across all stores** because it is a raw `DbSale::max('id')` etc. ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:35)). Returns use timestamp/`uniqid()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:91)). Prefixes come from the acting `store_settings()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:29)). Global-unique `item_code` and `reconciliation_code` add further collision pressure (see Deliverable B §2).

---

## A.4 Roles, Permissions & Access Control

### Defined roles (seeder-authoritative)

`RolePermissionSeeder` defines four roles with hardcoded permission arrays ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:75)): **Super Admin**, **Admin**, **Manager**, **Salesman**, **Cashier** — all created with `store_id => 1` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:137)). `PermissionSeeder` separately seeds a single Super Admin role + full permission list ([`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:26)). `AdminUserSeeder` creates three additional per-store Super Admin roles ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:59)).

### Enforcement model — the central finding

There is **no permission middleware** anywhere. `bootstrap/app.php` registers only one alias, `ensure.store` ([`bootstrap/app.php`](bootstrap/app.php:14)). Authorization is enforced by **two inconsistent, partly-applied in-controller patterns**:

1. **`Gate::authorize(...)` with policies** — used **only** in `UserController` and `RoleController`, and the policies are Super-Admin-only ([`UserPolicy.php`](app/Policies/UserPolicy.php:15), [`RolePolicy.php`](app/Policies/RolePolicy.php:15)).
2. **Ad-hoc `if (auth()->check() && !auth()->user()->hasPermission('slug')) abort(403)`** — present in many controllers (Warehouse, Unit, Transfer, Deposit, Account, Tax, Currency, Country, State, Language, PaymentType, Expense, ExpenseCategory, CashReconciliation, StockTransfer, StockAdjustment, Item print/import, SmtpSettings, StoreSettings). **Absent entirely** from core transactional controllers: **`PosController`, `SaleController`, `SalesReturnController`, `PurchaseController`, `QuotationController`, `CustomerController`, `SupplierController`, `CouponController`, `CustomerCouponController`, `ReportController`, `DashboardController`, `AdvanceController`, `ServiceController`** (no matches in the authorization grep for those files).

**Conflict #3 — permission slug vocabulary mismatch (UI vs. seeder/controller).**
- The sidebar, `NavigationShortcutService`, and `GlobalSearchController` check a *different* vocabulary, e.g. `sales_include_pos_view`, `sales_include_pos_add`, `sms_whatsapp_send_message`, `dashboard_view_dashboard_data`, `reports_view`, `customer_coupon_view`, `reports_cash_flow_view` ([`layouts/app.blade.php`](resources/views/layouts/app.blade.php:208), [`NavigationShortcutService.php`](app/Services/NavigationShortcutService.php:18), [`GlobalSearchController.php`](app/Http/Controllers/GlobalSearchController.php:40)).
- The seeders grant a *different* vocabulary: `sales_view`, `send_sms`, `dashboard_view`, `sales_report` ([`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:26)).
- **These strings do not overlap for these modules.** Consequences: a seeder-granted user fails the sidebar checks (menu hidden) even though the controller gates — where present — use the seeder vocabulary. The project has begun fixing this for the Accounts cluster only, documented by `AccountsPermissionSlugUnificationTest` which explicitly describes "Phase A slug unification" ([`AccountsPermissionSlugUnificationTest.php`](tests/Feature/AccountsPermissionSlugUnificationTest.php:40)). Other clusters remain unmigrated.

**Net security verdict:** for the core transactional controllers (POS/sales/purchases), authorization is **UI-only**. A `Cashier`-role user who knows a URL can invoke any POS/sale/purchase/report route because those controllers never call `hasPermission()` and no route middleware gate exists.

---

## A.5 Location / Warehouse Model

- **`db_store`** is the tenant/business unit. It holds business identity and financial settings (GST/VAT/PAN, invoice prefixes, currency, decimals, timezone, SMTP credentials which are `encrypted` at the model layer) ([`DbStore.php`](app/Models/DbStore.php:22), [`db_store migration`](database/migrations/2026_02_07_091820_create_db_store_table.php:14)).
- **`db_warehouse`** is a *stock bucket inside a store*: it has its own `store_id`, `warehouse_type` (`'System'` in seeder), name, mobile/email, status ([`db_warehouse migration`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:15), [`WarehouseSeeder.php`](database/seeders/WarehouseSeeder.php:16)). It has **no** cash account, no tax number, no address, no users of its own.
- Stock is materialised **only** as `db_warehouseitems (warehouse_id, item_id, available_qty)`. `db_items.stock` is a **legacy denormalised aggregate** that is still incremented/decremented in several flows (e.g. [`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:256), [`SaleController.php`](app/Http/Controllers/SaleController.php:328)) alongside the warehouse row — a **dual-write consistency hazard**.
- Sessions/requests resolve the store via the authenticated user's `store_id` (`current_store_id()`), with a CLI/default fallback to the first `db_store` row or `1` ([`helpers.php`](app/Helpers/helpers.php:152)).
- **There is no store-switcher UI** and no session-persisted "current store" distinct from `auth()->user()->store_id`. A user is hard-bound to exactly one store.
- A Super-Admin multi-store **dashboard** exists and aggregates every store by explicitly bypassing the scope (`withoutGlobalScope('store_id')`) via `DashboardController::computeStoreStats(int $storeId)` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53), [`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:37)). This is read-only aggregation — it is not a store switcher.

**Summary:** today the codebase means by "store" a **tenant** (separate data partition), and by "warehouse" a **stock bucket within a tenant**. Multiple stores already coexist in the schema and seed data (Store 1 Dhaka, Store 2 Chittagong, Store 3 Sylhet — [`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)), but operational routing is single-store-per-user.

---

## A.6 Testing & Known Issues Register

### Test inventory & real status

- Framework: **Pest** on top of PHPUnit, configured with `Unit` and `Feature` suites ([`phpunit.xml`](phpunit.xml:7)).
- `tests/Unit` contains only `ExampleTest.php`. All real coverage is in `tests/Feature` (~125 files) plus `tests/Feature/Auth` (6 files).
- **Executed result (this audit, `php artisan test`):** `Tests: 15 failed, 1000 passed (5477 assertions)`, duration 128.11s.

**The 15 failing tests** (all are *concurrency/race or browser-script* checks — none are ordinary functional failures):

| Test | Failure signature |
|---|---|
| `ItemImportTest > genuine parallel import category race creates exactly one` | expected 1, got 0 |
| `ItemsListRedesignTest > items list page inline Alpine script passes node --check` | inline script #0 failed `node --check` |
| `MoneyTransferFixesTest > transfer creation genuine parallel concurrency blocks overdraft` | parallelism assertion |
| `MoneyTransferFixesTest > delete reversal genuine parallel concurrency` | `false` vs `true` |
| `MoneyTransferFixesTest > same transfer concurrent double delete race (×2)` | `false` vs `true` |
| `PosSerialCheckoutValidationTest > genuine parallel checkouts of the same serial` | expected 1, got 0 |
| `SerialUniquenessAcrossEntryPointsTest > concurrent race same item serial` | expected 1, got 0 |
| `StockAdjustmentDeleteAndRaceTest > concurrent adjustments same new item warehouse final qty` | expected 1, got 0 |
| `StockCreateFormRedesignBrowserCheckTest > transfer create page node check` | inline script #0 failed `node --check` |
| `StockCreateFormRedesignBrowserCheckTest > adjustment create page node check` | inline script #0 failed `node --check` |
| `SupplierRedesignAndRisksTest > genuine parallel double delete` | expected 1, got 0 |
| `SupplierRedesignAndRisksTest > genuine parallel same store same phone` | expected 1, got 0 |
| `SystemAccountRaceConditionTest > concurrent external deposits create exactly one clearing account` | missing `RESULT:CREATED:` |
| `SystemAccountRaceConditionTest > concurrent account creation with opening balance` | missing `RESULT:CREATED:` |

`[INFERENCE]` The race tests spawn parallel PHP workers against a file-backed SQLite DB; the common `expected 1, got 0` / empty-output pattern suggests the worker harness itself (SQLite write-locking under the test runner) is failing in this environment rather than the production code. This should be confirmed before treating them as real defects. The three `node --check` failures are deterministic and indicate genuinely invalid inline JS in the items-list, transfer-create, and adjustment-create views.

### Known Issues register (code-derived)

| # | Issue | Evidence | Severity |
|---|---|---|---|
| K1 | **Authorization is UI-only for core transactional controllers** (POS/Sale/Purchase/Report/etc.). No middleware, no in-controller check. | absent in grep of [`app/Http/Controllers`](app/Http/Controllers); only [`bootstrap/app.php`](bootstrap/app.php:14) alias exists | High |
| K2 | **Permission slug vocabulary split** (UI uses `sales_include_pos_view` etc.; seeders grant `sales_view` etc.). Only Accounts cluster partially unified. | [`app.blade.php`](resources/views/layouts/app.blade.php:208) vs [`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:29) | High |
| K3 | **`isSuperAdmin()` matches `role_name==='Super Admin'`**, so per-store admins bypass all store checks and all permission gates. | [`User.php`](app/Models/User.php:101), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:18) | High |
| K4 | **Global-unique columns will collide across stores**: `db_items.item_code` globally unique, `cash_drawer_reconciliations.reconciliation_code` globally unique, `sms_blacklists.phone` / `sms_daily_stats.date` globally unique. | [`uq_db_items_item_code`](database/migrations/2026_08_29_174908_add_unique_item_code_to_db_items_table.php:49), [`reconciliations`](database/migrations/2026_08_24_000001_create_cash_drawer_reconciliations_table.php:16) | High |
| K5 | **Code numbers derive from global `max('id')`**, not per store; returns also use `date()`/`uniqid()` (non-deterministic, collision-safe but not per-store sequential). | [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:35) | Medium |
| K6 | **Dual stock source of truth**: `db_items.stock` and `db_warehouseitems.available_qty` are both written in purchase/sale/return flows. | [`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:256), [`SaleController.php`](app/Http/Controllers/SaleController.php:328) | Medium |
| K7 | **SMS tables not store-scoped** (`sms_campaigns` has no `store_id`; blacklist/date globally unique). SMS history is not per-store. | [`sms_campaigns migration`](database/migrations/2026_02_23_110001_create_sms_campaigns_table.php:14) | Medium |
| K8 | **Invalid inline JS** in items-list, stock-transfer-create and stock-adjustment-create pages (deterministic `node --check` failures). | `ItemsListRedesignTest`, `StockCreateFormRedesignBrowserCheckTest` | Medium |
| K9 | **Orphan/legacy view** `module/items/import_services.blade.php` with route removed. | [`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:44) | Low |
| K10 | **Legacy unused column** `db_stocktransfer.to_store_id` / `db_stocktransferitems.to_store_id` present but never written by controllers. | [`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17) (fillable) — no controller usage | Low |
| K11 | **Store rows are deleted with `cascade`** from `db_warehouse`/`db_warehouseitems`/`users` FKs — a mis-scoped delete could cascade real data. | [`db_warehouse migration`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:32) | Low |
| K12 | **`StoreScoped` matches `store_id IS NULL`**, meaning any legacy null row is globally visible until the NOT-NULL migration runs. | [`StoreScoped.php`](app/Models/Traits/StoreScoped.php:15) | Medium |
| K13 | **`GetCustomerDue` route is wired** (`sales.pos.customer.due`) and handler exists at [`PosController.php`](app/Http/Controllers/PosController.php:1119) — *no defect*; recorded here only to document the route was checked. | — | Info |
| K14 | **Windows-hardcoded dump path** `C:/xampp/mysql/bin/` in DB config and `DUMP_BINARY_PATH` env in provider — non-portable. | [`config/database.php`](config/database.php:65), [`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:21) | Low |
| K15 | **Public layout omits `app.js`** while app/guest layouts include it → Alpine-dependent public pages may silently not initialise. | [`public.blade.php`](resources/views/layouts/public.blade.php:19) | Low |

No `TODO`/`FIXME`/`XXX` markers were found in `app/` (only a false-positive comment containing the word "counter"). The codebase appears to rely on external issue tracking rather than inline markers.

### Coverage acknowledgement

Views under `resources/views/module/**` (≈150 files), `layouts/**`, `components/**`, `legal/**`, `auth/**`, `docs/**` are enumerated by directory; each module group is represented in A.2/A.3. Individual per-view JavaScript logic was **not** audited line-by-line — marked `[UNVERIFIED]` for those specific behaviours. All 64 controllers and 66 models are enumerated by name in the audit index below.

**Controller index (64):** Account, Advance, Backup, Brand, CashReconciliation, Category, Controller(base), Country, Coupon, Currency, Customer, CustomerCoupon, Dashboard, Deposit, Docs, ExpenseCategory, Expense, GlobalSearch, Item, Language, Legal, MessageTemplate, MultiStoreDashboard, PaymentType, Pos, Profile, Purchase, Quotation, Report, Role, Sale, SaleInvoice, SalesReturn, SerialHistory, Service, Sitemap, SmsAutoRule, SmsCampaign, SmsHistory, SmsLog, SmsSend, SmsSettings, SmsTemplate, SmtpSettings, State, StockAdjustment, StockTransfer, StoreSettings, Supplier, Tax, Transaction, Transfer, Unit, User, Variant, Warehouse, Auth/* (8), Concerns/ImportsItems.

**Model index (66):** AcAccount, AcMoneyDeposit, AcMoneyTransfer, AcTransaction, CashDrawerReconciliation, CustomerGuarantor, CustomerGuardian, DbBrand, DbCategory, DbCountry, DbCoupon, DbCurrency, DbCustAdvance, DbCustomer, DbCustomerCoupon, DbEmiSale, DbEmiSchedule, DbExpense, DbExpenseCategory, DbFivemojo, DbHold, DbHoldItem, DbItem, DbItemSerial, DbLanguage, DbPaymentType, DbPermission, DbPurchase, DbPurchaseItem, DbPurchaseItemReturn, DbPurchasePayment, DbPurchasePaymentReturn, DbPurchaseReturn, DbQuotation, DbQuotationItem, DbRole, DbSale, DbSaleItem, DbSalePayment, DbSalesItemReturn, DbSalesPaymentReturn, DbSalesReturn, DbSmsapi, DbSmsTemplate, DbState, DbStockAdjustment, DbStockAdjustmentItems, DbStockTransfer, DbStockTransferItems, DbStore, DbSupplier, DbSmtp, DbTax, DbUnit, DbVariant, DbWarehouse, DbWarehouseItem, SeoMeta, SmsAutoRule, SmsBlacklist, SmsCampaign, SmsDailyStat, SmsLog, User, Traits/StoreScoped.
