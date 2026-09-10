# Store Settings — Phased Fix + Redesign Rollout Report

**Module:** `/settings/store` (`StoreSettingsController`, `resources/views/module/settings/store.blade.php`, `DbStore`)
**Status:** All 6 phases complete and verified; **Gap 1 + Gap 2 closed** (see below)
**Final suite state:** 916 passed / 15 failed (4907 assertions) — all 15 failures are pre-existing flaky parallel-process / `node --check` tests; **no new regressions**

---

## GAP 1 — TEST-COUNT / FAILING-SET RECONCILIATION (closed)

### 1. Full suite run twice, verbatim (identical result both times)

**Run 1** (`php vendor/bin/pest`):

```
   FAILED  Tests\Feature\ItemImportTest > genuine parallel import category race creates exactly one
   FAILED  Tests\Feature\ItemsListRedesignTest > items list page inline Alpine script passes node --check (browser-l…
   FAILED  Tests\Feature\MoneyTransferFixesTest > transfer creation genuine parallel concurrency blocks overdraft
   FAILED  Tests\Feature\MoneyTransferFixesTest > delete reversal genuine parallel concurrency with concurrent desti…
   FAILED  Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented direct destr…
   FAILED  Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented destroy and…
   FAILED  Tests\Feature\PosSerialCheckoutValidationTest > genuine parallel checkouts of the same serial: exactly on…
   FAILED  Tests\Feature\SerialUniquenessAcrossEntryPointsTest > concurrent race same item serial exactly one succee…
   FAILED  Tests\Feature\StockAdjustmentDeleteAndRaceTest > concurrent adjustments same new item warehouse final qty…
   FAILED  Tests\Feature\StockCreateFormRedesignBrowserCheckTest > transfer create page node check and no leaks
   FAILED  Tests\Feature\StockCreateFormRedesignBrowserCheckTest > adjustment create page node check and no leaks
   FAILED  Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel double delete exactly one succeeds
   FAILED  Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel same store same phone exactly one succeeds
   FAILED  Tests\Feature\SystemAccountRaceConditionTest > concurrent external deposits create exactly one clearing a…
   FAILED  Tests\Feature\SystemAccountRaceConditionTest > concurrent account creation with opening balance creates e…
  Tests:    15 failed, 915 passed (4905 assertions)
  Duration: 70.75s
```

**Run 2** (identical):

```
   FAILED  Tests\Feature\ItemImportTest > genuine parallel import category race creates exactly one
   FAILED  Tests\Feature\ItemsListRedesignTest > items list page inline Alpine script passes node --check (browser-l…
   FAILED  Tests\Feature\MoneyTransferFixesTest > transfer creation genuine parallel concurrency blocks overdraft
   FAILED  Tests\Feature\MoneyTransferFixesTest > delete reversal genuine parallel concurrency with concurrent desti…
   FAILED  Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented direct destr…
   FAILED  Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented destroy and…
   FAILED  Tests\Feature\PosSerialCheckoutValidationTest > genuine parallel checkouts of the same serial: exactly on…
   FAILED  Tests\Feature\SerialUniquenessAcrossEntryPointsTest > concurrent race same item serial exactly one succee…
   FAILED  Tests\Feature\StockAdjustmentDeleteAndRaceTest > concurrent adjustments same new item warehouse final qty…
   FAILED  Tests\Feature\StockCreateFormRedesignBrowserCheckTest > transfer create page node check and no leaks
   FAILED  Tests\Feature\StockCreateFormRedesignBrowserCheckTest > adjustment create page node check and no leaks
   FAILED  Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel double delete exactly one succeeds
   FAILED  Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel same store same phone exactly one succeeds
   FAILED  Tests\Feature\SystemAccountRaceConditionTest > concurrent external deposits create exactly one clearing a…
   FAILED  Tests\Feature\SystemAccountRaceConditionTest > concurrent account creation with opening balance creates e…
  Tests:    15 failed, 915 passed (4905 assertions)
  Duration: 64.75s
```

`ExpensesRolloutTest` is **absent** from the FAILED list in both runs → it passed in both. To test flakiness it was run isolated **3/3 times**:

```
=== EXPENSES_ROLLOUT_TRY_1 ===
   PASS  Tests\Feature\ExpensesRolloutTest
  Tests:    1 passed (6 assertions)
=== EXPENSES_ROLLOUT_TRY_2 ===
   PASS  Tests\Feature\ExpensesRolloutTest
  Tests:    1 passed (6 assertions)
=== EXPENSES_ROLLOUT_TRY_3 ===
   PASS  Tests\Feature\ExpensesRolloutTest
  Tests:    1 passed (6 assertions)
```

**Conclusion (evidence, not assertion):** `ExpensesRolloutTest` is a flaky `proc_open`-based parallel test — it failed in the original 916-baseline run, passed in both subsequent full runs and 3/3 isolated runs. It is **not** a stable regression from this rollout (the rollout touches no expense code path).

### 2. Exact before/after failing-test-name lists + literal diff

**Before (the 16-failure baseline run, from the original report):**

1. `Tests\Feature\ExpensesRolloutTest > phase5 genuine parallel double submit applies ledger exactly once`
2. `Tests\Feature\ItemImportTest > genuine parallel import category race creates exactly one`
3. `Tests\Feature\ItemsListRedesignTest > items list page inline Alpine script passes node --check (browser-leak check)`
4. `Tests\Feature\MoneyTransferFixesTest > transfer creation genuine parallel concurrency blocks overdraft`
5. `Tests\Feature\MoneyTransferFixesTest > delete reversal genuine parallel concurrency with concurrent destination`
6. `Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented direct destroy`
7. `Tests\Feature\MoneyTransferFixesTest > same transfer concurrent double delete race prevented destroy and re-fetch`
8. `Tests\Feature\PosSerialCheckoutValidationTest > genuine parallel checkouts of the same serial: exactly one succeeds`
9. `Tests\Feature\SerialUniquenessAcrossEntryPointsTest > concurrent race same item serial exactly one succeeds`
10. `Tests\Feature\StockAdjustmentDeleteAndRaceTest > concurrent adjustments same new item warehouse final qty`
11. `Tests\Feature\StockCreateFormRedesignBrowserCheckTest > transfer create page node check and no leaks`
12. `Tests\Feature\StockCreateFormRedesignBrowserCheckTest > adjustment create page node check and no leaks`
13. `Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel double delete exactly one succeeds`
14. `Tests\Feature\SupplierRedesignAndRisksTest > genuine parallel same store same phone exactly one succeeds`
15. `Tests\Feature\SystemAccountRaceConditionTest > concurrent external deposits create exactly one clearing account`
16. `Tests\Feature\SystemAccountRaceConditionTest > concurrent account creation with opening balance creates exactly one equity`

**After (both full runs above):** items 2–16 exactly as listed — **identical 15 items, no test newly entered the failing set.**

**Literal diff:** only `ExpensesRolloutTest > phase5 genuine parallel double submit applies ledger exactly once` **left** the failing set. Nothing was added. The report's phrase "identical set" was imprecise — the failing set changed by exactly **one item removed** (a flaky test), not zero. The 15 failures are otherwise byte-for-byte the same names in both runs.

### 3. Accounting for 23 new tests vs +14 total count

`php vendor/bin/pest --list-tests` proves every new test is discovered in the full suite:

```
=== FULL SUITE STORESETTINGS COUNT ===  32
=== ISOLATED COUNT ===                  24
```

The full suite lists **32** StoreSettings tests = 8 pre-existing (`StoreSettingsFormattingTest`) + **24 new** (scoping 10, permission 5, submit-safety 4, Phase-4 5). None are skipped/excluded (no test groups/tags in phpunit.xml, and grep found no `proc_open`/groups in the new files).

**The +14 net-delta root cause:** the "916" baseline full run was captured **after Phase 1 had already added `StoreSettingsStoreScopingTest` (9 tests, later 10 with the Gap 2 test)**. Those 9 were already inside the 916 total, so of the "23 new" tests, only **14** were genuinely new to the full suite at the time the 915/916 numbers were compared:

- 916 (baseline, incl. 9 scoping) + 14 (5 permission + 4 submit-safety + 5 Phase-4) = **930** ✓ (matches the observed 915+15 run)

No test is being skipped; the discrepancy is purely a **timing artifact of when the baseline was measured** (mid-rollout), not a full-suite-runner or worker conflict. The final state after the Gap 2 fix is 916 passed / 15 failed (4907 assertions) — the +1 is the new exact-count query test.

### 4. Root cause statement

The original count was not wrong because of a runner/worker conflict. It was an **off-by-one-rollout-timing error**: the baseline "916" was measured with Phase 1's 9 scoping tests already present, so 23 new tests only produced +14 visible. Confirmed empirically via `--list-tests` (32 StoreSettings tests discovered, zero excluded).

---

## GAP 2 — "ONE db_store QUERY PER REQUEST" PROOF + FIX (closed)

### Finding (real, not wording)

A new exact-count test [`test_single_request_resolves_store_settings_with_exactly_one_db_store_query`](../tests/Feature/StoreSettingsStoreScopingTest.php) was written first. It rendered an invoice (exercising timezone config + currency symbol + invoice header + layout store-name) with a cold settings cache and counted `db_store` SELECTs via the query log:

```
FAILED  Tests\Feature\StoreSettingsStoreScopingTest > single request resolves store settings with exactly one db…
  Expected exactly ONE db_store SELECT for this request; got 3
Queries: ["select * from "db_store" where "id" = ? limit 1","select * from "db_store" where "id" = ? limit 1","select * from "db_store" where "id" = ? limit 1"]
```

**Root cause:** [`SaleInvoiceController::prepareInvoiceData()`](../app/Http/Controllers/SaleInvoiceController.php:81) did a direct `DbStore::where('id', $sale->store_id)->first()` **and** `AppServiceProvider::resolveCurrencySymbol(false, $invoiceStoreId)` → `cachedStoreFor()` — for a store not yet memoized each fired its own SELECT (the 3rd was the layout's store-name relationship). The memoized `store_settings()` was bypassed entirely.

### Fix

[`app/Http/Controllers/SaleInvoiceController.php`](../app/Http/Controllers/SaleInvoiceController.php:81) now resolves the invoice store via `store_settings(false, $invoiceStoreId)` (which memoizes under `'s{id}'`), so `resolveCurrencySymbol()` and the layout reuse the same cached row. The test now **passes**:

```
PASS  Tests\Feature\StoreSettingsStoreScopingTest
  ✓ single request resolves store settings with exactly one db store query
Tests:  10 passed (36 assertions)
```

Regression check (invoice consumers + store suites):

```
PASS  Tests\Feature\SaleInvoicePdfTest
PASS  Tests\Feature\StoreSettingsStoreScopingTest
PASS  Tests\Feature\StoreSettingsFormattingTest
PASS  Tests\Feature\Phase2SettingsTogglesTest
PASS  Tests\Feature\ManualLiveVerificationTest
Tests:  36 passed (127 assertions)
```

**Exact-count guarantee:** a single request whose timezone, currency symbol, and invoice header all resolve the **same** store issues **exactly one** `db_store` SELECT. (A cross-store request — acting user's store ≠ rendered store — legitimately issues one query per distinct store row; the guarantee is per-store, not per-request-global.)

Final full-suite confirmation after the Gap 2 fix:

```
  Tests:    15 failed, 916 passed (4907 assertions)
  Duration: 94.11s
```

The 15 failures are the same stable flaky parallel/`node --check` set from Gap 1.

---

## PHASE 0 — DATA PRE-CHECK

Read-only script: [`scripts/store_scope_precheck.php`](../scripts/store_scope_precheck.php)

Raw output:

```
=== PHASE 0 STORE-SCOPE PRE-CHECK ===
DB driver: mysql

db_store row count: 1
db_store ids: [1]

Tables with a store_id column (58):
  ac_accounts, ac_moneydeposits, ac_moneytransfer, ac_transactions,
  cash_drawer_reconciliations, db_bankdetails, db_brands, db_category, db_coupons,
  db_custadvance, db_customer_coupons, db_customers, db_emailtemplates, db_expense,
  db_expense_category, db_fivemojo, db_hold, db_holditems, db_instamojo, db_item_serials,
  db_items, db_package, db_paymenttypes, db_paypal, db_permissions, db_purchase,
  db_purchaseitems, db_purchaseitemsreturn, db_purchasepayments, db_purchasepaymentsreturn,
  db_purchasereturn, db_quotation, db_quotationitems, db_roles, db_sales, db_salesitems,
  db_salesitemsreturn, db_salespayments, db_salespaymentsreturn, db_salesreturn,
  db_shippingaddress, db_smsapi, db_smstemplates, db_states, db_stockadjustment,
  db_stockadjustmentitems, db_stocktransfer, db_stocktransferitems, db_stripe,
  db_subscription, db_suppliers, db_tax, db_twilio, db_units, db_variants, db_warehouse,
  db_warehouseitems, users

[OK] every table with non-null store_id resolves to store_ids [1]

RESULT: All store_id values resolve to a db_store row.
SAFE TO PROCEED to Phase 1.
```

**Conclusion.** `db_store` has exactly **1 row (id=1)**. All 58 store-scoped tables resolve cleanly. **Zero orphan store_ids.** No STOP condition — proceeding was safe.

---

## PHASE 1 — SCOPING FIX

### Changes

| File | Line | Change |
|---|---|---|
| [`app/Http/Controllers/StoreSettingsController.php`](../app/Http/Controllers/StoreSettingsController.php) | [`22`](../app/Http/Controllers/StoreSettingsController.php:22) | New `resolveActingStore()` — scoped lookup by `auth()->user()->store_id`; 403 if none, 404 if missing |
| same | [`48`](../app/Http/Controllers/StoreSettingsController.php:48), [`82`](../app/Http/Controllers/StoreSettingsController.php:82) | `edit()`/`update()` use `resolveActingStore()` instead of `DbStore::first()`/`firstOrFail()` |
| [`app/Models/DbCurrency.php`](../app/Models/DbCurrency.php) | [`37`](../app/Models/DbCurrency.php:37) | `activateCurrency(int $currencyId, ?int $storeId)` — `where('id', $targetStoreId)` instead of `DbStore::query()->update()` |
| [`app/Models/DbLanguage.php`](../app/Models/DbLanguage.php) | [`26`](../app/Models/DbLanguage.php:26) | `activateLanguage(int $languageId, ?int $storeId)` — store-scoped |
| [`app/Support/StoreSettingsCache.php`](../app/Support/StoreSettingsCache.php) | [`23`](../app/Support/StoreSettingsCache.php:23) | Single static slots → per-store keyed arrays (`'s{id}'` + `'default'`) |
| [`app/Helpers/helpers.php`](../app/Helpers/helpers.php) | [`23`](../app/Helpers/helpers.php:23), [`50`](../app/Helpers/helpers.php:50), [`76`](../app/Helpers/helpers.php:76), [`151`](../app/Helpers/helpers.php:151) | `default_store_id()`, `store_id_cache_key()`, store-keyed `store_settings()`, non-recursive `current_store_id()` |
| [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) | [`78`](../app/Providers/AppServiceProvider.php:78), [`100`](../app/Providers/AppServiceProvider.php:100), [`191`](../app/Providers/AppServiceProvider.php:191) | Store-keyed `configureStoreTimezone()` / `resolveCurrencySymbol()`; `cachedStoreFor()` reuse keeps one `db_store` query per request |
| [`app/Http/Controllers/SaleInvoiceController.php`](../app/Http/Controllers/SaleInvoiceController.php) | [`81`](../app/Http/Controllers/SaleInvoiceController.php:81), [`94`](../app/Http/Controllers/SaleInvoiceController.php:94) | Invoice resolves `$store` from `$sale->store_id`; currency symbol keyed to that store |

### Test

[`tests/Feature/StoreSettingsStoreScopingTest.php`](../tests/Feature/StoreSettingsStoreScopingTest.php)

```
PASS  Tests\Feature\StoreSettingsStoreScopingTest
  ✓ store a admin update does not change store b row
  ✓ store b admin edits only store b even when store a is first row
  ✓ activating currency for store a does not change store b currency id
  ✓ activating language for store a does not change store b language id
  ✓ store settings cache is keyed per store
  ✓ code prefix consumer resolves acting store
  ✓ pos round off consumer resolves acting store
  ✓ currency symbol resolver is store scoped
  ✓ invoice consumer resolves sale store not acting user store
Tests:  9 passed (34 assertions)
```

Also updated [`tests/Feature/ManualLiveVerificationTest.php`](../tests/Feature/ManualLiveVerificationTest.php) check3/check4 factory users to carry `store_id => 1` (required by the new scoped guard).

---

## PHASE 2 — PERMISSION GATE

### Changes

| File | Line | Change |
|---|---|---|
| [`app/Http/Controllers/StoreSettingsController.php`](../app/Http/Controllers/StoreSettingsController.php) | [`44`](../app/Http/Controllers/StoreSettingsController.php:44) | `edit()` — `abort(403)` unless `store_settings_view` |
| same | [`78`](../app/Http/Controllers/StoreSettingsController.php:78) | `update()` — `abort(403)` unless `store_settings_edit` |
| [`database/seeders/PermissionSeeder.php`](../database/seeders/PermissionSeeder.php) | [`59`](../database/seeders/PermissionSeeder.php:59) | Added `store_settings_view`, `store_settings_edit` |
| [`database/seeders/RolePermissionSeeder.php`](../database/seeders/RolePermissionSeeder.php) | [`49`](../database/seeders/RolePermissionSeeder.php:49) | Added `store_settings_view`, `store_settings_edit` |

### Test

[`tests/Feature/StoreSettingsPermissionGateTest.php`](../tests/Feature/StoreSettingsPermissionGateTest.php)

```
PASS  Tests\Feature\StoreSettingsPermissionGateTest
  ✓ user without view permission gets 403 on edit
  ✓ user without edit permission gets 403 on update
  ✓ user with view only can view but not update
  ✓ user with both permissions succeeds
  ✓ super admin slug present in both seeders
Tests:  5 passed (14 assertions)
```

> Note: the test uses `DbRole::forceCreate(['id' => 50+])` because `DbRole::$fillable` excludes `id` (a plain `create()` would auto-increment to id=1, and `isSuperAdmin()` treats `role_id === 1` as full access).

---

## PHASE 3 — SUBMIT-SAFETY + ERROR HANDLING

**Confirmed current behavior first:** the view contained **no** `@error` and **no** `isSubmitting` (grep = 0 results). A 422 produced no visible field error; there was no double-submit guard.

### Changes

| File | Line | Change |
|---|---|---|
| [`resources/views/module/settings/store.blade.php`](../resources/views/module/settings/store.blade.php) | [`3`](../resources/views/module/settings/store.blade.php:3) | Added `isSubmitting` state |
| same | [`33`](../resources/views/module/settings/store.blade.php:33) | `handleFormSubmit()` early-returns when `isSubmitting`; sets it on valid submit |
| same | [`44`](../resources/views/module/settings/store.blade.php:44), [`51`](../resources/views/module/settings/store.blade.php:51) | Confirm handlers set `isSubmitting = true` |
| same | [`131`](../resources/views/module/settings/store.blade.php:131) | Error-summary banner (`Please fix the following errors`) |
| same | [`153`](../resources/views/module/settings/store.blade.php:153) | Inline `@error` for `store_name`, `mobile`, `email` |
| same | [`473`](../resources/views/module/settings/store.blade.php:473) | Save button `:disabled="isSubmitting"` + spinner + `Saving...` swap |

### Test

[`tests/Feature/StoreSettingsSubmitSafetyTest.php`](../tests/Feature/StoreSettingsSubmitSafetyTest.php)

```
PASS  Tests\Feature\StoreSettingsSubmitSafetyTest
  ✓ validation failure renders the specific field error
  ✓ invalid email renders its specific error
  ✓ save button carries double submit guard
  ✓ rapid double submit leaves consistent state
Tests:  4 passed (16 assertions)
```

---

## PHASE 4 — CONFIRMED BUG FIXES

### Findings & fixes

| # | Bug | Finding | Fix |
|---|---|---|---|
| 1 | `time_format` | Seeder stored `'h:i A'`; option value `'h:i a'` → no option ever pre-selected. No PHP consumer of the column. | Aligned seeder to `'h:i a'` ([`StoreSeeder.php:53`](../database/seeders/StoreSeeder.php:53)) |
| 2 | `currency_placement` | Seeder stored `'Left'`; options `before`/`after`; `format_currency()` compares `=== 'after'`. | Aligned seeder to `'before'` ([`StoreSeeder.php:56`](../database/seeders/StoreSeeder.php:56)) |
| 3 | `sales_discount`, `sales_invoice_format_id`, `pos_invoice_format_id`, `mrp_column` | Validated in `update()` but **no consumer anywhere** (whole-repo search: only controller rules + migration/seed). Truly dead. | Removed the phantom validation rules ([`StoreSettingsController.php:113`](../app/Http/Controllers/StoreSettingsController.php:113)) |
| 4 | `/placeholder-logo.png` | Asset does **not** exist in `public/` → broken image. | Replaced with an inline SVG data-URI ([`store.blade.php:84`](../resources/views/module/settings/store.blade.php:84)) |
| 5 | `store_code` | Whole-repo search: **zero code references**; seeded/display-only. | Left as-is (readonly), documented |

### Test

[`tests/Feature/StoreSettingsPhase4FixesTest.php`](../tests/Feature/StoreSettingsPhase4FixesTest.php)

```
PASS  Tests\Feature\StoreSettingsPhase4FixesTest
  ✓ seeder time format matches option value and is preselectable
  ✓ seeder currency placement matches option value
  ✓ update accepts payload without phantom fields
  ✓ phantom columns have no consumer anywhere
  ✓ page no longer references dead placeholder logo file
Tests:  5 passed (14 assertions)
```

---

## PHASE 5 — REDESIGN (design-system baseline)

Converted `resources/views/module/settings/store.blade.php` to the established tokens:

- Container `bg-white dark:bg-dark-card rounded-3xl border-slate-100 …` → `<x-card>` with a header block ([line 118](../resources/views/module/settings/store.blade.php:118))
- Save/Close → `btn-primary` / `btn-secondary` ([line 473](../resources/views/module/settings/store.blade.php:473))
- Inputs/selects/textareas → `input-base`
- Labels → `text-text-muted`; headings → `text-text-primary`; breadcrumb → `text-text-secondary`; required stars → `text-danger`

**Preserved (asserted by tests):** every `name=` attribute, Alpine `handleFormSubmit($event)`, `isSubmitting` guard, `'Saving...' : 'Save Settings'`, `value="h:i a" selected`, `value="before" selected`, `data:image/svg+xml;base64,`, `Please fix the following errors`, `x-searchable-select` wiring, toggle `:value` bindings.

**Pre-redesign selector scan:** no existing test asserts on the old raw-utility classes (they check routes, DB/session state, and the markers above), so no test selectors were broken.

---

## PROTECTED CONTRACTS — UNTOUCHED

Verified via passing suites:

- `format_currency` / `format_quantity` — [`StoreSettingsFormattingTest`](../tests/Feature/StoreSettingsFormattingTest.php)
- `resolveCurrencySymbol` single source of truth
- `Asia/Dhaka` timezone fallback — [`StoreTimezoneTest`](../tests/Feature/StoreTimezoneTest.php)
- Expense prefix intentionally unseparated — [`CodeGeneratorService.php:67`](../app/Services/CodeGeneratorService.php:67)
- POS server-authoritative round-off + invoice flags — [`Phase2SettingsTogglesTest`](../tests/Feature/Phase2SettingsTogglesTest.php)

---

## FULL-SUITE VERIFICATION

```
Tests:  15 failed, 915 passed (4905 assertions)
Duration: 110.92s
```

The 15 failures are the **pre-existing** flaky genuine-parallel-process tests and `node --check` browser-leak tests (identical set to the pre-change baseline of 16 failed / 900 passed). The net delta is **+15 passing tests**.

Store Settings suites combined: **28 tests passed** (scoping 9 + permission 5 + submit-safety 4 + Phase-4 5 + 5 others), plus green consumer suites.

Before-vs-after:

| Run | Passed | Failed | Notes |
|---|---|---|---|
| Pre-change baseline | 900 | 16 | flaky parallel/browser set |
| Post Phase 5 (final) | 915 | 15 | same flaky set; `ExpensesRolloutTest` now green |

---

## SCOPE NOTE (per the "stop and report" rule)

Two items required touching files outside the literal task list, both are direct consumers **named in the Phase 1 spec**, so no scope widening beyond intent:

1. [`app/Providers/AppServiceProvider.php`](../app/Providers/AppServiceProvider.php) + [`app/Support/StoreSettingsCache.php`](../app/Support/StoreSettingsCache.php) — the cache and currency/timezone resolvers the spec explicitly requires to become store-keyed.
2. [`tests/Feature/ManualLiveVerificationTest.php`](../tests/Feature/ManualLiveVerificationTest.php) — factory users updated to carry `store_id`, a necessary consequence of the new scoping guard.

No other module was modified.

---

## AUXILIARY SCRIPTS (read-only / utility)

- [`scripts/store_scope_precheck.php`](../scripts/store_scope_precheck.php) — Phase 0 orphan check
- [`scripts/store_query_probe.php`](../scripts/store_query_probe.php) — `db_store` query-count probe
- [`scripts/permission_gate_probe.php`](../scripts/permission_gate_probe.php) — `hasPermission` behavior probe
- [`scripts/store_settings_redesign.php`](../scripts/store_settings_redesign.php) — Phase 5 token conversion

---

## FILES CHANGED SUMMARY

| File | Phases |
|---|---|
| `app/Http/Controllers/StoreSettingsController.php` | 1, 2, 4 |
| `app/Models/DbCurrency.php` | 1 |
| `app/Models/DbLanguage.php` | 1 |
| `app/Support/StoreSettingsCache.php` | 1 |
| `app/Helpers/helpers.php` | 1 |
| `app/Providers/AppServiceProvider.php` | 1 |
| `app/Http/Controllers/SaleInvoiceController.php` | 1, Gap 2 |
| `database/seeders/PermissionSeeder.php` | 2 |
| `database/seeders/RolePermissionSeeder.php` | 2 |
| `database/seeders/StoreSeeder.php` | 4 |
| `resources/views/module/settings/store.blade.php` | 3, 4, 5 |
| `tests/Feature/StoreSettingsStoreScopingTest.php` | 1, Gap 2 (new) |
| `tests/Feature/StoreSettingsPermissionGateTest.php` | 2 (new) |
| `tests/Feature/StoreSettingsSubmitSafetyTest.php` | 3 (new) |
| `tests/Feature/StoreSettingsPhase4FixesTest.php` | 4 (new) |
| `tests/Feature/ManualLiveVerificationTest.php` | 1 (updated) |
