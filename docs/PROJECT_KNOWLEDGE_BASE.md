# PROJECT KNOWLEDGE BASE — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-16
**Method:** every statement below was read directly from source during this audit pass. File citations use `path:line`. Anything not fully verified is marked `[UNVERIFIED]`; reasoned guesses are marked `[INFERENCE]`. Prior audit history is superseded and is not preserved here.

> Scope note: this is a **cold-start** document. It assumes the reader has never seen this codebase.

---

## 0. Repository scale (counted this pass)

| Thing | Count | Command used |
|---|---|---|
| Blade views (all) | 225 | `Get-ChildItem -Recurse -Filter *.blade.php resources/views` |
| Blade views under `resources/views/module/**` | 180 | same, scoped to `module` |
| Controllers (`*Controller.php`) | 59 | `Get-ChildItem app/Http/Controllers -Filter *Controller.php` |
| Models (`app/Models/*.php`) | 66 | `Get-ChildItem app/Models -Filter *.php` |
| Migrations | 143 | `Get-ChildItem database/migrations -Filter *.php` |
| Test files (`*Test.php`) | 159 | `Get-ChildItem -Recurse tests -Filter *Test.php` |

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
| Test framework | `pestphp/pest` `^3.8` + `pest-plugin-laravel` `^3.2` | [`composer.json`](composer.json:27), [`composer.json`](composer.json:28) |
| Other dev deps | `fakerphp/faker`, `laravel/breeze`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `mockery/mockery`, `nunomaduro/collision` | [`composer.json`](composer.json:19) |
| Autoloaded global helper | `app/Helpers/helpers.php` via `autoload.files` | [`composer.json`](composer.json:36) |
| Helper also `require_once`d in provider | yes | [`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:19) |
| Service providers registered | only `App\Providers\AppServiceProvider` | [`bootstrap/providers.php`](bootstrap/providers.php:4) |

### Frontend

| Item | Value | Evidence |
|---|---|---|
| Build tool | Vite `^7.0.7` + `laravel-vite-plugin` `^2.0.0` | [`package.json`](package.json:20), [`package.json`](package.json:17) |
| CSS framework | Tailwind CSS `4.1.18` (v4 CSS-first; **no `tailwind.config.js` exists**) | [`package.json`](package.json:19), [`vite.config.js`](vite.config.js:1) |
| Tailwind Vite plugin | `@tailwindcss/vite` `^4.0.0` | [`package.json`](package.json:12) |
| Tailwind plugins | `@tailwindcss/forms`, `@tailwindcss/typography` | [`app.css`](resources/css/app.css:2), [`app.css`](resources/css/app.css:3) |
| JS interactivity | Alpine.js `^3.4.2` + `@alpinejs/collapse` `^3.17.0` | [`package.json`](package.json:13), [`package.json`](package.json:23) |
| HTTP client | axios `^1.11.0` | [`package.json`](package.json:15) |
| Charts | Chart.js **vendored locally** at `public/vendor/chart.umd.min.js` | [`app.blade.php`](resources/views/layouts/app.blade.php:19) |
| Vite inputs | `resources/css/app.css`, `resources/js/app.js` | [`vite.config.js`](vite.config.js:9) |
| Built assets present | `public/build/manifest.json`, `public/build/assets/app-EY2auKIM.css`, `public/build/assets/app-vZIy2K19.js` | `public` directory listing |

**Asset-loading behaviour:**
- `layouts/app.blade.php` loads `@vite(['resources/css/app.css','resources/js/app.js'])` **and** `asset('vendor/chart.umd.min.js')` ([`app.blade.php`](resources/views/layouts/app.blade.php:17), [`app.blade.php`](resources/views/layouts/app.blade.php:19)).
- `layouts/public.blade.php` loads **only** `@vite('resources/css/app.css')` — **no `app.js`** ([`public.blade.php`](resources/views/layouts/public.blade.php:19)), plus Font Awesome 6 from cdnjs ([`public.blade.php`](resources/views/layouts/public.blade.php:16)).
- The authenticated app layout loads **no Font Awesome**; Google Font `Plus Jakarta Sans` only ([`app.blade.php`](resources/views/layouts/app.blade.php:21)). Mitigated: the FA spinner in `app.js` is the only FA dependency and it degrades to a missing glyph ([`app.js`](resources/js/app.js:50)).

### Database

- Default connection is `sqlite` unless `DB_CONNECTION` overrides ([`config/database.php`](config/database.php:19)); `sqlite`/`mysql`/`mariadb`/`pgsql`/`sqlsrv` all defined.
- MySQL connection hardcodes `'dump_binary_path' => 'C:/xampp/mysql/bin/'` ([`config/database.php`](config/database.php:65)) — non-portable (ISSUE-6).
- Tests default to in-memory SQLite ([`phpunit.xml`](phpunit.xml:26), [`phpunit.xml`](phpunit.xml:27)); the MySQL gate uses `laravelpos_test` ([`phpunit.mysql.xml`](phpunit.mysql.xml:26), [`phpunit.mysql.xml`](phpunit.mysql.xml:29)).

### App-specific config

- `config/sales.php` defines the single kill-switch `sales.enforce_total_validation` (env `ENFORCE_TOTAL_VALIDATION`, default `true`) gating server-side grand-total recompute rejection ([`config/sales.php`](config/sales.php:25)).
- **`config/services.php` contains only Laravel stock entries** (postmark/resend/ses/slack) ([`config/services.php`](config/services.php:17)) — no SMS settings. **However `config('sms.sandbox')` and `config('sms.throttle.*')` are read at runtime** ([`SmsService.php`](app/SMS/Services/SmsService.php:24), [`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:39)) and **no `config/sms.php` exists** (confirmed: `config/` contains app, auth, backup, cache, database, filesystems, logging, mail, queue, sales, services, session). They therefore always resolve to their inline defaults (`sandbox` → false, throttle → 5). See ISSUE-12.
- `.env.example` documents `ENFORCE_TOTAL_VALIDATION=true` ([`.env.example`](.env.example:20)).

---

## A.2 Design System

Full theme documentation is in [`docs/THEME_REFERENCE.md`](docs/THEME_REFERENCE.md). Summary:

- Tokens are declared in the Tailwind v4 `@theme` block ([`app.css`](resources/css/app.css:7)): indigo primary ramp `--color-primary*` ([`app.css`](resources/css/app.css:11)), `--primary-rgb: 99 102 241` ([`app.css`](resources/css/app.css:27)), semantic surfaces/borders/text ([`app.css`](resources/css/app.css:30)), status colours ([`app.css`](resources/css/app.css:41)), shadows ([`app.css`](resources/css/app.css:50)), radius ([`app.css`](resources/css/app.css:56)), dark palette ([`app.css`](resources/css/app.css:60)), `--font-sans` ([`app.css`](resources/css/app.css:8)).
- Dark mode uses `@custom-variant dark (&:where(.dark, .dark *))` ([`app.css`](resources/css/app.css:5)), toggled from `localStorage` on `<html>` ([`app.blade.php`](resources/views/layouts/app.blade.php:3)).
- Project utilities: `scrollbar-hide` ([`app.css`](resources/css/app.css:74)), `custom-scrollbar` ([`app.css`](resources/css/app.css:84)), `anime-fade-in` ([`app.css`](resources/css/app.css:105)), `gradient-bg` ([`app.css`](resources/css/app.css:122)), `hero-gradient` ([`app.css`](resources/css/app.css:142)), `shadow-premium`/`shadow-premium-lg` ([`app.css`](resources/css/app.css:146)), `pulse-orange` ([`app.css`](resources/css/app.css:154)); base `[x-cloak]{display:none!important}` ([`app.css`](resources/css/app.css:68)); POS fullscreen rule `.pos-screen:fullscreen` ([`app.css`](resources/css/app.css:174)).
- Semantic component classes now live in `@layer components`: `.card`, `.card-hover`, `.page-padding`, `.input-base`, `.btn-primary`, `.btn-secondary`, `.btn-danger`, `.btn-ghost` ([`app.css`](resources/css/app.css:188)-[`app.css`](resources/css/app.css:220)). *Note: these are newer than the previous audit, which described buttons as ad-hoc per-view helper classes.*
- Sidebar theming is centralised: `aside.app-sidebar` surface + collapsed-rail rules ([`app.css`](resources/css/app.css:224), [`app.css`](resources/css/app.css:296)).
- Blade component inventory (27 files under `resources/views/components/`, enumerated from the directory listing): `application-logo`, `auth-session-status`, `badge`, `card`, `danger-button`, `dropdown`, `dropdown-link`, `icon-button`, `input-error`, `input-label`, `keyboard-shortcuts`, `keyboard-shortcuts-modal`, `modal`, `nav-link`, `notification-toast`, `primary-button`, `report-export-buttons`, `responsive-nav-link`, `searchable-select`, `secondary-button`, `select`, `seo-meta`, `sidebar-tooltip`, `stat-card`, `table`, `text-input`, `textarea`. Class-based components `AppLayout`/`GuestLayout`/`PublicLayout` live under `app/View/Components/`. `[UNVERIFIED]` per-component `@props` tables were not exhaustively extracted.
- **No orphan `import_services.blade.php` exists** (confirmed by content search across `resources/views` for `import_services` — 0 results). The `import_services` *permission slug* is deliberately retained and documented as reserved in [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:33).

---

## A.3 Data & Financial Architecture

### A.3.1 Store-scoping mechanism (foundational)

1. **Eloquent global scope trait** `App\Models\Traits\StoreScoped` — adds a global scope on `store_id` filtered strictly to `current_store_id()` when a user is authenticated; the old `store_id IS NULL` escape hatch was **removed** ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:14), [`StoreScoped.php`](app/Models/Traits/StoreScoped.php:23)). It exposes `scopeAllStores()` = `withoutGlobalScope('store_id')` ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:28)).
2. **Acting-store context (Phase 2)** — `App\Services\StoreContext` is a **singleton** ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:25)) holding the resolved acting store, an `allStores` flag, and a `resolved` flag ([`StoreContext.php`](app/Services/StoreContext.php:20)). `set()`/`setAllStores()`/`forget()` ([`StoreContext.php`](app/Services/StoreContext.php:34), [`…:44`](app/Services/StoreContext.php:44), [`…:70`](app/Services/StoreContext.php:70)).
3. **`SetCurrentStore` middleware** is appended to the whole `web` group and aliased `store.context` ([`bootstrap/app.php`](bootstrap/app.php:18), [`bootstrap/app.php`](bootstrap/app.php:25)). It forgets the context each request, then resolves: session `current_store_id` **if allowed**, else the user's own `store_id` ([`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:39), [`…:48`](app/Http/Middleware/SetCurrentStore.php:48)). `canActAs()` permits cross-store identities (`canViewAllStores()`) and otherwise only the user's own store ([`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:63)). A forged/disallowed session value is ignored, never honoured.
4. **Helper functions** in [`app/Helpers/helpers.php`](app/Helpers/helpers.php:1):
   - `current_store_id()` → StoreContext binding first, then authenticated user's `store_id`, then `default_store_id()` ([`helpers.php`](app/Helpers/helpers.php:157)).
   - `default_store_id()` → memoized first `db_store` row, else `1` ([`helpers.php`](app/Helpers/helpers.php:24)).
   - `store_settings()` → memoized `DbStore` row keyed per store id ([`helpers.php`](app/Helpers/helpers.php:77)).
   - `store_id_cache_key()` → per-store cache key `s{id}` or `default` ([`helpers.php`](app/Helpers/helpers.php:51)).
   - `store_scoped_cached_list()` → per-store dropdown-list cache accessor with key `db_<list>_s{storeId}` ([`helpers.php`](app/Helpers/helpers.php:192)).
   - `flush_store_settings_cache()` → drops process-lifetime memoization (used by `tests/TestCase.php`) ([`helpers.php`](app/Helpers/helpers.php:134)).
   - `format_currency()` / `format_quantity()` honour `$store->decimals` / `qty_decimals` / `currency_placement` ([`helpers.php`](app/Helpers/helpers.php:209), [`helpers.php`](app/Helpers/helpers.php:239)).
   - Static cache holder: `App\Support\StoreSettingsCache`.

**Role relation isolation:** `User::role()` and `DbRole::permissions()` explicitly call `withoutGlobalScope('store_id')`, because privilege resolution must not be filtered by the acting store (otherwise it becomes circular) ([`User.php`](app/Models/User.php:82), [`DbRole.php`](app/Models/DbRole.php:87)).

**StoreScoped model count:** 44 models `use StoreScoped` (content search across `app/Models` for `StoreScoped` = 56 hits, of which 44 are trait applications; `User`, `ActivityLog`, `CustomerIdentity` and the trait file itself are deliberate non-users).

### A.3.2 Table inventory by domain

Tables are prefixed `db_`, `ac_`, `sms_` or plain. All operational tables carry a `store_id`.

**Store & identity**

| Table | Key columns | store_id? | StoreScoped? | Uniqueness |
|---|---|---|---|---|
| `db_store` | `store_code`, `store_name`, `gst_no`…, all `*_init` prefixes, `currency_id`, `timezone`, `smtp_*`, `decimals`, `qty_decimals`, `currency_placement` | is the store | n/a | — |
| `users` | `store_id`, `role_id`, `role_name`, `username`, `email` | yes | no trait | `email` unique |
| `db_roles` | `store_id`, `role_name`, `description`, `status`, `is_super_admin`, `is_owner` | yes | StoreScoped ([`DbRole.php`](app/Models/DbRole.php:10)) | `(store_id, role_name)` unique |
| `db_permissions` | `store_id`, `role_id`, `permissions` (JSON array) | yes | StoreScoped ([`DbPermission.php`](app/Models/DbPermission.php:10)) | — |
| `db_customer_identities` | `phone` (**globally** unique), `name`, `email`, `nid` | **no** (deliberate) | no trait ([`CustomerIdentity.php`](app/Models/CustomerIdentity.php:14)) | `phone` unique ([`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:28)) |
| `activity_logs` | `store_id` (nullable), `user_id` (nullable), `action`, `entity_type`/`entity_id`, `old_values`/`new_values` JSON, `ip_address`, `user_agent` | nullable | no trait ([`ActivityLog.php`](app/Models/ActivityLog.php:10)) | no `updated_at` ([`ActivityLog.php`](app/Models/ActivityLog.php:22)) |

**Items / stock**

| Table | store_id? | Traits | Uniqueness |
|---|---|---|---|
| `db_items` | yes | StoreScoped ([`DbItem.php`](app/Models/DbItem.php:11)) | `(store_id, item_code)` composite (global unique dropped); per-store unique SKU/barcode |
| `db_item_serials` | yes | StoreScoped ([`DbItemSerial.php`](app/Models/DbItemSerial.php:11)) | `(item_id, serial_number)` |
| `db_category`, `db_brands`, `db_variants` | yes | StoreScoped | per-store `(store_id, name)`/`(store_id, code)` |
| `db_warehouse` | yes | StoreScoped ([`DbWarehouse.php`](app/Models/DbWarehouse.php:11)) | `(store_id, warehouse_name)`; `delete_bit` |
| `db_warehouseitems` | yes | StoreScoped ([`DbWarehouseItem.php`](app/Models/DbWarehouseItem.php:11)) | `(warehouse_id, item_id)` |
| `db_stockadjustment`, `db_stockadjustmentitems` | yes | StoreScoped | — |
| `db_stocktransfer`, `db_stocktransferitems` | yes | StoreScoped ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:11)) | `delete_bit`; **`to_store_id` REMOVED** ([`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27)) |

**Sales** `db_sales`, `db_salesitems`, `db_salespayments`, `db_salesreturn`, `db_salesitemsreturn`, `db_salespaymentsreturn`, `db_hold`, `db_holditems`, `db_quotation`, `db_quotationitems` — all `store_id`, all StoreScoped ([`DbSale.php`](app/Models/DbSale.php:11)). `db_sales` carries `warehouse_id`, `sales_code`, `reference_no`, `grand_total`, `paid_amount`, `payment_status`, `sales_status`, `status` ([`DbSale.php`](app/Models/DbSale.php:15)).

**Purchases** `db_purchase`, `db_purchaseitems`, `db_purchasepayments`, `db_purchasereturn`, `db_purchaseitemsreturn`, `db_purchasepaymentsreturn` — all `store_id`, all StoreScoped, `(store_id, code)` composite uniques.

**Accounts / Ledger**

| Table | Key columns | store_id? | Uniqueness |
|---|---|---|---|
| `ac_accounts` | `account_code`, `system_key`, `parent_id`, `balance`, `is_system` | yes ([`AcAccount.php`](app/Models/AcAccount.php:11)) | `(store_id, account_code)`, `(store_id, system_key)` |
| `ac_transactions` | `debit_account_id`, `credit_account_id`, `debit_amt`, `credit_amt`, `ref_*` columns | yes ([`AcTransaction.php`](app/Models/AcTransaction.php:11)) | supplier index |
| `ac_moneytransfer` | `transfer_code`, `delete_bit` | yes ([`AcMoneyTransfer.php`](app/Models/AcMoneyTransfer.php:11)) | `(store_id, transfer_code)` |
| `ac_moneydeposits` | `deposit_date`, `delete_bit` | yes ([`AcMoneyDeposit.php`](app/Models/AcMoneyDeposit.php:11)) | — |
| `cash_drawer_reconciliations` | `reconciliation_code`, `warehouse_id`, `account_id`, `status`, `delete_bit` | yes ([`CashDrawerReconciliation.php`](app/Models/CashDrawerReconciliation.php:11)) | `(store_id, reconciliation_code)` composite |

**Expenses** `db_expense`, `db_expense_category` — `store_id`, StoreScoped, soft-delete via `delete_bit`. **Contacts** `db_customers` (SoftDeletes + StoreScoped, [`DbCustomer.php`](app/Models/DbCustomer.php:12)), `db_suppliers` (SoftDeletes + StoreScoped, [`DbSupplier.php`](app/Models/DbSupplier.php:12)). `db_customers.customer_identity_id` (nullable, indexed) links to the shared identity ([`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:45)).

> **Correction to prior docs:** `db_customers.mobile` has **no** database-level per-store unique index — it is a plain index ([`create_db_customers_table.php`](database/migrations/2026_02_07_085626_create_db_customers_table.php:62)). Per-store mobile/email uniqueness is enforced **at validation time** via `Rule::unique(...)->where('store_id', $storeId)->whereNull('deleted_at')` ([`CustomerController.php`](app/Http/Controllers/CustomerController.php:141), [`…:644`](app/Http/Controllers/CustomerController.php:644)) and in the CSV importer ([`CustomerController.php`](app/Http/Controllers/CustomerController.php:1013)). Contrast with `db_suppliers`, which *does* carry `(store_id, mobile)` / `(store_id, email)` unique indexes (downgraded from global by [`2026_09_07_000002`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48)).

**Coupons** `db_coupons`, `db_customer_coupons` — store-scoped codes. **Tax / settings** `db_tax`, `db_units`, `db_paymenttypes`, `db_states` — `store_id` + StoreScoped. **Global reference tables (not store-scoped):** `db_country`, `db_currency`, `db_languages`.

**Messaging (SMS)** — all store-scoped: `db_smsapi`, `db_smstemplates`, `db_fivemojo`, `sms_campaigns`, `sms_logs`, `sms_auto_rules` (with `(store_id, event_type)` unique), `sms_blacklists`, `sms_daily_stats`.

**EMI** — `db_emi_sales` and `db_emi_schedule` ([`create_db_emi_tables.php`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:14), [`…:32`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:32)) **carry no `store_id` column**; the model is not StoreScoped ([`DbEmiSale.php`](app/Models/DbEmiSale.php:8)). Isolation is **transitive** through `sale_id` → `db_sales.store_id` (the FK cascades on delete, [`create_db_emi_tables.php`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:28)); `EmiReminderJob` reaches the store via `emiSale.sale.store` ([`EmiReminderJob.php`](app/Jobs/EmiReminderJob.php:24)). This closes the previous `[UNVERIFIED]` on this table.

**Other** `seo_meta`, `db_emailtemplates`, `db_shippingaddress`, `db_bankdetails`, `db_subscription`, `db_package`, payment-gateway tables (`db_paypal`, `db_stripe`, `db_instamojo`, `db_twilio`) — all migrated from the original schema dump; individual `store_id` presence per table was **not** individually re-verified this pass. `[UNVERIFIED]`

### A.3.3 Canonical stock rule (supersedes the old "dual source of truth" concern)

The previously-flagged "dual stock source of truth" is **resolved as a documented contract**, not by dropping the column:

- `DbItem::availableStock(?int $warehouseId = null)` is the single canonical read rule ([`DbItem.php`](app/Models/DbItem.php:114)): requested warehouse's `available_qty` when a row exists → otherwise `SUM(available_qty)` across the item's warehouses → otherwise `db_items.stock` (warehouse-less items). Uses the eager-loaded relation when present (no N+1).
- `DbItem::syncGlobalStock(int $itemId)` sets `db_items.stock = SUM(available_qty)` **only when warehouse rows exist**, never zeroing warehouse-less items ([`DbItem.php`](app/Models/DbItem.php:148)). `ItemController::syncGlobalStock()` delegates to it ([`ItemController.php`](app/Http/Controllers/ItemController.php:1227)).
- All read sites route through the canonical rule: POS checkout gate ([`PosController.php`](app/Http/Controllers/PosController.php:1349)), POS item search ([`PosController.php`](app/Http/Controllers/PosController.php:230)), Stock Adjustment search ([`StockAdjustmentController.php`](app/Http/Controllers/StockAdjustmentController.php:648)), Stock Transfer search ([`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:660)), items list/export ([`ItemController.php`](app/Http/Controllers/ItemController.php:111)), SMS low-stock decision + payload ([`SmsTriggerService.php`](app/SMS/Services/SmsTriggerService.php:59), [`…:244`](app/SMS/Services/SmsTriggerService.php:244)).
- Diagnostic command `items:compare-stock-sources` is read-only ([`CompareItemStockSources.php`](app/Console/Commands/CompareItemStockSources.php:18)).
- **`db_items.stock` is intentionally retained** and remains authoritative for warehouse-less items.

### A.3.4 Verified money & stock flows

**Flow 1 — POS Sale → Payment → Ledger → Stock** ([`PosController.php`](app/Http/Controllers/PosController.php:1329) is the availability gate). Warehouse must exist & be active, stock checked via `availableStock()`, serial validation, then per-line the warehouse row is decremented and `db_items.stock` moved by a symmetric delta. SMS observer fires on `DbSale` created ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:50)).

**Flow 2 — Purchase → Stock → Payment/Payable → Ledger** ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:272)). Route-level gate for the FormRequest-backed actions: `permission:purchase_add` / `permission:purchase_edit` ([`routes/web.php`](routes/web.php:192), [`routes/web.php`](routes/web.php:197)).

**Flow 3 — Stock Transfer (warehouse→warehouse, same store).** Decrements source and increments destination `db_warehouseitems`; reassigns serials' `warehouse_id`. Inter-**store** transfer is **out of scope**, and the dead `to_store_id` columns have been dropped ([`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:10)).

**Flow 4 — Money Transfer / Deposit → Ledger.** Paired debit/credit `AcTransaction` rows + reversal rows on edit/delete, gated on `money_transfer_*` / `money_deposit_*`.

**Flow 5 — Cash Drawer Reconciliation → Ledger adjustment.** Two-step open/close flow; on close, an elevated user with `cash_reconciliation_adjust` posts overage/shortage rows. 21+ assertions in [`CashDrawerReconciliationTest.php`](tests/Feature/CashDrawerReconciliationTest.php:1) (including strict "a different user cannot close a drawer they did not open").

**Flow 6 — Quotation → Sale conversion.** `QuotationController::convertToSale`; requires `quotation_edit` + `sales_add` ([`routes/web.php`](routes/web.php:183)).

**Flow 7 — Customer Advance.** Posts `AcTransaction` for advance and reversal; gated on `cust_adv_payments_*`.

**Flow 8 — SMS pipeline.** `DbSale` observer → `SmsTriggerService::trigger(...)` → `RuleResolverService` → `SmsService` resolves the provider per store from `db_store.sms_status` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)). **Per-store blacklist** ([`SmsService.php`](app/SMS/Services/SmsService.php:71)) and **per-store duplicate suppression** ([`SmsService.php`](app/SMS/Services/SmsService.php:84)) are enforced; `sms_logs` rows carry `store_id` ([`SmsService.php`](app/SMS/Services/SmsService.php:91)).

**Flow 9 — Shared customer identity (Phase 5).** `CustomerIdentityResolver::resolveOrCreate()` normalises the phone and find-or-creates the cross-store identity ([`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29), [`…:78`](app/Services/CustomerIdentityResolver.php:78)). Wired into customer create/update/quick-add/save-step/import ([`CustomerController.php`](app/Http/Controllers/CustomerController.php:158), [`…:279`](app/Http/Controllers/CustomerController.php:279), [`…:532`](app/Http/Controllers/CustomerController.php:532), [`…:699`](app/Http/Controllers/CustomerController.php:699), [`…:1058`](app/Http/Controllers/CustomerController.php:1058)). Dues/loyalty/history stay per store.

### A.3.5 Code generation & numbering

`CodeGeneratorService::generate(type, offset, storeId)` resolves a store id, then inside `DB::transaction` locks the `db_store` row (serialising even an empty target table) and delegates to `generateSequential()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:33), [`…:42`](app/Services/CodeGeneratorService.php:42), [`…:46`](app/Services/CodeGeneratorService.php:46), [`…:206`](app/Services/CodeGeneratorService.php:206)). Prefixes come from `store_settings()`.

**`purchase_return` now uses the sequential generator** (Phase 4.3) with prefix `purchase_return_init`/`PR`, matching `sales_return` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167)). The old `uniqid()` behaviour is gone.

### A.3.6 Migration hygiene

- **Duplicate migration timestamp:** two distinct migrations share `2026_09_13_000003` — [`2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:1) and [`2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php`](database/migrations/2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php:1). Laravel orders by filename, so both run, but the duplicate timestamp is a latent ordering hazard. See ISSUE-13.

---

## A.4 Roles, Permissions & Access Control

### Enforcement model

1. **Route middleware `permission:<slug>`** — [`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), alias registered in [`bootstrap/app.php`](bootstrap/app.php:16). Unauthenticated passes to `auth`; JSON gets a 403 payload, web gets `abort(403)` ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:37)). Applied to the whole `reports/*` group ([`routes/web.php`](routes/web.php:364)) and to the purchase store/update routes ([`routes/web.php`](routes/web.php:192), [`routes/web.php`](routes/web.php:197)).
2. **In-controller inline gates** — `if (auth()->check() && !auth()->user()->hasPermission('slug')) abort(403)` across the controller surface (e.g. [`ItemController.php`](app/Http/Controllers/ItemController.php:34), [`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:45)).
3. **FormRequest `authorize()`** for validation-backed writes (`StorePurchaseRequest`, `UpdatePurchaseRequest`).
4. **Policies** (`Gate::policy`) for Roles ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:47)); `RolePolicy`/`UserPolicy` under `app/Policies`.

### Identity model (Phase 2 — three roles)

| Role | Seed id | Cross-store? | Bypasses permission checks? | Evidence |
|---|---|---|---|---|
| **Branch Admin** (seeded as `Super Admin`, one row per store) | 1, 2, 3 | no | no | `is_super_admin = false` ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:69)) |
| **Owner** | 19 | **yes** (`is_owner = true`) | **no** | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:169), permissions limited to `dashboard_view`, `reports_view`, `store_settings_view` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:173)) |
| **Developer** (system/maintenance) | 20 | yes | **yes** (`is_super_admin = true`) | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:179), [`…:184`](database/seeders/RolePermissionSeeder.php:184) |

- `User::isSuperAdmin()` reads only `db_roles.is_super_admin` ([`User.php`](app/Models/User.php:122)); `User::isOwner()` reads only `db_roles.is_owner` ([`User.php`](app/Models/User.php:134)); `User::canViewAllStores()` = `isSuperAdmin() || isOwner()` ([`User.php`](app/Models/User.php:144)); `hasPermission()` short-circuits only for `isSuperAdmin()` ([`User.php`](app/Models/User.php:149)).
- Name-based super-admin seeding is **opt-in and off by default**, enabled only by the test bootstrap ([`DbRole.php`](app/Models/DbRole.php:49), [`DbRole.php`](app/Models/DbRole.php:71), [`tests/TestCase.php`](tests/TestCase.php:53)).
- `EnsureUserHasStore` bypasses for super admin ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:30)) and for the Owner ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:38)); regular users are validated for store existence and `status === 1` ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:43), [`…:56`](app/Http/Middleware/EnsureUserHasStore.php:56)).

**Net security verdict:** the branch-admin privilege escalation previously reported (ISSUE-8) is **fixed** — by migration ([`2026_09_13_000003`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:28)), by the seeder ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:75)), by RolePermissionSeeder ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:197)) and by passing tests ([`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:76), [`…:87`](tests/Feature/ThreeRoleModelTest.php:87), [`…:100`](tests/Feature/ThreeRoleModelTest.php:100), [`…:111`](tests/Feature/ThreeRoleModelTest.php:111)).

### Permission slug vocabulary

A single canonical vocabulary is in use (full list in [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:16)): `sales_view`/`sales_add`/…, a single coarse `reports_view` for the whole report group ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:71)), `items_print_labels`, `database_backup` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:74)), `sms_blacklist_*`, `multi_store_dashboard_view`. Prior UI-autoslugged values are reconciled by data migrations (e.g. [`2026_09_13_000002`](database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php:1), [`2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php`](database/migrations/2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php:35)).

`multi_store_dashboard_view` is excluded from the non-super-admin permission set ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:92)).

---

## A.5 Location / Warehouse Model

- **`db_store`** is the tenant/business unit: business identity + financial settings (GST/VAT/PAN, invoice prefixes, currency, decimals, timezone, SMTP credentials). Seeded prefixes are documented in [`StoreSeeder.php`](database/seeders/StoreSeeder.php:62).
- **`db_warehouse`** is a *stock bucket inside a store*: own `store_id`, name, status. No cash account, no tax number, no users of its own.
- Stock is materialised **only** as `db_warehouseitems (warehouse_id, item_id, available_qty)`; `db_items.stock` is a documented aggregate/fallback under the canonical rule in §A.3.3.
- **Acting-store resolution is now explicit** (§A.3.1): a branch admin is binary-bound to their own store; Owner/Developer may act as any store. The Owner's selector is **wired at the route and controller level only** — see ISSUE-14.
- **Store switcher UI: NOT present.** Content search across all Blade files for `store-context`, `store_context`, `Acting store`, `switch store` returned **0 results**. The sidebar only renders Consolidated Reports and Multi-Store links for `canViewAllStores()` ([`app.blade.php`](resources/views/layouts/app.blade.php:153)).
- Multi-store **dashboard** is gated on `canViewAllStores()` ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:29)) and aggregates every store by bypassing the scope via `DashboardController::computeStoreStats(int $storeId)` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:42), [`…:53`](app/Http/Controllers/DashboardController.php:53)).

**Summary:** "store" = **tenant** (separate data partition); "warehouse" = **stock bucket within a tenant**. Multiple stores coexist in seed data (Store 1 Dhaka / 2 Chittagong / 3 Sylhet — [`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)).

---

## A.6 Testing & Known Issues Register

### Test inventory & current status

- Framework: **Pest** on PHPUnit, with `Unit` + `Feature` suites ([`phpunit.xml`](phpunit.xml:7)). `tests/Pest.php` binds `Tests\TestCase` + `RefreshDatabase` to `Feature` ([`tests/Pest.php`](tests/Pest.php:14)).
- `tests/Unit` contains only `ExampleTest.php`; all real coverage is in `tests/Feature`.
- Two SQLite-specific test helpers: `node_binary()` resolves an absolute `node` path for `node --check` inline-JS tests ([`tests/Pest.php`](tests/Pest.php:52)); `skipUnlessSqlite()` marks the self-contained SQLite concurrency/migration harnesses as skipped on MySQL ([`tests/Pest.php`](tests/Pest.php:91)).
- `tests/TestCase.php` flushes the store-settings memoization between tests ([`tests/TestCase.php`](tests/TestCase.php:43)) and enables test-only name-based super-admin seeding ([`tests/TestCase.php`](tests/TestCase.php:53)).

**Executed result — SQLite (`php artisan test --parallel`), 2026-09-16:**

```
Tests:    1346 passed (6479 assertions)
Duration: 149.92s
Parallel: 12 processes
```

**Zero failures.** This is a material change from the previous audit, which recorded `15 failed, 1285 passed`. The 12 race tests and 3 `node --check` tests previously recorded as failing now pass (e.g. `ItemsListRedesignTest > items list page inline Alpine script passes node --check` reported PASS; `CategoryBrandVariantRaceTest` genuine-parallel cases PASS).

**Executed result — MySQL gate (`php vendor\bin\pest -c phpunit.mysql.xml`), 2026-09-16:**

```
Tests:    16 skipped, 1330 passed (6392 assertions)
Duration: 336.42s
```

Run against a disposable `laravelpos_test` database (never the dev DB). The 16 skips are the self-contained SQLite concurrency/migration harnesses, skipped by design via `skipUnlessSqlite()` ([`tests/Pest.php`](tests/Pest.php:91)); the count matches the 16 `skipUnlessSqlite()` call sites in test bodies exactly. **Zero failures on both drivers.**

### Known Issues register (rebuilt this pass)

| # | Issue | Evidence | Severity |
|---|---|---|---|
| ISSUE-1 | ~~Dual stock source of truth~~ — **RESOLVED** as a documented contract (`availableStock()` canonical rule; `db_items.stock` retained as authoritative for warehouse-less items). | [`DbItem.php`](app/Models/DbItem.php:114), [`DbItem.php`](app/Models/DbItem.php:148), [`ItemStockSourceOfTruthTest.php`](tests/Feature/ItemStockSourceOfTruthTest.php:1) | Closed |
| ISSUE-2 | ~~Invalid inline JS on items-list / transfer-create / adjustment-create~~ — **RESOLVED**. `node --check` tests now pass. | [`ItemsListRedesignTest.php`](tests/Feature/ItemsListRedesignTest.php:1) | Closed |
| ISSUE-3 | ~~Orphan view `import_services.blade.php`~~ — **RESOLVED**. No such file exists (content search: 0 results). The `import_services` slug is deliberately reserved and documented as such. | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:33) | Closed |
| ISSUE-4 | ~~Dead `to_store_id` columns~~ — **RESOLVED**. Dropped from both tables with index cleanup. | [`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27) | Closed |
| ISSUE-5 | **Store rows cascade-delete children** via FKs with `onDelete('cascade')` (`db_warehouse`/`db_warehouseitems`/`users`), and a mis-scoped delete could cascade real data. | [`db_warehouse` migration](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:32) | Low |
| ISSUE-6 | **Windows-hardcoded dump path** `C:/xampp/mysql/bin/` in DB config; provider tolerates `DUMP_BINARY_PATH` override. Non-portable. | [`config/database.php`](config/database.php:65), [`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:28) | Low |
| ISSUE-7 | **Public layout omits `app.js`** while the app layout includes it → Alpine directives on public pages depend on per-page scripts. | [`public.blade.php`](resources/views/layouts/public.blade.php:19) | Low |
| ISSUE-8 | ~~Three seeded per-store administrators globally privileged~~ — **RESOLVED**. `is_super_admin` cleared on all `Super Admin` role rows; the flag is reserved for the `Developer` role. | [`2026_09_13_000003`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:28), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:69), [`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:76) | Closed |
| ISSUE-9 | **The app layout loads no Font Awesome**, yet `app.js` builds button-spinner markup using `<i class="fas fa-circle-notch fa-spin">` — the spinner glyph may not render in the authenticated layout. | [`app.js`](resources/js/app.js:50) vs [`app.blade.php`](resources/views/layouts/app.blade.php:21) | Low |
| ISSUE-10 | ~~`purchase_return` codes non-sequential~~ — **RESOLVED** (Phase 4.3). Routed through `generateSequential()` with prefix `PR`. | [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167) | Closed |
| ISSUE-11 | **`activity_logs.store_id` is nullable** — intentional deviation so an audit write cannot break login. | [`ActivityLog.php`](app/Models/ActivityLog.php:10) | Info |
| ISSUE-12 | **`config('sms.*')` is read but no `config/sms.php` exists.** `SmsService` reads `config('sms.sandbox')` and `SendSingleSmsJob` reads `config("sms.throttle.{$provider}")`; with no config file both always use inline defaults. Any intended SMS sandbox/throttle tuning is therefore currently unreachable via config. | [`SmsService.php`](app/SMS/Services/SmsService.php:24), [`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:39); `config/` listing | Medium |
| ISSUE-13 | **Duplicate migration timestamp** `2026_09_13_000003` used by two different migrations. | [`…clear_is_super_admin…`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:1), [`…grant_database_backup…`](database/migrations/2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php:1) | Low |
| ISSUE-14 | **Owner store selector has no UI.** `StoreSelectorController` + `store.context.update`/`store.context.clear` routes exist and are gated correctly, but no Blade view posts to them (content search: 0 results), so the Owner cannot actually switch the acting store from the UI. | [`StoreSelectorController.php`](app/Http/Controllers/StoreSelectorController.php:21), [`routes/web.php`](routes/web.php:60) | Medium |
| ISSUE-15 | **`StoreSettingsController::resolveActingStore()` uses `auth()->user()->store_id` directly**, not `current_store_id()`. An Owner (nominal `store_id` only) therefore cannot edit a chosen branch's settings even after switching, and the Owner bypass in `EnsureUserHasStore` does not help here. | [`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:25) | Medium |
| ISSUE-16 | **EMI tables are not store-scoped at the column level.** `db_emi_sales`/`db_emi_schedule` have no `store_id`; isolation is transitive via `db_sales.store_id`. Correct today, but any direct EMI query that forgets the join loses store isolation. | [`create_db_emi_tables.php`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:14), [`DbEmiSale.php`](app/Models/DbEmiSale.php:8) | Low |

**No `TODO`/`FIXME`/`XXX`/`HACK` markers were found in `app/`** (content search across `app/**/*.php`: the single hit is the substring "Start" in a comment at [`ItemController.php`](app/Http/Controllers/ItemController.php:375), not a marker).

### Coverage acknowledgement

Views under `resources/views/module/**` (180 files), `layouts/**`, `components/**`, `legal/**`, `auth/**` are **enumerated by directory** but not read line-by-line. Their design-system conformance is covered by the feature tests listed in §A.6 (e.g. `LookupModulesRedesignTest`, `ItemsListRedesignTest`, `ReportPhase7RedesignTest`). Per-view JavaScript logic was **not** audited line-by-line — `[UNVERIFIED]` for those specific behaviours.

---

## A.7 Background Jobs, Scheduler & Integrations

- **Jobs:** [`DispatchCampaignJob`](app/Jobs/DispatchCampaignJob.php:1), [`EmiReminderJob`](app/Jobs/EmiReminderJob.php:15), [`SendSingleSmsJob`](app/Jobs/SendSingleSmsJob.php:14) (`tries = 3`, `backoff = [60, 300, 600]`, rate-limited per provider — [`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:18), [`…:19`](app/Jobs/SendSingleSmsJob.php:19), [`…:37`](app/Jobs/SendSingleSmsJob.php:37)), [`SmsHealthCheckJob`](app/Jobs/SmsHealthCheckJob.php:1).
- **Commands:** [`CompareItemStockSources`](app/Console/Commands/CompareItemStockSources.php:18) (`items:compare-stock-sources`, read-only diagnostic), [`MigrateCustomerEmiData`](app/Console/Commands/MigrateCustomerEmiData.php:1), [`ProcessScheduledSmsRules`](app/Console/Commands/ProcessScheduledSmsRules.php:20) (`sms:process-scheduled-rules`, selects `is_active` rules).
- **Schedule** ([`routes/console.php`](routes/console.php:12)):
  - `EmiReminderJob` — daily 09:00 ([`routes/console.php`](routes/console.php:12)); dispatches per-store SMS with the schedule's own store ([`EmiReminderJob.php`](app/Jobs/EmiReminderJob.php:48)).
  - `SmsHealthCheckJob` — every 15 minutes ([`routes/console.php`](routes/console.php:13)).
  - Scheduled-campaign dispatch — every minute, across all stores; each `DispatchCampaignJob` resolves the campaign's own `store_id` ([`routes/console.php`](routes/console.php:16), [`…:22`](routes/console.php:22)).
  - `sms:process-scheduled-rules` — daily 09:00 ([`routes/console.php`](routes/console.php:30)).
  - `backup:run` daily 00:00; `backup:clean` daily 01:00 ([`routes/console.php`](routes/console.php:33), [`…:34`](routes/console.php:34)).
- **SMS providers:** Alpha, BulkSmsBd, FiveMojo, Http, Sandbox, SslWireless under `app/SMS/Providers/`; selected by `db_store.sms_status` via a `match` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)). See ISSUE-12 for the missing `config/sms.php`.

---

## A.8 Seeders

`DatabaseSeeder` calls, in order ([`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php:27)): Currency, Language, Country, Store, AdminUser, RolePermission, **OwnerDeveloper**, SmsTemplate, SmsAutoRule, State, PaymentType, Unit, Tax, Brand, Category, Customer, EndToEndCoupon, Supplier, Warehouse, Item, SiteSettings, SeoMeta.

- Stores seeded: 1 Dhaka / 2 Chittagong / 3 Sylhet ([`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)).
- Three per-store **branch-admin** users created with `is_super_admin = false` ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:69), [`…:75`](database/seeders/AdminUserSeeder.php:75)).
- **`OwnerDeveloperSeeder`** creates `owner@corevisys.com` (Owner role) and `developer@corevisys.com` (Developer role), both with password `password` ([`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:39), [`…:57`](database/seeders/OwnerDeveloperSeeder.php:57)). Both use a *nominal* `store_id` only, because `users.store_id` is NOT NULL and the acting store is chosen at runtime ([`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:33), [`…:37`](database/seeders/OwnerDeveloperSeeder.php:37)).
- `DatabaseSeeder` also creates `test@example.com` via factory if absent ([`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php:20)).
- The **`Developer` role is the only role seeded with `is_super_admin = true`** ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:184)); every other seeded role (including the three branch-admin `Super Admin` rows) is seeded false ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:197)).
