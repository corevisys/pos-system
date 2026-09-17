# Multi-Store Follow-ups (deferred / not-yet-done work)

**Last verified:** 2026-09-16
**Status:** the multi-store programme is implemented and green. This file tracks only the items that remain **genuinely deferred or unwired** after the 2026-09-16 audit. Every entry below was re-verified against source in this pass; items that have since shipped were removed rather than left as stale history.

> **Why this file still exists:** the audit found real, verified, still-open work (notably the Owner store-selector UI, which has a controller and routes but no view). See §1.
> Items previously tracked here and now **CLOSED** are listed in §5 so the removal is explicit.

---

## 1. OPEN — Owner store-selector has no UI (ISSUE-14)

**What exists:** `StoreSelectorController::update()` / `clear()` correctly gate on `canViewAllStores()` and persist the acting store to the session under `SetCurrentStore::SESSION_KEY` ([`StoreSelectorController.php`](app/Http/Controllers/StoreSelectorController.php:21), [`…:35`](app/Http/Controllers/StoreSelectorController.php:35), [`…:40`](app/Http/Controllers/StoreSelectorController.php:40)). Both routes are registered inside the authenticated + `ensure.store` group ([`routes/web.php`](routes/web.php:60), [`routes/web.php`](routes/web.php:61)). The middleware that *reads* the session value is wired globally ([`bootstrap/app.php`](bootstrap/app.php:25)).

**What is missing:** **no Blade view posts to either route.** A content search across all of `resources/views` for `store-context`, `store_context`, `Acting store`, `acting store`, `switch store` returned **0 results**. The sidebar only renders links for `canViewAllStores()` ([`app.blade.php`](resources/views/layouts/app.blade.php:153)).

**Consequence:** the Owner cannot actually switch the acting store from the UI. The plumbing underneath is complete and tested ([`SetCurrentStoreMiddlewareTest.php`](tests/Feature/SetCurrentStoreMiddlewareTest.php:1), [`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:1)), so this is a UI-wiring task only.

**Suggested approach:** add a store `<select>` to the app layout header (or the consolidated report pages) rendered only when `auth()->user()->canViewAllStores()`, POSTing to `store.context.update`; optionally a "revert to my store" control POSTing/DELETEing `store.context.clear`. Display the currently acting store (readable from `app(StoreContext::class)`).

---

## 2. OPEN — Store settings ignore the acting store (ISSUE-15)

`StoreSettingsController::resolveActingStore()` reads `auth()->user()->store_id` **directly** ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:25)) instead of going through `current_store_id()` ([`helpers.php`](app/Helpers/helpers.php:157)).

**Consequence:** even after switching (once §1 is done), an Owner — whose `users.store_id` is only nominal ([`OwnerDeveloperSeeder.php`](database/seeders/OwnerDeveloperSeeder.php:33)) — would still be served their own nominal store's settings, not the chosen branch's. `EnsureUserHasStore` already exempts the Owner ([`EnsureUserHasStore.php`](app/Http/Middleware/EnsureUserHasStore.php:38)), so the guard is not the obstacle.

**Suggested approach:** resolve via `current_store_id()` while keeping the existing "store must exist" guard, and keep the `store_settings_view` permission gate ([`StoreSettingsController.php`](app/Http/Controllers/StoreSettingsController.php:45)).

---

## 3. OPEN — `config/sms.php` does not exist (ISSUE-12)

Two runtime reads have no configuration backing:

- `config('sms.sandbox', false)` — the sandbox-provider override ([`SmsService.php`](app/SMS/Services/SmsService.php:24)).
- `config("sms.throttle.{$provider}", 5)` — the per-provider rate limit ([`SendSingleSmsJob.php`](app/Jobs/SendSingleSmsJob.php:39)).

Because `config/` contains no `sms.php` (files present: app, auth, backup, cache, database, filesystems, logging, mail, queue, sales, services, session), both always fall back to their inline defaults and cannot be tuned from `.env` or config. A test asserts the sandbox branch *works when the key is present* ([`SmsProviderSelectorTest.php`](tests/Feature/SmsProviderSelectorTest.php:1)), so only the production-config surface is missing.

**Suggested approach:** add `config/sms.php` exposing `sandbox` and a `throttle` array keyed by provider name.

---

## 4. OPEN (low priority)

| # | Item | Evidence | Note |
|---|---|---|---|
| 4.1 | **Duplicate migration timestamp** `2026_09_13_000003` shared by two migrations. | [`…clear_is_super_admin…`](database/migrations/2026_09_13_000003_clear_is_super_admin_from_branch_admin_roles.php:1), [`…grant_database_backup…`](database/migrations/2026_09_13_000003_grant_database_backup_permission_to_existing_roles.php:1) | Both run (Laravel orders by filename) but the tie is a latent ordering hazard. Rename one to `000007` or similar. |
| 4.2 | **EMI tables carry no `store_id`.** `db_emi_sales` / `db_emi_schedule` rely on transitive isolation via `db_sales.store_id`. | [`create_db_emi_tables.php`](database/migrations/2026_02_18_052015_create_db_emi_tables.php:14), [`DbEmiSale.php`](app/Models/DbEmiSale.php:8) | Correct today; adding an explicit `store_id` would remove reliance on the join. |
| 4.3 | **No DB-level per-store unique on `db_customers.mobile`.** Uniqueness is validation-only. | [`create_db_customers_table.php`](database/migrations/2026_02_07_085626_create_db_customers_table.php:62) vs [`CustomerController.php`](app/Http/Controllers/CustomerController.php:141) | `db_suppliers` does have the composite index; symmetric hardening would be a migration. |
| 4.4 | **Font Awesome not loaded in the app layout** though `app.js` builds an FA spinner. | [`app.js`](resources/js/app.js:50) vs [`app.blade.php`](resources/views/layouts/app.blade.php:21) | Load FA in the app layout, or swap the spinner for an inline SVG. |
| 4.5 | **Per-store invoice templates** not implemented. | gap analysis §7 | Optional — only if document branding becomes a requirement. |
| 4.6 | **Consolidated Owner customer/due rollup** deliberately not built. Customer sharing is shared-identity-only, so a cross-store customer view was explicitly excluded. | [`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:20) | Deferred by decision, not by omission. |
| 4.7 | **Identity merge tooling** for corrected phone numbers is not built. | [`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29) | A phone correction creates a new identity today; no merge/relink tool exists. |
| 4.8 | **Windows-hardcoded MySQL dump path** `C:/xampp/mysql/bin/`. | [`config/database.php`](config/database.php:65) | Non-portable; env-overridable via `DUMP_BINARY_PATH` ([`AppServiceProvider.php`](app/Providers/AppServiceProvider.php:28)). |

---

## 5. CLOSED since the previous audit (removed from the tracker, kept here for traceability)

| Previously tracked | Resolution | Evidence |
|---|---|---|
| **Phase 4.2 — stock source of truth** ("drop the column") | Resolved pragmatically: `DbItem::availableStock()` is the single canonical rule; `db_items.stock` is retained as authoritative for warehouse-less items and never zeroed. `syncGlobalStock()` is the single aggregation. | [`DbItem.php`](app/Models/DbItem.php:114), [`DbItem.php`](app/Models/DbItem.php:148), [`ItemStockSourceOfTruthTest.php`](tests/Feature/ItemStockSourceOfTruthTest.php:1) |
| **Stock read-site gap in search endpoints** | Fixed in POS, Stock Adjustment and Stock Transfer search, all routed through `availableStock()`. | [`PosController.php`](app/Http/Controllers/PosController.php:230), [`StockAdjustmentController.php`](app/Http/Controllers/StockAdjustmentController.php:648), [`StockTransferController.php`](app/Http/Controllers/StockTransferController.php:660), [`PosItemSearchStockTest.php`](tests/Feature/PosItemSearchStockTest.php:1), [`StockSearchStockTest.php`](tests/Feature/StockSearchStockTest.php:1) |
| **Phase 4.3 — `purchase_return` numbering** | Routed through `generateSequential()` with prefix `PR`. | [`CodeGeneratorService.php`](app/Services/CodeGeneratorService.php:167), [`CodeGeneratorServiceTest.php`](tests/Feature/CodeGeneratorServiceTest.php:1) |
| **Phase 4.1 — dead `to_store_id` columns** | Dropped from both tables (with index cleanup) and removed from `$fillable`. | [`2026_09_13_000005`](database/migrations/2026_09_13_000005_drop_dead_to_store_id_columns.php:27) |
| **Phase 5 — customer sharing decision** | Decided (shared identity only) and implemented. | [`2026_09_13_000006`](database/migrations/2026_09_13_000006_create_db_customer_identities_table.php:25), [`CustomerIdentityResolver.php`](app/Services/CustomerIdentityResolver.php:29), [`SharedCustomerIdentityTest.php`](tests/Feature/SharedCustomerIdentityTest.php:1) |
| **Phase 2 — three-role model** | Branch Admin / Owner / Developer implemented; branch-admin global privilege cleared. | [`RolePermissionSeeder.php`](database/seeders/RolePermissionSeeder.php:169), [`ThreeRoleModelTest.php`](tests/Feature/ThreeRoleModelTest.php:1) |
| **Phase 3.2 — Owner-gated consolidated reporting** | Ledger + stock consolidated endpoints, Owner-gated, with per-store filter. | [`ConsolidatedReportController.php`](app/Http/Controllers/ConsolidatedReportController.php:25), [`…:69`](app/Http/Controllers/ConsolidatedReportController.php:69), [`ConsolidatedReportingTest.php`](tests/Feature/ConsolidatedReportingTest.php:1) |
| **Acting-store context / `SetCurrentStore`** | Implemented as a `web`-group middleware + `StoreContext` singleton. | [`bootstrap/app.php`](bootstrap/app.php:25), [`SetCurrentStore.php`](app/Http/Middleware/SetCurrentStore.php:39), [`SetCurrentStoreMiddlewareTest.php`](tests/Feature/SetCurrentStoreMiddlewareTest.php:1) |
| **Invalid inline JS + failing concurrency tests** | The full SQLite suite now passes with **0 failures**. | see §6 |

---

## 6. Verification commands & latest result

```
# SQLite (default) — run 2026-09-16
php artisan test --parallel
#   Tests:    1346 passed (6479 assertions)
#   Duration: 149.92s
#   Parallel: 12 processes
```

```
# MySQL gate (dedicated throwaway database; never the dev DB)
php -r "$p=new PDO('mysql:host=127.0.0.1;port=3306','root','');$p->exec('DROP DATABASE IF EXISTS laravelpos_test');$p->exec('CREATE DATABASE laravelpos_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');"
php vendor\bin\pest -c phpunit.mysql.xml
```

**Result (2026-09-16):**

```
Tests:    16 skipped, 1330 passed (6392 assertions)
Duration: 336.42s
```

The self-contained SQLite concurrency/migration harnesses are reported as **skipped** on MySQL by design, via `skipUnlessSqlite()` ([`tests/Pest.php`](tests/Pest.php:91)); the 16 skips match the 16 `skipUnlessSqlite()` call sites in test bodies. **Zero failures.**
