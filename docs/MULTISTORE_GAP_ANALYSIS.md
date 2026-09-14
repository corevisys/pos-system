# MULTI-STORE GAP ANALYSIS — Corevisys POS (LaravelPOS)

**Last verified:** 2026-09-13
**Companion document:** [`docs/PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) (current-state documentation).
**Purpose:** state exactly what exists today versus what is required to safely operate multiple independent stores/branches, precise enough to implement from directly. Business-owner decisions (2026-09-13) are now folded in as requirements.
**Method:** every statement was read directly from source; citations use `path:line`; uncertain items are marked `[UNVERIFIED]`/`[INFERENCE]`. Prior audit history has been superseded and is no longer preserved in this document.

---

## Confirmed Model (business-owner decisions, 2026-09-13)

These are now settled requirements, not open questions:

1. **Stores stay fully independent.** Each `db_store` (a *business/tenant*) owns its own `db_items`, customers, suppliers and ledger. There is **no shared item catalogue** — no development work; the current isolation is exactly the desired end state.
2. **One store = one business; multiple physical outlets = multiple warehouses.** Outlets under a single store are represented as separate `db_warehouse` rows within that store, and stock moves between them via the existing warehouse-to-warehouse transfer ([`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:201)). This is already supported.
3. **Single currency (BDT) across all stores.** No per-store currency/language work. The existing global single-active currency/language constraint is acceptable ([`2026_08_24_000004`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php:1)).
4. **Per-store sequential invoice/receipt numbering stays as-is.** Confirmed correct ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:207)).
5. **No inter-store stock transfer.** Moving stock between separate stores is out of scope; outlet-level rebalancing is the warehouse transfer above.
6. **Branches stay fully isolated**; only the new **Owner** role has cross-store visibility.
7. **Tax: each branch files independently** via its own `db_store.gst_no` ([`db_store migration`](database/migrations/2026_02_07_091820_create_db_store_table.php:32)). Confirmed correct.
8. **No production data exists yet** — no backfill/attribution work now. **Reminder for the future:** before any bulk import or migration script runs, the target store **must be specified explicitly**; never rely on the silent `default_store_id()` fallback to Store 1 ([`helpers.php`](app/Helpers/helpers.php:24)).
9. **Customer sharing is still OPEN** — see the dedicated section at the end; it is a blocking decision.

### Decision Register

| # | Question | Decision | Impact on work |
|---|---|---|---|
| 1 | Shared or independent catalogue? | **Fully independent** (as today) | None — no dev work. |
| 2 | Shared or independent customers? | **OPEN — not yet decided** | Blocks customer-scoped work (see [Open Decision](#open-decision-customer-sharing)). |
| 3 | HQ role definition | **Three roles: Branch Admin / Owner / Developer** | Main remaining dev work (see [Role Model](#role-model)). |
| 4 | Multi-currency? | **Single currency (BDT)** | None — drop per-store currency/language from scope. |
| 5 | Numbering policy | **Per-store sequential** (current) | None. |
| 6 | Inter-store transfers? | **Not needed** | None; outlet transfers already work. |
| 7 | Inter-store visibility | **Branches isolated; Owner-only cross-store** | Consolidated reports must be Owner-gated. |
| 8 | Tax filing per store | **Independent per branch** | None — already correct. |
| 9 | Legacy data attribution | **None needed (no prod data)** | None now; explicit-store rule for future imports. |

---

## Role Model

Three distinct roles, with **binary** access (a user is either bound to exactly one branch, or is the Owner with all-store visibility). No `user_store` pivot is required; selective multi-branch grants are explicitly out of scope unless a concrete need emerges.

| Role | Scope | Current implementation state | Required work |
|---|---|---|---|
| **Branch Admin** | Own branch only | Exists as the three seeded per-store admins, but **currently globally privileged** (`is_super_admin = true`), so they bypass store and permission checks ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68)). | **Clear `is_super_admin`** on the branch-admin roles so they are constrained to their own `store_id`. Their existing permission set is otherwise complete. |
| **Owner** | All stores, full cross-store visibility and all consolidated reports | **Not implemented.** Cross-store read aggregation exists only as the Super-Admin dashboard ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:34)), and its gate is the generic `is_super_admin` flag rather than a named role. | Add an **Owner** role/user; expose consolidated reporting (ledger/P&L/stock) gated to Owner-only; provide a cross-store read context so the Owner can view any store (this is the "acting store" problem in [gap 8](#8-operational--session-context)). |
| **Developer** | System/maintenance super-admin, used for support only | Conceptually the current global super-admin. | Create a **separate** Developer account that is **never shared with or used as the Owner login**; it may be restricted or disabled later. |

**Design notes:**
- The privilege flag `db_roles.is_super_admin` ([`User.php`](app/Models/User.php:121)) becomes the mechanism for the *Developer* (system) account. The *Owner* should be a normal named role with cross-store read privileges, **not** a bypass-everything super-admin — so branch admins and the Owner are both subject to permission checks, and only the Developer account is the unrestricted maintenance identity.
- Because access is binary, `current_store_id()` only needs to resolve to "a specific branch" (Branch Admin) or "an Owner-chosen store / all stores" (Owner). No per-user multi-store mapping is needed ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)).

---

## Executive Summary

**The data model is ready. The remaining work is the role model (Owner vs Branch Admin vs Developer) and an explicit acting-store request context.**

**What already works in its favour:**
- A real tenant model exists: `db_store`, per-store user binding, and a strict `StoreScoped` global scope on ~46 models ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:11)).
- Every operational table carries a `store_id`, migrated to `NOT NULL store_id` with a data-safety guard ([`2026_09_11_000001`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21)).
- Per-store uniqueness is in place for the riskiest entities — items/codes ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76), [`2026_09_11_000003`](database/migrations/2026_09_11_000003_replace_global_unique_item_code_with_composite.php:43)), categories/brands/variants ([`2026_09_08_000001`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:44)), suppliers ([`2026_09_07_000002`](database/migrations/2026_09_07_000002_make_supplier_mobile_email_unique_per_store.php:48)), warehouses ([`2026_09_10_000002`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:51)), accounts ([`2026_09_03_000001`](database/migrations/2026_09_03_000001_add_unique_store_account_code_to_ac_accounts_table.php:36)), roles ([`2026_09_12_000003`](database/migrations/2026_09_12_000003_make_role_name_unique_per_store.php:28)) and SMS rules ([`2026_09_13_000001`](database/migrations/2026_09_13_000001_make_event_type_unique_per_store_on_sms_auto_rules.php:62)).
- Numbering is per-store and sequential, serialised with a row lock plus retry ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:207)).
- Authorization is enforced server-side (route `permission:` middleware + inline `hasPermission()` gates) across the transactional surface ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), [`routes/web.php`](routes/web.php:351)).
- SMS is fully store-scoped end-to-end ([`SmsService.php`](app/SMS/Services/SmsService.php:71)).
- A Super-Admin aggregated dashboard already proves cross-store read aggregation works ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:34)), and can be repurposed as the basis for Owner consolidated views.
- Seed data contains three stores (Dhaka/Chittagong/Sylhet) with per-store admins ([`StoreSeeder.php`](database/seeders/StoreSeeder.php:16)).

**What remains:**
1. **Branch admins are globally privileged** (`is_super_admin = true` from the backfill) and must be constrained to their own branch; the Developer account should hold the system super-admin privilege instead ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36)).
2. **No Owner role or Owner-gated consolidated reporting** exists — consolidated reports must be built and gated to Owner only ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42)).
3. **No explicit acting-store request context.** `current_store_id()` is derived only from `auth()->user()->store_id`, so an Owner cannot view a chosen store and there is no session store ([`helpers.php`](app/Helpers/helpers.php:152)).
4. **Customer sharing is undecided** (blocking — see the end of this document).
5. **Dual stock source of truth** (`db_items.stock` vs `db_warehouseitems`) remains; `to_store_id` columns are confirmed dead code.

---

## The 12 Gap Tables

Legend: **P0** blocker · **P1** important · **P2** nice-to-have.

### 1. Data model & scoping

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Every operational table carries a `store_id`; the strict `StoreScoped` scope filters to the acting store with no NULL escape hatch ([`StoreScoped.php`](app/Models/Traits/StoreScoped.php:23)). SMS tables are store-scoped and NOT NULL ([`2026_09_12_000004`](database/migrations/2026_09_12_000004_add_store_id_to_sms_auto_rules_campaigns_logs.php:50), [`2026_09_12_000005`](database/migrations/2026_09_12_000005_add_store_id_to_sms_blacklists_and_daily_stats.php:32)). | Stores fully independent; every read path store-scoped. | `activity_logs.store_id` is deliberately nullable (audit writes must not fail login) ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:33)). `db_items.stock` duplicates warehouse stock ([ISSUE-1](docs/PROJECT_KNOWLEDGE_BASE.md)). | Inconsistent stock between `db_items.stock` and `db_warehouseitems`. | Deprecate `db_items.stock` in favour of `db_warehouseitems`; decide whether `activity_logs` should become NOT NULL. |

### 2. Uniqueness rules

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Master and transactional codes are per-store unique: `db_items.item_code` ([`2026_09_11_000003`](database/migrations/2026_09_11_000003_replace_global_unique_item_code_with_composite.php:43)), SKU/barcode ([`2026_09_11_000006`](database/migrations/2026_09_11_000006_add_per_store_unique_sku_barcode_to_db_items_table.php:1)), `cash_drawer_reconciliations.reconciliation_code` ([`2026_09_11_000004`](database/migrations/2026_09_11_000004_replace_global_unique_reconciliation_code_with_composite.php:1)), and the 9 code-bearing tables ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76)). Per-store numbering is sequential with a lock + retry ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:207)). | Per-store unique codes; per-store sequential numbering (confirmed). | `purchase_return` still uses `uniqid()` rather than the sequential generator ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170)). | Non-sequential, non-auditable purchase-return numbers. | Route `purchase_return` through `generateSequential()`. |
| Coupon codes are network-wide unique ([`2026_09_11_000005`](database/migrations/2026_09_11_000005_add_unique_code_to_coupon_tables.php:39)). | Coupons must not clash across stores. | — | — | Keep the global rule; document it as intended. |

### 3. Accounts & ledger isolation

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `ac_accounts`/`ac_transactions` are `StoreScoped`; system contra accounts are found-or-created per `(store_id, system_key)` with a unique index + lock + retry ([`AcAccount::findOrCreateSystemAccount`](app/Models/AcAccount.php:44)). Ledger writes carry an explicit `store_id` from the source document (e.g. [`TransferController.php`](app/Http/Controllers/TransferController.php:221)). | Cash/bank accounts and ledger per store, with a consolidated view **for the Owner only**. | No consolidated ledger view; the current dashboard aggregates sales/purchase counters only ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42)). | The Owner cannot produce consolidated financials. | Add a `withoutGlobalScope('store_id')`-based consolidated balance/P&L report **gated to the Owner role**, mirroring the `computeStoreStats` pattern. |

### 4. Users, roles & permissions

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| A user has exactly one `store_id` ([`users` migration](database/migrations/2026_02_07_083100_create_users_table.php:16)). | **Binary** access: one branch, or all branches as Owner. **No `user_store` pivot.** | — (no pivot needed) | — | Keep the single `store_id`; add a named Owner role that reads across stores instead of a pivot. |
| Roles are per-store rows with a `(store_id, role_name)` unique ([`2026_09_12_000003`](database/migrations/2026_09_12_000003_make_role_name_unique_per_store.php:28)); the seeder assigns every role `store_id=1` ([`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:158)). | Branch Admin (own branch), Owner (all), Developer (system). | Roles are nominally per-store but seeded to store 1 only → Store 2/3 non-admin roles are ad-hoc. | Inconsistent role availability across stores. | Define the role set consistently (global definitions where appropriate); migrate the seeders. |
| `isSuperAdmin()` reads the explicit `is_super_admin` flag only ([`User.php`](app/Models/User.php:121)); the name/id match no longer confers privilege at check time ([`DbRole.php`](app/Models/DbRole.php:59)). | Only the Developer (system) account holds unrestricted cross-store privilege; Owner is a normal named role; Branch Admin is branch-bound. | The one-time backfill set the flag true for all three per-store "Super Admin" roles, so branch admins still bypass store checks and all permission gates ([`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68)). | Any branch admin has unrestricted cross-store power. | **Clear `is_super_admin` on the branch-admin roles.** Add the Owner role (cross-store reads, **not** a bypass-everything super-admin). Keep the flag for the Developer/system account only. |
| A single canonical permission-slug vocabulary is in place; prior UI-autoslugged values were reconciled by data migrations ([`2026_09_12_000001`](database/migrations/2026_09_12_000001_reconcile_permission_slugs_reports_view_and_sales.php:44), [`2026_09_13_000002`](database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php:28)). Controller authorization is enforced server-side (route `permission:` middleware + inline `hasPermission()` + FormRequest gates) ([`EnsureUserHasPermission.php`](app/Http/Middleware/EnsureUserHasPermission.php:28), [`StorePurchaseRequest.php`](app/Http/Requests/StorePurchaseRequest.php:21)). | One canonical slug vocabulary; every state-changing route gated. | — | — | Keep new slugs aligned; new modules must add a gate. |

### 5. Stock & transfers

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Stock lives in `db_warehouseitems (warehouse_id, item_id, available_qty)`, unique `(warehouse_id, item_id)` ([`uq_warehouse_item`](database/migrations/2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41)); a warehouse belongs to a store ([`db_warehouse.store_id`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:16)). `StockTransferController` moves stock between warehouses of the same store ([`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:201)). | Outlets within one store are separate warehouses; outlet-to-outlet transfer via the existing warehouse transfer. | The model already meets the requirement; `db_items.stock` remains a redundant dual-written aggregate ([ISSUE-1](docs/PROJECT_KNOWLEDGE_BASE.md)). | Divergence between the two stock figures. | Keep `warehouse_id` as the stock dimension; deprecate `db_items.stock`. |
| Legacy `to_store_id` columns exist on `db_stocktransfer`/`db_stocktransferitems` ([`DbStockTransfer.php`](app/Models/DbStockTransfer.php:17)) but are never written by any controller. | **No inter-store transfer.** Outlets under one store use the warehouse transfer. | `to_store_id` is **confirmed dead code** (inter-store transfer out of scope). | Dead schema confuses future readers. | Remove the `to_store_id` columns and their fillable entries. |
| Cross-store visibility: `StoreScoped` prevents one store seeing another's stock/pricing. | Branches isolated; **Owner only** sees across stores. | Only the current Super-Admin dashboard reads across stores ([`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:37)). | No consolidated stock view for the Owner. | Add an **Owner-gated** consolidated stock report using `withoutGlobalScope('store_id')`. |

### 6. Reporting

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Reports are gated by a single `permission:reports_view` route-group middleware plus a shared `report.export` middleware that turns any `/data` JSON into CSV/Excel/PDF/print without cross-store leakage ([`routes/web.php`](routes/web.php:351), [`EnsureReportExport.php`](app/Http/Middleware/EnsureReportExport.php:31), [`ExportsReportData.php`](app/Http/Controllers/Concerns/ExportsReportData.php:37)). | Per branch reports stay acting-store scoped; consolidated reports are **Owner-only**. | No per-store selector; no "All stores" mode. | — | Add, **gated to Owner only**, an optional `store_id` selector plus an "All stores" mode using `withoutGlobalScope`. **Branch admins must not gain cross-store visibility.** |

### 7. Settings

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Store settings are per store; `StoreSettingsController::resolveActingStore()` scopes to `auth()->user()->store_id` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:22)); `store_settings()` is memoized per store id ([`helpers.php`](app/Helpers/helpers.php:77)); changes are audited in `activity_logs` ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:273)). | Per-store business name/logo, tax number, timezone, prefixes; single currency BDT. | Structurally per-store; editing another store's settings requires an Owner context/switcher. | The Owner cannot configure a branch without a dedicated login. | Once the acting-store context exists (gap 8), allow the **Owner** to select which store's settings to edit. |
| Currency and language are globally single-active, enforced by data-fix migrations ([`enforce_single_active_currency`](database/migrations/2026_08_24_000004_enforce_single_active_currency_data_fix.php), [`…language`](database/migrations/2026_08_24_000005_enforce_single_active_language_data_fix.php)). | **Single currency (BDT)** for all stores — confirmed. | — | — | No change; per-store currency/language is out of scope. |
| SMTP credentials live on the store row and are encrypted ([`DbStore.php`](app/Models/DbStore.php:22)). Invoice-template `*_invoice_format_id` columns are treated as dead and removed from validation. | Per-store documents/branding (optional). | Document formats are not implemented per store. | Document branding is single-implementation. | Optional: re-introduce a real per-store invoice template model if document branding becomes a requirement. |

### 8. Operational / session context

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| `current_store_id()` = `auth()->user()->store_id` else default/first store else `1` ([`helpers.php`](app/Helpers/helpers.php:152)); `EnsureUserHasStore` validates that `store_id` ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:35)). | An explicit "acting store" for the request: branch admins resolve to their own branch; the Owner may target a chosen store or the all-stores aggregate. | **No store switcher, no session store, no header-based store** (the migration comment confirms none exists) ([`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:16)). | The Owner cannot view a chosen store or consolidated views; accidental default-to-store-1 behaviour in CLI/queue contexts ([`default_store_id()`](app/Helpers/helpers.php:24)). | Introduce `SetCurrentStore` middleware that resolves the store from (session → user default) and binds it into the container; `current_store_id()` reads the binding. Expose a **store selector to the Owner only**. |

### 9. Messaging / notifications

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Provider credentials, templates and rules are per store; the pipeline resolves the provider, blacklist and duplicate suppression per store ([`SmsService.php`](app/SMS/Services/SmsService.php:34), [`…:71`](app/SMS/Services/SmsService.php:71), [`…:84`](app/SMS/Services/SmsService.php:84)). Scheduled-campaign dispatch runs across all stores, and each job resolves its own store ([`routes/console.php`](routes/console.php:16)). | Per-store templates, triggers, provider config, and history. | — | — | Maintain per-store scoping for any new SMS feature. |

### 10. Authentication & login flow

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Login authenticates, records an `activity_logs` row, and redirects to the dashboard, resolving to exactly one business context via `user->store_id` ([`AuthenticatedSessionController.php`](app/Http/Controllers/Auth/AuthenticatedSessionController.php:37)). | Branch admins log straight into their branch; the Owner logs in and can switch store / view all stores. | No post-login store selection; `EnsureUserHasStore` aborts 403 if `store_id` is empty ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:36)). | The Owner is forced onto one store with no cross-store view. | After `SetCurrentStore`, provide a store selector to the Owner (binary access means branch admins need none); persist the Owner's choice in session. |

### 11. Database strategy options

| Option | Fit with current stack | Pros | Cons | Recommendation |
|---|---|---|---|---|
| **(a) Single DB + `store_id` column + scoping everywhere** | Already implemented (`StoreScoped` + store_id on all operational tables) | Lowest migration cost; consolidated reporting natural; existing tests cover it | Requires flawless scope discipline; requires explicit request context | **Recommended** |
| **(b) Separate DB per store** | Would fight the existing architecture | Strong physical isolation | No consolidated reporting without cross-DB queries; schema migrations × N stores; `db_store` settings duplication | Not recommended |
| **(c) Hybrid (single DB, per-store schemas)** | Not supported by SQLite; complex on MySQL | Isolation + single server | High complexity, cross-schema reporting friction | Not recommended now |

**For the recommended option (a), the specific work:**
- **Context mechanism:** add `App\Http\Middleware\SetCurrentStore` (alias e.g. `store.context`) registered in [`bootstrap/app.php`](bootstrap/app.php:16), resolving the store from session (falling back to `user->store_id`), storing it via `app()->instance('current_store_id', $id)` or a dedicated `StoreContext` singleton. Rewrite [`current_store_id()`](app/Helpers/helpers.php:152) to read that binding first.
- **Owner cross-store reads:** expose a store selector to the Owner and reuse `DashboardController::computeStoreStats` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)) as the basis for consolidated reports.
- **Models needing verified per-store scoping (trait already present):** all `StoreScoped` models listed in [`PROJECT_KNOWLEDGE_BASE.md`](docs/PROJECT_KNOWLEDGE_BASE.md) §A.3.2 — prioritise `DbSale`, `DbSaleItem`, `DbSalePayment`, `DbPurchase`, `DbPurchaseItem`, `DbPurchasePayment`, `AcAccount`, `AcTransaction`, `AcMoneyTransfer`, `AcMoneyDeposit`, `DbItem`, `DbWarehouseItem`, `DbCustomer`, `DbSupplier`, `DbExpense`.
- **Scope-bypass call sites (intentional cross-store readers):** `DashboardController::computeStoreStats` ([`DashboardController.php`](app/Http/Controllers/DashboardController.php:53)) and per-store code generation ([`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:215)), plus any new Owner report — centralise these so they are the only sanctioned `withoutGlobalScope` call sites.

### 12. Migration path for existing data & concurrency

| Current State (evidence) | Confirmed Requirement | Gap / Missing | Risk if Unaddressed | Suggested Approach |
|---|---|---|---|---|
| Store-scoped tables were migrated to `NOT NULL store_id` with a data-safety guard; per-store uniques were swapped after duplicate pre-checks that abort loudly ([`2026_09_11_000001`](database/migrations/2026_09_11_000001_add_not_null_store_id_to_store_scoped_tables.php:21), [`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:66)). | No backfill needed now (no production data). | `default_store_id()` silently returns the first store or `1` ([`helpers.php`](app/Helpers/helpers.php:24)). | A future bulk import could silently attribute rows to Store 1 if the store is not specified. | **Before any bulk import or migration script runs, require the target store explicitly**; never rely on the implicit `1` fallback. |
| Numbering/authorization/SMS scoping are already per-store; concurrency is not yet proven in this environment — 12 race tests fail with `expected 1, got 0` under parallel workers ([§A.6](docs/PROJECT_KNOWLEDGE_BASE.md)). | Confident concurrency behaviour before multi-store increases parallel writes. | Parallel-write correctness unproven here. | Multi-store increases concurrent writers; latent races could become production incidents. | Re-run the race tests in a MySQL-backed environment; fix or de-scope the harness before migrating. |

---

## Prioritised Action List

### P0 — blocker
1. **Owner cross-store context.** Introduce `SetCurrentStore` middleware + container binding; rewrite `current_store_id()`; expose a store selector to the **Owner only**. Without this there is no cross-store or consolidated view (gap 8, gap 6). Evidence of absence: [`helpers.php`](app/Helpers/helpers.php:152), [`2026_09_11_000007`](database/migrations/2026_09_11_000007_create_activity_logs_table.php:16).

### P1 — important
2. **Implement the three-role model** (gap 4): clear `is_super_admin` on the branch-admin roles so they are branch-bound; add the **Owner** role (cross-store reads, not a bypass-everything super-admin); create a separate **Developer** system account that is never the Owner login. [`2026_09_12_000002`](database/migrations/2026_09_12_000002_add_is_super_admin_to_db_roles_table.php:36), [`AdminUserSeeder.php`](database/seeders/AdminUserSeeder.php:68).
3. **Owner-gated consolidated reports** (P&L / ledger / stock) via sanctioned `withoutGlobalScope('store_id')` readers; **ensure branch admins get no cross-store visibility** (gaps 3, 6). [`MultiStoreDashboardController.php`](app/Http/Controllers/MultiStoreDashboardController.php:42).

### P2 — nice-to-have
4. **Remove confirmed dead code**: `db_stocktransfer.to_store_id` / `db_stocktransferitems.to_store_id` columns and fillable, and the orphan view `import_services.blade.php` (gap 5; [ISSUE-3](docs/PROJECT_KNOWLEDGE_BASE.md), [ISSUE-4](docs/PROJECT_KNOWLEDGE_BASE.md)).
5. **Deprecate `db_items.stock`** in favour of `db_warehouseitems` (gap 1). [`PosController.php`](app/Http/Controllers/PosController.php:494).
6. **Make `purchase_return` sequential** (gap 2). [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:170).
7. **Fix invalid inline JS** in items-list / stock-transfer-create / stock-adjustment-create pages ([ISSUE-2](docs/PROJECT_KNOWLEDGE_BASE.md)). `ItemsListRedesignTest`, `StockCreateFormRedesignBrowserCheckTest`.
8. **Optional: per-store invoice templates** (gap 7) — only if document branding becomes a requirement.

> **Out of scope (decided):** shared catalogue, per-store currency/language, inter-store stock transfer, per-store user mapping (`user_store` pivot), and legacy backfill.

---

## Open Decision: Customer Sharing

**Status: NOT YET DECIDED — this is a blocking decision.** Do not implement customer-scoped work until the owner chooses.

The three options remain:

| Option | Meaning | Consequences if chosen |
|---|---|---|
| **(a) Store-specific (current behaviour)** | Each store has its own `db_customers` rows; a customer exists per store. | No change. `db_customers` stays `StoreScoped` ([`DbCustomer.php`](app/Models/DbCustomer.php:1)); `customer_code` stays `(store_id, customer_code)` unique ([`2026_09_11_000002`](database/migrations/2026_09_11_000002_add_composite_unique_code_to_store_scoped_tables.php:76)). |
| **(b) Fully shared** | One customer record visible to all stores. | Requires de-scoping `db_customers` (or a shared variant), a global customer identity, and a rethink of per-store codes; cross-store loyalty/dues become company-wide. Large change. |
| **(c) Shared identity only** | A customer's identity is shared, but per-store ledgers/dues remain separate. | Requires a shared identity table linked to per-store customer rows; loyalty/dues stay per store but the person is recognised across branches. Medium change. |

**Downstream items paused until this is answered:**
- **Data model / scoping** ([gap 1](#1-data-model--scoping)) — whether `db_customers` remains `StoreScoped`.
- **Uniqueness rules** ([gap 2](#2-uniqueness-rules)) — whether `customer_code` stays per-store unique or becomes global.
- **Loyalty / dues / advance logic** — `db_customers` carries due/loyalty fields consumed by POS and sales, and `db_custadvance` posts against a customer ([`AdvanceController.php`](app/Http/Controllers/AdvanceController.php:21)); whether these follow the customer across branches depends on the choice.
- **POS and sales customer selection** — customer search and the `customer_previous_due` / `customer_total_due` fields on `db_sales` ([`db_sales` migration](database/migrations/2026_02_07_090938_create_db_sales_table.php:15)) assume a store-scoped customer today.
- **Owner consolidated reporting** ([gap 6](#6-reporting)) — a consolidated customer/due view is only meaningful once the sharing model is decided.

> Everything else in this document is decided; only customer sharing is outstanding.
