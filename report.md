# Store Isolation Fix Rollout Report (Phase 2B & Verification Gaps)

**Module:** Store Isolation Across Controllers, Raw Queries, and Cache Keys  
**Audit Reference:** `docs/23-store-isolation-controller-audit.md`  
**Status:** All steps complete, Gap 1 & Gap 2 fully closed and verified live  
**Suite State:** 983 passed / 15 failed (5,425 assertions) — exactly +31 passing tests over the 952-pass baseline, 0 new regressions (all 15 remaining failures are the pre-existing parallel SQLite locking and node-check tests).

---

## 1. Executive Summary

Phase 2B addressed the direct query leaks, scoped all cache operations per store, created automated defensive tests for transitive scopes, and resolved the two verification gaps:
1. **Full-suite regression verification (Gap 1):** Verified against the 952-pass baseline. Zero new regressions were introduced. Net pass count increased from 952 to 983.
2. **Cache invalidation same-store freshness verification (Gap 2):** Proven via automated feature tests that whenever an acting store writes/updates/deletes data, its own caches are immediately purged and the subsequent read reflects fresh, live database values.

---

## 2. Changes Implemented (Phase 2B)

### Step 1 — Direct Query-Level Leaks

1. **DashboardController.php (Total Outstanding Due):**
   - **File:** `app/Http/Controllers/DashboardController.php` (lines 104-127)
   - **Fix:** Joined `db_sales` and `db_salesreturn` with explicit `store_id = current_store_id()` filters on raw SQL aggregate queries. Prevents cross-store leakage of outstanding balance calculations.
2. **DashboardController.php (Customers with Due):**
   - **File:** `app/Http/Controllers/DashboardController.php` (lines 256-278)
   - **Fix:** Added `store_id` filter to `db_sales` and `db_salesreturn` in the customer due query.
3. **ReportController.php (Opening Stock):**
   - **File:** `app/Http/Controllers/ReportController.php` (lines 134-140)
   - **Fix:** Routed query through `\App\Models\DbStockAdjustmentItems` (StoreScoped) and added explicit `where('db_stockadjustmentitems.store_id', current_store_id())`.

### Step 2 — Cache Key Scoping & Helper Functions

1. **`store_scoped_cached_list()` Helper:**
   - **File:** `app/Helpers/helpers.php`
   - Scopes list caches by appending `_s{storeId}`:
     - `db_categories_list_s{storeId}`
     - `db_brands_list_s{storeId}`
     - `db_customers_summary_list_s{storeId}`
     - `db_accounts_list_s{storeId}`
2. **Dashboard Cache Invalidation:**
   - **File:** `app/Http/Controllers/DashboardController.php` (`clearDashboardCache`)
   - Invalidates all 8 dashboard cache keys with `_s{storeId}` suffix.
3. **Controller Invalidation Hooks Updated:**
   - `PosController.php`: Lines 609-615 (standard sale) and 1027-1033 (EMI sale) forget `_s{sid}` dashboard keys post-commit.
   - `PurchaseController.php`: Lines 471 (create), 918 (update), 1327 (delete) forget `dashboard_month_purchases_s{sid}`.
   - `SaleController.php`: Line 362 (sale delete calls `clearDashboardCache($storeId)`), lines 784-785 (receive payment), and lines 862-863 (destroy payment) forget `dashboard_outstanding_due_s{sid}` and `dashboard_customers_due_s{sid}`.
   - `SalesReturnController.php`: Lines 483-489 forget all 7 dashboard keys post-commit.
   - `RuleResolverService.php`: Cache keys updated to `sms_rules_{eventType}_s{storeId}` and `clearCache()` updated accordingly.

---

## 3. Verification Gaps Closure

### Gap 1 — Full-Suite Regression Verification (Live Run)

- **Total Tests:** 998 tests
- **Results:** 983 passed, 15 failed (5,425 assertions)
- **Duration:** 80.80s
- **Baseline Comparison:**
  - Baseline: 952 passed / 15 failed
  - Current: 983 passed / 15 failed (+31 passing tests)
  - Zero new regressions.

The 15 failing tests match the known pre-existing parallel-process / `node --check` suite:
- `Tests\Feature\ItemImportTest > genuine parallel import category race creates exactly one`
- `Tests\Feature\ItemsListRedesignTest > items list page inline Alpine script passes node --check`
- `Tests\Feature\MoneyTransferFixesTest > transfer creation genuine parallel concurrency blocks overdraft`
- `Tests\Feature\MoneyTransferFixesTest > delete reversal genuine parallel concurrency with concurrent destination spend`
- `Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented direct destroy`
- `Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented destroy and bulk destroy`
- `Tests\Feature\PosSerialCheckoutValidationTest > genuine parallel checkouts of the same serial: exactly one succeeds`
- `Tests\Feature\SerialUniquenessAcrossEntryPointsTest > concurrent race same item serial exactly one succeeds`
- `Tests\Feature\StockAdjustmentDeleteAndRaceTest > concurrent adjustments same new item warehouse final qty correct`
- `Tests\Feature\StockCreateFormRedesignBrowserCheckTest > transfer create page node check and no leaks`
- `Tests\Feature\StockCreateFormRedesignBrowserCheckTest > adjustment create page node check and no leaks`
- `Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel double delete exactly one succeeds`
- `Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel same store same phone exactly one succeeds`
- `Tests\Feature\SystemAccountRaceConditionTest > concurrent external deposits create exactly one clearing account`
- `Tests\Feature\SystemAccountRaceConditionTest > concurrent account creation with opening balance creates exactly one equity account`

### Gap 2 — Cache Invalidation Same-Store Refresh Verification

Implemented in `tests/Feature/StoreCacheInvalidationFreshnessTest.php`:

| Test | Touch Point Tested | Verification Performed | Status |
|---|---|---|---|
| GAP 2.1 | `DashboardController::clearDashboardCache` | Purges all 8 store keys; subsequent read fetches live DB metrics | PASS |
| GAP 2.2 | `PosController.php:609-615` | POS checkout purges all 7 keys; subsequent read reflects new due | PASS |
| GAP 2.3 | `PosController.php:1027-1033` | EMI sale checkout purges all 7 keys; fresh metrics served | PASS |
| GAP 2.4a | `PurchaseController.php:471` | Purchase create purges month purchases; dashboard shows new total | PASS |
| GAP 2.4b | `PurchaseController.php:918` | Purchase update purges month purchases; dashboard shows updated total | PASS |
| GAP 2.4c | `PurchaseController.php:1327` | Purchase delete purges month purchases; dashboard reflects deletion | PASS |
| GAP 2.5a | `SaleController.php:362` | Sale deletion purges all dashboard keys via `clearDashboardCache` | PASS |
| GAP 2.5b | `SaleController.php:784-785` | Payment receive purges due caches; dashboard due drops by payment | PASS |
| GAP 2.5c | `SaleController.php:862-863` | Payment delete purges due caches; dashboard due increases by payment | PASS |
| GAP 2.6 | `SalesReturnController.php:483-489` | Sales return purges 7 keys; dashboard due decreases by return credit | PASS |
| GAP 2.7 | `RuleResolverService::clearCache` | Rule clearCache purges store key; next resolve loads DB-added rule | PASS |

**Test Execution:** 11 passed (83 assertions) in 3.64s.

---

## 4. Test Suites Consolidated Inventory

1. `tests/Feature/StoreIsolationDirectQueryLeaksTest.php` (3 tests) — PASS
2. `tests/Feature/StoreIsolationCacheScopingTest.php` (9 tests) — PASS
3. `tests/Feature/StoreIsolationTransitiveScopingRegressionTest.php` (8 tests) — PASS
4. `tests/Feature/StoreCacheInvalidationFreshnessTest.php` (11 tests) — PASS
Total new store-isolation automated tests: **31 tests, all passing**.
