# Warehouse Rollout — Phase-by-Phase Implementation Report

> **Scope:** Fix + build on the Warehouse module (WarehouseController, db_warehouse,
> db_warehouseitems, warehouse views) plus the unscoped warehouse-dropdown consumers.
> Full audit baseline: [`docs/12b-warehouse-audit.md`](docs/12b-warehouse-audit.md:1).
> Every numbered item reports exact file/line before-after and the exact test
> assertion used. Tests: [`tests/Feature/WarehouseRolloutTest.php`](tests/Feature/WarehouseRolloutTest.php:1)
> (**18 tests / 83 assertions, all pass**).

---

## DEFAULT DECISIONS TAKEN (stated explicitly)

1. **Phase 1 — soft-delete** (added `delete_bit` to `db_warehouse`), not
   hard-delete-when-safe. Rationale: a warehouse with zero stock and zero
   historical references still leaves an audit trail, mirroring the
   Expenses/Deposit soft-delete convention. **Auto-transfer-stock-on-delete was
   rejected** (blocking is the safer default; auto-transfer is a separate feature
   decision outside this rollout).
2. **Phase 6 — stats cards KEPT and surfaced** (not removed). The `$stats`
   computation at `WarehouseController.php:37-41` is cheap and useful, so it is
   now rendered as three summary cards above the list.
3. **Phase 6 — status filter control ADDED** (not left dead or removed). A
   simple All/Active/Inactive select was wired to the existing `status` param.

No other choice was silently substituted.

---

## PHASE 1 — Delete guard (CRITICAL)

### Item 1a: block delete when the warehouse still holds stock (reusing index()'s aggregate)

**Before** — [`WarehouseController.php:90`](app/Http/Controllers/WarehouseController.php:90) (original):
```php
public function destroy(DbWarehouse $warehouse) {
    $warehouse->delete();   // unconditional hard delete
    return redirect()->route('warehouse.list')->with('success', 'Warehouse deleted successfully.');
}
```

**After** — new helper [`warehouseStock()`](app/Http/Controllers/WarehouseController.php:20)
reuses the exact `withCount('warehouseItems as total_items')` /
`withSum('warehouseItems as available_qty','available_qty')` / `worth` subselect
shape from `index()` (lines 14-20). `destroy()` now computes
`$stock = $this->warehouseStock($warehouse)` and, when
`$stock['total_items'] > 0 || $stock['available_qty'] > 0`, returns
`back()->with('error', 'This warehouse cannot be deleted because it still holds
{N} item(s) / {Q} quantity (worth {CUR}). Reassign or remove the stock first.')`
— closing the info-hiding gap the audit flagged.

### Item 1b: block delete when historical tables still reference the warehouse

New helper [`referencingTables()`](app/Http/Controllers/WarehouseController.php:45)
checks (in order) `db_warehouseitems`, `db_sales`, `db_item_serials`,
`db_stocktransfer` (`warehouse_from`/`warehouse_to`), `db_purchase`,
`db_quotation`, `db_stockadjustment` — the audit's §1.3 blast-radius list. Any hit
returns `back()->with('error', 'This warehouse cannot be deleted because the
following records still reference it: {table labels}.')` (mirrors the Expenses
category-in-use guard shape).

### Item 1c: soft-delete default

When stock is zero AND no references exist, `destroy()` does
`$warehouse->update(['delete_bit' => 1])` instead of `->delete()`. The
`delete_bit` column is added by migration
[`2026_09_10_000001_add_delete_bit_to_db_warehouse_table.php`](database/migrations/2026_09_10_000001_add_delete_bit_to_db_warehouse_table.php:1)
and added to [`DbWarehouse::$fillable`](app/Models/DbWarehouse.php:16).

### Verify (Phase 1)
- `test_phase1_delete_blocked_when_stock_present_shows_counts`: warehouse with 1
  item / 5 qty → `assertSessionHas('error')`, message contains `1 item(s)` and
  `5.00`, `delete_bit` stays 0, `db_warehouseitems` row still present.
- `test_phase1_delete_blocked_when_dangling_references_name_the_table`: historical
  `db_sales` row → error message contains `db_sales`; `delete_bit` stays 0.
- `test_phase1_serial_reference_also_blocks_delete`: `db_item_serials` row → error
  contains `db_item_serials`; `delete_bit` stays 0.
- `test_phase1_clean_warehouse_soft_deletes_with_delete_bit`: clean warehouse →
  `assertSessionHas('success')`, `delete_bit === 1`, row still exists.

---

## PHASE 2 — Cross-tenant CRUD fix (CRITICAL)

**Item 2:** store-scoped `index()` base query
([`WarehouseController.php:88`](app/Http/Controllers/WarehouseController.php:88)),
`stats` (:157-161), and a new
[`scopedWarehouse()`](app/Http/Controllers/WarehouseController.php:236) helper used
by `edit()`/`update()`/`destroy()` (`abort_unless(..., 404)` on cross-store). The
model scopes [`scopeSearch`](app/Models/DbWarehouse.php:38) and
[`scopeFilterStatus`](app/Models/DbWarehouse.php:50) now accept an optional
`$storeId` and add `where('store_id', ...)`. Reuses the exact
`where('store_id', current_store_id())` pattern from the Stock/CashRecon/Quotation
dropdowns (`StockTransferController.php:120`, `CashReconciliationController.php:106`,
`QuotationController.php:56`).

**Verify:** `test_phase2_store_b_cannot_edit_update_delete_store_a_warehouse`
(Store-B GET edit `assertNotFound()`, PUT `assertNotFound()` + name unchanged,
DELETE `assertNotFound()` + `delete_bit` 0; Store-A control update succeeds);
`test_phase2_index_and_stats_store_scoped` (Store-A list `assertSee('S1-WH')` /
`assertDontSee('S2-WH')`, `stats['total'] === 1`).

---

## PHASE 3 — Permission gates (CRITICAL)

**Item 3:** `hasPermission('warehouse_view')` on `index()`, `warehouse_add` on
`create()`/`store()`, `warehouse_edit` on `edit()`/`update()`, `warehouse_delete`
on `destroy()` ([`WarehouseController.php:82,172,193,211,231,252`](app/Http/Controllers/WarehouseController.php:82)).
Slugs already seeded ([`PermissionSeeder.php:57`](database/seeders/PermissionSeeder.php:57)).

**Verify:** `test_phase3_permission_gates_403_without_slug_and_control_succeeds` —
a user with only `sales_view` gets `assertForbidden()` on list/add/edit/store/update/destroy;
view-only user can list but is `assertForbidden()` on store; full-permission user's
store `assertRedirect()` + row created.

---

## PHASE 4 — Unscoped dropdown consumers (HIGH)

**Item 4:** added `->where('store_id', current_store_id())->where('delete_bit', 0)`
(plus standardizing on `status = 1`) to every listed dropdown:

| Consumer | File:line (after) |
|---|---|
| Purchase list + create + filter | [`PurchaseController.php:92,102`](app/Http/Controllers/PurchaseController.php:92) |
| Item add/edit/import dropdowns (3 sites) | [`ItemController.php:137,463,880`](app/Http/Controllers/ItemController.php:137) |
| POS index + hold-list dropdowns | [`PosController.php:57,1081`](app/Http/Controllers/PosController.php:57) |
| Sale create + edit + payments (`DbWarehouse::all()`) | [`SaleController.php:37,68,186`](app/Http/Controllers/SaleController.php:37) |
| SalesReturn dropdown | [`SalesReturnController.php:99`](app/Http/Controllers/SalesReturnController.php:99) |
| Report pages (7 methods) | [`ReportController.php:1122,1180,1248,1311,1421,1528,1603`](app/Http/Controllers/ReportController.php:1122) |
| Report cached dropdowns (sales_summary, cash_reconciliation_report) — **also a cross-store cache leak** | [`ReportController.php:1799-1803,2115-2119`](app/Http/Controllers/ReportController.php:1799) |

The inactive-warehouse inconsistency (SaleController previously `DbWarehouse::all()`)
is fixed — all dropdowns now filter `status = 1` (active only), matching the
intended soft-disable semantics.

**Genuine gap found + fixed (not just a missing test):** the two `Cache::remember('db_warehouses_list', ...)`
dropdowns at ReportController `salesSummary()` and `cashReconciliationReport()`
were (a) unscoped and (b) cached under a **global, store-agnostic key**, so Store A's
warehouse list was served to Store B from cache. Fixed by scoping the query AND
keying the cache per store (`'db_warehouses_list_s' . $storeId`).

**Verify:** one test per consumer —
`test_phase4_purchase_list_and_create_dropdowns_store_scoped_and_active_only`,
`test_phase4_item_dropdown_...`, `test_phase4_pos_dropdown_...`,
`test_phase4_sale_create_and_edit_dropdowns_...`,
`test_phase4_sales_return_dropdown_...`,
`test_phase4_purchase_create_dropdown_...`,
`test_phase4_report_dropdowns_...` — each asserts the view-data warehouse list
does NOT contain `StoreB-Only` and does NOT contain `Inactive-Only`.

---

## PHASE 5 — Per-store unique warehouse name (MEDIUM)

**Item 5:** migration
[`2026_09_10_000002_make_warehouse_name_unique_per_store.php`](database/migrations/2026_09_10_000002_make_warehouse_name_unique_per_store.php:1)
follows the exact category/brand/variant precedent
([`2026_09_08_000001_...`](database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php:1)):
(1) per-store `assertNoDuplicates` pre-check (abort loudly, no silent dedupe);
(2) drop the superseded plain `db_warehouse_warehouse_name_index`; (3) add
composite unique `db_warehouse_store_warehouse_name_unique (store_id, warehouse_name)`.
Validation switched from `unique:db_warehouse,warehouse_name` to
`Rule::unique(...)->where(store_id = ? AND delete_bit = 0)` at
[`WarehouseController.php:183`](app/Http/Controllers/WarehouseController.php:183)
(store) and `->ignore($warehouse->id)` at [:220](app/Http/Controllers/WarehouseController.php:220) (update).

**Verify:** `test_phase5_same_warehouse_name_allowed_across_stores_but_blocked_within_store`
— Store A creates `Shared Name` (redirect); Store B creates `Shared Name`
(redirect, previously blocked by the global unique); Store A creating a second
`Shared Name` → `assertSessionHasErrors('warehouse_name')`; total count of
`Shared Name` === 2 (one per store).

---

## PHASE 6 — Dead UI decisions (MEDIUM)

**Item 6 — stats cards:** `$stats` (total/active/inactive) rendered as three
summary cards above the list in
[`warehouse_list.blade.php`](resources/views/module/warehouse/warehouse_list.blade.php:44-75)
(same `card` summary-card pattern as Deposit/Expenses).

**Item 7 — status filter control:** All/Active/Inactive select in the toolbar
(:94-103) submits the existing `status` param (controller already read it at
`WarehouseController.php:26-28`). Per-page whitelist `[10,25,50,100]` added.

**Item 8 — Copy/Excel/PDF export:** the dead Copy button removed; Excel and PDF
are real store-scoped links using the Deposit/Expenses convention — CSV via
`response()->stream(...)` ([`WarehouseController.php:105-126`](app/Http/Controllers/WarehouseController.php:105))
and PDF via new print view
[`warehouse_list_print.blade.php`](resources/views/module/warehouse/warehouse_list_print.blade.php:1).

**Verify:**
- `test_phase6_status_filter_narrows_list_and_stats_cards_render`: `stats`
  view-data `total===2/active===1/inactive===1`; `?status=1` shows `ActiveWH`,
  hides `InactiveWH`.
- `test_phase6_csv_export_is_store_scoped`: Store-A CSV contains `ExportS1`, not
  `ExportS2`.
- `test_phase6_pdf_export_is_store_scoped`: Store-A PDF view contains `PdfS1`, not
  `PdfS2`.

---

## PHASE 7 — Redesign / visual pass (LAST — presentation only)

**Item 9 — design-system baseline:** all three views migrated from legacy
hand-rolled style to the established baseline (`card`, `btn-primary`, `btn-secondary`,
`input-base`, `<x-card>`, `<x-dropdown>`/`<x-dropdown-link>`,
`text-text-*` tokens, `{{ $warehouses->links() }}`):
[`warehouse_list.blade.php`](resources/views/module/warehouse/warehouse_list.blade.php:1),
[`add_warehouse.blade.php`](resources/views/module/warehouse/add_warehouse.blade.php:1),
[`edit_warehouse.blade.php`](resources/views/module/warehouse/edit_warehouse.blade.php:1).

**Item 10 — compact + user-friendly:** reduced cell/header padding
(`px-4 py-1.5` rows, `px-4 py-2.5` headers), dead `#` column removed, and the bare
`confirm()` replaced with a disclosure confirm stating the warehouse's stock
counts and the block conditions (`warehouse_list.blade.php:140-142`). The Add
form's missing Status selector was NOT added (out of scope; Add hard-codes active,
matching prior behavior).

**Protected regions untouched:** the `total_items/available_qty/worth` aggregate
formula, `store_id` attribution, `status` soft-disable semantics, form field-name
contract, and all Phase 1–4 guards.

**Verify:** all 18 Phase 1–6 tests pass **unmodified** after the redesign.

---

## Regression verification

- [`tests/Feature/WarehouseRolloutTest.php`](tests/Feature/WarehouseRolloutTest.php) —
  **18 tests / 83 assertions, all pass** (Phases 1–7).
- Related suites re-run after the changes — **109 tests / 461 assertions, all
  pass**: `PageTitleTest`, `SalesSummaryReportTest`, `PurchasePhaseATest`,
  `PurchasePhaseBTest`, `QuotationModuleTest`, `CashReconciliationFixesTest`,
  `ItemAddValidationAndContractTest`, `PosTotalValidationMatrixTest`,
  `SalesListPageTest`. No regression from the Phase 4 dropdown scoping or the
  cached-dropdown fix.
- Phase 7 verify: the 18 Phase 1–6 tests ran unmodified and green after the
  visual pass (no guard/scoping/permission behavior changed by markup).

---

## Honest per-item status

| Item | First-run result | Production fix needed? |
|---|---|---|
| Phase 1 delete guards + soft-delete | ✅ (test FK/seed fixes, test-only) | guards genuinely absent → added |
| Phase 2 store scoping | ✅ | no |
| Phase 3 permission gates | ✅ | no |
| Phase 4 dropdown scoping (7 consumers) | ✅ | **yes — 2 additional cached Report dropdowns found unscoped + globally cached; fixed** |
| Phase 5 per-store unique | ✅ | no |
| Phase 6 stats/filter/export | ✅ | no |
| Phase 7 redesign | ✅ | no |
