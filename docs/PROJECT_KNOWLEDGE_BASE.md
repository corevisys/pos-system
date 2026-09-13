# PROJECT KNOWLEDGE BASE — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-13
**Method:** every statement below was read directly from source. File citations use `path:line`. Anything not fully verified is marked `[UNVERIFIED]`; inferences are marked `[INFERENCE]`. Prior audit history has been superseded and is no longer preserved in this document.

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
| Dev/test | `fakerphp/faker`, `laravel/breeze`, `laravel/pail`, `laravel/pint`, `laravel/sail`, `mockery`, `nunomaduro/collision`, `pestphp/pest` `^3.8`, `pest-plugin-laravel` `^3.2` | [`composer.json`](composer.json:19) |
| Autoloaded global helper | `app/Helpers/helpers.php` via `autoload.files` (also `require_once`d in provider) | [`composer.json`](composer.json:36), [`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:18) |
| Service providers | Only `AppServiceProvider` registered | [`bootstrap/providers.php`](bootstrap/providers.php:3) |

### Frontend

| Item | Value | Evidence |
|---|---|---|
| Build tool | Vite `^7.0.7` + `laravel-vite-plugin` `^2.0.0` | [`package.json`](package.json:20) |
| CSS framework | Tailwind CSS `4.1.18` (v4 CSS-first config) | [`package.json`](package.json:19) |
| Tailwind plugins | `@tailwindcss/forms`, `@tailwindcss/typography` | [`app.css`](resources/css/app.css:2), [`app.css`](resources/css/app.css:3) |
| JS interactivity | Alpine.js `^3.4.2` + `@alpinejs/collapse` `^3.17.0` | [`package.json`](package.json:13), [`package.json`](package.json:23) |
| HTTP client | axios `^1.11.0` | [`package.json`](package.json:15) |
| Charts | **Chart.js vendored locally** at `public/vendor/chart.umd.min.js` (+ `moment.min.js`) | [`public/vendor/chart.umd.min.js`](public/vendor/chart.umd.min.js:1), [`app.blade.php`](resources/views/layouts/app.blade.php:19), [`cash_flow.blade.php`](resources/views/module/reports/cash_flow.blade.php:656) |
| Fonts | Google Fonts: app = `Plus Jakarta Sans`; guest = `Hind Siliguri`,`Inter`; public = `Plus Jakarta Sans`,`Hind Siliguri`; welcome = `Hind Siliguri`,`Inter` | [`app.blade.php`](resources/views/layouts/app.blade.php:21), [`guest.blade.php`](resources/views/layouts/guest.blade.php:14), [`public.blade.php`](resources/views/layouts/public.blade.php:13), [`welcome.blade.php`](resources/views/welcome.blade.php:9) |
| Icon library | Font Awesome 6 loaded only in guest/public/welcome (cdnjs); the app layout uses inline SVG and loads no Font Awesome | [`guest.blade.php`](resources/views/layouts/guest.blade.php:17), [`public.blade.php`](resources/views/layouts/public.blade.php:16), [`welcome.blade.php`](resources/views/welcome.blade.php:8) |

**Asset-loading behaviour:**
- `layouts/app.blade.php` loads `@vite(['resources/css/app.css','resources/js/app.js'])` **and** `asset('vendor/chart.umd.min.js')` ([`app.blade.php`](resources/views/layouts/app.blade.php:17), [`…:19`](resources/views/layouts/app.blade.php:17)).
- `layouts/guest.blade.php` loads `@vite(['resources/css/app.css','resources/js/app.js'])` ([`guest.blade.php`](resources/views/layouts/guest.blade.php:20)).
- `layouts/public.blade.php` loads only `@vite('resources/css/app.css')` — **no `app.js`** ([`public.blade.php`](resources/views/layouts/public.blade.php:19)). Any Alpine directives on public pages therefore depend on a per-page script include (a potential silent bug source — see ISSUE-7).
- Compiled assets present: [`public/build/manifest.json`](public/build/manifest.json:1) → `app-EY2auKIM.css`, `app-vZIy2K19.js`.

### Database

- Default connection is `sqlite` unless `DB_CONNECTION` overrides; MySQL/MariaDB/PgSQL/SQLSrv connections are all defined ([`config/database.php`](config/database.php:19)).
- MySQL connection hardcodes `dump.dump_binary_path => 'C:/xampp/mysql/bin/'` ([`config/database.php`](config/database.php:65)).
- Test suite forces `sqlite` `:memory:` ([`phpunit.xml`](phpunit.xml:26)).

### App-specific config

- `config/sales.php` defines the single kill-switch `sales.enforce_total_validation` (env `ENFORCE_TOTAL_VALIDATION`, default `true`) that gates server-side grand-total recompute rejection ([`config/sales.php`](config/sales.php:25)).

---

## A.2 Design System

Full theme documentation is in [`docs/THEME_REFERENCE.md`](docs/THEME_REFERENCE.md). Summary:

- Tokens are declared in the Tailwind v4 `@theme` block ([`app.css`](resources/css/app.css:7)): brand primary (indigo `#4f46e5` + `--primary-rgb`), semantic surfaces, text, status, shadows, radius, dark-mode tokens, `--font-sans`.
- Dark mode uses a class variant `@custom-variant dark (&:where(.dark, .dark *))` ([`app.css`](resources/css/app.css:5)), toggled from `localStorage` in the layout ([`app.blade.php`](resources/views/layouts/app.blade.php:3)).
- Project utilities: `scrollbar-hide` ([`app.css`](resources/css/app.css:74)), `custom-scrollbar` ([`app.css`](resources/css/app.css:84)), `anime-fade-in` ([`app.css`](resources/css/app.css:105)), plus base `[x-cloak]{display:none!important}` ([`app.css`](resources/css/app.css:68)).
- Blade component inventory (27 files): `application-logo`, `auth-session-status`, `badge`, `card`, `danger-button`, `dropdown`, `dropdown-link`, `icon-button`, `input-error`, `input-label`, `keyboard-shortcuts`, `keyboard-shortcuts-modal`, `modal`, `nav-link`, `notification-toast`, `primary-button`, `report-export-buttons`, `responsive-nav-link`, `searchable-select`, `secondary-button`, `select`, `seo-meta`, `sidebar-tooltip`, `stat-card`, `table`, `text-input`, `textarea` — all under `resources/views/components/`. Per-component `@props` prop lists are read from each component's `@props` and were not exhaustively extracted; `[UNVERIFIED]` for the exact per-component API.
- Legacy / out-of-system markup: a large inline Tailwind utility vocabulary (`text-[10px]`, `tracking-widest`, `!important`-prefixed classes) is used directly across module views rather than via components (e.g. [`module/users/roles_list.blade.php`](resources/views/module/users/roles_list.blade.php:139)). This is legacy-by-convention, not a formal component system.
- Orphan view: [`module/items/import_services.blade.php`](resources/views/module/items/import_services.blade.php:1) exists but its route was removed (see ISSUE-3).

---

## A.3 Data & Financial Architecture

### A.3.1 Store-scoping mechanism (foundational)

1. **Eloquent global scope trait** `App\Models\Traits\StoreScoped` — adds a global scope on `store_id` that filters strictly to `current_store_id()` when a user is authenticated; there is **no NULL escape hatch** ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:9), [`…:23`](app/Models/Traits/StoreScoped.php:9)). It exposes `scopeAllStores()` = `withoutGlobalScope('store_id')` ([`…:28`](app/Models/Traits/StoreScoped.php:28)).
2. **Helper functions** in [`app/Helpers/helpers.php`](app/Helpers/helpers.php:1):
   - `current_store_id()` → authenticated user's `store_id`, else `default_store_id()` ([`helpers.php`](app/Helpers/helpers.php:152)).
   - `default_store_id()` → memoized first `db_store` row, else `1` ([`helpers.php`](app/Helpers/helpers.php:24)).
   - `store_settings()` → memoized `DbStore` row keyed per store id ([`helpers.php`](app/Helpers/helpers.php:77)).
   - `store_id_cache_key()` → per-store cache key `s{id}` or `default` ([`helpers.php`](app/Helpers/helpers.php:51)).
   - `flush_store_settings_cache()` → drops process-lifetime memoization between tests ([`helpers.php`](app/Helpers/helpers.php:134)).

**Role relation isolation:** `User::role()` and `DbRole::permissions()` explicitly call `withoutGlobalScope('store_id')`, because privilege resolution must not be filtered by the acting store (otherwise it becomes circular) ([`User.php`](app/Models/User.php:81), [`DbRole.php`](app/Models/DbRole.php:81)).

### A.3.2 Table inventory by domain

Tables are prefixed `db_`, `ac_`, `sms_` or plain. All operational tables carry a `store_id`.

**Store & identity**
| Table | Key columns | store_id? | StoreScoped? | Uniqueness |
|---|---|---|---|---|
| `db_store` | `store_code`, `store_name`, `gst_no`, `vat_no`, `pan_no`, all `*_init` prefixes, `currency_id`, `language_id`, `timezone`, `smtp_*`, `decimals`, `qty_decimals` | is the store | n/a ([`DbStore.php`](app/Models/DbStore.php:8)) | — |
| `users` | `store_id`, `role_id`, `role_name`, `username`, `email` | yes | no trait ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)) | `email` global unique |
| `db_roles` | `store_id`, `role_name`, `description`, `status`, `is_super_admin` | yes | StoreScoped ([`DbRole.php`](app/Models/DbRole.php:10)) | `(store_id, role_name)` unique `uq_db_roles_store_role_name` ([`2026_09_12_000003`](database/migrations/2026_09_12_000003_make_role_name_unique_per_store.php:28)) |
| `db_permissions` | `store_id`, `role_id`, `permissions` (JSON array) | yes | no trait ([`DbPermission.php`](app/Models/DbPermission.php:17)) | — |
| `activity_logs` | `store_id` (nullable), `user_id` (nullable), `action`, `entity_type`/`entity_id`, `old_values`/`new_values` JSON, `ip_address`, `user_agent`, `created_at` only | nullable | no trait ([`ActivityLog.php`](app/Models/ActivityLog.php:10)) | indexes on `(store_id,created_at)`, `user_id`, `action`; no hard FKs ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:31)) |

**Items / stock**
| Table | store_id? | Traits | Uniqueness |
|---|---|---|---|
| `db_items` | yes | StoreScoped ([`DbItem.php`](app/Models/DbItem.php:10)) | `(store_id, item_code)` composite `uq_db_items_store_item_code` (global unique dropped) ([`2026_09_11_000003`](database/migrations/2026_09_11_000003_replace_global_unique_item_code_with_composite.php:43)); per-store unique SKU/barcode ([`2026_09_11_000006`](database/migrations/2026_09_11_000006_add_per_store_unique_sku_barcode_to_db_items_table.php:1)) |
| `db_item_serials` | yes | StoreScoped ([`DbItemSerial.php`](app/Models/DbItemSerial.php:10)) | `(item_id, serial_number)` `uq_db_item_serials_item_serial` |
| `db_category`, `db_brands`, `db_variants` | yes | StoreScoped | per-store `(store_id, name)`/`(store_id, code)` ([`…per_store`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44)) |
| `db_warehouse` | yes | StoreScoped ([`DbWarehouse.php`](app/Models/DbWarehouse.php:11)) | `(store_id, warehouse_name)` ([`2026_09_10_000002`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:51)); `delete_bit` ([`2026_09_10_000001`](database/migrations/2026_09_10_000001_add_delete_bit_to_db_warehouse_table.php:1)) |
| `db_warehouseitems` | yes | StoreScoped ([`DbWarehouseItem.php`](app/Models/DbWarehouseItem.php:11)) | `(warehouse_id, item_id)` `uq_warehouse_item` ([`2026_08_29_164955`](database/migrations/2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41)) |
| `db_stockadjustment`, `db_stockadjustmentitems` | yes | StoreScoped | — |
| `db_stocktransfer`, `db_stocktransferitems` | yes (+ dormant `to_store_id`) | StoreScoped ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:16)) | `delete_bit` ([`2026_09_09_000001`](database/migrations/2026_09_09_000001_add_delete_bit_to_db_stocktransfer_table.php:1)) |

**Sales**
`db_sales`, `db_salesitems`, `db_salespayments`, `db_salesreturn`, `db_salesitemsreturn`, `db_salespaymentsreturn`, `db_hold`, `db_holditems`, `db_quotation`, `db_quotationitems` — all with `store_id`, all StoreScoped. `db_sales` carries `warehouse_id`, `sales_code`, `reference_no`, `coupon_id`, `coupon_amt`, `customer_previous_due`, `customer_total_due` ([`db_sales` migration](database/migrations/2026_02_07_090938_create_db_sales_table.php:15)). `db_sales`/`db_quotation` have `(store_id, code)` composite uniques via [`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76).

**Purchases**
`db_purchase`, `db_purchaseitems`, `db_purchasepayments`, `db_purchasereturn`, `db_purchaseitemsreturn`, `db_purchasepaymentsreturn` — all `store_id`, all StoreScoped, `(store_id, code)` composite uniques ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76)).

**Accounts / Ledger**
| Table | Key columns | store_id? | Uniqueness |
|---|---|---|---|
| `ac_accounts` | `account_code`, `system_key`, `parent_id`, `balance`, `is_system` | yes ([`AcAccount.php`](app/Models/AcAccount.php:11)) | `(store_id, account_code)`, `(store_id, system_key)` ([`2026_09_03_000001`](database/migrations/2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table.php:36), [`2026_09_03_000005`](database/migrations/2026_09_03_000005_add_system_account_fields_to_ac_accounts_table.php:1)) |
| `ac_transactions` | `debit_account_id`, `credit_account_id`, `debit_amt`, `credit_amt`, `ref_*` FK-ish columns for sales/purchase/expense/transfer/deposit | yes ([`AcTransaction.php`](app/Models/AcTransaction.php:11)) | supplier index ([`2026_09_07_000003`](database/migrations/2026_09_07_000003_add_supplier_index_to_ac_transactions_table.php:1)) |
| `ac_moneytransfer` | `transfer_code`, `delete_bit` | yes | `(store_id, transfer_code)` ([`2026_09_03_000002`](database/migrations/2026_09_03_000002_add_delete_bit_and_unique_code_to_ac_moneytransfer_table.php:1)) |
| `ac_moneydeposits` | `deposit_date`, `delete_bit` | yes | — ([`2026_09_03_000003`](database/migrations/2026_09_03_000003_add_delete_bit_to_ac_moneydeposits_table.php:1)) |
| `cash_drawer_reconciliations` | `reconciliation_code`, `warehouse_id`, `account_id`, `status`, `delete_bit` | yes | `(store_id, reconciliation_code)` composite (global unique dropped) ([`2026_09_11_000004`](database/migrations/2026_09_11_000004_replace_global_unique_reconciliation_code_with_composite.php:1)) |

**Expenses** `db_expense`, `db_expense_category` — `store_id`, StoreScoped, soft-delete via `delete_bit`.

**Contacts** `db_customers` (SoftDeletes + StoreScoped), `db_suppliers` (SoftDeletes + StoreScoped, unique `(store_id, mobile)` / `(store_id, email)` — [`2026_09_07_000002`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48)). `(store_id, customer_code)`/`(store_id, supplier_code)` composite uniques ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76)).

**Coupons** `db_coupons`, `db_customer_coupons` — codes are network-wide unique (`unique(code)`) ([`2026_09_11_000005`](database/migrations/2026_09_11_000005_add_unique_code_to_coupon_tables.php:39)).

**Tax / settings** `db_tax`, `db_units`, `db_paymenttypes`, `db_states` — `store_id` + StoreScoped. **Global reference tables:** `db_country`, `db_currency`, `db_languages`.

**Messaging (SMS)** — all store-scoped:
| Table | store_id? | Evidence |
|---|---|---|
| `db_smsapi` | yes | [`DbSmsapi.php`](app/Models/DbSmsapi.php:10) |
| `db_smstemplates` | yes | [`DbSmsTemplate.php`](app/Models/DbSmsTemplate.php:12) |
| `db_fivemojo` | yes (column) — model does not use StoreScoped | [`FiveMojoSMSProvider.php`](app/SMS/Providers/FiveMojoSMSProvider.php:18) |
| `sms_campaigns` | yes, NOT NULL | [`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:50) |
| `sms_logs` | yes, NOT NULL | [`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:50) |
| `sms_auto_rules` | yes, NOT NULL | [`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:50); unique `(store_id, event_type)` ([`2026_09_13_000001`](database/migrations/2026_09_13_000001_make_event_type_unique_per_store_on_sms_auto_rules.php:62)) |
| `sms_blacklists` | yes, NOT NULL | unique `(store_id, phone)` ([`2026_09_12_000005`](database/migrations/2026_09_12_000005_add_store_id_to_sms_blacklists_and_daily_stats.php:45)) |
| `sms_daily_stats` | yes, NOT NULL | unique `(store_id, date)` ([`2026_09_12_000005`](database/migrations/2026_09_12_000005_add_store_id_to_sms_blacklists_and_daily_stats.php:73)) |

**EMI** `db_emi_sale`, `db_emi_schedule` (from `2026_02_18_052015_create_db_emi_tables.php`) — `[UNVERIFIED]` whether they carry `store_id`; the migration was not read in full.

**Other** `seo_meta` (`page_key` unique), `db_emailtemplates`, `db_shippingaddress`, `db_bankdetails`, `db_subscription`, `db_package`, and payment-gateway tables (`db_paypal`, `db_stripe`, `db_instamojo`, `db_twilio`) all carry a `store_id` column.

### A.3.3 Verified money & stock flows

**Flow 1 — POS Sale → Payment → Ledger → Stock** (`PosController::store`).
1. Gated on `sales_add` ([`PosController.php`](app/Http/Controllers/PosController.php:70)).
2. Warehouse must exist & be active; warehouse stock availability checked, serial validation, then `DbSale` created with `store_id`, `warehouse_id`, `sales_code`.
3. Per-line: `db_warehouseitems.available_qty` decremented; `db_items.stock` also decremented ([`PosController.php`](app/Http/Controllers/PosController.php:494)).
4. Per payment with `account_id`: `AcTransaction::create([...'store_id'=>$sale->store_id...])`.
5. Serial checkout locks via `allStores()->lockForUpdate()` ([`PosController.php`](app/Http/Controllers/PosController.php:1440)).
6. SMS observer fires on `DbSale` created ([`SMSObserver.php`](app/Observers/SMSObserver.php:13)).

**Flow 2 — Purchase → Stock → Payment/Payable → Ledger** (`PurchaseController`).
- Stock up: `DbItem::increment('stock')` **and** `db_warehouseitems` upsert ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:272)) — dual write (ISSUE-1).
- Payments and payable both post `AcTransaction` rows; returns reverse stock and post reversal rows ([`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:1159)).

**Flow 3 — Stock Transfer (warehouse→warehouse, same store).** Decrements source and increments destination `db_warehouseitems`; reassigns serials' `warehouse_id`. The legacy `to_store_id` column is present in the model fillable but is **never written** by any controller — inter-**store** transfer is not implemented (ISSUE-4) ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17)).

**Flow 4 — Money Transfer / Deposit → Ledger.** `TransferController` writes paired debit/credit `AcTransaction` rows and reversal rows on edit/delete; gated on `money_transfer_*` ([`TransferController.php`](app/Http/Controllers/TransferController.php:21)).

**Flow 5 — Cash Drawer Reconciliation → Ledger adjustment.** Open/close flow computes expected cash and, if variance posted and the user has `cash_reconciliation_adjust`, writes overage/shortage `AcTransaction` rows ([`CashReconciliationController.php`](app/Http/Controllers/CashReconciliationController.php:361)).

**Flow 6 — Quotation → Sale conversion.** `QuotationController::convertToSale` creates a `DbSale` from quotation items; requires both `quotation_edit` and `sales_add` ([`QuotationController.php`](app/Http/Controllers/QuotationController.php:573)).

**Flow 7 — Customer Advance.** `AdvanceController` posts `AcTransaction` for the advance and for reversals; gated on `cust_adv_payments_*` ([`AdvanceController.php`](app/Http/Controllers/AdvanceController.php:21)).

**Flow 8 — SMS pipeline.** `DbSale` observer → `SmsTriggerService::trigger('InvoiceCreated', sale)` → `RuleResolverService` (cache key includes store) → `SmsService` resolves the provider per `store_id` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)); per-store blacklist and duplicate suppression are enforced ([`SmsService.php`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:71)).

### A.3.4 Code generation & numbering

`CodeGeneratorService::generate(type, offset, storeId)` resolves a store id, then inside `DB::transaction` locks the `db_store` row (to serialise even an empty target table) and delegates to `generateSequential()`, which locks the latest row per `store_id` and retries on collision ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:42), [`…:207`](app/Services/CodeGeneratorService.php:207)). Prefixes come from `store_settings()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:40)). Unique-constraint violations are retried via `executeWithRetry()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:253)). Sales-return is sequential. **Exception:** `purchase_return` still uses `uniqid()` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170)) — see ISSUE-10.

---

## A.4 Roles, Permissions & Access Control

### Enforcement model

Authorization is enforced server-side through four complementary mechanisms:

1. **Route middleware `permission:<slug>`** — [`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), registered as alias `permission` ([`bootstrap/app.php`](bootstrap/app.php:16)). Applied to the whole `reports/*` group with `permission:reports_view` ([`routes/web.php`](routes/web.php:351)) and to the purchase store/update routes ([`routes/web.php`](routes/web.php:179), [`…:184`](routes/web.php:179)). Unauthenticated requests pass to `auth`; JSON requests receive a 403 JSON payload and web requests `abort(403)` ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:37)).
2. **In-controller inline gates** — `if (auth()->check() && !auth()->user()->hasPermission('slug')) abort(403)`, present across the controller surface: POS, Sale, SalesReturn, Purchase, Quotation, Customer, Supplier, Coupon, CustomerCoupon, Advance, Service, Item, the Report group, Stock*, Cash*, Account, Transfer, Deposit, Transaction, Expense*, Tax, Unit, PaymentType, Warehouse, Brand, Category, Variant, StoreSettings, SmtpSettings, Backup, SMS*, MessageTemplate, Language, Country, State, Currency.
3. **FormRequest `authorize()`** for validation-backed writes — [`StorePurchaseRequest.php`](app/Http/Requests/StorePurchaseRequest.php:21), [`UpdatePurchaseRequest.php`](app/Http/Requests/UpdatePurchaseRequest.php:19).
4. **Policies** (`Gate::authorize`) for Users/Roles, aligned to the `*_edit`/`*_delete` slugs — [`UserPolicy.php`](app/Policies/UserPolicy.php:46), [`RolePolicy.php`](app/Policies/RolePolicy.php:43).

### Super-admin model

`User::isSuperAdmin()` reads only the explicit **`db_roles.is_super_admin`** boolean ([`User.php`](app/Models/User.php:121)); it is not derived from a role name or a role id. The column was added with a one-time backfill ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:31)). `DbRole` exposes the flag as fillable/cast, and name-based super-admin seeding is opt-in and disabled by default in application code ([`DbRole.php`](app/Models/DbRole.php:43)); the seeders set the flag explicitly ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:160), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68), [`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:23)). Because the backfill matched `role_name = 'Super Admin'` **or** `id = 1`, all three seeded per-store administrators are globally privileged ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36)) — see ISSUE-8.

### Permission slug vocabulary

A single canonical vocabulary is in use. `sales_view`/`sales_add`/… (not `sales_include_pos_*`), a single coarse `reports_view` gate for the whole report group, `items_print_labels` (not `print_labels`), `database_backup`, `sms_blacklist_*`, and `multi_store_dashboard_view`. Prior UI-autoslugged values are reconciled by data migrations ([`2026_09_12_000001`](database/migrations/2026_09_12_000001_reconcile_permission_slugs_reports_view_and_sales.php:44), [`2026_09_13_000002`](database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php:28)) and the seeders grant the canonical slugs ([`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:82), [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:71)).

### Defined roles (seeder-authoritative)

`RolePermissionSeeder` defines Super Admin, Admin, Manager, Salesman and Cashier with hardcoded permission arrays ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:96)); all roles are created with `store_id => 1` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:158)) and only Super Admin carries `is_super_admin` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:160)). `PermissionSeeder` seeds a single Super Admin role + the full permission list ([`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:27)). `AdminUserSeeder` creates three per-store admin users/roles ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:18)). `multi_store_dashboard_view` is excluded from every non-super-admin role ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:92)).

**Net security verdict:** the transactional surface is server-side gated. The remaining authorization risk is that the three per-store administrators are globally privileged (ISSUE-8).

---

## A.5 Location / Warehouse Model

- **`db_store`** is the tenant/business unit. It holds business identity and financial settings (GST/VAT/PAN, invoice prefixes, currency, decimals, timezone, and SMTP credentials which are `encrypted` at the model layer) ([`DbStore.php`](app/Models/DbStore.php:22), [`db_store migration`](database/migrations/2026_02_07_091820_create_db_store_table.php:14)).
- **`db_warehouse`** is a *stock bucket inside a store*: it has its own `store_id`, `warehouse_type` (`'System'` in the seeder), name, mobile/email, status ([`db_warehouse migration`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:15)). It has no cash account, no tax number, no address, and no users of its own.
- Stock is materialised **only** as `db_warehouseitems (warehouse_id, item_id, available_qty)`. `db_items.stock` is a legacy denormalised aggregate that is still incremented/decremented in several flows alongside the warehouse row — a dual-write consistency hazard ([`PosController.php`](app/Http/Controllers/PosController.php:494), [`ItemController.php`](app/Http/Controllers/ItemController.php:1233)) — see ISSUE-1.
- Requests resolve the store via the authenticated user's `store_id` (`current_store_id()`), with a CLI/default fallback to the first `db_store` row or `1` ([`helpers.php`](app/Helpers/helpers.php:152)). `EnsureUserHasStore` validates that the store exists and is active ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:35)).
- **There is no store-switcher UI, no session-persisted "acting store", and no `X-Store-Id` handling.** A user is hard-bound to exactly one store; the schema comment notes "there is no store-switching feature in this application" ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:16)).
- A Super-Admin multi-store **dashboard** exists and aggregates every store by explicitly bypassing the scope (`withoutGlobalScope('store_id')`) via `DashboardController::computeStoreStats(int $storeId)` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53), [`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:37)). It is double-gated (must be Super Admin **and** hold `multi_store_dashboard_view`) ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:26)) and is read-only aggregation, not a store switcher.

**Summary:** "store" means a **tenant** (separate data partition) and "warehouse" means a **stock bucket within a tenant**. Multiple stores already coexist in the schema and seed data (Store 1 Dhaka, Store 2 Chittagong, Store 3 Sylhet — [`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)), but operational routing is single-store-per-user.

---

## A.6 Testing & Known Issues Register

### Test inventory & current status

- Framework: **Pest** on top of PHPUnit, configured with `Unit` and `Feature` suites ([`phpunit.xml`](phpunit.xml:7)).
- `tests/Unit` contains only `ExampleTest.php`. All real coverage is in `tests/Feature` (~150 files) plus `tests/Feature/Auth` (6 files).
- **Executed result (`php artisan test`):** `Tests: 15 failed, 1285 passed (6283 assertions)`, duration 158.73s.

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

`[INFERENCE]` The 12 race tests spawn parallel PHP workers against a file-backed SQLite DB; the common `expected 1, got 0` / empty-output pattern suggests the worker harness (SQLite write-locking under the test runner) is the cause rather than the production code — confirm by re-running against MySQL before treating them as real defects. The 3 `node --check` failures are deterministic and indicate genuinely invalid inline JS in the items-list, transfer-create, and adjustment-create views (ISSUE-2).

### Known Issues register

| # | Issue | Evidence | Severity |
|---|---|---|---|
| ISSUE-1 | **Dual stock source of truth:** `db_items.stock` and `db_warehouseitems.available_qty` are both written across sale/purchase/return/adjustment/quote/item flows. | [`PosController.php`](app/Http/Controllers/PosController.php:494), [`PurchaseController.php`](app/Http/Controllers/PurchaseController.php:272), [`ItemController.php`](app/Http/Controllers/ItemController.php:1233) | Medium |
| ISSUE-2 | **Invalid inline JS** on the items-list, stock-transfer-create and stock-adjustment-create pages (deterministic `node --check` failures). | `ItemsListRedesignTest`, `StockCreateFormRedesignBrowserCheckTest` | Medium |
| ISSUE-3 | **Orphan view** `module/items/import_services.blade.php` with its route removed; the `import_services` slug is reserved-but-unused. | [`import_services.blade.php`](resources/views/module/items/import_services.blade.php:1), [`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:44) | Low |
| ISSUE-4 | **Dormant column** `db_stocktransfer.to_store_id` / `db_stocktransferitems.to_store_id` present in fillable but never written by any controller (no inter-store transfer). | [`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17), [`DbStockTransferItems.php`](app/Models/DbStockTransferItems.php:18) | Low |
| ISSUE-5 | **Store rows cascade-delete children** via FKs with `onDelete('cascade')` (`db_warehouse`/`db_warehouseitems`/`users`) — a mis-scoped delete could cascade real data. | [`db_warehouse migration`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:32) | Low |
| ISSUE-6 | **Windows-hardcoded dump path** `C:/xampp/mysql/bin/` in DB config plus `DUMP_BINARY_PATH` in the provider — non-portable. | [`config/database.php`](config/database.php:65), [`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:21) | Low |
| ISSUE-7 | **Public layout omits `app.js`** while app/guest layouts include it → Alpine-dependent public pages may silently not initialise. | [`public.blade.php`](resources/views/layouts/public.blade.php:19) | Low |
| ISSUE-8 | **Three seeded per-store administrators are globally privileged** (`is_super_admin = true` from the one-time backfill), so they bypass `EnsureUserHasStore` and every permission gate. | [`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68) | High |
| ISSUE-9 | **The app layout loads no Font Awesome**, yet `app.js` builds button-spinner markup using `<i class="fas fa-circle-notch fa-spin">` — the spinner icon may not render in the authenticated layout. | [`app.js`](resources/js/app.js:50) vs [`app.blade.php`](resources/views/layouts/app.blade.php:1) | Low |
| ISSUE-10 | **`purchase_return` codes remain non-sequential** (`uniqid()`), unlike every other entity. | [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170) | Low |
| ISSUE-11 | **`activity_logs.store_id` is nullable** — an intentional deviation from the NOT-NULL store pattern so an audit write cannot break login. | [`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:33), [`ActivityLog.php`](app/Models/ActivityLog.php:10) | Info |

No `TODO`/`FIXME`/`XXX` markers were found in `app/`.

### Coverage acknowledgement

Views under `resources/views/module/**` (≈150 files), `layouts/**`, `components/**`, `legal/**`, `auth/**`, `docs/**` are enumerated by directory; each module group is represented in A.2/A.3. Individual per-view JavaScript logic was **not** audited line-by-line — marked `[UNVERIFIED]` for those specific behaviours.

**Controller index (≈65):** Account, Advance, Backup, Brand, CashReconciliation, Category, Controller(base), Country, Coupon, Currency, Customer, CustomerCoupon, Dashboard, Deposit, Docs, ExpenseCategory, Expense, GlobalSearch, Item, Language, Legal, MessageTemplate, MultiStoreDashboard, PaymentType, Pos, Profile, Purchase, Quotation, Report, Role, Sale, SaleInvoice, SalesReturn, SerialHistory, Service, Sitemap, SmsAutoRule, SmsBlacklist, SmsCampaign, SmsHistory, SmsLog, SmsSend, SmsSettings, SmsTemplate, SmtpSettings, State, StockAdjustment, StockTransfer, StoreSettings, Supplier, Tax, Transaction, Transfer, Unit, User, Variant, Warehouse, Auth/* (8), Concerns/{ImportsItems, ExportsReportData, ValidatesReportFilters}.

**Model index (≈67):** AcAccount, AcMoneyDeposit, AcMoneyTransfer, AcTransaction, ActivityLog, CashDrawerReconciliation, CustomerGuarantor, CustomerGuardian, DbBrand, DbCategory, DbCountry, DbCoupon, DbCurrency, DbCustAdvance, DbCustomer, DbCustomerCoupon, DbEmiSale, DbEmiSchedule, DbExpense, DbExpenseCategory, DbFivemojo, DbHold, DbHoldItem, DbItem, DbItemSerial, DbLanguage, DbPaymentType, DbPermission, DbPurchase, DbPurchaseItem, DbPurchaseItemReturn, DbPurchasePayment, DbPurchasePaymentReturn, DbPurchaseReturn, DbQuotation, DbQuotationItem, DbRole, DbSale, DbSaleItem, DbSalePayment, DbSalesItemReturn, DbSalesPaymentReturn, DbSalesReturn, DbSmsapi, DbSmsTemplate, DbState, DbStockAdjustment, DbStockAdjustmentItems, DbStockTransfer, DbStockTransferItems, DbStore, DbSupplier, DbSmtp, DbTax, DbUnit, DbVariant, DbWarehouse, DbWarehouseItem, SeoMeta, SmsAutoRule, SmsBlacklist, SmsCampaign, SmsDailyStat, SmsLog, User, Traits/StoreScoped.

---

## A.7 Background Jobs, Scheduler & Integrations

- Jobs: [`DispatchCampaignJob`](app/Jobs/DispatchCampaignJob.php:1), [`EmiReminderJob`](app/Jobs/EmiReminderJob.php:1), [`SendSingleSmsJob`](app/Jobs/SendSingleSmsJob.php:1) (tries=3, backoff 60/300/600, rate-limited per provider — [`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:37)), [`SmsHealthCheckJob`](app/Jobs/SmsHealthCheckJob.php:1).
- Commands: [`MigrateCustomerEmiData`](app/Console/Commands/MigrateCustomerEmiData.php:1), [`ProcessScheduledSmsRules`](app/Console/Commands/ProcessScheduledSmsRules.php:1) (`sms:process-scheduled-rules`).
- Schedule ([`routes/console.php`](routes/console.php:12)): `EmiReminderJob` daily 09:00; `SmsHealthCheckJob` every 15 minutes; scheduled-campaign dispatch every minute across all stores (each job resolves its own store) [`…:25`](routes/console.php:16); `sms:process-scheduled-rules` daily 09:00; `backup:run` daily 00:00; `backup:clean` daily 01:00.
- SMS providers: Alpha, BulkSmsBd, FiveMojo, Http, Sandbox, SslWireless ([`app/SMS/Providers/`](app/SMS/Providers/SslWirelessProvider.php:1)); selected by `db_store.sms_status` ([`SmsService.php`](app/SMS/Services/SmsService.php:34)); sandbox override via `config('sms.sandbox')` ([`SmsService.php`](app/SMS/Services/SmsService.php:24)).

---

## A.8 Seeders

`DatabaseSeeder` calls, in order: Currency, Language, Country, Store, AdminUser, RolePermission, SmsTemplate, SmsAutoRule, State, PaymentType, Unit, Tax, Brand, Category, Customer, EndToEndCoupon, Supplier, Warehouse, Item, SiteSettings, SeoMeta ([`DatabaseSeeder.php`](database/seeders/DatabaseSeeder.php:27)). Stores seeded: Store 1 Dhaka / Store 2 Chittagong / Store 3 Sylhet ([`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)). Three per-store admin users are created, each with an `is_super_admin` role ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68)).
