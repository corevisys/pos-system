# MULTI-STORE GAP ANALYSIS — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-13
**Companion document:** [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (current-state documentation).
**Purpose:** state exactly what exists today versus what is required to safely operate multiple independent stores/branches, precise enough to implement from directly.
**Method:** every statement was read directly from source; citations use `path:line`; uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`. Prior audit history has been superseded and is no longer preserved in this document.

---

## Definition of "multi-store" assumed here

**Assumption:** multiple *independent business units* (branches/outlets), each with its own stock, cash/bank accounts, ledger, invoice numbering, tax registration, branding, settings, and reports — but potentially **shared** master data (item catalogue and/or customer list) and a **consolidated HQ view** for ownership.

**Open question for the business owner:** the codebase does not make this unambiguous. Each `db_store` row is effectively a **hard tenant** — inventory, customers, suppliers, items and ledger all carry `store_id`, and the `StoreScoped` global scope filters them per store ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:9)). Items are **not** shared (each store has its own `db_items` rows). The current shape leans toward **fully independent stores**. If the business wants a shared item catalogue with per-store pricing/stock, that is a different and larger design (see Open Questions).

---

## Executive Summary

**The codebase is close to store-switcher-ready, but the acting-store request context is still missing.**

**What already works in its favour:**
- A real tenant model exists: `db_store`, per-store user binding, and a strict `StoreScoped` global scope on ~46 models ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:11)).
- Every operational table carries a `store_id`, and store-scoped tables were migrated to `NOT NULL store_id` with a data-safety guard ([`2026_09_11_000001`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21)).
- Per-store uniqueness is in place for the riskiest entities — items/codes ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76), [`2026_09_11_000003`](database/migrations/2026_09_11_000003_replace_global_unique_item_code_with_composite.php:43)), categories/brands/variants ([`2026_09_08_000001`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44)), suppliers ([`2026_09_07_000002`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48)), warehouses ([`2026_09_10_000002`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:51)), accounts ([`2026_09_03_000001`](database/migrations/2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table.php:36)), roles ([`2026_09_12_000003`](database/migrations/2026_09_12_000003_make_role_name_unique_per_store.php:28)) and SMS rules ([`2026_09_13_000001`](database/migrations/2026_09_13_000001_make_event_type_unique_per_store_on_sms_auto_rules.php:62)).
- Numbering is per-store and sequential, serialised with a row lock plus retry ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:207)).
- Authorization is enforced server-side (route `permission:` middleware + inline `hasPermission()` gates) across the transactional surface ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), [`routes/web.php`](routes/web.php:351)).
- SMS is fully store-scoped end-to-end ([`SmsService.php`](app/SMS/Services/SmsService.php:71)).
- A Super-Admin aggregated dashboard proves cross-store read aggregation works ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:34)).
- Seed data contains three stores (Dhaka/Chittagong/Sylhet) with per-store admins ([`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)).

**What blocks it:**
1. **No "current store" concept independent of the logged-in user.** `current_store_id()` is derived only from `auth()->user()->store_id` ([`helpers.php`](app/Helpers/helpers.php:152)); there is **no session store, no store-switcher, no `X-Store-Id` handling.** A user can serve exactly one store.
2. **Three per-store administrators are globally privileged** via the `is_super_admin` backfill, so they bypass store checks and all permission gates ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36)).
3. **No consolidated HQ reporting beyond dashboard counters.** The multi-store dashboard aggregates sales/purchase counters only ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42)); there is no consolidated ledger/P&L/stock.
4. **No multi-store user mapping.** A user has exactly one `store_id`; there is no `user_store` pivot and no post-login store selection ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)).
5. **Messaging is now scoped, but currency/language remain globally single-active**, constraining multi-country operation ([`2026_08_24_000004`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php:1)).

**Bottom line:** the *data model*, *authorization*, *numbering* and *SMS scoping* layers are in good shape. The single remaining foundational blocker is making "acting store" an explicit, session/header-driven request context **before** exposing any store switcher.

---

## The 12 Gap Tables

Legend: **P0** blocker · **P1** important · **P2** nice-to-have.

### 1. Data model & scoping

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Every operational table carries a `store_id`; the strict `StoreScoped` scope filters to the acting store with no NULL escape hatch ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:23)). `sms_auto_rules`/`sms_campaigns`/`sms_logs` and `sms_blacklists`/`sms_daily_stats` are now store-scoped and NOT NULL ([`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:50), [`2026_09_12_000005`](database/migrations/2026_09_12_000005_add_store_id_to_sms_blacklists_and_daily_stats.php:32)). | Every operational table has a non-null store identifier and every read path is scoped. | `activity_logs.store_id` is deliberately nullable (audit writes must not fail login) ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:33)). `db_items.stock` duplicates warehouse stock ([ISSUE-1](docs/PROJECT_KNOWLEDGE_BASE.md)). | Inconsistent stock between `db_items.stock` and `db_warehouseitems`; null audit store is acceptable but should be a conscious decision. | Decide whether `activity_logs` should become NOT NULL; deprecate `db_items.stock` in favour of `db_warehouseitems`. |

### 2. Uniqueness rules

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Master and transactional codes are per-store unique: `db_items.item_code` ([`2026_09_11_000003`](database/migrations/2026_09_11_000003_replace_global_unique_item_code_with_composite.php:43)), SKU/barcode ([`2026_09_11_000006`](database/migrations/2026_09_11_000006_add_per_store_unique_sku_barcode_to_db_items_table.php:1)), `cash_drawer_reconciliations.reconciliation_code` ([`2026_09_11_000004`](database/migrations/2026_09_11_000004_replace_global_unique_reconciliation_code_with_composite.php:1)), and the 9 code-bearing tables ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76)). Coupon codes are network-wide unique ([`2026_09_11_000005`](database/migrations/2026_09_11_000005_add_unique_code_to_coupon_tables.php:39)). | Same policy everywhere. | Coupon codes are intentionally **global** (a coupon must not clash across stores) — confirm this is the desired rule. | Ambiguous redemption if a store expected its own coupon namespace. | Keep global coupon uniqueness; document it as intended. |
| Numbering is per-store and sequential with a lock + retry ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:207)). | Per-store monotonic sequencing. | `purchase_return` still uses `uniqid()` rather than the sequential generator ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170)). | Non-sequential, non-auditable purchase-return numbers. | Route `purchase_return` through `generateSequential()`. |

### 3. Accounts & ledger isolation

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `ac_accounts`/`ac_transactions` are `StoreScoped`; system contra accounts are found-or-created per `(store_id, system_key)` with a unique index + lock + retry ([`AcAccount::findOrCreateSystemAccount`](app/Models/AcAccount.php:44)). | Cash/bank accounts and ledger per store, with a consolidated HQ view. | No consolidated ledger view; the multi-store dashboard aggregates sales/purchase counters only ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42)), not the ledger. | HQ cannot produce consolidated financials; owners see KPIs but no P&L/ledger roll-up. | Add a `withoutGlobalScope('store_id')`-based consolidated balance/P&L report for super admins, mirroring the `computeStoreStats` pattern. |
| Every money flow writes `AcTransaction` with an explicit `store_id` from the source document (e.g. [`TransferController.php`](app/Http/Controllers/TransferController.php:221)). | Ledger writes never cross stores. | The isolation relies on the *source* having the right `store_id`, derived from `current_store_id()`, which is safe only once the request context is explicit (gap 8). | If a switcher were added without context hardening, ledger rows could be posted to the wrong store. | Make the acting store explicit *before* adding any switcher; assert `store_id` server-side on every ledger write. |

### 4. Users, roles & permissions

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| A user has exactly one `store_id` ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)); no pivot for multi-store users. | A user may be granted access to one **or many** stores, with an HQ role spanning all. | No `user_store` pivot; no HQ-vs-store role distinction. | A manager covering two branches needs two logins. | Add `user_store` pivot + an `is_hq` flag or a dedicated HQ role. |
| Roles are per-store rows with a `(store_id, role_name)` unique ([`db_roles`](database/migrations/2026_09_12_000003_make_role_name_unique_per_store.php:28)); the seeder still assigns every role `store_id=1` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:158)). | Roles either global (shared definition) or per-store; must be consistent. | Roles are nominally per-store but seeded to store 1 only → Store 2/3 non-admin roles are ad-hoc. | Inconsistent role availability across stores. | Decide global-vs-per-store roles; migrate seeders accordingly. |
| `isSuperAdmin()` reads the explicit `is_super_admin` flag only ([`User.php`](app/Models/User.php:121)); the name/id match no longer confers privilege at check time ([`DbRole.php`](app/Models/DbRole.php:59)). | Super admin must be a **single global** privilege. | The one-time backfill set the flag true for all three per-store "Super Admin" roles (ids 1–3), so per-store admins still bypass store checks and all permission gates ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68)). | Any per-store admin has unrestricted cross-store power. | Decide whether per-store admins should be global; if not, clear `is_super_admin` for the non-HQ admin roles. |
| A single canonical permission-slug vocabulary is in place; prior UI-autoslugged values were reconciled by data migrations ([`2026_09_12_000001`](database/migrations/2026_09_12_000001_reconcile_permission_slugs_reports_view_and_sales.php:44), [`2026_09_13_000002`](database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php:28)). | One canonical slug vocabulary. | — | — | Keep new slugs aligned to the seeder vocabulary. |
| Controller-level authorization is enforced server-side: route `permission:` middleware + inline `hasPermission()` + FormRequest gates ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), [`StorePurchaseRequest.php`](app/Http/Requests/StorePurchaseRequest.php:21)). | Every state-changing route enforces authorization server-side. | — | — | Keep it centralised; new modules must add a gate. |

### 5. Stock & transfers

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Stock lives in `db_warehouseitems (warehouse_id, item_id, available_qty)`, unique `(warehouse_id, item_id)` ([`uq_warehouse_item`](database/migrations/2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41)); a warehouse belongs to a store ([`db_warehouse.store_id`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:16)). | Stock per store, selectable warehouse within a store. | The model is sound; `db_items.stock` remains a redundant dual-written aggregate ([ISSUE-1](docs/PROJECT_KNOWLEDGE_BASE.md)). | Divergence between the two stock figures. | Keep `warehouse_id` as the stock dimension; deprecate `db_items.stock`. |
| `StockTransferController` moves stock between **warehouses of the same store**, reassigning serials' `warehouse_id`. | Inter-**store** transfer (branch A → branch B). | **No inter-store transfer.** Legacy `to_store_id` columns exist on `db_stocktransfer`/`db_stocktransferitems` ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17)) but are never written by any controller. | Branches cannot rebalance stock; owners must use manual adjustments. | Build inter-store transfer as a distinct, dual-store-aware operation (writes both stores' `db_warehouseitems` atomically); decide whether `to_store_id` is revived or replaced. |
| Cross-store visibility: `StoreScoped` prevents one store seeing another's stock/pricing. | Configurable — HQ may need consolidated stock; stores should not see each other by default. | Only the super-admin dashboard reads across stores ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:37)). | No consolidated stock view for HQ. | Add a super-admin consolidated stock report using `withoutGlobalScope('store_id')`. |

### 6. Reporting

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Reports are gated by a single `permission:reports_view` route-group middleware plus a shared `report.export` middleware that turns any `/data` JSON into CSV/Excel/PDF/print without cross-store leakage ([`routes/web.php`](routes/web.php:351), [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:31), [`ExportsReportData.php`](app/Http/Controllers/Concerns/ExportsReportData.php:37)). | — | Reports remain acting-store scoped. | — | — |
| ~25 reports are scoped to the acting store (e.g. [`ReportController.php`](app/Http/Controllers/ReportController.php:1148)); no per-store selector and no consolidated report except the dashboard counters. | Every report filterable per store **and** consolidatable across all stores. | No per-store selector; no "All stores" mode. | HQ cannot report per-branch or across branches. | Add an optional `store_id` filter (default = acting store) plus an "All stores" mode gated to super admins, using `withoutGlobalScope`. |

### 7. Settings

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Store settings are per store; `StoreSettingsController::resolveActingStore()` scopes to `auth()->user()->store_id` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:22)); `store_settings()` is memoized per store id ([`helpers.php`](app/Helpers/helpers.php:77)); changes are audited in `activity_logs` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:273)). | Per-store business name/logo, tax number, currency, timezone, prefixes. | Already structurally per-store — but only **one store row per user**, so editing another store's settings requires a switcher. | HQ cannot configure a branch without a dedicated admin login. | Once request context is explicit, allow super admins to select which store's settings to edit. |
| Currency and language are globally single-active, enforced by data-fix migrations ([`enforce_single_active_currency`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php), [`…language`](database/migrations/2026_08_24_000005_enforce_single_active_language_data_fix.php)). | Possibly per-store currency/language. | Global single-active currency/language constrains multi-country stores. | Stores in different countries cannot use different currencies. | Decide if multi-currency is in scope; if so, relax the global single-active rule to per-store. |
| SMTP credentials live on the store row and are encrypted ([`DbStore.php`](app/Models/DbStore.php:22)). Invoice-template `*_invoice_format_id` columns are treated as dead and removed from validation. | Per-store documents/branding. | Document formats are not implemented per store. | Document branding is single-implementation. | Re-introduce a real per-store invoice template model. |

### 8. Operational / session context

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `current_store_id()` = `auth()->user()->store_id` else default/first store else `1` ([`helpers.php`](app/Helpers/helpers.php:152)); `EnsureUserHasStore` validates that `store_id` ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:35)). | An explicit "acting store" for the request, switchable by authorised users, persisted in session. | **No store switcher, no session store, no header-based store** (the migration comment confirms none exists) ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:16)). | Multi-store users impossible; accidental default-to-store-1 behaviour in CLI/queue contexts ([`default_store_id()`](app/Helpers/helpers.php:24)). | Introduce `SetCurrentStore` middleware that resolves the store from (session → user default) and binds it into the container; `current_store_id()` reads the binding. Add a super-admin-only switcher. |

### 9. Messaging / notifications

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Provider credentials, templates and rules are per store; the pipeline resolves the provider, blacklist and duplicate suppression per store ([`SmsService.php`](app/SMS/Services/SmsService.php:34), [`…:71`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:84)). Scheduled-campaign dispatch runs across all stores, and each job resolves its own store ([`routes/console.php`](routes/console.php:16)). | Per-store templates, triggers, provider config, and history. | — | — | Maintain per-store scoping for any new SMS feature. |

### 10. Authentication & login flow

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Login authenticates, records an `activity_logs` row, and redirects to the dashboard, resolving to exactly one business context via `user->store_id` ([`AuthenticatedSessionController.php`](app/Http/Controllers/Auth/AuthenticatedSessionController.php:37)). | Users with multi-store access choose a store post-login. | No post-login store selection; `EnsureUserHasStore` aborts 403 if `store_id` is empty ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:36)). | Multi-store users are blocked or forced onto one store. | After `SetCurrentStore`, add a store-selection screen when a user maps to >1 store; persist the choice in session. |

### 11. Database strategy options

| Option | Fit with current stack | Pros | Cons | Recommendation |
|---|---|---|---|---|
| **(a) Single DB + `store_id` column + scoping everywhere** | Already implemented (`StoreScoped` + store_id on all operational tables) | Lowest migration cost; consolidated reporting natural; existing tests cover it | Requires flawless scope discipline; requires explicit request context | **Recommended** |
| **(b) Separate DB per store** | Would fight the existing architecture | Strong physical isolation | No consolidated reporting without cross-DB queries; schema migrations × N stores; `db_store` settings duplication | Not recommended |
| **(c) Hybrid (single DB, per-store schemas)** | Not supported by SQLite; complex on MySQL | Isolation + single server | High complexity, cross-schema reporting friction | Not recommended now |

**For the recommended option (a), the specific work:**
- **Context mechanism:** add `App\Http\Middleware\SetCurrentStore` (alias e.g. `store.context`) registered in [`bootstrap/app.php`](bootstrap/app.php:16), resolving the store from session (falling back to `user->store_id`), storing it via `app()->instance('current_store_id', $id)` or a dedicated `StoreContext` singleton. Rewrite [`current_store_id()`](app/Helpers/helpers.php:152) to read that binding first.
- **Models needing verified per-store scoping (trait already present):** all `StoreScoped` models listed in [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.3.2 — prioritise `DbSale`, `DbSaleItem`, `DbSalePayment`, `DbPurchase`, `DbPurchaseItem`, `DbPurchasePayment`, `AcAccount`, `AcTransaction`, `AcMoneyTransfer`, `AcMoneyDeposit`, `DbItem`, `DbWarehouseItem`, `DbCustomer`, `DbSupplier`, `DbExpense`.
- **Scope-bypass call sites (intentional cross-store readers):** `DashboardController::computeStoreStats` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)) and per-store code generation ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:215)), plus any new HQ report — centralise these so they are the only sanctioned `withoutGlobalScope` call sites.

### 12. Migration path for existing data & concurrency

| Current State (evidence) | Required for Multi-Store | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Store-scoped tables were migrated to `NOT NULL store_id` with a data-safety guard, and per-store uniques were swapped after duplicate pre-checks that abort loudly ([`2026_09_11_000001`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21), [`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:66)). SMS backfills attributed legacy rows to the first store ([`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:56)). | A safe migration that backfills a default store on every existing row. | `default_store_id()` still silently returns the first store or `1` ([`helpers.php`](app/Helpers/helpers.php:24)). | Silently attributing legacy rows to Store 1 can make later per-store report splits wrong. | Use an explicit, logged backfill; never rely on the implicit `1` fallback. |
| Concurrency correctness is not yet proven in this environment: 12 race tests fail with `expected 1, got 0` under parallel workers ([§A.6](docs/PROJECT_KNOWLEDGE_BASE.md)). | Confident concurrency behaviour before multi-store increases parallel writes. | Parallel-write correctness unproven here. | Multi-store increases concurrent writers; latent races could become production incidents. | Re-run the race tests in a MySQL-backed environment; fix or de-scope the harness before migrating. |

---

## Prioritised Action List

### P0 — blocker for a store switcher
1. **Explicit request store context.** Introduce `SetCurrentStore` middleware + container binding; rewrite `current_store_id()`. Without this, no switcher can be exposed (gap 8). Evidence of absence: [`helpers.php`](app/Helpers/helpers.php:152), [`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:16).

### P1 — important
2. **Decide the fate of the three globally-privileged per-store admins** (`is_super_admin` backfill) (gap 4). [`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36).
3. **HQ consolidated reports** (P&L / ledger / stock) via sanctioned `withoutGlobalScope('store_id')` readers (gaps 3, 6). [`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42).
4. **Multi-store user mapping** (`user_store` pivot) + post-login store selection (gaps 4, 10). [`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16).
5. **Per-store report filters + "All stores" mode** (gap 6). [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:76).

### P2 — nice-to-have
6. **Inter-store stock transfer** (gap 5). [`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17).
7. **Deprecate `db_items.stock`** in favour of `db_warehouseitems` (gap 1). [`PosController.php`](app/Http/Controllers/PosController.php:494).
8. **Per-store currency/language** (relax global single-active) (gap 7). [`2026_08_24_000004`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php:1).
9. **Per-store invoice templates** (gap 7).
10. **Fix invalid inline JS** in items-list / stock-transfer-create / stock-adjustment-create pages ([ISSUE-2](docs/PROJECT_KNOWLEDGE_BASE.md)). `ItemsListRedesignTest`, `StockCreateFormRedesignBrowserCheckTest`.
11. **Remove orphan view** `import_services.blade.php` and dormant `to_store_id` columns ([ISSUE-3](docs/PROJECT_KNOWLEDGE_BASE.md), [ISSUE-4](docs/PROJECT_KNOWLEDGE_BASE.md)).
12. **Make `purchase_return` sequential** (gap 2). [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170).

---

## Open Questions for the Business Owner

1. **Shared or independent catalogue?** Each store has its own `db_items` rows (full tenant isolation). If branches should share one product catalogue with per-branch pricing/stock, the data model needs redesign — the single largest scope question.
2. **Shared or independent customers?** `db_customers` is store-scoped; should a customer's loyalty/due follow them across branches?
3. **HQ role definition:** should there be a true global "owner" role that sees all stores, distinct from branch admins? *(Materially relevant: the `is_super_admin` backfill made all three branch admins global — confirm this is intended.)*
4. **Multi-currency:** do stores operate in different currencies? The system currently forces a single active currency globally.
5. **Numbering policy:** must invoice numbers be unique per store, or is a company-wide sequence acceptable? (Per-store sequences are currently implemented.)
6. **Inter-store transfers:** is moving stock between branches a real workflow, and should it be a two-step (dispatch/receive) approval or an atomic move?
7. **Inter-store visibility:** may one branch see another branch's stock/pricing, or is it strictly private with HQ-only visibility?
8. **Tax registration per store:** each branch already has its own GST/VAT column ([`db_store.gst_no`](database/migrations/2026_02_07_091820_create_db_store_table.php:32)) — confirm each branch files independently.
9. **Existing data:** which store owns all legacy rows (assumed Store 1 / "Dhaka")?
