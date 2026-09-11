# MULTI-STORE GAP ANALYSIS — Corevisys POS (LaravelPOS)

**Document date:** 2026-09-11
**Companion document:** [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (current-state documentation)
**Purpose:** define exactly what exists today versus what is required to safely operate multiple independent stores/branches, precise enough to implement from directly.

---

## Definition of "multi-store" assumed here

**Assumption:** multiple *independent business units* (branches/outlets), each with its own stock, cash/bank accounts, ledger, invoice numbering, tax registration, branding, settings, and reports — but potentially **shared** master data (item catalogue and/or customer list) and a **consolidated HQ view** for ownership.

**Open question for the business owner:** the codebase does **not** make this unambiguous. Today each `db_store` row is effectively a **hard tenant** — inventory, customers, suppliers, items and ledger all carry `store_id`, and the `StoreScoped` global scope filters them per store ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:9)). Items are *not* shared (each store has its own `db_items` rows). So the current shape already leans toward **fully independent stores**, not shared-catalogue branches. If the business wants a shared item catalogue with per-store pricing/stock, that is a **different and larger** design than what exists. This must be decided before building (see Open Questions).

---

## Executive Summary — verdict

**This codebase is closer to multi-store-ready than a typical single-store Laravel POS, but it needs foundational rework before it is *safe*.**

**What already works in its favour:**
- A real tenant model exists: `db_store`, per-store user binding, and a `StoreScoped` global scope on ~46 models ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:11); full list in Deliverable A §A.3.2).
- 57 store-scoped tables have been migrated to `NOT NULL store_id` with a data-safety guard ([`add_not_null_store_id…`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21)).
- Per-store uniqueness has already been retrofitted for the riskiest master tables — categories, brands, variants, suppliers, warehouses, and ledger accounts all have composite `(store_id, code/name)` uniques ([`…unique_per_store`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44), [`db_suppliers`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48), [`ac_accounts`](database/migrations/2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table.php:36)).
- A Super-Admin aggregated dashboard already proves cross-store read aggregation works ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:34)).
- Seed data already contains three stores (Dhaka/Chittagong/Sylhet) with per-store admins ([`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)).

**What blocks it:**
1. **Authorization is UI-only for the money-moving controllers.** POS, Sale, Purchase, Quotation, Returns, Reports and Customers/Suppliers have *no* server-side permission enforcement (Deliverable A §A.4, K1). No store-switching feature can be safely exposed while any authenticated user can hit any transactional route.
2. **`isSuperAdmin()` is a name match, not a scoped role.** Three different per-store admins are all "Super Admin" by string; they bypass the `EnsureUserHasStore` middleware and all permission gates ([`User.php`](app/Models/User.php:101), [`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:30)). This is both a correctness and a security problem for multi-store.
3. **No "current store" concept independent of the logged-in user.** `current_store_id()` is derived *only* from `auth()->user()->store_id` ([`helpers.php`](app/Helpers/helpers.php:152)); there is **no session store, no store-switcher, no `X-Store-Id` header handling.** A user can serve exactly one store.
4. **Invoice/SKU numbering is global, not per store.** `CodeGeneratorService` uses global `max('id')` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:35)); `db_items.item_code` and `cash_drawer_reconciliations.reconciliation_code` are globally unique ([`uq_db_items_item_code`](database/migrations/2026_08_29_174908_add_unique_item_code_to_db_items_table.php:49), [`reconciliations`](database/migrations/2026_08_24_000001_create_cash_drawer_reconciliations_table.php:16)).
5. **Messaging is half-scoped.** `sms_campaigns` has no `store_id`; `sms_blacklists.phone` and `sms_daily_stats.date` are globally unique ([`sms_campaigns`](database/migrations/2026_02_23_110001_create_sms_campaigns_table.php:14), [`sms_blacklists`](database/migrations/2026_02_23_110004_create_sms_blacklists_table.php:16)).

**Bottom line:** the *data model* is ~70% there; the *authorization + request-context + numbering* layers are ~10% there. Treat multi-store as **foundational rework**, not a feature toggle. The single biggest P0 is to make "acting store" an explicit, session/header-driven request context and to enforce access control server-side *before* exposing any store switcher.

---

## The 12 Gap Tables

Legend: **P0** blocker · **P1** important · **P2** nice-to-have.

### 1. Data model & scoping

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| 58 tables carry `store_id`; 57 forced `NOT NULL` by [`2026_09_11_000001`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21). `StoreScoped` trait on ~46 models ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:11)). | Every operational table has a non-null store identifier and every read path is scoped. | `sms_campaigns`, `sms_blacklists`, `sms_daily_stats` have **no** `store_id` ([`sms_campaigns`](database/migrations/2026_02_23_110001_create_sms_campaigns_table.php:14)); `db_country`, `db_currency`, `db_languages` are global by design. `db_items.stock` duplicates warehouse stock ([K6](docs/PROJECT_KNOWLEDGE_BASE.md)). | Cross-store SMS data bleed; inconsistent stock between `db_items.stock` and `db_warehouseitems`. | Add `store_id` to the three SMS tables + composite uniques; deprecate `db_items.stock` in favour of `db_warehouseitems`. |
| Global scope additionally matches `store_id IS NULL` ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:15)). | Null must never mean "visible everywhere". | Null-tolerant scope remains until the NOT-NULL migration runs; migration **aborts** (does not backfill) on any null ([`…:95`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:95)). | A single null row is globally readable by every store. | Remove the `orWhereNull` branch; run an explicit, reviewed backfill first. |

### 2. Uniqueness rules

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `db_items.item_code` is **globally** unique ([`uq_db_items_item_code`](database/migrations/2026_08_29_174908_add_unique_item_code_to_db_items_table.php:49)). | SKU/barcode unique **per store** (unless catalogue is shared — see Open Questions). | Global unique will reject Store B creating an item that Store A already has. | Store B cannot onboard items; import fails with obscure constraint errors. | Replace with `(store_id, item_code)` unique; decide barcode policy explicitly. |
| `cash_drawer_reconciliations.reconciliation_code` is **globally** unique ([`reconciliations`](database/migrations/2026_08_24_000001_create_cash_drawer_reconciliations_table.php:16)). | Per-store sequential reconciliation codes. | Global unique collides across concurrently closing stores. | Second store cannot close its drawer. | `(store_id, reconciliation_code)` unique. |
| Invoice numbers generated from **global** `max('id')` ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:35)). Returns use `date('YmdHis')`/`uniqid()` ([`…:91`](app/Services/CodeGeneratorService.php:91)). | Per-store monotonic sequencing. | Numbering ignores store; returns are non-deterministic. | Non-sequential, non-auditable invoice numbers; concurrent stores interleave. | Introduce a per-store sequence table (`store_id`, entity, last_number`) read under a row lock inside the existing DB transaction. |
| Master data already per-store unique: category/brand/variant ([`…per_store`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44)), suppliers ([`…`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48)), warehouses ([`…`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:51)), accounts ([`…`](database/migrations/2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table.php:36)). | Same policy everywhere. | Coupon codes: `db_coupons.code` has only a plain index ([`db_coupons`](database/migrations/2026_02_07_085618_create_db_coupons_table.php:32)) — no unique at all (global or per-store). | Duplicate coupon codes; ambiguous redemption. | Add `(store_id, code)` unique after dedupe. |

### 3. Accounts & ledger isolation

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `ac_accounts` and `ac_transactions` are `StoreScoped` ([`AcAccount.php`](app/Models/AcAccount.php:11), [`AcTransaction.php`](app/Models/AcTransaction.php:11)); system contra accounts are found-or-created per `(store_id, system_key)` with a unique index + lock + retry ([`AcAccount::findOrCreateSystemAccount`](app/Models/AcAccount.php:44)). | Cash/bank accounts and ledger per store, with a consolidated HQ view. | No consolidated ledger view exists; the multi-store dashboard aggregates **sales counters only** ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42)), not the ledger. | HQ cannot produce consolidated financials; owners see KPIs but no P&L/ledger roll-up. | Add a `withoutGlobalScope`-based consolidated balance/P&L report for super admins, mirroring the `computeStoreStats` pattern. |
| Every money flow writes `AcTransaction` with an explicit `store_id` from the source document (e.g. [`PosController.php`](app/Http/Controllers/PosController.php:507), [`TransferController.php`](app/Http/Controllers/TransferController.php:221)). | Ledger writes never cross stores. | Strong already; the isolation relies on the *source* having the right `store_id`, which is derived from `current_store_id()` — safe only once request context is explicit (gap 8). | If request context stays user-bound, fine; if a switcher is added without context hardening, ledger rows could be posted to the wrong store. | Make acting store explicit *before* adding any switcher; assert `store_id` server-side on every ledger write. |

### 4. Users, roles & permissions

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| A user has exactly one `store_id` ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)); no pivot for multi-store users. | A user may be granted access to one **or many** stores, with an HQ role spanning all. | No `user_store` pivot; no HQ-vs-store role distinction. | A manager covering two branches needs two logins. | Add `user_store` pivot + an `is_hq` flag or a dedicated HQ role. |
| Roles are per-store rows (`db_roles.store_id`) but the seeder assigns every role `store_id=1` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:137)). | Roles either global (shared definition) or per-store; must be consistent. | Roles are nominally per-store but seeded to store 1 only → Store 2/3 roles are ad-hoc (created by `AdminUserSeeder` for admins only, [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:59)). | Inconsistent role availability across stores. | Decide global-vs-per-store roles; migrate seeders accordingly. |
| `isSuperAdmin()` is true for `role_id===1` **or** `role_name==='Super Admin'` ([`User.php`](app/Models/User.php:101)). | Super admin must be a **single global** privilege, not a per-store string. | K3: three per-store admins all match the string and bypass store checks ([`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:18)) and permission gates ([`User.php`](app/Models/User.php:108)). | Any "Super Admin"-named role gets unrestricted cross-store power. | Replace with an explicit `is_super_admin` boolean or a single role id constant; remove the string match. |
| Permission slug **vocabulary split**: UI checks `sales_include_pos_view`, `sms_whatsapp_send_message`, `reports_view`, etc. ([`app.blade.php`](resources/views/layouts/app.blade.php:208), [`NavigationShortcutService.php`](app/Services/NavigationShortcutService.php:18)); seeders grant `sales_view`, `send_sms`, `dashboard_view` ([`PermissionSeeder.php`](database/seeders/PermissionSeeder.php:26)). | One canonical slug vocabulary. | Only the Accounts cluster is partially unified so far ([`AccountsPermissionSlugUnificationTest`](tests/Feature/AccountsPermissionSlugUnificationTest.php:40)); Sales/Reports/Messaging still split. | Menus silently hidden for legitimate users; permission audits unreliable. | Complete the slug-unification migration cluster by cluster (see P1 action list). |
| **Controller-vs-UI enforcement gap:** no permission middleware; core controllers (POS/Sale/Purchase/Report/etc.) have **no** `hasPermission` check at all. | Every state-changing route must enforce authorization server-side. | K1: absent in grep of [`app/Http/Controllers`](app/Http/Controllers); only `ensure.store` alias registered ([`bootstrap/app.php`](bootstrap/app.php:14)). | **Compounds multi-store risk enormously:** once a store switcher exists, any user could operate any store's POS/purchases/reports. | Add a `permission:` route middleware and apply it to every module; do this **before** any store switcher. |

### 5. Stock & transfers

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Stock lives in `db_warehouseitems (warehouse_id, item_id, available_qty)`, unique `(warehouse_id, item_id)` ([`uq_warehouse_item`](database/migrations/2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41)). | Stock per store, selectable warehouse within a store. | Model already supports this — warehouse belongs to a store ([`db_warehouse.store_id`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:16)). | Low gap here; the model is sound. | Keep `warehouse_id` as the stock dimension; ensure every write filters by the store's warehouse set. |
| `StockTransferController` moves stock between **warehouses of the same store**, reassigning serials' `warehouse_id` ([`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:201), [`…:245`](app/Http/Controllers/StockTransferController.php:245)). | Inter-**store** transfer (branch A → branch B). | **No inter-store transfer.** Legacy `to_store_id` columns exist on `db_stocktransfer`/`db_stocktransferitems` ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17)) but are **never written** by any controller. | Branches cannot rebalance stock; owners must use manual adjustments. | Build inter-store transfer as a distinct, dual-store-aware operation (writes both stores' `db_warehouseitems` atomically); decide whether `to_store_id` is revived or replaced. |
| Cross-store visibility: `StoreScoped` prevents one store seeing another's stock/pricing. | Configurable — HQ may need consolidated stock; stores should not see each other by default. | Only the super-admin dashboard reads across stores ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:37)). | No consolidated stock view for HQ. | Add a super-admin consolidated stock report using `withoutGlobalScope('store_id')`. |

### 6. Reporting

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| ~25 reports in `ReportController`, each typically filtering `warehouse_id` but scoped to the acting store by the global scope (e.g. [`ReportController.php`](app/Http/Controllers/ReportController.php:1148)). | Every report filterable per store **and** consolidatable across all stores. | No per-store selector (reports assume the user's single store); *no* consolidated report except the dashboard counters. | HQ cannot report per-branch or across branches. | Add an optional `store_id` filter (default = acting store) plus an "All stores" mode gated to super admins, using `withoutGlobalScope`. |
| Report routes require only `auth/verified/ensure.store` ([`routes/web.php`](routes/web.php:344)). | Report access must be permission-gated. | No `reports_view` enforcement server-side (the sidebar checks `reports_view`, the controller never does). | Any authenticated user can pull financial reports. | Apply `permission:reports_view` middleware. |

### 7. Settings

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Store settings are **per store** already: `StoreSettingsController::resolveActingStore()` scopes to `auth()->user()->store_id` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:22)); `store_settings()` is memoized per store id ([`helpers.php`](app/Helpers/helpers.php:77)). | Per-store business name/logo, tax number, currency, timezone, prefixes. | Already structurally per-store — but only **one store row per user**, so editing another store's settings is impossible without a switcher. | HQ cannot configure a branch; a new branch needs a dedicated admin login. | Once request context is explicit, allow super admins to select which store's settings to edit. |
| Currency and language are effectively **globally single-active**, enforced by data-fix migrations ([`enforce_single_active_currency`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php), [`…language`](database/migrations/2026_08_24_000005_enforce_single_active_language_data_fix.php)). | Possibly per-store currency. | Global single-active currency/language constrains multi-country stores. | Stores in different countries cannot use different currencies. | Decide if multi-currency is in scope; if so, relax the global single-active rule to per-store. |
| SMTP credentials live on the store row and are encrypted ([`DbStore.php`](app/Models/DbStore.php:22)). Printer/invoice templates reference `*_invoice_format_id` columns historically treated as dead (removed from validation) ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:114)). | Per-store documents/branding. | Invoice template columns were pruned as dead; document formats not implemented per store. | Document branding is single-implementation. | Re-introduce a real per-store invoice template model. |

### 8. Operational / session context

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `current_store_id()` = `auth()->user()->store_id` else default/first store else `1` ([`helpers.php`](app/Helpers/helpers.php:152)); `EnsureUserHasStore` validates that `store_id` ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:35)). | An explicit "acting store" for the request, switchable by authorised users, persisted in session. | **No store switcher, no session store, no header-based store.** | Multi-store users impossible; accidental default-to-store-1 behaviour in CLI/queue contexts ([`default_store_id()`](app/Helpers/helpers.php:24)). | Introduce `SetCurrentStore` middleware that resolves store from (session → user default) and binds into the container; `current_store_id()` reads the binding. Add a super-admin-only switcher. |

### 9. Messaging / notifications

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Provider credentials are per store (`DbSmsapi`, `DbFivemojo` queried by `store_id` — [`SslWirelessProvider.php`](app/SMS/Providers/SslWirelessProvider.php:18), [`FiveMojoSMSProvider.php`](app/SMS/Providers/FiveMojoSMSProvider.php:18)); rule cache is per store ([`RuleResolverService.php`](app/SMS/Services/RuleResolverService.php:16)); triggers derive store from the model ([`SmsTriggerService.php`](app/SMS/Services/SmsTriggerService.php:371)). | Per-store templates, triggers, provider config, and history. | `sms_campaigns` has **no** `store_id`; `sms_blacklists.phone` and `sms_daily_stats.date` are **globally unique** ([`sms_campaigns`](database/migrations/2026_02_23_110001_create_sms_campaigns_table.php:14), [`blacklists`](database/migrations/2026_02_23_110004_create_sms_blacklists_table.php:16)). | Campaigns/blacklists/statistics are shared across stores; one store blacklisting a number blacklists it for all. | Add `store_id` + per-store uniques to the three tables; backfill to store 1. |

### 10. Authentication & login flow

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Login authenticates and redirects to dashboard; resolves to exactly one business context via `user->store_id` ([`AuthenticatedSessionController.php`](app/Http/Controllers/Auth/AuthenticatedSessionController.php:31)). | Users with multi-store access choose a store post-login. | No post-login store selection; `EnsureUserHasStore` simply aborts 403 if `store_id` empty ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:36)). | Multi-store users are blocked or forced onto one store. | After `SetCurrentStore`, add a store-selection screen when a user maps to >1 store; persist choice in session. |

### 11. Database strategy options

| Option | Fit with current stack | Pros | Cons | Recommendation |
|---|---|---|---|---|
| **(a) Single DB + `store_id` column + scoping everywhere** | Already implemented (58 tables, `StoreScoped`) | Lowest migration cost; consolidated reporting natural; existing tests cover it | Requires flawless scope discipline; requires explicit request context | **Recommended** |
| **(b) Separate DB per store** | Would fight the existing architecture | Strong physical isolation | No consolidated reporting without cross-DB queries; 58-table migrations × N stores; `db_store` settings duplication | Not recommended |
| **(c) Hybrid (single DB, per-store schemas)** | Not supported by SQLite; complex on MySQL | Isolation + single server | High complexity, cross-schema reporting friction | Not recommended now |

**For the recommended option (a), the specific work:**
- **Context mechanism:** add `App\Http\Middleware\SetCurrentStore` (alias e.g. `store.context`) registered in [`bootstrap/app.php`](bootstrap/app.php:14), resolving store from session (falling back to `user->store_id`), storing it via `app()->instance('current_store_id', $id)` or a dedicated `StoreContext` singleton. Rewrite [`current_store_id()`](app/Helpers/helpers.php:152) to read that binding first.
- **Models needing verified per-store scoping (trait already present, but audit the "NULL" branch):** all 46 `StoreScoped` models listed in Deliverable A §A.3.2 — prioritise `DbSale`, `DbSaleItem`, `DbSalePayment`, `DbPurchase`, `DbPurchaseItem`, `DbPurchasePayment`, `AcAccount`, `AcTransaction`, `AcMoneyTransfer`, `AcMoneyDeposit`, `DbItem`, `DbWarehouseItem`, `DbCustomer`, `DbSupplier`, `DbExpense`.
- **Tables still missing `store_id`:** `sms_campaigns`, `sms_blacklists`, `sms_daily_stats`.
- **Scope-bypass call sites (intentional cross-store readers):** `DashboardController::computeStoreStats` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)) and any new HQ report — these must be the *only* sanctioned `withoutGlobalScope` call sites, ideally centralised.

### 12. Migration path for existing data

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| The `2026_09_11_000001` migration asserts the DB was **empty** and refuses to backfill nulls ([`…:15`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:15), [`…:95`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:95)). | A safe migration that backfills a default store on every existing row. | No reviewed backfill script; `default_store_id()` silently returns the first store or `1` ([`helpers.php`](app/Helpers/helpers.php:24)). | If history is loaded into a store_id=1 default, later per-store report split is wrong; silently attributing rows to Store 1 is dangerous. | Write an explicit, logged backfill: assign every legacy row to a named "Legacy Store 1"; never rely on the implicit `1` fallback. |
| Global uniques will block migration ordering (`item_code`, `reconciliation_code`, `sms_blacklists.phone`, `sms_daily_stats.date`). | Per-store uniques before any multi-store writes occur. | Must drop global unique then add composite unique, **after** verifying no cross-store duplicates exist. | Migration fails or silently loses data. | Sequence: (1) `NOT NULL` backfill → (2) dedupe audit → (3) swap uniques → (4) enable switcher. |
| Failing race tests (`ItemImportTest`, `SupplierRedesignAndRisksTest`, `SystemAccountRaceConditionTest`, etc. — 15 total) suggest DB write-locking behaviour is not yet stable under parallel writers ([Deliverable A §A.6](docs/PROJECT_KNOWLEDGE_BASE.md)). | Confident concurrency behaviour before multi-store increases parallel writes. | Parallel-write correctness unproven in this environment. | Multi-store increases concurrent writers; latent races become production incidents. | Re-run race tests in a MySQL-backed environment; fix or de-scope the harness before migrating. |

---

## Prioritised Action List

### P0 — blockers (must fix before any store switcher ships)
1. **P0-1 — Server-side authorization.** Add `permission:` route middleware and apply it to every module; start with POS/Sale/Purchase/Quotation/Returns/Reports/Customers/Suppliers. Resolves gap **4** (controller-vs-UI enforcement).
2. **P0-2 — Fix `isSuperAdmin()`.** Replace the `role_name === 'Super Admin'` string match with an explicit flag/constant so per-store admins stop bypassing store and permission checks. Resolves gap **4** and [K3](docs/PROJECT_KNOWLEDGE_BASE.md).
3. **P0-3 — Explicit request store context.** Introduce `SetCurrentStore` middleware + container binding; rewrite `current_store_id()`. Resolves gap **8**.
4. **P0-4 — Per-store numbering.** Replace global `max('id')` sequencing with a locked per-store sequence; make `item_code` and `reconciliation_code` per-store unique. Resolves gaps **2** and **1**.
5. **P0-5 — Remove `orWhereNull` from `StoreScoped`** after a reviewed backfill. Resolves gap **1** / [K12](docs/PROJECT_KNOWLEDGE_BASE.md).

### P1 — important
6. **P1-1 — Complete permission slug unification** for Sales, Reports, Messaging clusters (Accounts done). Resolves gap **4** / [K2](docs/PROJECT_KNOWLEDGE_BASE.md).
7. **P1-2 — Scope SMS tables** (`sms_campaigns`, `sms_blacklists`, `sms_daily_stats`). Resolves gap **9** / [K7](docs/PROJECT_KNOWLEDGE_BASE.md).
8. **P1-3 — HQ consolidated reports** (P&L/ledger/stock) via sanctioned `withoutGlobalScope` readers. Resolves gaps **3** and **6**.
9. **P1-4 — Multi-store user mapping** (`user_store` pivot) + post-login store selection. Resolves gaps **4**, **10**.
10. **P1-5 — Per-store report filters + "All stores" mode.** Resolves gap **6**.

### P2 — nice-to-have
11. **P2-1 — Inter-store stock transfer.** Resolves gap **5**.
12. **P2-2 — Deprecate `db_items.stock`** in favour of `db_warehouseitems`. Resolves gap **1** / [K6](docs/PROJECT_KNOWLEDGE_BASE.md).
13. **P2-3 — Per-store currency/language** (relax global single-active). Resolves gap **7**.
14. **P2-4 — Per-store invoice templates.** Resolves gap **7**.
15. **P2-5 — Fix invalid inline JS** in items-list / stock-transfer-create / stock-adjustment-create pages ([K8](docs/PROJECT_KNOWLEDGE_BASE.md)).
16. **P2-6 — Remove orphan view** `import_services.blade.php` and dormant `to_store_id` columns ([K9](docs/PROJECT_KNOWLEDGE_BASE.md), [K10](docs/PROJECT_KNOWLEDGE_BASE.md)).

---

## Open Questions for the Business Owner

1. **Shared or independent catalogue?** Today each store has its own `db_items` rows (full tenant isolation). If branches should share one product catalogue with per-branch pricing/stock, the data model needs redesign — this is the single largest scope question.
2. **Shared or independent customers?** `db_customers` is store-scoped today. Should a customer's loyalty/due follow them across branches?
3. **HQ role definition:** should there be a true global "owned" role that sees all stores, distinct from branch admins? (The current three "Super Admin" roles are branch admins masquerading as global.)
4. **Multi-currency:** do stores operate in different currencies? The system currently forces a single active currency globally ([`enforce_single_active_currency`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php)).
5. **Numbering policy:** must invoice numbers be unique per store, or is a company-wide sequence acceptable? This decides whether per-store sequences are required.
6. **Inter-store transfers:** is moving stock between branches a real workflow, and should it be a two-step (dispatch/receive) approval or an atomic move?
7. **Inter-store visibility:** may one branch see another branch's stock/pricing, or is it strictly private with HQ-only visibility?
8. **Tax registration per store:** each branch has its own GST/VAT number already available as a column ([`db_store.gst_no`](database/migrations/2026_02_07_091820_create_db_store_table.php:32)) — confirm each branch files independently.
9. **Existing data:** which single store should all current production rows be attributed to (assumed Store 1 / "Dhaka"), and can this be confirmed before any backfill?
