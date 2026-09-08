# CURRENT STATE REPORT — CATEGORIES, BRANDS, VARIANTS

Compiled at current workspace state. Status report only — no code was changed in this prompt.

Git baseline: single commit `774e544` ("Initial commit: CorevisysPOS baseline import"). **All module work is UNCOMMITTED** — no separate commits exist for any of it.

---

## 1. FILES & ROUTES (current, per module)

### CATEGORIES
- Controller: `app/Http/Controllers/CategoryController.php`
- Model: `app/Models/DbCategory.php` → table `db_category` (**UNMODIFIED from baseline**)
- Migration: `database/migrations/2026_02_07_085609_create_db_category_table.php` (baseline, untouched)
- New migration (added): `database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php`
- Views: `resources/views/module/items/categories_list.blade.php` (list), `add_category.blade.php`, `edit_category.blade.php`
- Routes (7, confirmed via `php artisan route:list`):
  - GET `items/categories` → `CategoryController@index` (`items.categories`)
  - GET `items/categories/add` → `create` (`items.categories.add`)
  - POST `items/categories` → `store` (`items.categories.store`)
  - GET `items/categories/{category}/edit` → `edit` (`items.categories.edit`)
  - PUT `items/categories/{category}` → `update` (`items.categories.update`)
  - DELETE `items/categories/{category}` → `destroy` (`items.categories.destroy`)
  - PATCH `items/categories/toggle-status/{category}` → `toggleStatus` (`items.categories.toggle-status`)

### BRANDS
- Controller: `app/Http/Controllers/BrandController.php`
- Model: `app/Models/DbBrand.php` → table `db_brands` (**UNMODIFIED**)
- Migration: `database/migrations/2026_02_07_085606_create_db_brands_table.php` (baseline, untouched)
- Views: `brands_list.blade.php`, `add_brand.blade.php`, `edit_brand.blade.php`
- Routes (7, mirror of categories):
  - GET `items/brands` → index; GET `items/brands/add` → create; POST `items/brands` → store
  - GET `items/brands/{brand}/edit` → edit; PUT `items/brands/{brand}` → update
  - DELETE `items/brands/{brand}` → destroy; PATCH `items/brands/toggle-status/{brand}` → toggleStatus

### VARIANTS
- Controller: `app/Http/Controllers/VariantController.php`
- Model: `app/Models/DbVariant.php` → table `db_variants` (**UNMODIFIED**)
- Migration: `database/migrations/2026_02_07_092509_create_db_variants_table.php` (baseline, untouched)
- Views: `variants_list.blade.php`, `add_variant.blade.php`, `edit_variant.blade.php`
- Routes (7, mirror of categories):
  - GET `items/variants` → index; GET `items/variants/add` → create; POST `items/variants` → store
  - GET `items/variants/{variant}/edit` → edit; PUT `items/variants/{variant}` → update
  - **DELETE `items/variants/{variant}` → destroy (ADDED in this work — was missing on baseline)**
  - PATCH `items/variants/toggle-status/{variant}` → toggleStatus (ADDED)

All route declarations: `routes/web.php:255-275`, inside the `['auth','verified']` middleware group (`:44`), `items.` prefix (`:232`). No permission middleware on any route (gates are in-controller).

---

## 2. WHAT'S CHANGED vs. BASELINE

### Uncommitted modified files (git status `M`)
| File | Change summary |
|---|---|
| `app/Http/Controllers/CategoryController.php` | Permission gates (`items_category_view/add/edit/delete`), store-scoped index/stats/edit/update/destroy/toggle, `store()` uses `current_store_id()`, per-store `Rule::unique`, try/catch on create/update, pre-delete usage guard, new `toggleStatus()` |
| `app/Http/Controllers/BrandController.php` | Same treatment with `brand_*` slugs |
| `app/Http/Controllers/VariantController.php` | Same treatment with `variant_*` slugs + usage guard on destroy (currently no-op) |
| `routes/web.php` | Added 3 `toggle-status` PATCH routes + Variants DELETE route |
| `resources/views/module/items/categories_list.blade.php` | Redesigned (named Alpine fn, x-table/x-badge/x-dropdown/btn-primary/card/input-base, status toggle, dead buttons removed) |
| `resources/views/module/items/brands_list.blade.php` | Same |
| `resources/views/module/items/variants_list.blade.php` | Same |
| `resources/views/module/items/add_category.blade.php` | Redesigned (card/input-base/btn-primary/btn-secondary header, inline `@error`, root-scoped double-submit guard) |
| `resources/views/module/items/edit_category.blade.php` | Same + Status select parity |
| `resources/views/module/items/add_brand.blade.php` | Same |
| `resources/views/module/items/edit_brand.blade.php` | Same + Status select |
| `resources/views/module/items/add_variant.blade.php` | Same |
| `resources/views/module/items/edit_variant.blade.php` | Same + Status select |
| `database/seeders/BrandSeeder.php` | Collision-free `brand_code` generation (Gigabyte/Gigasonic both → `GIG` fix) |

### Untracked files (`??`)
- `database/migrations/2026_09_08_000001_make_category_brand_variant_name_code_unique_per_store.php` — per-store composite unique indexes
- `tests/Feature/CategoryBrandVariantRolloutTest.php` (38 tests)
- `tests/Feature/CategoryBrandVariantRaceTest.php` (3 genuine-parallel tests)
- `tests/Feature/CategoryBrandVariantAddEditRedesignTest.php` (10 tests)
- `docs/18a-categories-brands-variants-audit.md`, `docs/18b-categories-brands-variants-rollout-report.md`

### Committed history
None for this work — everything sits uncommitted on top of `774e544`. No separate commits exist.

### Module status verdict
- **Categories**: **COMPLETE** (controllers + list + add/edit all changed and tested)
- **Brands**: **COMPLETE**
- **Variants**: **COMPLETE**
- Not-started: none of the three. Models (`DbCategory/DbBrand/DbVariant`) remain baseline-unmodified (they were already correct — fillable/scopes present).

---

## 3. QUICK FUNCTIONAL AUDIT (current state)

### Categories — list page
| Element | Status | Note |
|---|---|---|
| Search input | WORKS | GET form + `submitFilters()` Alpine |
| Status filter select | WORKS | `@change="submitFilters()"` |
| Per-page (10/25/50/100) | WORKS | `@change="submitFilters()"` |
| Sort (Category Name) | WORKS | URL query `sort`/`order` |
| Pagination links | WORKS | `$categories->links()` |
| Copy/Excel/PDF export buttons | REMOVED (18b) | previously decorative-dead |
| Bulk select-all checkboxes | REMOVED (18b) | previously dead (selection never consumed) |
| Edit row action | WORKS | dropdown → edit route |
| Delete row action | WORKS | modal → DELETE route (store-scoped + usage guard) |
| Activate/Deactivate toggle | WORKS | PATCH toggle-status (added 18b) |
| Stats cards (total/active/inactive) | WORKS | store-scoped |

### Categories — add/edit forms
| Element | Status | Note |
|---|---|---|
| category_name | WORKS | required, inline `@error` (added 18c) |
| category_code | WORKS | nullable, inline `@error` |
| description | WORKS | textarea, inline `@error` |
| Status select (edit only) | WORKS | pre-selects current value |
| Save/Update button | WORKS | `btn-primary` + double-submit guard |
| Cancel button | WORKS | `btn-secondary` → list |
| Top-of-page error alert | WORKS | `$errors`/`session('error')` (added 18c) |
| Client-side (JS) validation | MISSING | none on add/edit (server-side only) |

### Brands — identical to Categories (list + forms), all WORKS / same removals.

### Variants — list page
Same as Categories list: search/status/per-page/sort/pagination/stats/edit/delete/toggle all **WORKS**; exports + bulk-select **REMOVED**; delete route **now exists** (was MISSING on baseline).

### Variants — add/edit forms
Same as Categories: name/code/description (+ Status on edit) all **WORKS** with inline `@error`; Save/Cancel `btn-primary`/`btn-secondary` + double-submit guard. **No child-value row UI exists** (by design — flat model, deferred).

---

## 4. STORE SCOPING (current)

| Scope point | Categories | Brands | Variants |
|---|---|---|---|
| `index()` list query | ✅ `where('store_id', current_store_id())` | ✅ | ✅ |
| `$stats` counts | ✅ store-scoped | ✅ | ✅ |
| `edit()` lookup | ✅ store-scoped → 404 cross-store | ✅ | ✅ |
| `update()` lookup | ✅ store-scoped → 404 cross-store | ✅ | ✅ |
| `destroy()` lookup | ✅ store-scoped → 404 cross-store | ✅ | ✅ |
| `toggleStatus()` | ✅ store-scoped → 404 cross-store | ✅ | ✅ |
| `store()` write | ✅ `current_store_id()` (no `?? 1`) | ✅ | ✅ |
| Filter dropdowns on the LIST pages | N/A (no category/brand dropdown filters on these lists; Items/POS dropdowns are separate) | | |
| **Items/POS category/brand dropdowns** | ⚠️ **NOT store-scoped** — `ItemController.php:122-123,130-131,456-457`, `PosController.php:57` (pre-existing, outside scope, flagged) | | |

---

## 5. DESIGN-SYSTEM ADOPTION

Per module, list + forms:

- **List pages (all 3)**: ~**90%** — `card`, `btn-primary`, `input-base`, `x-table`, `x-badge`, `x-dropdown`/`x-dropdown-link`, `@push('scripts')` + named `Alpine.data()`. Remaining legacy: stats cards use inline `rounded-2xl border` markup (not `<x-stat-card>`).
- **Add/Edit pages (all 6)**: ~**90%** — `card p-4 md:p-6`, `input-base`, `btn-primary`/`btn-secondary` header buttons, section-header token, inline `@error` with `text-danger`. Remaining legacy: `<x-primary-button>`/`<x-secondary-button>` Blade components exist but the pages use the `btn-primary`/`btn-secondary` class forms directly (same as Add Item / Add Service do).

---

## 6. VALIDATION STATE (add/edit forms)

| Module | Client-side JS validation | Server-side rules | Duplicate handling | Format validation |
|---|---|---|---|---|
| Category | ❌ none | ✅ `required|string|max:255` name; `nullable|string|max:255` code; `nullable|string` desc; edit adds `status in:0,1` | ✅ per-store `Rule::unique` (name + code) + try/catch race translation + inline error | ❌ none on name/code (codes are free-text) |
| Brand | ❌ none | ✅ same shape with `brand_*` | ✅ per-store unique + race translation | ❌ none |
| Variant | ❌ none | ✅ same shape with `variant_*` | ✅ per-store unique + race translation | ❌ none |

Gap vs. Add Item: Add Item has full client-side Alpine validation (`validateForm()`, `clientErrors`); these six forms have none — errors are server-side only, displayed via the top alert + inline `@error`. This is consistent with Add Service's current level (service also relies on server-side + a lighter client alert) but is a known gap if parity with Add Item is the target.

---

## 7. TEST COVERAGE (current status)

Suites touching the 3 modules — run just now:

| Suite | Tests | Status |
|---|---|---|
| `tests/Feature/CategoryBrandVariantRolloutTest.php` | 38 | ✅ PASS |
| `tests/Feature/CategoryBrandVariantRaceTest.php` | 3 | ✅ PASS |
| `tests/Feature/CategoryBrandVariantAddEditRedesignTest.php` | 10 | ✅ PASS |
| `tests/Feature/NavigationShortcutTest.php` (brand seeding collision regression) | 5 | ✅ PASS |
| **Scoped run total** | **56 passed, 606 assertions** | ✅ |

Earlier consolidated run including downstream Item/Service suites: **106 passed, 802 assertions, 0 failed** (RolloutTest, RaceTest, AddEditRedesignTest, NavigationShortcut, ServiceControllerFeature, ServiceDeleteStoreScope, ServiceStoreScope, ServiceStockGuardPaths, ItemDeleteHistoryGuard, ItemDeleteStoreScope, ItemsListRedesign).

Known full-suite caveat (pre-existing, unrelated to these modules): the proc_open+barrier timing-sensitive parallel tests (`MoneyTransferFixesTest`, `SupplierRedesignAndRisksTest`, `SystemAccountRaceConditionTest`, `PosSerialCheckoutValidationTest`, `SerialUniquenessAcrossEntryPointsTest`) and `ManualLiveVerificationTest` check2 (DbStore query count) fail under full-suite load but pass in isolation.

---

## 8. ANYTHING ELSE FLAGGED (do not fix here)

1. **Items/POS dropdowns unscoped** — `ItemController.php:122-123,130-131,456-457` and `PosController.php:57` load `DbCategory`/`DbBrand` with `where('status',1)` but **no store scoping**. A Store-B user's Items/POS add screens can see Store-A categories/brands. Pre-existing, outside the three module controllers; flagged in 18b and still open.
2. **`db_items.variant_id` dead column** — no FK, never written; only the sale-detail display branch reads it. Wiring Variants into the Item form is an open product decision (18b out-of-scope list).
3. **Deferred attribute-value model** — Variants is flat only; the Color→{Red,Blue,Green} model is explicitly deferred (would need new schema + touches protected Box-variant region).
4. **`db_variants` unused downstream** — new Variants are "usable" per status flag but appear nowhere in Item/POS flows.
5. **Client-side validation gap** — six add/edit forms have no JS validation, unlike Add Item (server-side + inline `@error` only). Low risk (server still guards), but a parity item if Add Item's UX is the bar.
6. **Uncommitted work** — all of 18a/18b/18c sits uncommitted on `774e544`; there is no rollback point beyond the baseline import. Committing the module work as discrete commits is recommended before the next pass.
7. **BrandSeeder collision fix** is required for the DB-level per-store brand_code unique index to be insertable — the index migration (`2026_09_08_000001`) will fail on existing data if any store already has two brands with the same first-3-letter-derived code.
8. **No toggle-status in sidebar shortcuts / permission catalog drift** — toggle uses `_edit` slugs; Manager has `brand_edit`/`items_category_edit` but **no `variant_edit`**, so a Manager cannot toggle Variant status (no variant slug granted at all). Confirm intended.
