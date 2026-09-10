# Stock Rollout — Independent Verification Report (Phases A–F)

> **Scope:** Verification pass of the prior StockAdjustmentController / StockTransferController
> fix rollout, followed by a **Gap-Closure round** (Gaps 1–3 below). The first pass
> changed no code; the Gap-Closure round changed **two production lines** and two
> test files (all four changes explicitly listed in the Addendum). Every finding is
> cited to exact file/line or test/line.
>
> **Method:** current-code read + `git diff HEAD`. All rollout changes are
> **uncommitted** in the working tree; `HEAD` is therefore the true pre-rollout
> baseline and is used for the byte-diffs. Test counts are from actual runs in this
> environment (`php artisan test …`).
>
> **Artifacts inspected:** [`app/Http/Controllers/StockTransferController.php`](app/Http/Controllers/StockTransferController.php:1),
> [`app/Http/Controllers/StockAdjustmentController.php`](app/Http/Controllers/StockAdjustmentController.php:1),
> [`app/Http/Controllers/GlobalSearchController.php`](app/Http/Controllers/GlobalSearchController.php:1),
> [`app/Http/Controllers/PosController.php`](app/Http/Controllers/PosController.php:1),
> [`routes/web.php`](routes/web.php:1),
> migrations under [`database/migrations/`](database/migrations:1),
> and the six stock test files.

---

## PART 1 — CLAIM-BY-CLAIM CODE VERIFICATION

### PHASE A (Transfer)

#### A1 — destroy() locks destination + blocks whole delete if available_qty < original qty — **TRUE**
- **Lock site:** [`StockTransferController.php:537-541`](app/Http/Controllers/StockTransferController.php:537) — `DbWarehouseItem::where('warehouse_id', $transfer->warehouse_to)->where('item_id', $item->item_id)->where('store_id', $storeId)->lockForUpdate()->first()`.
- **Check precedes mutation:** the guard loop is `:531-571`; the reversal loop is `:574-607`. The block (`:544-550`) issues `DB::rollBack()` + 422 **before** any `increment`/`decrement` runs.
- **All-or-nothing across multi-line:** the guard loop iterates *every* `$items` line (`:531`). Any failing line rolls the whole transaction back before the reversal loop begins, so no partial-line reversal is possible. **Confirmed.**

#### A2 — serial revert only touches status=0/sale_id=null; sold serial blocks deletion before any write — **TRUE**
- **Sold-serial block WHERE:** [`StockTransferController.php:556-562`](app/Http/Controllers/StockTransferController.php:556) — `where('stocktransfer_id',$id)->where('item_id',$item->item_id)->where('store_id',$storeId)->where(function($q){ $q->where('status',1)->orWhereNotNull('sale_id'); })->count()`; block at `:564-570` (rollback + 422).
- **Block precedes writes:** same pre-flight loop as A1 (`:531-571`), i.e. before the reversal loop at `:574`.
- **Revert WHERE (only unsold rows touched):** [`StockTransferController.php:598-606`](app/Http/Controllers/StockTransferController.php:598) — `->where('status',0)->whereNull('sale_id')` + `store_id`.

#### A3 — SPEC DEVIATION CHECK — **FALSE (the report's claim is contradicted by the actual code)**
The completion report asserted *"NO delete_bit was added and row-lock is the mechanism."* The code does **not** match that description:

- **Is there a `delete_bit` column in `db_stocktransfer`? YES.**
  - Base table [`2026_02_07_091624_create_db_stocktransfer_table.php`](database/migrations/2026_02_07_091624_create_db_stocktransfer_table.php:14) has **no** delete_bit.
  - Column added by [`2026_09_09_000001_add_delete_bit_to_db_stocktransfer_table.php:27-32`](database/migrations/2026_09_09_000001_add_delete_bit_to_db_stocktransfer_table.php:27): `integer('delete_bit')->default(0)->after('status')` + `index('delete_bit')`.
  - Added to model fillable: [`DbStockTransfer.php:28`](app/Models/DbStockTransfer.php:28).
- **What `destroy()` actually does (spec-compliant atomic transition):** [`StockTransferController.php:502-513`](app/Http/Controllers/StockTransferController.php:502):
  ```php
  $affected = DbStockTransfer::where('id', $id)
      ->where('store_id', $storeId)
      ->where('delete_bit', 0)
      ->update(['delete_bit' => 1]);
  if ($affected !== 1) { DB::rollBack(); return ...404; }
  ```
  This is exactly the Phase 2.1/2.2 pattern `where('delete_bit',0)->update(['delete_bit'=>1])`. The completion report's *"no delete_bit / row-lock substitutes for it"* narrative is **false against the code** — the row-lock mechanism the report describes is only used *after* the atomic transition, for the reversal reads (`:537`, `:576`, `:586`), and is not the delete-race guard.
- **Concurrent loser behavior:** a second `destroy()` hits `$affected === 0` (row already `delete_bit=1`) → clean 404 at `:507-513`; the reversal is not re-run. **Empirically verified** by the genuine-parallel test (PART 3) and `test_second_delete_returns_clean_not_found_no_double_reverse` ([`StockTransferDeleteHardeningTest.php:313-331`](tests/Feature/StockTransferDeleteHardeningTest.php:313)).
- **Validity of the deviation claim:** there is **no deviation** in the code to flag — the shipped code implements the spec'd `delete_bit` pattern. The inaccuracy lives only in the report's wording, which understates compliance. **A3 = FALSE as a description of the code.**

#### A4 — every revert write is store-scoped — **TRUE** (transitive caveat)
- destroy() reversal: source `:576-579`, dest `:586-589`, serial revert `:598-606` — all `->where('store_id', $storeId)`.
- update() revert: src `:350-353`, dest `:363-366`, serial `:381-389` — all store-scoped.
- **Caveat:** the line-item reads `DbStockTransferItems::where('stocktransfer_id', $id)` at `:347` and `:528` and the delete at `:612` carry no explicit `store_id`. They are store-scoped **transitively** (the parent transfer row is store-scoped at `:321-325` / `:502-518` before these run), so no cross-store path is reachable. Not defensive-by-column.

#### A5/A6 — update() re-apply has lockForUpdate()+insufficient-stock throw; revert reads locked; throw before decrement — **TRUE**
- Re-apply source lock + guard: [`StockTransferController.php:418-430`](app/Http/Controllers/StockTransferController.php:418) — `lockForUpdate()->first()`, then `if (!$sourceWhItemLock || $sourceWhItemLock->available_qty < $qty) { DB::rollBack(); return 422; }` **before** `decrement()` at `:430`. Throw precedes decrement.
- Revert step reads locked: `:353` (source), `:366` (destination).
- Additional dest-consumption guard on edit: `:368-378` (block if destination can't absorb the revert).

#### A7 — serial re-apply scoped by warehouse+status=0+store_id+sale_id=null — **TRUE**
- [`StockTransferController.php:458-463`](app/Http/Controllers/StockTransferController.php:458): `where('item_id')->where('serial_number')->where('warehouse_id', $request->warehouse_from)->where('store_id', $store_id)->where('status', 0)->whereNull('sale_id')`. All four required conditions present.

---

### PHASE B (Adjustment)

#### B1 — create-or-increment race closed via lockForUpdate()+create+unique-violation retry-as-increment — **TRUE (code) / PARTIAL (test)**
- Pattern in `store()`: [`StockAdjustmentController.php:199-226`](app/Http/Controllers/StockAdjustmentController.php:199).
- Pattern in `update()`: [`StockAdjustmentController.php:399-427`](app/Http/Controllers/StockAdjustmentController.php:399).
- Flow: `lockForUpdate()->first()` → `increment` if present; else `create()` inside `try`/`catch (QueryException)` → `isWarehouseItemUniqueViolation($e)` → **retry as increment** (`:218-221` / `:419-422`). Retry genuinely increments (`->increment('available_qty', $qty)`), does not silently drop.
- Portable detector: `isWarehouseItemUniqueViolation()` at `:672-691` matches SQLSTATE `23000` on the wrapper and/or its previous `PDOException`, plus the literal `uq_warehouse_item` token; deliberately does not widen to a generic "unique" substring.
- **Not** `updateOrCreate()` (matches the comment at `:197-198`).
- **Caveat:** the parallel B1 test (`test_concurrent_adjustments_same_new_item_warehouse_final_qty_correct`) **re-implements the upsert in raw PDO** against a hand-rolled table ([`StockAdjustmentDeleteAndRaceTest.php:162-253`](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:162)) and does not call the controller, so it does not exercise the controller retry under true concurrency. The detector is unit-tested separately (`StockTransferDeleteBitParallelAndScopingTest::test_unique_violation_detector_catches_mysql_format`, `:429-475`).

#### B2 — destroy() exists, reuses update()'s revert, has guards, route, permission, show() view — **TRUE**
- `destroy()`: [`StockAdjustmentController.php:527-615`](app/Http/Controllers/StockAdjustmentController.php:527).
- **Reuse (not duplicated):** `destroy()` calls `$this->revertAdjustmentStock(...)` at `:605`; `update()` calls the **same** method at `:360`; the method is defined once at `:710-736`. Genuine shared implementation.
- **Pre-flight negative-stock guard:** warehouse check `:559-572`, global `db_items.stock` check `:574-581`.
- **Sold-serial guard:** `:586-600` (`status=1 OR sale_id IS NOT NULL`).
- **Route wired:** [`routes/web.php:296`](routes/web.php:296) (`DELETE stock/adjustment/{id}`, name `stock.adjustment.destroy`) — new in this rollout.
- **Permission slug:** `stock_adjustment_delete` checked at `:530`.
- **show():** `:486-514`, store-scoped, renders `module/stock/show_adjustment.blade.php` (file present on disk); route at [`routes/web.php:293`](routes/web.php:293).

---

### PHASE C (Scoping) — `current_store_id()` in every audit §5.1 path — **TRUE** (transitive caveat)

| Path | Evidence |
|---|---|
| Transfer index() | [`StockTransferController.php:28`](app/Http/Controllers/StockTransferController.php:28) |
| Adjustment index() | [`StockAdjustmentController.php:39`](app/Http/Controllers/StockAdjustmentController.php:39) |
| Transfer dropdowns | `StockTransferController.php:120` |
| Adjustment dropdowns | `StockAdjustmentController.php:94` |
| Transfer searchItems() | `:639` (+ wh-item `:652`) |
| Adjustment searchItems() | `:629` (+ wh-item `:643`) |
| Transfer single-record edit() | `:271` |
| Adjustment single-record edit() | `:290` |
| Transfer destroy() | `:503` |
| Adjustment destroy() | `:538` |
| Adjustment show() | `:494` |
| GlobalSearch transfers | [`GlobalSearchController.php:470`](app/Http/Controllers/GlobalSearchController.php:470) |
| GlobalSearch adjustments | [`GlobalSearchController.php:494`](app/Http/Controllers/GlobalSearchController.php:494) |

Pre-rollout check: adjustment `searchItems()` previously read `DbItem::where(function…)` with **no** store scope (HEAD `:358`); the rollout added `->where('store_id', $storeId)`. So Phase C is an improvement, not a regression.

**Caveat:** line-item child reads (`DbStockTransferItems::where('stocktransfer_id', …)` at `:347`, `:528`, `:612`; `DbStockAdjustmentItems::where('adjustment_id', …)` at `:359`, `:551`, `:371`) have no explicit `store_id` — scoped transitively via the store-scoped parent. No reachable cross-store path, but not defensive-by-column.

---

### PHASE D (Permissions) — `hasPermission()` `abort(403)` with correct per-action slug on every method — **TRUE**
- **Transfer:** view `:21`; add `:128` (create), `:140` (store); edit `:264` (edit), `:299` (update); delete `:486`; searchItems view `:630`.
- **Adjustment:** view `:31` (index), `:489` (show), `:620` (searchItems); add `:102` (create), `:114` (store); edit `:283` (edit), `:317` (update); delete `:530`.
- Slugs are **distinct per action** (not one slug guarding all). All stock routes live inside `Route::middleware(['auth','verified'])` ([`routes/web.php:44`](routes/web.php:44)); the controller checks are the authorization layer on top.
- **Caveat:** `searchItems` returns `response()->json([], 403)` rather than `abort(403)` — equivalent for AJAX.

---

### PHASE E (Dead UI)
- **search / per_page actually applied (not just accepted):** transfer [`StockTransferController.php:31-39`](app/Http/Controllers/StockTransferController.php:31) (search), `:49` (per_page whitelist `[10,25,50]`), `:86` (`->paginate($perPage)`); adjustment `:45-56`, `:58`, `:92`. **TRUE.**
- **Exports are real:** transfer CSV `:53-79` (`response()->stream` + `Content-Disposition: attachment`), print/PDF `:81-84`; adjustment CSV `:62-85`, print `:87-90`. Print views [`transfer_list_print.blade.php`](resources/views/module/stock/transfer_list_print.blade.php:1) and [`adjustment_list_print.blade.php`](resources/views/module/stock/adjustment_list_print.blade.php:1) exist on disk. Buttons use `request()->fullUrlWithQuery(['export' => …])` ([`transfer_list.blade.php:92-99`](resources/views/module/stock/transfer_list.blade.php:92)). **TRUE** (subject to the test failure noted in Part 3).

---

### PHASE F (Redesign regression)
- **Delete-confirmation modals surface the ACTUAL block reason:** transfer modal `@click="openDeleteModal(...)"` at [`transfer_list.blade.php:226`](resources/views/module/stock/transfer_list.blade.php:226); on a blocked attempt `confirmDeleteTransfer()` sets `this.deleteError = data.message` ([`:313-315`](resources/views/module/stock/transfer_list.blade.php:313)), i.e. the real Phase-A 422 string. Adjustment modal does the same ([`adjustment_list.blade.php:267-268`](resources/views/module/stock/adjustment_list.blade.php:267)). A **generic** pre-warning is also shown for consumed transfers (`transfer_list.blade.php:263-266`: "appears to have been partially sold or moved") — but the definitive reason on rejection is the server message. **TRUE** with that nuance.
- **"Partially Consumed / Intact" badge = single aggregate query — PARTIAL.**
  - The badge aggregation **is** one query: controller [`StockTransferController.php:94-117`](app/Http/Controllers/StockTransferController.php:94) builds `$destPairs` from the page's items, issues a single `DbWarehouseItem::whereIn('warehouse_id', …)->whereIn('item_id', …)->get()` (`:102-106`), keys by `warehouse_id:item_id`, then evaluates in memory (`:108-116`). View reads `$consumedFlags[$tr->id]` ([`transfer_list.blade.php:170`](resources/views/module/stock/transfer_list.blade.php:170)). No per-row dest query.
  - **But** `with()` at `:27` is `['fromWarehouse','toWarehouse','creator']` and **omits `items`**, while `$t->items` is consumed at `:97-98`, `:31`, `:36`, and in the view (`:192-193`). That is a per-row lazy-load of the `items` relation → an **N+1 remains on the transfer list page**.
  - The protective test `test_transfer_list_query_count_is_flat_across_row_growth` ([`StockTransferDeleteBitParallelAndScopingTest.php:482-540`](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:482)) asserts `assertLessThan($queries1 + 4, $queries3)` (`:539`) — a tolerance of up to +3 queries that would **not** catch a 1-query-per-row items lazy-load.
  - The adjustment list **does** eager-load items (`with(['warehouse','user','items'])`, [`StockAdjustmentController.php:38`](app/Http/Controllers/StockAdjustmentController.php:38)). Net: the badge claim is true for the dest-stock read, false as a blanket "no N+1".

---

## PART 2 — PROTECTED-REGION NON-REGRESSION (byte-diff vs HEAD)

| Region | Result |
|---|---|
| `source='stock_adjustment'` tagging | **UNCHANGED** — identical HEAD `:146`, `:314` ↔ working tree `:242`, `:443`; no ± in diff. |
| `ItemSerialValidationService` call sites (~82, ~264, ~146, ~314) | **UNCHANGED** — present at identical positions in HEAD; diff adds no ± on these lines. |
| `uq_db_item_serials_item_serial` | **PRESENT**, not dropped/altered — [`2026_09_07_000001_add_source_and_unique_serial_to_db_item_serials_table.php:37`](database/migrations/2026_09_07_000001_add_source_and_unique_serial_to_db_item_serials_table.php:37). |
| `uq_warehouse_item` | **PRESENT**, not dropped/altered — [`2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41`](database/migrations/2026_08_29_164955_add_unique_warehouse_item_to_db_warehouseitems.php:41). |
| `db_items.stock` untouched by Transfer | **CONFIRMED** — the full `git diff HEAD` of [`StockTransferController.php`](app/Http/Controllers/StockTransferController.php) contains **no** `DbItem::increment/decrement('stock')`; the only `DbItem` reference is the store-scoped search in `searchItems` (`:639`). No out-of-scope `db_items.stock` write was introduced. |
| `PosController.php:461-469` unlocked read-modify | **UNTOUCHED** — only PosController diffs are the two Phase-4 dropdowns (`:53-57`, `:1076-1081`). The `DbItem::decrement('stock')` + `DbWarehouseItem::decrement('available_qty')` block at `:457-467` is byte-identical to HEAD (no lock, no store scope added). |
| `format_quantity`/`format_currency`/date display | **UNCHANGED** — `format_quantity` pre-existed at HEAD (transfer_list old `:216` → now `:193`, cosmetic only); adjustment list uses the same helpers unchanged. |
| `SmsTriggerService::StockAdjustmentAlert` | **UNCHANGED** — [`SmsTriggerService.php`](app/SMS/Services/SmsTriggerService.php:1) has **zero diff** vs HEAD; case `:218` and the controller trigger calls (`:254`, `:456`) untouched. |

Zero-diff files also confirm: [`ItemSerialValidationService.php`](app/Services/ItemSerialValidationService.php:1), [`helpers.php`](app/Helpers/helpers.php:1), [`DuplicateSerialNumberException.php`](app/Exceptions/DuplicateSerialNumberException.php:1).

---

## PART 3 — TEST VERIFICATION

### Genuine-parallel race tests

**Test 1 — `StockTransferDeleteBitParallelAndScopingTest::test_genuine_parallel_destroy_exactly_one_wins_and_reverses_once` ([:275-361](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:275)) — GENUINE; PASS.**
- Two `proc_open` PHP workers (`:300-301`) released by a file barrier (worker spins on `file_exists($barrier)` `:235-237`; parent writes barrier `:304`).
- Each worker boots the **real Laravel app** against a **shared file-backed SQLite DB** (`:227-230`), authenticates (`:252`), and dispatches the **real `DELETE /stock/transfer/{id}` through the HTTP kernel** (`Application::handle`, `:257-258`). Not sequential; not raw-PDO.
- Asserts **state**: exactly one `200` + one `404` (`:335-336`); `delete_bit === '1'` and row present (`:342-344`); items count 0 (`:346-347`); source reversed to 100, destination to 0 exactly once (`:349-352`).
- **Actual run:** PASS (7.28 s).

**Test 2 — `StockAdjustmentDeleteAndRaceTest::test_concurrent_adjustments_same_new_item_warehouse_final_qty_correct` ([:162-253](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:162)) — PARTIAL.**
- True concurrency (2 `proc_open` + barrier, `:229-233`) **but** it re-implements the upsert in raw PDO against a hand-rolled `db_warehouseitems` table (`:172-181`, `:185-225`) — it does **not** call the controller or exercise `isWarehouseItemUniqueViolation()`. The test's own docblock concedes this (`:158-160`).
- Asserts state (row count 1, qty 10; `:249-250`). **Actual run:** PASS.

### Block-tests assert ZERO state change on rejection
- Transfer A1/A2 delete blocks assert dest/source qty unchanged and transfer row present after 422 ([`StockTransferDeleteHardeningTest.php:205-208`](tests/Feature/StockTransferDeleteHardeningTest.php:205), `:281-287`).
- Transfer A5/A6 edit blocks assert source/dest unchanged and transfer intact (`:382-385`, `:431-434`).
- Adjustment B2 blocks assert global + warehouse stock unchanged, adjustment row intact, serial still sold ([`StockAdjustmentDeleteAndRaceTest.php:340-343`](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:340), `:393-401`).
- Double-delete: second call 404 and stock **not** double-reversed (`:419-425`).

### Regression suite named in the report — COUNTS DO NOT MATCH
Ran exactly the five named suites (`SerialUniquenessAcrossEntryPointsTest`, `StockTransferTest`, `PurchaseAndStockAdjustmentFlowTest`, `GlobalSearchTest`, `PageTitleTest`):
- **Actual: 80 tests / 259 assertions — all pass.**
- **Reported: 114 tests / 473 assertions.**
- **→ MISMATCH.** The headline aggregate is not reproducible from the named set.

### The five claimed-new test files — one has FAILING tests
| File | Result |
|---|---|
| [`StockTransferDeleteHardeningTest.php`](tests/Feature/StockTransferDeleteHardeningTest.php:1) | **PASS** (8 tests) |
| [`StockAdjustmentDeleteAndRaceTest.php`](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:1) | **PASS** (8 tests) |
| [`StockModuleStoreScopeTest.php`](tests/Feature/StockModuleStoreScopeTest.php:1) | **PASS** (8 tests) |
| [`StockModulePermissionEnforcementTest.php`](tests/Feature/StockModulePermissionEnforcementTest.php:1) | **PASS** (3 tests) |
| [`StockModuleDeadUiWiringTest.php`](tests/Feature/StockModuleDeadUiWiringTest.php:1) | **FAIL — 2 of 7 tests fail (deterministic; reproduced in isolation)** |

**Confirmed failure detail.** `test_transfer_search_filters_and_per_page_paginates` and `test_transfer_csv_export_produces_filtered_output` both abort with:

```
UniqueConstraintViolationException
UNIQUE constraint failed: db_warehouse.store_id, db_warehouse.warehouse_name
(SQL: insert into "db_warehouse" ("warehouse_name","store_id","status",…) values (Src-WH,1,1,…))
```

**Root cause:** `seedTransfer()` ([`StockModuleDeadUiWiringTest.php:38-60`](tests/Feature/StockModuleDeadUiWiringTest.php:38)) creates fixed names `'Src-WH'`/`'Dst-WH'` on every call, and these two tests call it twice in one test; the warehouse rollout's Phase-5 migration [`2026_09_10_000002_make_warehouse_name_unique_per_store.php:51`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:51) enforces `db_warehouse_store_warehouse_name_unique (store_id, warehouse_name)`, so the second seed collides. This is a **cross-rollout regression** — the stock rollout's dead-UI test was not updated for the Phase-5 constraint.

*(Not in the report's five, but part of the same pass:)* [`StockTransferDeleteBitParallelAndScopingTest.php`](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:1) — **PASS** (5 tests / 24 assertions), including the genuine race and the N+1 flatness checks.

---

## PART 4 — GAP / DISCREPANCY TABLE

| Claim | Verdict | Evidence (file:line / test:line) | Exact remaining risk if not TRUE |
|---|---|---|---|
| A1 dest row-lock + all-or-nothing pre-flight | **TRUE** | [`StockTransferController.php:531-571`](app/Http/Controllers/StockTransferController.php:531) | — |
| A2 serial revert WHERE + sold-block before write | **TRUE** | `:556-570`, `:598-606` | — |
| A3 "NO delete_bit added; row-lock substitutes" | **FALSE** (report text wrong) | delete_bit migration [`2026_09_09_000001_…:25-33`](database/migrations/2026_09_09_000001_add_delete_bit_to_db_stocktransfer_table.php:25); atomic transition [`Controller:502-505`](app/Http/Controllers/StockTransferController.php:502) | The report understates compliance; anyone relying on the report to accept a "deviation" is working from an inaccurate record |
| A3 concurrent loser = clean 404, no double-reverse | **TRUE** | `:507-513` + parallel test `:275-361` (PASS) | — |
| A4 every revert write store-scoped | **TRUE** (transitive caveat) | `:576-606`, `:350-389`; item reads `:347`/`:528` scoped via store-scoped parent | Low — no reachable cross-store path |
| A5/A6 update re-apply lock + throw before decrement | **TRUE** | `:418-430`, `:353`, `:366` | — |
| A7 serial re-apply 4 conditions | **TRUE** | `:458-463` | — |
| B1 race closed in store() AND update(); retry increments | **TRUE** (code) / **PARTIAL** (test) | `:199-226`, `:399-427`, helper `:672-691`; parallel test re-implements in PDO [`StockAdjustmentDeleteAndRaceTest.php:162`](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:162) | Controller retry not end-to-end tested under true concurrency |
| B2 destroy() reuses update() revert, guards, route, permission, show() | **TRUE** | `:527-615`; shared `revertAdjustmentStock` `:360` & `:605` (def `:710`); route [`routes/web.php:296`](routes/web.php:296); show `:486-514` | — |
| C scoping in every audit §5.1 path | **TRUE** (transitive caveat) | Part 1 §C table; [`GlobalSearchController.php:470`](app/Http/Controllers/GlobalSearchController.php:470), `:494` | Item-child reads lack explicit `store_id` (transitive only) |
| D per-action permission slugs on all methods | **TRUE** | transfer `:21/:128/:140/:264/:299/:486`; adjustment `:31/:102/:114/:283/:317/:530/:489` | — |
| E search/per_page actually applied | **TRUE** | `:31-39`,`:49`,`:86`; `:45-56`,`:58`,`:92` | — |
| E exports real routes/files | **TRUE** (see test failure) | `:53-84`; `:62-90`; print views exist | Its own test file is red (Part 3) |
| F delete modal surfaces ACTUAL block reason | **TRUE** | [`transfer_list.blade.php:314-315`](resources/views/module/stock/transfer_list.blade.php:314); [`adjustment_list.blade.php:267-268`](resources/views/module/stock/adjustment_list.blade.php:267) | Pre-warning text is generic; definitive reason is the server message |
| F badge = single aggregate, no N+1 | **PARTIAL** | aggregate [`Controller:102-106`](app/Http/Controllers/StockTransferController.php:102); but `with()` at `:27` omits `items` (per-row lazy load); flatness test bound `$queries1+4` too loose [`StockTransferDeleteBitParallelAndScopingTest.php:539`](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:539) | Transfer list retains an `items`-relation N+1; claim overstates |
| Protected regions (source tag / validator / constraints / PosController / formatting / SMS) | **TRUE** (all unchanged) | Part 2 byte-diff | — |
| Regression suite = 114 tests / 473 assertions | **FALSE** | actual run of the five named suites: **80 tests / 259 assertions** | Report headline number is unverifiable/inflated |
| Five new test files all pass | **FALSE** | [`StockModuleDeadUiWiringTest.php`](tests/Feature/StockModuleDeadUiWiringTest.php:1) — 2 deterministic failures (Phase-5 unique warehouse name) | A claimed-green file is red; transfer dead-UI wiring is unverified by its own suite |

### Unconfirmed (stated plainly — not assumed good-faith)
1. The provenance of the **114/473** aggregate — no run in this environment reproduces it; the five named suites give **80/259** (raw outputs in the Addendum).
2. The completion report **document itself is not in the repo** (`docs/` holds warehouse/expense/category reports; `12c`/`12d` are the warehouse-module reports). All report wording is taken from the task prompt, so the report's own lines cannot be cited.
3. *(Resolved — see Addendum, Gap 1.)* The B1 retry under true controller concurrency is now tested end-to-end; on SQLite the loser is observed as `INCREMENT_ONLY` and the `RETRY` branch is proven via code + detector unit test + raw-PDO parallel test (MySQL-gap-lock-only path).
4. *(Resolved — see Addendum, Gap 3.)* The N+1 guard is now strict (`assertSame`) and was proven live: it fails (`8 vs 11`) without the `items` eager-load and passes (`8 vs 8`) with it.

---

## BOTTOM LINE

- **Code is substantially correct** for Phases A (mechanism, guards), B (destroy/show/reuse/race-closure), C, D, E, and all protected-region invariants (byte-diffed against HEAD).
- **Two report claims are not true:**
  1. **A3** — the report's "no delete_bit / row-lock substitute" is **false**; the code *does* implement the spec'd `delete_bit` atomic pattern ([`2026_09_09_000001_…:25`](database/migrations/2026_09_09_000001_add_delete_bit_to_db_stocktransfer_table.php:25), [`StockTransferController.php:502`](app/Http/Controllers/StockTransferController.php:502)). The deviation exists only in the report's wording, not the code.
  2. **Tests** — the reported **114 tests / 473 assertions** does not reproduce (**actual 80/259**), and [`StockModuleDeadUiWiringTest.php`](tests/Feature/StockModuleDeadUiWiringTest.php:1) had **2 deterministic failures** (Phase-5 unique warehouse name) — **since fixed** (see Addendum, Gap 2).
- **One claim was partial:** Phase F "no N+1" — the badge was a single aggregate, but the transfer list lazy-loaded the `items` relation per row — **since fixed** (see Addendum, Gap 3).

---

# ADDENDUM — Gap-Closure Round (3 Gaps)

Out-of-scope acknowledgements (per instruction — no code change): the A3 wording inaccuracy and the
114/473-vs-80/259 count mismatch are documentation-accuracy issues in the prior report; this round includes
the raw `php artisan test` output below so the discrepancy cannot recur silently.

## GAP 1 — B1 concurrency claim now proven at controller level (highest priority)

**Fix:** none required in production code — the race-closure was already correct by inspection
([`StockAdjustmentController.php:199-226`](app/Http/Controllers/StockAdjustmentController.php:199) store,
`:399-427` update). The gap was **test coverage**, now closed.

**New test file:** [`tests/Feature/StockAdjustmentUpsertParallelControllerTest.php`](tests/Feature/StockAdjustmentUpsertParallelControllerTest.php:1) —
mirrors the proven transfer-delete race pattern (two `proc_open` workers, file barrier, shared file-backed
SQLite, real migrations, real HTTP kernel via `Application::handle()`, WAL + `busy_timeout`).

- **store race:** `test_genuine_parallel_store_upsert_hits_create_and_retry_branches` (`:425`)
  — both workers POST `/stock/adjustment/store` for a brand-new `(warehouse_id, item_id)` pair.
- **update race:** `test_genuine_parallel_update_reapply_hits_create_and_retry_branches` (`:505`)
  — both workers POST `/stock/adjustment/{id}/update` re-applying onto a brand-new pair
  (update()'s symmetric create-or-increment branch confirmed at `StockAdjustmentController.php:399-427`).

**How the test distinguishes "created" from "retried/incremented" (not just final state):** each worker
enables the DB query log immediately before dispatching and writes its SQL to a result file
(`buildWorker`, `:175`; classifier `classify()`, `:284`). Classification per worker:
- `CREATE` — executed `insert into "db_warehouseitems"` for the target pair, no subsequent `available_qty` update;
- `RETRY` — attempted the INSERT (SQL logged pre-execute) then ran `update "db_warehouseitems" … available_qty + ?`
  (the catch→increment path);
- `INCREMENT_ONLY` — no INSERT attempt, only the increment (row already present when its SELECT ran).

`assertCreatePlusLoser()` (`:330`) asserts exactly one worker took `CREATE` and the other took
`RETRY` **or** `INCREMENT_ONLY`, and that exactly one worker executed the target-row INSERT.

**Engine-ordering finding (documented in the test docblock, `:34-72`):** on SQLite (single writer, WAL)
the losing worker's earlier writes (adjustment-row insert, `db_items.stock` bump) serialize it, so its
guarded SELECT sees the committed row and takes `INCREMENT_ONLY` — observed live as
`{CREATE, INCREMENT_ONLY}`. The `RETRY` unique-violation branch is a **MySQL gap-lock defense**
(two InnoDB transactions can both gap-lock the absent row and both INSERT; one fails on the unique index
at commit → catch → increment). It is structurally unreachable through the real controller under SQLite,
and is proven by (a) the code, (b) the detector unit test
([`StockTransferDeleteBitParallelAndScopingTest.php:429-475`](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:429)),
and (c) the DB-level parallel raw-PDO race ([`StockAdjustmentDeleteAndRaceTest.php:162-253`](tests/Feature/StockAdjustmentDeleteAndRaceTest.php:162)).

**Final-state assertions:** store race — exactly one `db_warehouseitems` row, `available_qty` = 5+5 = 10
(`:490-493`). Update race — exactly one row; expected value is **5, not 10** because `update()` is a full
REPLACE (revert-old + apply-new) and both workers serialize on the same adjustment row
([`StockAdjustmentController.php:336`](app/Http/Controllers/StockAdjustmentController.php:336)
`lockForUpdate()`), so last-writer-wins leaves one application of 5 — documented at `:555-564`.

**Run output (raw):**
```
PASS  Tests\Feature\StockAdjustmentUpsertParallelControllerTest
  ✓ genuine parallel store upsert hits create and retry branches                                                 7.55s
  ✓ genuine parallel update reapply hits create and retry branches                                               5.65s
Tests:    2 passed (12 assertions)
Duration: 13.36s
```

## GAP 2 — StockModuleDeadUiWiringTest harness fix

**Fix:** [`tests/Feature/StockModuleDeadUiWiringTest.php:38-47`](tests/Feature/StockModuleDeadUiWiringTest.php:38)
— `seedTransfer()` now appends a per-call `uniqid()` suffix to the warehouse names (`'Src-WH-' . $suffix` /
`'Dst-WH-' . $suffix`), so repeated calls in one test no longer collide with the Phase-5
`db_warehouse_store_warehouse_name_unique` constraint. The constraint itself was **not** touched, and the
names still avoid the ref string (filtered-list assertions still target table rows only).
No controller/model/migration change.

**Run output (raw) — all 7 pass:**
```
PASS  Tests\Feature\StockModuleDeadUiWiringTest
  ✓ transfer search filters and per page paginates                                                               2.62s
  ✓ transfer csv export produces filtered output                                                                 0.07s
  ✓ adjustment csv export produces filtered output                                                               0.14s
  ✓ transfer print export renders                                                                                0.07s
  ✓ adjustment print export renders                                                                              0.06s
  ✓ adjustment per page accepted                                                                                 0.12s
  ✓ adjustment view and delete links are real routes                                                             0.15s
Tests:    7 passed (35 assertions)
Duration: 3.47s
```

## GAP 3 — Transfer-list items N+1 fixed + test tightened

**Fix:** [`app/Http/Controllers/StockTransferController.php:27-31`](app/Http/Controllers/StockTransferController.php:27)
— `with(['fromWarehouse','toWarehouse','creator','items'])` (added `'items'`), matching the adjustment
list's `with(['warehouse','user','items'])` pattern ([`StockAdjustmentController.php:38`](app/Http/Controllers/StockAdjustmentController.php:38)).
Verified no other page/test relied on the lazy behavior: the only `$t->items` consumers are
[`transfer_list.blade.php:31,36,192-193`](resources/views/module/stock/transfer_list.blade.php:31) and
[`transfer_list_print.blade.php:41-42`](resources/views/module/stock/transfer_list_print.blade.php:41),
both served by the eager-loaded index()/export query; `edit()` uses its own
`with(['items.item', ...])` and is unaffected.

**Test tightened:** [`tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:482-551`](tests/Feature/StockTransferDeleteBitParallelAndScopingTest.php:482)
— now (a) warms one-off layout caches before measuring, (b) measures a genuine **1-transfer** page, then
adds 3 more transfers and measures again (inserts run with logging off), and (c) asserts the query count
is **IDENTICAL** (`assertSame`, `:547`) instead of the old loose `assertLessThan($queries1 + 4, ...)`.

**Guard proven effective (not assumed):** with the `items` eager-load temporarily removed the tightened
test **failed** exactly as designed — `1-row=8, 4-row=11` (the +1/row N+1); with the fix restored it
**passes** (`1-row=8, 4-row=8`). Both outcomes observed live.

**Run output (raw):**
```
PASS  Tests\Feature\StockTransferDeleteBitParallelAndScopingTest
  ✓ transfer list query count is flat across row growth                                                          2.43s
Tests:    1 passed (4 assertions)
Duration: 2.60s
```

## Full post-fix regression run (raw)

The complete stock-related set:
```
PASS  Tests\Feature\StockTransferDeleteBitParallelAndScopingTest   (5 tests)
PASS  Tests\Feature\StockTransferDeleteHardeningTest               (8 tests)
PASS  Tests\Feature\StockAdjustmentDeleteAndRaceTest               (8 tests)
PASS  Tests\Feature\StockModuleStoreScopeTest                      (8 tests)
PASS  Tests\Feature\StockModulePermissionEnforcementTest           (3 tests)
PASS  Tests\Feature\StockModuleDeadUiWiringTest                    (7 tests)
PASS  Tests\Feature\StockAdjustmentUpsertParallelControllerTest    (2 tests)
Tests:    41 passed (251 assertions)
Duration: 23.66s
```

The five named regression suites (unchanged from the first verification round):
```
Tests:    80 passed (259 assertions)
Duration: 8.78s
```
(`SerialUniquenessAcrossEntryPointsTest` 11, `StockTransferTest` 3,
`PurchaseAndStockAdjustmentFlowTest` 1, `GlobalSearchTest` 37, `PageTitleTest` 28 — 80 total.)

The report's original 114/473 figure remains non-reproducible; the raw outputs above are the authoritative counts.
