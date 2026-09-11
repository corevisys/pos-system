# Phase 2A — Store-Isolation Gap Audit — `app/Http/Controllers` (Discovery Only)

**Status:** AUDIT ONLY — no fixes applied. Findings below are the input for Phase 2B.
**Date:** 2026-09-11
**Scope:** every controller under `app/Http/Controllers` (incl. `Auth/`, `Concerns/`), audited
for the 4 gap classes vs the Phase-1 `StoreScoped` global Eloquent scope.

**Standing rule acknowledged:** this is discovery-only; I will not reverse any documented
Phase-1 decision. No architectural changes proposed here — Phase 2B will be built from this.

---

## 0. Addendum — tables NOT in the StoreScoped set but relevant

- `db_emi_sales`, `db_emi_schedule`: verified **no `store_id` column** (migration
  `2026_02_18_052015_create_db_emi_tables.php`) and no StoreScoped trait. They are child
  tables of `db_sales` and are **transitively scoped via `sale_id`**. So every
  `DB::table('db_emi_sales')` usage (e.g. [`DashboardController.php:67`](app/Http/Controllers/DashboardController.php:67)
  `->whereIn('sale_id', $todaySalesIds)`) is store-safe **because `$todaySalesIds` is scoped** —
  but note that at `:190` the month-sale-ids cache is global, so the transitive guarantee
  depends on the cache fix in §3.1.
- `db_stockentry`: no model, no `store_id`; used only as a delete-block history check keyed on
  the already-scoped item id.

---

## 1. Controller inventory (real, complete — 62 files)

`AccountController`, `AdvanceController`, `BackupController`, `BrandController`,
`CashReconciliationController`, `CategoryController`, `Controller`, `CountryController`,
`CouponController`, `CurrencyController`, `CustomerController`, `CustomerCouponController`,
`DashboardController`, `DepositController`, `DocsController`, `ExpenseCategoryController`,
`ExpenseController`, `GlobalSearchController`, `ItemController`, `LanguageController`,
`LegalController`, `MessageTemplateController`, `PaymentTypeController`, `PosController`,
`ProfileController`, `PurchaseController`, `QuotationController`, `ReportController`,
`RoleController`, `SaleController`, `SaleInvoiceController`, `SalesReturnController`,
`SerialHistoryController`, `ServiceController`, `SitemapController`, `SmsAutoRuleController`,
`SmsCampaignController`, `SmsHistoryController`, `SmsLogController`, `SmsSendController`,
`SmsSettingsController`, `SmsTemplateController`, `SmtpSettingsController`, `StateController`,
`StockAdjustmentController`, `StockTransferController`, `StoreSettingsController`,
`SupplierController`, `TaxController`, `TransactionController`, `TransferController`,
`UnitController`, `UserController`, `VariantController`, `WarehouseController`,
`Auth/AuthenticatedSessionController`, `Auth/ConfirmablePasswordController`,
`Auth/EmailVerificationNotificationController`, `Auth/EmailVerificationPromptController`,
`Auth/NewPasswordController`, `Auth/PasswordController`, `Auth/PasswordResetLinkController`,
`Auth/RegisteredUserController`, `Auth/VerifyEmailController`, `Concerns/ImportsItems`.

**`ReportController` EXISTS** (2,622 lines) — it is already built, not "planned". Separate open item: none.

---

## 2. StoreScoped model/table set (45 models, authoritative)

DbSale, DbSaleItem, DbSalePayment, DbSalesReturn, DbSalesItemReturn, DbSalesPaymentReturn,
DbPurchase, DbPurchaseItem, DbPurchaseReturn, DbPurchaseItemReturn, DbPurchasePayment,
DbPurchasePaymentReturn, DbQuotation, DbQuotationItem, DbCustomer, DbSupplier, DbItem,
DbItemSerial, DbWarehouse, DbWarehouseItem, AcAccount, AcMoneyDeposit, AcMoneyTransfer,
AcTransaction, CashDrawerReconciliation, DbExpense, DbExpenseCategory, DbCategory, DbBrand,
DbVariant, DbUnit, DbTax, DbPaymentType, DbHold, DbHoldItem, DbCoupon, DbCustomerCoupon,
DbCustAdvance, DbStockAdjustment, DbStockAdjustmentItems, DbStockTransfer,
DbStockTransferItems, DbSmsTemplate, DbSmsapi, DbState.

---

## 3. Findings

### 3.1 HIGH RISK — exploitable cross-store leak TODAY (user-facing route)

| Controller | Method | Class | File:line | Current behavior | Risk |
|---|---|---|---|---|---|
| `DashboardController` | `index` | 1 + cache | [`DashboardController.php:104-127`](app/Http/Controllers/DashboardController.php:104) — `DB::table('db_salesreturn')` subquery + outer `DB::table('db_sales')`, **no store_id filter**; `:117` cache key `dashboard_outstanding_due` **not store-keyed** | Store-2 dashboard's "Total Outstanding Due" computed from ALL stores; 5-min global cache → store B renders store A's cached figure. | **HIGH — data leak + cache cross-contamination** |
| `DashboardController` | `index` | 1 + cache | [`DashboardController.php:256-278`](app/Http/Controllers/DashboardController.php:256) — `DB::table('db_sales')` + `leftJoinSub(returnsSubquery)`, no store_id; cache `dashboard_customers_due` global | Top-5 customers-with-due is cross-store. | **HIGH — data leak** |
| `DashboardController` | `index` | cache | [`DashboardController.php:190`](app/Http/Controllers/DashboardController.php:190) — `dashboard_month_sale_ids` not store-keyed (follow-on `DB::table('db_salesitems')` at `:201` uses those ids) | Scoped query but global cache key → 5-min cross-store leak window. | **HIGH (cache)** |
| `DashboardController` | `index` | cache | [`DashboardController.php:283`](app/Http/Controllers/DashboardController.php:283) — `dashboard_month_purchases` global | Cross-store purchase total via shared key. | **HIGH (cache)** |
| `DashboardController` | `getDashboardData` | cache | [`DashboardController.php:324`](app/Http/Controllers/DashboardController.php:324) — `dashboard_chart_<period>` global | Store B sees store A's chart for 5 min. | **HIGH (cache)** |
| `SaleController` | `create`, `edit` | 1 + cache | [`SaleController.php:41,45`](app/Http/Controllers/SaleController.php:41) — `db_categories_list` / `db_brands_list` **not store-keyed** | Scoped query but global cache key → store A's category/brand dropdown served to store B (1 hr). | **HIGH (cache)** |
| `ReportController` | `salesSummary` page, `cashReconciliationReport` page | 1 + cache | [`ReportController.php:1809,1813`](app/Http/Controllers/ReportController.php:1809) — `db_categories_list`, `db_customers_summary_list`; `:2123,2225` `db_accounts_list` global | Same — global cache keys on scoped models → cross-store dropdown data (1 hr). | **HIGH (cache)** |
| `ReportController` | `getProfitLossData` | 1 | [`ReportController.php:134-140`](app/Http/Controllers/ReportController.php:134) — `DB::table('db_items')->join('db_stockadjustmentitems')` **no store_id** | Opening-stock figure aggregates adjustment items across ALL stores (raw query bypasses scope). | **HIGH — data leak** in P&L opening-stock |

### 3.2 MEDIUM — looks suspicious but SAFE (transitive scoping), flag for Phase 2B verification

| Controller | Method | Class | File:line | Behavior | Assessment |
|---|---|---|---|---|---|
| `ReportController` | profit-loss / sales-summary / sales-report / GST / tax / payments data methods | 1 | `DB::table('db_purchaseitems'\|'db_salesitems'\|'db_salesitemsreturn'\|'db_salesreturn'\|'db_salespayments'\|'db_sales'\|'db_emi_sales'\|'db_purchaseitemsreturn')` — e.g. [`ReportController.php:157,185,223,267,1856,1883,1912,1926,1954,2004,2038`](app/Http/Controllers/ReportController.php:1856) | All driven by `$purchaseIds`/`$salesIds`/`$salesReturnIds`/`$accountIds` **from StoreScoped model queries** (DbPurchase/DbSale/DbSalesReturn/AcAccount carry the scope). Raw joins are transitively store-safe (filter by scoped IDs). | **SAFE (transitive)** — no UI-reachable cross-store path. Verify parent ID lists stay scoped during refactor. |
| `ReportController` | `getCashFlowReportData` | 1 | [`ReportController.php:2244-2251`](app/Http/Controllers/ReportController.php:2244) `AcAccount::where('delete_bit',0)` → `$accountIds` drives scoped `DbSalePayment::`/`DbExpense::` | AcAccount scoped → `$accountIds` store-safe; downstream models scoped. Only risk is the global `db_accounts_list` cache key (listed above). | **SAFE (query) / LOW (cache)** |
| `ServiceController` | `edit`, `destroy` | 1 | [`ServiceController.php:244-245,365-374`](app/Http/Controllers/ServiceController.php:244) `DB::table('db_salesitems')…exists()` etc. | `$service->id` already store-scoped (via `DbItem::where('store_id',…)`). History guards are delete-blocks, not leaks. | **SAFE (transitive)** |
| `ItemController` | `destroy` | 1 | [`ItemController.php:801-812`](app/Http/Controllers/ItemController.php:801) `DB::table('db_salesitems')…whereIn('item_id', $itemIds)` | `$itemIds` from scoped DbItem. Delete-block only. | **SAFE (transitive)** |
| `AccountController` | `accountHasBlockingDependencies` | 1 | [`AccountController.php:329-388`](app/Http/Controllers/AccountController.php:329) — `DB::table('ac_moneytransfer'\|'ac_moneydeposits'\|'cash_drawer_reconciliations'\|'db_salespayments'\|'db_purchasepayments'\|'db_expense'\|'db_salespaymentsreturn'\|'db_purchasepaymentsreturn')` | `$accountId` from scoped AcAccount fetch (`:308`). Delete-block, not a leak. | **SAFE (transitive)** |
| `StoreSettingsController` | `edit`, `getStates` | 1 | [`StoreSettingsController.php:50-56,68-72`](app/Http/Controllers/StoreSettingsController.php:50) — `DB::table('db_languages'\|'db_currency'\|'db_country'\|'db_states')` | Lookup/config tables (no per-store data). `db_states` filtered by `country`, store-agnostic. Not a store-scoped leak. | **SAFE** |
| `SalesReturnController` | `create`, `show` | 2 | [`SalesReturnController.php:152-154,238`](app/Http/Controllers/SalesReturnController.php:152) `DbSale::…->findOrFail`; `DbSalesReturn::…->findOrFail` | Both StoreScoped → relations (`items`, `returnItems`, `sale`) inherit scope. | **SAFE** |
| `SaleController` | `edit`, `destroy`, `payments` | 2 | [`SaleController.php:75,291,684`](app/Http/Controllers/SaleController.php:75) | Scoped; `DbItem::find($item->item_id)` operates on scoped parent. | **SAFE** |
| `QuotationController` | `create`, `update`, `convertToSale` | 2 | [`QuotationController.php:133,334,657`](app/Http/Controllers/QuotationController.php:133) `DbItem::findOrFail($cartItem['item_id'])` | StoreScoped → cross-store id → `findOrFail` 404 (correct IDOR guard) / `find` null. | **SAFE** |
| `PosController` | `searchItems`, `store` | 2 | [`PosController.php:131-140,184-196`](app/Http/Controllers/PosController.php:131) `DbItem::…->leftJoin('db_salesitems')` | Base scoped; join targets scoped tables. | **SAFE** |
| `SmsSendController` | `send`, `customers` | 2 | [`SmsSendController.php:137-152`](app/Http/Controllers/SmsSendController.php:137) `DbCustomer::…->whereIn('id', $customerIds)` | Scoped → cross-store ids resolve to nothing. | **SAFE** |

### 3.3 LOW / DEFENSIVE — internal or theoretical only

| Controller | Method | Class | File:line | Behavior | Assessment |
|---|---|---|---|---|---|
| `ServiceController`/`ItemController` | `edit`/`destroy` | 1 | [`ServiceController.php:372`](app/Http/Controllers/ServiceController.php:372), [`ItemController.php:809`](app/Http/Controllers/ItemController.php:809) — `DB::table('db_stockentry')` | `db_stockentry` has **no model** (not in the StoreScoped set); used only as a delete-block history existence check keyed on the already-scoped item. No data returned to user. | **SAFE (defensive)** — no leak; no model to scope. |
| `SerialHistoryController` | `show` | 1 | [`SerialHistoryController.php:66`](app/Http/Controllers/SerialHistoryController.php:66) `DbPurchase::find($serial->purchase_id)` | StoreScoped → cross-store purchase resolves null (lookup already store-scoped at `:49`). | **SAFE** |
| `GlobalSearchController` | search | 2 | all StoreScoped models queried | Scoped ✓ (Phase-1 verified in docs/20). | **SAFE** |

---

## 4. Gap classes — explicit results

- **Gap 1 (raw query-builder bypasses):** `DB::table(...)` found in `ReportController`, `DashboardController`, `ServiceController`, `ItemController`, `AccountController`, `StoreSettingsController`. **HIGH:** Dashboard outstanding-due/customers-due raw `db_sales`+`db_salesreturn` (no store filter) and ReportController P&L opening-stock (`db_items`+`db_stockadjustmentitems`, no store filter). All others transitively safe (scoped parent IDs) or config/lookup tables.
- **Gap 2 (relation/eager-load leaks):** no custom relation with `newQuery()`/`withoutGlobalScopes()` in any controller. All `with([...])` use Eloquent relations that inherit the related model's global scope. `findOrFail` on scoped models is the correct IDOR guard. **No Gap-2 findings.**
- **Gap 3 (`allStores()`/`withoutGlobalScope('store_id')`):** exactly **3 call sites, all documented & justified** — [`SaleInvoiceController.php:31`](app/Http/Controllers/SaleInvoiceController.php:31) (cross-store invoice view), [`PosController.php:1387`](app/Http/Controllers/PosController.php:1387) (serial validation lookup), [`CashReconciliationController.php:220,355`](app/Http/Controllers/CashReconciliationController.php:220) (global-unique code gen). **No accidental/new bypasses.**
- **Gap 4 (raw-SQL ID interpolation):** no `DB::select("... = $id")` string interpolation anywhere. All `DB::raw()` are **aggregate expressions** (SUM/COALESCE) and all `whereRaw()` use **bound parameters** (`?`, `[$val]`). **No Gap-4 findings.**

---

## 5. Prioritized summary for Phase 2B

**Exploitable today (fix first):**
1. `DashboardController` — store-scope the raw `db_sales`/`db_salesreturn` due queries AND make all 5 dashboard cache keys store-keyed.
2. `SaleController::create/edit` + `ReportController` — make the dropdown cache keys store-keyed (`_s{storeId}`), matching the existing `db_taxes_list_{store}` / `db_warehouses_list_s{storeId}` pattern.
3. `ReportController::getProfitLossData` opening-stock — add `store_id` filter (or route through the scoped `DbStockAdjustmentItems` model).

**Verify during refactor (not currently leaking):** the transitively-scoped `DB::table()` report aggregations — ensure the parent ID lists stay StoreScoped.

**Defensive / no action:** `db_stockentry` history guard; `StoreSettingsController` lookup tables; Gap 2/Gap 4 (clean).

---

## 6. Open item (separate from gap-audit)

**`ReportController` already exists** (2,622 lines). No "create ReportController" task is pending. (The original generic plan's reporting module is already implemented; this audit treated it as in-scope and found the Gap-1 items above.)
