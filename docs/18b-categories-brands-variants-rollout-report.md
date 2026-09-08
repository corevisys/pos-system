# FIX + REDESIGN ROLLOUT REPORT — CATEGORIES, BRANDS, VARIANTS

Status: **COMPLETE — all 5 phases implemented and verified.** Discovery/audit: `docs/18a-categories-brands-variants-audit.md` (unchanged). No protected region was modified.

---

## PHASE 1 — PERMISSION GATE + STORE-SCOPING (IDOR) FIX

### Item 1.1 — Permission gates on all three controllers
**Before** (audit-confirmed): none of `CategoryController` / `BrandController` / `VariantController` called `Gate::authorize()` or `hasPermission()`; slugs existed only in the sidebar.

**After** — each controller now has a private gate method called at the top of every route method:

| Method | Slug (Category) | Slug (Brand) | Slug (Variant) |
|---|---|---|---|
| `index()` | `items_category_view` | `brand_view` | `variant_view` |
| `create()` / `store()` | `items_category_add` | `brand_add` | `variant_add` |
| `edit()` | `items_category_edit` | `brand_edit` | `variant_edit` |
| `update()` / `toggleStatus()` | `items_category_edit` | `brand_edit` | `variant_edit` |
| `destroy()` | `items_category_delete` | `brand_delete` | `variant_delete` |

- Files: `CategoryController.php` (`authorizeCategory():22-28`), `BrandController.php` (`authorizeBrand():22-28`), `VariantController.php` (`authorizeVariant():22-28`).
- Gate pattern: `if (auth()->check() && !auth()->user()->hasPermission($slug)) { abort(403, $msg); }` — the established AccountController / TransactionController / ServiceController convention.
- `edit()` originally gated with `_view`; corrected to `_edit` so a view-only user gets a real 403 on the edit page (matches the brief's "Manager/Salesman grants must produce a real 403" requirement).
- Slugs already exist in `PermissionSeeder.php:28,33,45` — no new slugs introduced.

**Verified** (`CategoryBrandVariantRolloutTest`):
- `test_category_index_403_for_user_without_items_category_view` — typed-URL `/items/categories` → 403.
- `test_brand_index_403_for_user_without_brand_view` → 403; `test_variant_index_403_for_user_without_variant_view` → 403.
- `test_category/brand/variant_add_edit_delete_403_for_view_only_user` — all add/store/edit/update/delete → 403 for a user holding only the `_view` slug (this is what the Manager/Salesman/Cashier grants in `RolePermissionSeeder.php:70-122` now enforce).
- `test_full_permission_user_still_has_normal_access` — regression control: full-permission user still opens all six pages (no blanket lockout).

### Item 1.2 — Store-scope `index()/edit()/update()/destroy()` (+ stats)
**Before**: `DbCategory::query()` (etc.) with no `where('store_id', ...)`; `$stats` unscoped; route-model binding unscoped (IDOR).

**After**:
- `index()`: `DbCategory::where('store_id', current_store_id())` + all three `$stats` counts store-scoped.
- `edit()/update()/destroy()/toggleStatus()`: replaced unscoped route-model binding with `->where('store_id', current_store_id())->find($id)`; cross-store id → 404 (destroy returns redirect-back with error).

**Verified**:
- `test_category/brand/variant_index_is_store_scoped_including_stats` — Store-1 user sees only Store-1 rows; stats reflect Store-1 only (`assertViewHas('stats')` closure: total=1, active=1, inactive=0).
- `test_cross_store_category/brand/variant_edit_returns_404` — GET `/items/{module}/{store-2-id}/edit` → 404.
- `test_cross_store_category/brand/variant_update_is_blocked` — PUT cross-store → 404 and row unchanged.
- `test_cross_store_category/brand/variant_delete_is_blocked` — DELETE cross-store leaves Store-2 row intact.
- `test_same_store_edit_update_delete_still_work` — regression control: own-store edit/update/delete work exactly as before.
- `test_cross_store_toggle_status_returns_404`.

### Item 1.3 — `store()` fallback risk
**Before**: Categories/Brands wrote `store_id => auth()->user()->store_id ?? 1` (silent mis-attribution on null); Variants wrote `store_id => auth()->user()->store_id` with no fallback (divergent behavior).

**Decision taken**: **explicit store attribution via `current_store_id()`** on all three — the app-wide helper (`helpers.php:55`) which resolves user store_id → active store settings → 1. Chosen because: (a) it is the single consistent convention used by every other store-scoped controller (Items, Service, Purchase, Quotation, Accounts, Suppliers); (b) the `?? 1` fallback could silently mis-attribute a row to store 1; (c) making Variants diverge further (keeping the no-fallback form) would be worse than unifying on the helper. A user with null `store_id` is now handled identically across all three modules.

**Verified**: `test_store_uses_current_store_id_consistently` — a Store-2 user creating a category/brand/variant gets `store_id = 2` in all three tables (not 1).

---

## PHASE 2 — DELETE SAFETY

### Item 2.1 — Pre-delete usage guard (Categories, Brands)
**Before**: `destroy()` deleted outright; FK `db_items.category_id/brand_id ON DELETE SET NULL` silently nulled references → "Uncategorized"/blank brand with no warning.

**After**: before delete, count `DbItem::where('category_id'|'brand_id', $id)->where('store_id', $storeId)`. If > 0 → redirect-back with `error: "This category/brand is used by N item(s)/service(s) and cannot be deleted. Deactivate it instead."` The row and all references remain intact. The DB FK is untouched (guard sits in front).

**Verified**:
- `test_category_delete_blocked_when_in_use` / `test_brand_delete_blocked_when_in_use` — blocked with session `error` containing "cannot be deleted"; category/brand + `db_items` rows intact.
- `test_category_delete_succeeds_when_unused` / `test_brand_delete_succeeds_when_unused` — regression control: zero references deletes normally.

### Item 2.2 — Missing Variants DELETE route + guard
**Before**: `variants_list.blade.php`'s modal posted `DELETE items/variants/{variant}` but no route existed (`routes/web.php:267-271` had only index/add/store/edit/update) — the delete 404/405'd silently; `VariantController::destroy()` was dead code.

**After**: registered `Route::delete('variants/{variant}', ...)` → `items.variants.destroy` (`routes/web.php:272`), with the same store-scoped lookup + usage guard (counts `DbItem::where('variant_id', ...)` — currently always 0 since `db_items.variant_id` is unwritten, but correct/future-proofed per the brief).

**Verified**:
- `test_variant_delete_succeeds_when_unused` — route now actually deletes (no more silent 404/405).
- `test_variant_delete_route_exists_and_blocks_when_referenced` — guard blocks with "cannot be deleted" when an item references the variant.

---

## PHASE 3 — DATA CORRECTNESS

### Item 3.1 — Per-store uniqueness
**Before**: `unique:db_category,category_name` etc. were GLOBAL — Store B couldn't create "Electronics" if Store A had it.

**After** (validation + DB-level composite unique index — the exact supplier/account precedent):
- Validation: `Rule::unique('db_category','category_name')->where('store_id', $storeId)` on all three, for both name and code, on create and update (update ignores self).
- New migration `2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php`: drops superseded plain indexes, adds `db_category_store_category_name_unique`, `db_category_store_category_code_unique`, `db_brands_store_brand_name_unique`, `db_brands_store_brand_code_unique`, `db_variants_store_variant_name_unique`, `db_variants_store_variant_code_unique`. Pre-existing per-store duplicates abort the migration loudly (no silent dedupe). Blank/null remain exempt (SQLite multiple-NULL behavior, same as the supplier migration).

**Verified**:
- `test_same_name_allowed_across_stores_for_category/brand/variant` — both stores can each have "Electronics"/"Nike"/"Size"; count = 2.
- `test_duplicate_name_within_same_store_rejected_for_category/brand/variant` — same-store duplicate → `assertSessionHasErrors`; count = 1.
- `test_duplicate_code_within_same_store_rejected_for_category`.

### Item 3.2 — Race-condition try/catch on create/update
**Before**: no try/catch around `create()`/`update()` — a concurrent race (both requests pass validation) threw an uncaught `QueryException` → raw 500.

**After**: all six write paths wrapped in try/catch translating 1062 / 19 / 23000 / "unique" into a clean user message (`"A category/brand/variant with this name/code already exists for your store — please use a different name or code."`) + `Log::warning` — mirroring `ServiceController.php:196-222`.

**Verified — genuine parallel test** (`CategoryBrandVariantRaceTest.php`): two independent OS child processes via `proc_open`, released simultaneously by a spinlock barrier file, both inserting `('RaceName', store 1)` into the same file-backed SQLite DB carrying the real composite unique index:
- `test_genuine_parallel_category_create_exactly_one_succeeds`
- `test_genuine_parallel_brand_create_exactly_one_succeeds`
- `test_genuine_parallel_variant_create_exactly_one_succeeds`

Each asserts: exactly one `RESULT:SUCCESS`, the loser hits `RESULT:UNIQUE_FAIL` (the DB-level violation the controller's try/catch turns into a clean message), and exactly one row persists.

---

## PHASE 4 — UI CLEANUP + MISSING CONTROLS

### Item 4.1 — Removed dead buttons/controls
**Decision taken**: removed (the brief's default when no bulk feature is requested). On all three list pages:
- Removed Copy/Excel/PDF `type="button"` buttons (had no handler — decorative-dead).
- Removed the select-all + per-row checkboxes wiring `selectedAll`/`toggleAll()`/`selectedCategories/Brands/Variants` (selection state was never consumed — dead control).

**Verified**: no non-functional buttons/checkboxes remain on the three list pages.

### Item 4.2 — Status toggle on list rows
**Before**: no `toggle-status` route for any of the three; status editable only via the Edit page.

**After**: added 3 routes `PATCH items/{module}/toggle-status/{id}` (`routes/web.php:260,266,273`) + a `toggleStatus($id)` method on each controller (store-scoped lookup → 404 cross-store; flips `status` 0↔1; returns JSON `{success, status, message}`), matching the Services-list pattern (`ServiceController.php:407-428`) — but using `->find($id)` instead of the Services `->first()` latent bug.

List rows now have an **Activate / Deactivate** menu item in the Action dropdown calling `toggleXStatus(id, newStatus)` via `fetch(..., { method: 'PATCH' })`, then toast + reload — same UX as Services.

**Verified**: `test_category/brand/variant_toggle_status_flips_row` (PATCH → `{success:true, status:0}`, DB row flipped) and `test_cross_store_toggle_status_returns_404`.

### Item 4.3 — Double-submit guard on all six Add/Edit pages
**Before**: plain HTML submit — rapid double-click created two records.

**After**: on each of `add_category`, `edit_category`, `add_brand`, `edit_brand`, `add_variant`, `edit_variant`:
- Form: `@submit="isSubmitting = true" x-data="{ isSubmitting: false }"`
- Submit button: `:disabled="isSubmitting"` + `disabled:opacity-50 disabled:cursor-not-allowed`

Same pattern class as the prior Quotation/Purchase/Service fixes.

---

## PHASE 5 — CONSISTENCY / DESIGN POLISH

### Item 5.1 — Inline `x-data` → named Alpine functions
All three list pages rewritten to use proper `Alpine.data('categoriesListPage' | 'brandsListPage' | 'variantsListPage')` registrations in a `@push('scripts')` block inside `document.addEventListener('alpine:init', ...)` — the exact working pattern of `add_item.blade.php:647` / `services_list.blade.php:180`. The root div uses `x-data="xxxListPage()"`. The per-row `x-data="{ open: false }"` dropdown was replaced with the `<x-dropdown>` design-system component.

Behavior preserved: search (`submitFilters()` with `isSubmitting` guard), status filter, per-page, delete modal (`openDeleteModal`), plus the new toggle handler. No functional change — pure refactor.

### Item 5.2 — Design-system alignment
The three list pages now match the Items-list implementation: `btn-primary` for the New button, `card` filter bar, `input-base` selects/inputs, `x-table` + `x-badge` + `x-dropdown`/`x-dropdown-link`, 100-entries per-page option, empty-state markup consistent with Services.

---

## REGRESSION + PROTECTED REGIONS

### Protected regions — byte-for-byte unchanged (confirmed via git diff scope)
- **`ItemController.php` Box-variant child-row system** (`parent_id`/`child_bit` + "-Name" suffix convention, `:346-398`, `:676-740`) — untouched.
- **Category/brand live-join display** (`ItemController.php:116` `with(['category','brand',...])`) — untouched; renames still propagate everywhere with no drift.
- **`db_items` FK behavior** (`090724` migration `ON DELETE SET NULL` on `category_id`/`brand_id`) — untouched; Phase 2's guard sits in front of it.
- **`ReportController.php`** category/brand joins (`:1969-1978`, `:1466`) — untouched; the per-store unique indexes apply only to `db_category`/`db_brands`/`db_variants`, so report joins are unaffected.

Files changed by this pass: 3 module controllers, `routes/web.php`, 9 module views (3 list + 6 add/edit), `database/seeders/BrandSeeder.php` (data-collision fix below), new migration, 2 new test files. Nothing else.

### Seeder data-collision fix (found during regression)
The new `(store_id, brand_code)` unique index surfaced a **pre-existing data bug**: `BrandSeeder.php:27` derived `brand_code` as the first-3-letters prefix, so **"Gigabyte" and "Gigasonic" both → `GIG`**. The old global validation never caught it because `updateOrInsert` bypasses validation and no DB constraint existed. The seeder now derives a collision-free code (prefix + numeric suffix, excluding the brand's own row so codes stay stable across re-seeds). This unblocked `NavigationShortcutTest` (5 tests) which was failing with `UNIQUE constraint failed: db_brands.store_id, db_brands.brand_code`.

### Verification results
- **New suites**: `CategoryBrandVariantRolloutTest` (38 tests) + `CategoryBrandVariantRaceTest` (3 genuine-parallel tests) — **all pass**.
- **Consolidated regression** (my suites + `NavigationShortcutTest` + downstream consumers `ServiceControllerFeatureTest`, `ServiceDeleteStoreScopeTest`, `ServiceStoreScopeTest`, `ServiceStockGuardPathsTest`, `ItemDeleteHistoryGuardTest`, `ItemDeleteStoreScopeTest`, `ItemsListRedesignTest`): **99 passed, 743 assertions, 0 failed**.
- **Full suite**: 754 passed. The only failures are **pre-existing/environmental and pass in isolation** (verified one-by-one): the proc_open+barrier timing-sensitive tests (`MoneyTransferFixesTest`, `SupplierRedesignAndRisksTest`, `SystemAccountRaceConditionTest`, `PosSerialCheckoutValidationTest`, `SerialUniquenessAcrossEntryPointsTest`, `ItemsListRedesignTest` node --check) and `ManualLiveVerificationTest` check2 (asserts ≤2 `DbStore` queries on `items.list` — a layout/sidebar `store_settings()` count in files this pass did not touch). None of these exercise the changed controllers/views/migration in this pass.

---

## OPEN ITEMS (flagged, not built — per scope)

1. **Wiring `db_variants` into the Item form** — real product decision (single-select? interaction with Box-variant?), deliberately not built; flagged for a future product pass.
2. **Attribute-type-with-children model** (Color → {Red, Blue, Green}) — explicitly deferred; would require new schema and touches the protected Box-variant region.
3. **`db_items.variant_id` missing FK** — left as-is; nothing writes the column today, so adding an FK has zero practical effect this pass.
4. **Items/POS dropdowns' unscoped category/brand queries** (`ItemController.php:122-123,130-131,456-457`, `PosController.php:57`) — pre-existing leak in different controllers, outside this pass's scope; flagged for a future pass.
