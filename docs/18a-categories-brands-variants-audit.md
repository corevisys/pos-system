# COMBINED DISCOVERY + FUNCTIONAL AUDIT — CATEGORIES, BRANDS, VARIANTS

Scope: `app/Http/Controllers/CategoryController.php`, `app/Http/Controllers/BrandController.php`, `app/Http/Controllers/VariantController.php`, their models/migrations/views, and every downstream consumer traced. Discovery + audit only — **no code was changed**.

---

## 0. SHARED FACTS (verified across all three modules)

- **Route group**: All three modules are in the `items.` prefix group under `['auth', 'verified']` only — `routes/web.php:44` and `routes/web.php:232-271`. There is no permission middleware anywhere in the app.
- **Controllers**: None call `Gate::authorize()` or `hasPermission()` (contrast `UserController.php:22` / `RoleController.php:14`). **All three modules are fully unguarded at the route/controller level** — the same bug class Service had before its fix.
- **Permission slugs exist and are wired only in the sidebar**:
  - `items_category_view` (`PermissionSeeder.php:33`, sidebar `app.blade.php:529`)
  - `brand_view` (`PermissionSeeder.php:28`, sidebar `app.blade.php:534`)
  - `variant_view` (`PermissionSeeder.php:45`, sidebar `app.blade.php:539`)
- **Role grants** (`RolePermissionSeeder.php:70-122`):
  - Manager: `brand_add/edit/view` + `items_category_add/edit/view` but **no brand/category delete and no `variant_*` slugs** (`:86-87`).
  - Salesman: `brand_view` + `items_category_view` but **no `variant_view`** (`:108`).
  - Cashier: no category/brand/variant permissions at all.
  - Direct URL access bypasses the sidebar gate completely.
- **`current_store_id()`** helper exists (`helpers.php:55`) and is used app-wide — but none of the three controllers use it in `index()/edit()/update()/destroy()`.
- **Add/Edit = separate full pages, not modals** for all three modules (6 views).
- **All three lists = server-rendered pagination** (`->paginate($perPage)->withQueryString()`), not DataTable/AJAX.
- **No partials** included; each view is self-contained under `<x-app-layout>`.
- **Cross-store uniqueness**: all `unique:` rules are unscoped (`unique:db_category,category_name`, etc.). Store A cannot create "Electronics" if Store B already has it.
- **Alpine inline `x-data="{...}"` anti-pattern on all three list pages** (`categories_list.blade.php:2-25`, `brands_list.blade.php:2-25`, `variants_list.blade.php:2-25`) plus per-row `x-data="{ open: false }"` — the exact pattern that broke Add/Edit Item — vs. the proper `function itemForm()` used by Add Item (`add_item.blade.php:647`).

---

## 1. CATEGORIES

### 1.1 LOCATE
- **Controller**: `CategoryController.php` — `index():10`, `create():38`, `store():43`, `edit():71`, `update():76`, `destroy():90`.
- **Routes** (`items.categories.*`): `routes/web.php:255-260`.
- **Views**: `module/items/categories_list.blade.php` (list), `module/items/add_category.blade.php` (Add), `module/items/edit_category.blade.php` (Edit). No partials.
- **Data model**: `DbCategory` → table `db_category` (migration `2026_02_07_085609`). Key fields: `store_id` (nullable), `count_id`, `category_code`, `category_name`, `description`, `company_id`, `status` (default 1). **FLAT — no `parent_id`, no nesting (confirmed from migration).**
- **Data to view**: `compact('categories', 'stats')` (`CategoryController.php:35`).
- **Store scoping per CRUD method**:
  - `index()` — **NOT scoped**: `DbCategory::query()` with no `where('store_id', ...)` (`:12`); `$stats` counts also unscoped (`:29-33`). A Store-A user sees Store-B's categories and counts.
  - `create()` — view only, no data, no scope concern.
  - `store()` — writes `store_id => auth()->user()->store_id ?? 1` (`:56`); the `?? 1` fallback can silently misattribute the row when the user's `store_id` is null.
  - `edit()` — **unscoped route-model binding → IDOR** (`:71`).
  - `update()` — **same IDOR** (`:76`).
  - `destroy()` — **same IDOR** (`:90`). Cross-store delete confirmed (same class as pre-fix `ItemController::destroy()`).
- **Permission gate**: NONE on any route/controller method.

### 1.2 UI INVENTORY — LIST
- Filters/search:
  - Search input `name="search" x-model="searchTerm" @keydown.enter.prevent="submitFilters()"` (`:113`) — **WORKS**.
  - Status select `name="status" @change="submitFilters()"` (`:98-102`) — **WORKS**.
  - Per-page `name="per_page" @change="submitFilters()"` (`:89-93`) — **WORKS**.
  - All inside GET form `x-ref="filterForm"` (`:85`); `submitFilters()` calls `this.$refs.filterForm.submit()`.
- **Copy/Excel/PDF buttons: `type="button"`, no handler — DECORATIVE-DEAD** (`:108-110`).
- Columns: checkbox (`:145`), Category Name (sortable header `:128-133`), Category Code (`:156`), Description (`:159`), Status badge (`:162-166`), Action (`:169-180`). **No item-count per category** (no `withCount`).
- Row actions: Action dropdown → **Edit** link (`:176`), **Delete** button `@click="openDeleteModal(id); open=false"` (`:177`). Both **WORK**.
- Table-level: "New Category" link (`:41-44`) **WORK**. **Select-all checkbox + row checkboxes wire `selectedAll`/`toggleAll()`/`selectedCategories`, but `selectedCategories` is never consumed by anything → DEAD bulk-select control** (`:125`, `:145`). No bulk action, no export, **no activate/deactivate toggle on the list** (status only editable via the Edit page; there is no `toggle-status` route for categories).
- Delete confirmation modal (`:205-222`): `openDeleteModal(id)` sets `deleteId`, form posts `'{{ url('items/categories') }}/' + deleteId` with `_method=DELETE` — **WORKS**.
- Alpine: inline `x-data` object (anti-pattern); functions `openDeleteModal/toggleAll/submitFilters` all defined and reachable.

### 1.3 UI INVENTORY — ADD / EDIT (full pages)
- Add (`add_category.blade.php:24-64`): `category_name` (required), `category_code` (optional), `description` (textarea). Buttons: **Save Category** (`type="submit"`, `:55`), **Close** (link, `:59`). No Alpine on this page.
- Edit (`edit_category.blade.php:24-83`): adds **Status select Active/Inactive** (`:61-64`). Buttons: **Update Category** (`:74`), Close (`:78`).
- **No double-submit guard**: plain HTML submit, no `isSubmitting`/disable-on-submit (the gap class found originally on Quotation/Purchase/Service pre-fix).
- No undefined-function references on these pages (no Alpine handlers to check).

### 1.4 FUNCTIONAL AUDIT
- **Delete guard: NONE — silently orphans via `SET NULL`.** `destroy()` deletes outright (`CategoryController.php:92`). The DB FK `db_items.category_id → db_category ON DELETE SET NULL` (migration `090724:74`) silently nulls `category_id` on every referencing Item/Service (Services are `db_items` rows) → they render as "Uncategorized" (`COALESCE(db_category.category_name, 'Uncategorized')` at `ReportController.php:1973`). **Not blocked, not cascaded — orphaned-by-null.** No equivalent of `ItemController::destroy()`'s history guard (`ItemController.php:789-807`).
- **Store scoping on delete: MISSING (IDOR)**.
- **Double-submit guard: MISSING** on both Add and Edit.
- **Default visibility on create: OK** — `status => 1` hardcoded (`:55`); new categories are immediately Active and appear in downstream dropdowns (which filter `status = 1`). No hidden-inactive bug.
- **Raw DB error exposure on unique collision: PARTIALLY.** `unique:` validation catches the normal duplicate-name case with a clean error (`:46`). But there is **no try/catch around `create()`/`update()`** — a concurrent-race duplicate throws an uncaught `QueryException` → generic 500 (contrast `ServiceController.php:196-222` which translates 1062/19/23000). Uniqueness is **global across stores**, not per-store.
- **Edit rename cascade: SAFE** — displays join `db_items.category_id → db_category` live (e.g. `ItemController.php:116` `with(['category',...])`), so renaming a category correctly updates every Item/Service display with no drift.
- **New record end-to-end**: validate → insert (`status=1`, store_id from user) → redirect to list (`:68`); AJAX JSON branch (`:59-66`) is **dead — no consumer found calling this route via AJAX**. Immediately usable in Items/Service dropdowns; but note Items/POS dropdowns are **unscoped** (`ItemController.php:122-123,130-131,456-457`, `PosController.php:57`) while Service/Quotation/Purchase dropdowns are store-scoped (`ServiceController.php:107-109`, `QuotationController.php:67`, `PurchaseController.php:600-602`).

### 1.5 NON-STANDARD / HIGH RISK
- Flat (no tree) → a future delete guard is a simple existence check on `db_items.category_id`.
- Double-action risk: the Delete modal submit is a normal POST with no disable guard; double-click = second DELETE → `ModelNotFoundException` 404 (not data-corrupting, poor UX).
- Cross-store uniqueness is a functional defect.
- No toggle-status route exists for categories (list-level active/inactive toggle is entirely absent).

---

## 2. BRANDS

### 2.1 LOCATE
- **Controller**: `BrandController.php` — `index():11`, `create():39`, `store():44`, `edit():72`, `update():77`, `destroy():91`.
- **Routes** (`items.brands.*`): `routes/web.php:261-266`.
- **Views**: `module/items/brands_list.blade.php`, `module/items/add_brand.blade.php`, `module/items/edit_brand.blade.php`. No partials.
- **Data model**: `DbBrand` → table `db_brands` (migration `2026_02_07_085606`). Fields: `store_id` (nullable), `brand_code`, `brand_name`, `description`, `status` (default 1). **Flat.**
- **Data to view**: `compact('brands', 'stats')` (`:36`).
- **Store scoping per CRUD method** — identical gap profile to Categories:
  - `index()` unscoped (`:13`), `$stats` unscoped (`:30-34`).
  - `store()` writes `store_id => auth()->user()->store_id ?? 1` (`:57`).
  - `edit()`/`update()`/`destroy()` unscoped route-model binding (`:72`,`:77`,`:91`) → **IDOR**.
- **Permission gate: NONE** on any route/controller.

### 2.2 UI INVENTORY — LIST
- Structurally identical to Categories list: search (`:112-113`), status select (`:96-102`), per-page (`:88-93`), **Copy/Excel/PDF DEAD** (`:107-110`), sortable Brand Name header (`:127-133`), Brand Code (`:155`), Description (`:158`), Status badge (`:161-166`), Action dropdown Edit/Delete (`:168-180`), dead select-all (`:124-125`, `:144`), delete modal (`:207-224`). **No item-count column.**
- Row/table actions status: same as Categories — Edit/Delete/New **WORK**, Copy/Excel/PDF and bulk-selection **DEAD**, no toggle-status route.

### 2.3 UI INVENTORY — ADD / EDIT
- Add (`add_brand.blade.php:24-64`): `brand_name` (required), `brand_code`, `description`. Save Brand (`:55`) / Close (`:59`).
- Edit (`edit_brand.blade.php:24-83`): adds Status select (`:61-64`). Update Brand (`:74`) / Close (`:78`).
- **No double-submit guard**; no Alpine handlers to check.

### 2.4 FUNCTIONAL AUDIT
- **Delete guard: NONE — silently orphans via `SET NULL`.** `destroy()` deletes outright (`BrandController.php:93`); FK `db_items.brand_id → db_brands ON DELETE SET NULL` (migration `090724:75`). Items/Services referencing the brand silently lose `brand_id` → blank display. **Not blocked, not cascaded — orphaned-by-null.** No history guard (no equivalent of `ItemController::destroy()` `:789-807`).
- **Store scoping on delete: MISSING (IDOR)**.
- **Double-submit guard: MISSING** on Add and Edit.
- **Default visibility on create: OK** — `status => 1` hardcoded (`:56`); new brand immediately Active/visible.
- **Raw DB error exposure: PARTIALLY** — `unique:` clean on single-request dup (`:47`), but no try/catch → race = raw 500. Cross-store uniqueness.
- **Edit rename cascade: SAFE** — live join via `with(['brand',...])` (`ItemController.php:116`); renames propagate with no drift.
- **New record end-to-end**: validate → insert → redirect (`:69`); AJAX JSON branch (`:60-66`) is **dead — no consumer calls it**. Usable in Items/Quotation/Purchase/POS dropdowns (unscoped in Items/POS; store-scoped in Service/Quotation/Purchase).

### 2.5 NON-STANDARD / HIGH RISK
- Flat; delete guard = `db_items.brand_id` existence check.
- Double-action risk on delete modal (no disable guard).
- Cross-store uniqueness defect.
- No toggle-status route for brands.

---

## 3. VARIANTS

### 3.0 CRITICAL — WHAT A "VARIANT" RECORD ACTUALLY IS (confirmed from schema, not page name)
**A `db_variants` record is a flat attribute-type (e.g. "Color", "Size") with NO child values, NO hierarchy, NO combination logic.**

Evidence:
- Migration `2026_02_07_092509` (`create_db_variants_table.php`): only `id, store_id, variant_code, variant_name, description, status`. **No child/value table, no JSON column, no parent_id.**
- Add form (`add_variant.blade.php:24-64`): only name/code/description; placeholder "E.g. Color, Size" = attribute type. **There is NO repeatable add/remove-value-row UI and NO per-value status flag** — nothing to inventory.
- **Downstream**: `db_items.variant_id` exists and `DbItem::variant()` belongsTo exists (`DbItem.php:84-87`), **but nothing in the codebase ever writes `variant_id`** (no `variant_id =>` assignment found in any controller). ItemController's Box-variant UI (`parent_id`/`child_bit`) **does NOT consume `db_variants`** — it is a separate child-row system (`ItemController.php:346-398`, update loop `:676-740`).
- **Only reader**: `module/sales/show.blade.php:127-131` and `module/sales/emi_details.blade.php:94` — sale detail checks `$item->item?->variant_id && $item->item?->variant` to show a `variant->variant_name` badge. Since `variant_id` is never written, this branch is **de facto dead**; Box-variant sold items are handled through parent_id-children, not `db_variants`.
- `db_items.variant_id` has **no FK** (migration `090724` only declares FKs for `category_id` and `brand_id`) → deleting a `db_variants` row never NULLs any item's `variant_id` (dangling id), but since nothing writes it, real impact is zero.

**Conclusion: Variants module = flat single-level list. Attribute-type-with-children (Color → {Red,Blue,Green}) is NOT the model. Box-variant combination logic (`parent_id`/`child_bit`) lives in Items, is protected, and is outside VariantController's reach.**

### 3.1 LOCATE
- **Controller**: `VariantController.php` — `index():10`, `create():38`, `store():43`, `edit():62`, `update():67`, `destroy():81`.
- **Routes** (`items.variants.*`): `routes/web.php:267-271`.
- **Views**: `module/items/variants_list.blade.php`, `module/items/add_variant.blade.php`, `module/items/edit_variant.blade.php`. No partials.
- **Data to view**: `compact('variants', 'stats')` (`:35`).
- **Store scoping per CRUD method**:
  - `index()` **NOT scoped** (`:12`); `$stats` unscoped (`:29-33`).
  - `store()` writes `store_id => auth()->user()->store_id` (`:56`) — no `?? 1` fallback (slightly better), but can write a store-less row if the user's `store_id` is null.
  - `edit()`/`update()`/`destroy()` unscoped route-model binding (`:62`,`:67`,`:81`) → **IDOR**.
- **Permission gate: NONE** — no `variant_view/variant_add/...` gate on any route/controller (sidebar link at `app.blade.php:539-542` is the only gate).

### 3.2 UI INVENTORY — LIST
- Identical skeleton to Categories/Brands: search (`:112-113`), status select (`:96-102`), per-page (`:88-93`), **Copy/Excel/PDF DEAD** (`:107-110`), sortable Variant Name (`:127-133`), Variant Code (`:156`), Description (`:159`), Status badge (`:162-166`), Action dropdown Edit/Delete (`:168-180`), dead select-all (`:124-125`, `:144`), delete modal (`:204-222`).
- Row actions: Edit **WORK**; Delete button `@click="openDeleteModal(...)` **WORK** (modal opens), **but there is NO `Route::delete('variants/{variant}', ...)`** — `routes/web.php:267-271` only registers index/add/store/edit/update. The modal's DELETE POST therefore hits no matching route → **404/405. Delete is MISSING/BROKEN end-to-end.**

### 3.3 UI INVENTORY — ADD / EDIT
- Add (`add_variant.blade.php:24-64`): `variant_name` (required), `variant_code`, `description`. Save Variant (`:55`) / Close (`:59`).
- Edit (`edit_variant.blade.php:24-83`): adds Status select (`:61-64`). Update Variant (`:74`) / Close (`:78`).
- **No child-value row UI** — add/remove/reorder/status-per-value: nothing exists.
- **No double-submit guard** — plain submit.

### 3.4 FUNCTIONAL AUDIT
- **Delete guard: NONE — and `db_variants.store_id` FK (`db_store ON DELETE CASCADE`, migration `:27`) is the only FK.** `db_items.variant_id` has no FK → deleting a variant never NULLs item references (dangling id), but since nothing writes it, real impact is nil. **Not blocked, not cascaded — de facto disconnected.**
- **Store scoping on delete: MISSING (IDOR)** + **DELETE route absent** → `destroy()` is unreachable via UI.
- **Single Value deletion/edit**: N/A — there is no Value concept. Box-variant child-row delete/update lives in Items (`ItemController.php:740` — `whereNotIn`-excluded children deleted), untouched by this module.
- **Double-submit guard: MISSING**.
- **Default visibility on create: OK** — `status => 1` hardcoded (`:55`). **However, a new Variant is consumed nowhere** — Items' Box-variant UI does not read `db_variants`, POS does not, so a new Variant is "immediately usable" but **never used by any UI (MISSING downstream wiring)**.
- **Raw DB error exposure: PARTIALLY** — `unique:` clean error on duplicate name (`:46`), but no try/catch → race = raw 500. Cross-store uniqueness.

### 3.5 NON-STANDARD / HIGH RISK
- **Highest risk**: Variants module is a half-built model — `db_variants` is read/written by nothing effective. A future redesign must preserve/merge two distinct things: (a) the flat `db_variants` attribute-type list, and (b) Items' Box-variant child-row system (`parent_id`/`child_bit` + the `item_name`-"-Name" suffix convention, `ItemController.php:680`) — the latter is **protected logic** that must remain intact.
- **`db_items.variant_id` is a dead schema column** — no FK, no writer, read only by dead sale-detail branches.
- Double-action risk on delete modal (no disable guard), though moot while the delete route is absent.
- Cross-store uniqueness defect.
- No toggle-status route for variants.

---

## 4. CROSS-MODULE SUMMARY

| Dimension | Categories | Brands | Variants |
|---|---|---|---|
| **Store-scoping `index()`** | MISSING (unscoped query + unscoped stats) | MISSING (same) | MISSING (same) |
| **Store-scoping `create()`** | OK at write (`?? 1` fallback risk) | OK at write (`?? 1` fallback risk) | OK at write (no fallback; NULL-store risk) |
| **Store-scoping `edit()`** | MISSING (IDOR) | MISSING (IDOR) | MISSING (IDOR) |
| **Store-scoping `update()`** | MISSING (IDOR) | MISSING (IDOR) | MISSING (IDOR) |
| **Store-scoping `destroy()`** | MISSING (IDOR) | MISSING (IDOR) | MISSING (IDOR) + **DELETE route absent** |
| **Delete-guard status** | **SILENTLY ORPHANS** — outright delete; FK `SET NULL` on `db_items.category_id` | **SILENTLY ORPHANS** — outright delete; FK `SET NULL` on `db_items.brand_id` | **NEITHER** — no FK on `db_items.variant_id`; no delete route; de facto disconnected |
| **Permission-gate status** | **UNGUARDED** (slug `items_category_view` exists, sidebar-only) | **UNGUARDED** (slug `brand_view` exists, sidebar-only) | **UNGUARDED** (slug `variant_view` exists, sidebar-only) |
| **Default-visibility-on-create** | **OK** — `status=1` hardcoded | **OK** — `status=1` hardcoded | **OK** — `status=1` hardcoded (but consumed nowhere) |
| **Alpine inline `x-data` anti-pattern** | **YES** (list page top-level + per-row) | **YES** (same) | **YES** (same) |
| **Raw DB error on unique collision** | **YES** — validation covers single-request dup, but no try/catch → race = raw 500 | **YES** (same) | **YES** (same) |
| **Active/Inactive toggle on list** | **NO** (only via Edit page Status select) | **NO** (same) | **NO** (same) |
| **Design-system adoption** | **~65%** — token classes/rounded cards match redesigned pages, but: no `<x-searchable-select>` filter (items_list uses it), dead Copy/Excel/PDF, dead bulk-select, `<x-primary-button>`/`btn-primary` unused, raw `<select>` | **~65%** (identical) | **~65%** (identical) |
| **Downstream consumers traced** | Items/Services/Quotation/Purchase/POS dropdowns + ReportController joins + item-sales-by-category report (`ReportController.php:1969-1978`) | Items/Quotation/Purchase/POS dropdowns + brand-wise stock report (`ReportController.php:1466`) | **None effective** — `db_items.variant_id` never written; only dead display branches in sales show / emi_details |

### Most important confirmed findings (all three modules)

1. **All three are fully unguarded** — no permission gate in any route/controller (exact pre-fix Service state). Slugs exist only in the sidebar; direct URL access works.
2. **Store-scoping is zero** — `index()` through `destroy()`, all three modules can view/edit/delete cross-store rows (the pre-fix Item/Service IDOR state). `store()` is the only partially-scoped method.
3. **No delete guard** — Categories/Brands silently orphan via `SET NULL` on `db_items` FKs; no history-block (unlike `ItemController::destroy()`). Variants' whole delete path is disconnected (no route).
4. **"Variant" = flat attribute-type record** (Color/Size only); child-values/combination do not exist here. The Box-variant combination system lives in Items under `parent_id`/`child_bit` and is protected.
5. **Dead UI elements** on all three lists: Copy/Excel/PDF buttons (no handler), select-all bulk checkboxes (never consumed), plus Categories/Brands' AJAX store branches (no consumer).
6. **Add/Edit pages lack double-submit guards**; create/update lack try/catch so concurrent duplicate-name races produce raw 500s; uniqueness is global across stores, not per-store.
