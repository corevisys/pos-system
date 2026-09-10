# Warehouse Module — Functional & High-Risk Audit

> **Status:** Documentation/discovery + functional audit pass. **No code was changed.**
> Everything below is **CONFIRMED BY CODE** (file/line cited). Anything needing a
> live runtime check is labelled **NEEDS RUNTIME TEST**.

---

## 1. SCOPE FRAMING

### 1.1 Controller / routes / views / models

| Layer | Exact location |
|---|---|
| Controller | [`app/Http/Controllers/WarehouseController.php`](app/Http/Controllers/WarehouseController.php:9) — `WarehouseController`: `index()` (:11), `create()` (:46), `store()` (:51), `edit()` (:71), `update()` (:76), `destroy()` (:90) |
| Routes | [`routes/web.php:390-398`](routes/web.php:390) — `warehouse.` → `list`, `add`, `store`, `{warehouse}/edit`, `PUT {warehouse}`, `DELETE {warehouse}` |
| List view | [`resources/views/module/warehouse/warehouse_list.blade.php`](resources/views/module/warehouse/warehouse_list.blade.php:1) |
| Add view | [`resources/views/module/warehouse/add_warehouse.blade.php`](resources/views/module/warehouse/add_warehouse.blade.php:1) |
| Edit view | [`resources/views/module/warehouse/edit_warehouse.blade.php`](resources/views/module/warehouse/edit_warehouse.blade.php:1) |
| Model | [`app/Models/DbWarehouse.php`](app/Models/DbWarehouse.php:8) — table `db_warehouse`, `belongsTo(DbStore,'store_id')`, `hasMany(DbWarehouseItem,'warehouse_id')`, scopes `search` (:35), `filterStatus` (:45) |
| Stock model | [`app/Models/DbWarehouseItem.php`](app/Models/DbWarehouseItem.php:8) — table `db_warehouseitems` |

### 1.2 What a "Warehouse" represents — **CONFIRMED: a physical sub-location of a Store**

- `db_warehouse.store_id` FK → `db_store` `onDelete('cascade')` — [`2026_02_07_092518_create_db_warehouse_table.php:16,32`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:16).
- `store()` belongsTo confirms ownership ([`DbWarehouse.php:19-22`](app/Models/DbWarehouse.php:19)).
- **Not a stand-in for Store.** Every `db_sale`, `db_purchase`, `db_quotation`,
  `db_stocktransfer`, `db_stockadjustment`, `cash_drawer_reconciliations` carries
  BOTH `store_id` AND `warehouse_id`. Store = tenant; Warehouse = branch/depot.
- `db_warehouse.warehouse_type` column (migration :17) is **unused anywhere** (grep 0 hits in `app/`) — vestigial.

### 1.3 Tables referencing `warehouse_id` (blast radius)

`db_hold` (:17), `db_purchase` (:17), `db_quotation` (:17), `db_sales` (:17),
`db_purchasereturn` (:18), `db_salesreturn` (:19), `db_stocktransfer`
(`warehouse_from`/`warehouse_to` :18-19), `db_stocktransferitems` (:19-20),
`db_stockadjustmentitems` (:17), `db_stockadjustment` (:17), `db_warehouseitems`
(:17), `db_item_serials` (:21), `cash_drawer_reconciliations` (:18),
`db_userswarehouses` (:17).

---

## 2. LOCATE

### 2.1 `db_warehouse` schema — [`2026_02_07_092518_create_db_warehouse_table.php:14-33`](database/migrations/2026_02_07_092518_create_db_warehouse_table.php:14)

`id` PK; `store_id` unsignedBigInteger **nullable column**, FK→`db_store`
`onDelete('cascade')`; `warehouse_type` string (unused); `warehouse_name` string
(indexed); `mobile` string (indexed); `email` string (indexed); `status` int
default 1; `created_date` date; `created_at`/`updated_at` timestamps.

No `delete_bit`/soft-delete. No code column; **no warehouse code generation**
(`CodeGeneratorService` has no `warehouse` case, grep 0 hits).

### 2.2 Data passed to views

- **List** ([`index()`](app/Http/Controllers/WarehouseController.php:11)): `warehouses`
  (paginated + `withCount('warehouseItems as total_items')` :14 +
  `withSum('warehouseItems as available_qty','available_qty')` :15 + `worth`
  correlated subselect `SUM(available_qty * db_items.purchase_price)` :16-20) and
  `stats` (:37-41 total/active/inactive — **computed but never rendered**).
- **Add** ([`create()`](app/Http/Controllers/WarehouseController.php:46)): none.
- **Edit** ([`edit()`](app/Http/Controllers/WarehouseController.php:71)): `warehouse`.

### 2.3 Query scoping — **THE CENTRAL GAP**

| Query | Store-scoped? | Evidence |
|---|---|---|
| List `index()` base | ❌ NO | [`WarehouseController.php:13`](app/Http/Controllers/WarehouseController.php:13) `DbWarehouse::query()` |
| List `stats` | ❌ NO | :38-40 |
| Edit lookup | ❌ NO | route-model binding `edit(DbWarehouse $warehouse)` :71 |
| Update | ❌ NO | `update(Request, DbWarehouse $warehouse)` :76 |
| Destroy | ❌ NO | `destroy(DbWarehouse $warehouse)` :90 |
| `search`/`filterStatus` scopes | ❌ NO | [`DbWarehouse.php:35-48`](app/Models/DbWarehouse.php:35) |

**Result:** any authenticated user can list/edit/update/delete **any store's**
warehouses (route-model binding ignores tenant).

### 2.4 Code-generation pattern

None.

---

## 3. FULL UI INVENTORY

### 3.1 Warehouse List — [`warehouse_list.blade.php`](resources/views/module/warehouse/warehouse_list.blade.php)

| Element | Markup location | Notes |
|---|---|---|
| Breadcrumb Home | :8-10 | → `route('dashboard')` |
| "Add Warehouse" button | :17-20 | → `route('warehouse.add')` — not Blade-gated |
| Show per-page `name="per_page"` | :32-36 | `onchange="this.form.submit()"`; 10/25/50 |
| Search `name="search"` | :40 | GET → `warehouse.list` |
| **Copy button** | :47 | no handler |
| **Excel button** | :48 | no handler |
| **PDF button** | :49 | no handler |
| Column `#` | :59,70 | `$loop->iteration` |
| Column Warehouse Name | :60,72 | `$wh->warehouse_name` |
| Column Contact | :61,74-78 | `mobile`/`email` |
| Column Details | :62,80-94 | `total_items` :84, `available_qty` :88, `worth` :92 |
| Column Status | :63,96-99 | badge Active/Inactive |
| Column Action | :64,101-117 | Alpine `x-data="{open:false}"` dropdown |
| └ Edit link | :109 | → `warehouse.edit` |
| └ Delete form | :110-114 | `DELETE warehouse.destroy/{id}` + `onsubmit="return confirm(...)"` |
| Pagination | :135 | `{{ $warehouses->links() }}` |

### 3.2 Add Warehouse — [`add_warehouse.blade.php`](resources/views/module/warehouse/add_warehouse.blade.php)

Warehouse Name* :36 (`required`); Mobile :53; Email :69 (`type="email"`);
Save Data :80 → `warehouse.store`; Cancel :84 → `warehouse.list`.
**Status field MISSING** — controller hard-codes `status=1` at
[`store()`](app/Http/Controllers/WarehouseController.php:63).

### 3.3 Edit Warehouse — [`edit_warehouse.blade.php`](resources/views/module/warehouse/edit_warehouse.blade.php)

Warehouse Name* :37; Mobile :54; Email :70; **Status toggle** :86-88 (hidden
`status=0` + checkbox `status=1` — correct pattern); Update Data :98; Cancel :102.

### 3.4 JS/frontend handlers

List: only the Alpine dropdown `open` toggle (:102-117) and the inline
`onchange="this.form.submit()"` per-page select (:32). **No Copy/Excel/PDF
handlers** (buttons :47-49 inert). Add/Edit: no Alpine `x-data`; pure
server-rendered forms.

### 3.5 Styling baseline comparison

The three views are the **legacy hand-rolled style** (`bg-white dark:bg-dark-card
rounded-3xl border-slate-100`, `bg-slate-50 dark:bg-slate-800` inputs,
`bg-rose-600` buttons) — NOT the design-system baseline now used by
Customers/Suppliers/Deposit/Expenses (`card`, `btn-primary`, `btn-secondary`,
`input-base`, `x-card`, `x-dropdown`/`x-dropdown-link`, `text-text-*` tokens,
`{{ $paginator->links() }}`).

---

## 4. FUNCTIONAL AUDIT

| Element | Class | Evidence |
|---|---|---|
| Add Warehouse button | ✅ WORKS | route+controller exist |
| Search | ✅ WORKS | `index()` → `scopeSearch` :22-24 |
| Per-page select (10/25/50) | ✅ WORKS | `onchange` + `per_page` :34 — **not whitelisted** (any integer accepted) |
| Sort (`sort`/`order`) | ⚠️ PARTIAL | controller reads :30-32 but view has **no sortable column headers** — URL-only |
| Pagination links | ✅ WORKS | `{{ $warehouses->links() }}` |
| Row Edit | ✅ WORKS (unguarded) | → `edit()` — no store/permission guard |
| Row Delete | ✅ WORKS (unguarded) | → `destroy()` — **unconditional hard delete** |
| Status filter | ❌ DEAD | controller `if ($request->filled('status'))` :26-28 but **no view control** |
| Copy / Excel / PDF | 🗑️ DECORATIVE-DEAD | no handlers anywhere |
| `stats` (total/active/inactive) | ❌ DEAD | computed :37-41, **never rendered** |
| Add: Status selector | ❌ MISSING | hard-coded `status=1` |
| Add/Edit: `@error` blocks | ✅ WORKS | render |
| Edit: Status toggle | ✅ WORKS | hidden 0 + checkbox 1 |
| Permission gates | ❌ MISSING | no `hasPermission()` in `WarehouseController` |

---

## 5. HIGH-RISK FLOW TRACE(S)

### 5.1 Delete cascade — **CRITICAL CONFIRMED ORPHAN/CASCADE RISK**

`destroy()` ([`WarehouseController.php:90-95`](app/Http/Controllers/WarehouseController.php:90)) is an
**unconditional hard delete** with **no guard**:

- `db_warehouseitems.warehouse_id` FK **`onDelete('cascade')`** —
  [`2026_02_07_092727_create_db_warehouseitems_table.php:29`](database/migrations/2026_02_07_092727_create_db_warehouseitems_table.php:29).
  Deleting a warehouse **silently deletes ALL its per-warehouse stock rows**.
- `cash_drawer_reconciliations.warehouse_id` FK **`onDelete('set null')`** —
  [`2026_08_24_000001_create_cash_drawer_reconciliations_table.php:53`](database/migrations/2026_08_24_000001_create_cash_drawer_reconciliations_table.php:53).
  Deleting a warehouse **nulls historical reconciliation rows** → they lose branch
  attribution and `getCalculationBreakdown()`'s `whereHas('sale', warehouse_id)`
  isolation stops matching them.
- **Other tables have NO FK on `warehouse_id`** (`db_sales`, `db_purchase`,
  `db_quotation`, `db_stocktransfer`, `db_stockadjustment`,
  `db_stocktransferitems`, `db_stockadjustmentitems`, `db_purchasereturn`,
  `db_salesreturn`, `db_item_serials`, `db_hold`, `db_userswarehouses`) — a
  deleted warehouse leaves **dangling `warehouse_id` references** in every one:
  - `db_item_serials` → serial scans never match (PosController :179-183);
  - `db_sales` → sales-list filters (:1056-1059) and report warehouse-wise
    grouping (:1913-1928, `COALESCE(...,'Default Warehouse')`) lump orphans into a
    phantom "Default Warehouse";
  - `db_stocktransfer.warehouse_from/to` → transfer edit/delete re-apply
    (StockTransferController :349-466) resolves a non-existent warehouse → fail
    or double-adjust.

### 5.2 Stock-quantity consumers (protected contracts)

Source of truth: `db_warehouseitems.available_qty`. Key consumers (all filter/join
on `warehouse_id`):

- **POS stock join** — [`PosController.php:130-134`](app/Http/Controllers/PosController.php:130)
- **POS decrement** — [`PosController.php:461-467`](app/Http/Controllers/PosController.php:461)
- **POS serial validation (double-sell race guard)** — [`PosController.php:1327-1396`](app/Http/Controllers/PosController.php:1327)
- **Purchase inbound** — [`PurchaseController.php:246-257`](app/Http/Controllers/PurchaseController.php:246)
- **Sales return re-stock / reverse** — [`SalesReturnController.php:394-405`](app/Http/Controllers/SalesReturnController.php:394), [:527-538](app/Http/Controllers/SalesReturnController.php:527)
- **Stock transfer move** — [`StockTransferController.php:196-221`](app/Http/Controllers/StockTransferController.php:196), [:349-466](app/Http/Controllers/StockTransferController.php:349), [:536-605](app/Http/Controllers/StockTransferController.php:536)
- **Stock adjustment / revert** — [`StockAdjustmentController.php:199-221`](app/Http/Controllers/StockAdjustmentController.php:199), [:710-721](app/Http/Controllers/StockAdjustmentController.php:710)
- **Item add/edit stock** — [`ItemController.php:639-641`](app/Http/Controllers/ItemController.php:639)
- **Quotation stock check / conversion** — [`QuotationController.php:587-588,662-663`](app/Http/Controllers/QuotationController.php:587)
- **Stock report per-warehouse** — [`ReportController.php:1444-1447`](app/Http/Controllers/ReportController.php:1444)
- **Item import validation** — [`ImportsItems.php:470-476`](app/Http/Controllers/Concerns/ImportsItems.php:470)
- **Warehouse list aggregates** — [`WarehouseController.php:14-20`](app/Http/Controllers/WarehouseController.php:14)

### 5.3 Double-action / race risk

- **Delete race:** `destroy()` is find→delete with **no atomic conditional
  transition** (no `delete_bit`). Concurrent double-delete: second 404s. The DB
  `cascade` already removed `db_warehouseitems`. **NEEDS RUNTIME TEST.**
- **Cross-tenant race:** edit/update/destroy bind by id with no store filter —
  Store-B can target Store-A. **NEEDS RUNTIME TEST** (two-store).
- **Create race:** global `unique:db_warehouse,warehouse_name` (:54) blocks
  cross-store name reuse; no per-store unique index exists.

---

## 6. SUBMISSION / EDIT / DELETE FLOW

### 6.1 Create
`store()` (:51-69): validates `warehouse_name` (global unique), `mobile`, `email`;
creates with `store_id = auth()->user()->store_id` (:64), `status = 1` (:63).
**No permission gate** (`warehouse_add` unchecked); `auth()->user()->store_id`
can be null (user without store → null store_id row).

### 6.2 Edit
`update()` (:76-88): validates unique-ignore-self, updates `warehouse_name`,
`mobile`, `email`, `status`. **No store-scope check** — can rename/deactivate
another store's warehouse, or deactivate one active stock/serials/sales still
reference. `status=0` is the intended soft-disable, but inactive warehouses
still appear in several unscoped dropdowns (e.g. `SaleController.php:183`
`DbWarehouse::all()`, inconsistent with `PosController.php:56` which filters
`status=1`). Rename is safe (id-based FKs).

### 6.3 Delete
`destroy()` (:90-95): unconditional hard delete — see §5.1. **Not guarded
against related/historical records.**

### 6.4 Permission gates — **CONFIRMED ABSENT at controller level**
Slugs seeded: `warehouse_add`, `warehouse_edit`, `warehouse_delete`,
`warehouse_view` ([`PermissionSeeder.php:57`](database/seeders/PermissionSeeder.php:57),
[`RolePermissionSeeder.php:47`](database/seeders/RolePermissionSeeder.php:47)). But
**no method in `WarehouseController` calls `hasPermission()`** — only Blade
sidebar gate ([`app.blade.php:795-823`](resources/views/layouts/app.blade.php:795))
and Global Search entries ([`GlobalSearchController.php:138-140`](app/Http/Controllers/GlobalSearchController.php:138)).
Direct URL access (`/warehouse/add`, `/{id}/edit`, `DELETE /{id}`) is open to
any authenticated user — the codebase-wide gap.

### 6.5 Store scoping — **Store-B can touch Store-A's warehouse: YES**
`edit/update/destroy` use route-model binding with no `store_id` filter;
`index()`/`stats` unscoped. (Contrast: most warehouse *dropdowns* elsewhere ARE
store-scoped — Stock, CashRecon, Quotation, Purchase list, Imports — so the gap
is specific to the Warehouse module's own CRUD.)

---

## 7. NON-STANDARD / HIGH-RISK / PROTECTED REGIONS

- **`stats` computed but unused** — [`WarehouseController.php:37-41`](app/Http/Controllers/WarehouseController.php:41).
- **`filterStatus` scope + `status` request param dead** — :26-28 vs no view control.
- **No soft-delete / `delete_bit`** — hard delete only (contrast: Expenses,
  Deposits, Cash Reconciliation all moved to soft-delete+reversal).
- **`warehouse_type` unused column.**
- **Global-unique warehouse name across ALL stores** — `unique:db_warehouse,
  warehouse_name` (:54) — blocks two stores using the same branch name; store
  should scope uniqueness (like the per-store unique migrations added for
  categories/brands/variants).
- **Info-hiding:** the list page already has `total_items`/`available_qty`/`worth`
  (:84-92) but the **delete confirm is a bare `confirm()`** (:110) with no
  disclosure of what will be destroyed — the same info-hiding class flagged in
  prior audits (Deposit/Expenses solvency messaging vs warehouse's silent cascade).

---

## CONSUMERS OF `warehouse_id` (report controller + line, store-scope flag)

| Consumer | File:line | Store-scoped? |
|---|---|---|
| POS stock join | [`PosController.php:130-134`](app/Http/Controllers/PosController.php:130) | via sale/store context |
| POS decrement | [`PosController.php:461-467`](app/Http/Controllers/PosController.php:461) | via sale store_id |
| POS serial validation | [`PosController.php:1327-1396`](app/Http/Controllers/PosController.php:1327) | checks store_id + warehouse_id |
| Purchase inbound | [`PurchaseController.php:246-257`](app/Http/Controllers/PurchaseController.php:246) | via user store_id |
| Purchase filter | [`PurchaseController.php:70-73,993-995`](app/Http/Controllers/PurchaseController.php:70) | ❌ no store filter |
| Sales return re-stock | [`SalesReturnController.php:394-405`](app/Http/Controllers/SalesReturnController.php:394) | via sale |
| Stock transfer move | [`StockTransferController.php:196-221`](app/Http/Controllers/StockTransferController.php:196) | ✅ current_store_id |
| Stock adjustment | [`StockAdjustmentController.php:199-221`](app/Http/Controllers/StockAdjustmentController.php:199) | ✅ current_store_id |
| Item add/edit stock | [`ItemController.php:639-641`](app/Http/Controllers/ItemController.php:639) | ✅ current_store_id |
| Item warehouse dropdown | [`ItemController.php:136,462,879`](app/Http/Controllers/ItemController.php:136) | ❌ `where('status',1)` only |
| Pos warehouse dropdown | [`PosController.php:56,1079`](app/Http/Controllers/PosController.php:56) | ❌ |
| Sale dropdowns | [`SaleController.php:37,68,183`](app/Http/Controllers/SaleController.php:37) | ❌ |
| SalesReturn dropdown | [`SalesReturnController.php:98`](app/Http/Controllers/SalesReturnController.php:98) | ❌ |
| Purchase create dropdown | [`PurchaseController.php:101`](app/Http/Controllers/PurchaseController.php:101) | ❌ (list is scoped :91,1013) |
| Report dropdowns | [`ReportController.php:1121,1178,1245,1307,1416,1522,1596`](app/Http/Controllers/ReportController.php:1121) | ❌ |
| CashReconciliation dropdown | [`CashReconciliationController.php:106,124`](app/Http/Controllers/CashReconciliationController.php:106) | ✅ |
| Stock dropdowns | [`StockTransferController.php:120,133,274`](app/Http/Controllers/StockTransferController.php:120), [`StockAdjustmentController.php:94,107,292`](app/Http/Controllers/StockAdjustmentController.php:94) | ✅ |
| Quotation dropdowns | [`QuotationController.php:56,65,284`](app/Http/Controllers/QuotationController.php:56) | ✅ |
| Item import | [`ImportsItems.php:60,470-476`](app/Http/Controllers/Concerns/ImportsItems.php:60) | ✅ |
| Warehouse list aggregates | [`WarehouseController.php:14-20`](app/Http/Controllers/WarehouseController.php:14) | ❌ list itself unscoped |

---

## NEEDS RUNTIME TEST

1. **Cross-tenant CRUD:** Store-B user editing/deleting Store-A warehouse succeeds
   (code shows no store filter; end-to-end needs two stores).
2. **Delete cascade visibility:** delete a warehouse with stock → confirm
   `db_warehouseitems` rows vanish (schema confirms cascade) and no warning shown.
3. **Dangling references:** delete a warehouse referenced by sales/serials/
   transfers → report "Default Warehouse" lumping and POS serial scan silently not
   matching.
4. **Double-delete UX:** concurrent double-delete — second 404 vs graceful message.
5. **Status filter dead param:** `/warehouse/list?status=0` filters server-side
   while no UI control exposes it.

---

## REDESIGN CONSTRAINTS (must survive any future compact/user-friendly redesign)

1. **Do not change the stock-quantity contract:** `db_warehouseitems.available_qty`
   is the per-warehouse stock source of truth; every consumer in §5.2 filters by
   `warehouse_id` (+ mostly `store_id`). A redesign must not alter how `index()`
   computes `total_items`/`available_qty`/`worth` (`withCount`/`withSum`/
   purchase-price subselect at [`WarehouseController.php:14-20`](app/Http/Controllers/WarehouseController.php:14)).
2. **Warehouse is a Store sub-location:** keep `store_id` FK and attribution on
   create; any store-scoping fix must mirror the established
   `where('store_id', current_store_id())` pattern used by Stock/CashRecon/
   Quotation dropdowns.
3. **The `status` soft-disable flag** (`status=0`) is the intended deactivate
   mechanism (edit toggle at [`edit_warehouse.blade.php:86-88`](resources/views/module/warehouse/edit_warehouse.blade.php:86));
   a redesign may surface it but must not change its meaning, and must not replace
   it with a hard-delete default.
4. **Preserve `warehouse_name` + `mobile` + `email` + `status` form contract**
   (field names consumed by `store()`/`update()` validation).
5. **Keep the global-unique-name decision visible:** changing uniqueness to
   per-store requires a migration + seeder + validation change (out of scope for
   a pure visual pass).
