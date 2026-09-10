# Expense Category Views — Visual/UX Redesign Report

> **Scope:** Presentation-only redesign of the three Expense Category blade views
> served by `ExpenseCategoryController`. **No functional, permission, guard, or
> validation behavior changed.** All protected regions left intact.

---

## 1. Files located (exact)

The controller serves exactly three separate page views — **there is no
modal-based category add/edit anywhere** (confirmed: `ExpenseCategoryController`
returns `view('module.expenses.categories_list' | 'add_category' | 'edit_category')`,
and no category modal exists in any blade; the app's `x-modal` component is used
only for delete-confirmations and serial pickers):

| Route | Method | View file |
|---|---|---|
| `expenses.categories` | `index()` | [`resources/views/module/expenses/categories_list.blade.php`](resources/views/module/expenses/categories_list.blade.php:1) |
| `expenses.categories.add` | `create()` | [`resources/views/module/expenses/add_category.blade.php`](resources/views/module/expenses/add_category.blade.php:1) |
| `expenses.categories.edit` | `edit()` | [`resources/views/module/expenses/edit_category.blade.php`](resources/views/module/expenses/edit_category.blade.php:1) |

---

## 2. Layout choice made, and why

**Choice: compact list-density change + keep separate create/edit pages.**

I selected the "more compact list layout" option and **did not** convert
create/edit to inline modals, for three reasons:

1. **Route/test semantics.** The Gap-1 coverage tests exercise the
   `expenses.categories.add` and `expenses.categories.edit` **GET routes**
   directly (`$this->actingAs(...)->get(route('expenses.categories.add'))`,
   `...->get(route('expenses.categories.edit', $id))`). Converting to modals would
   remove/replace those pages and risk changing route semantics — explicitly
   out of scope ("no functional behavior may change").
2. **Module-consistent baseline.** The module's own Phase-6 baseline
   (`create_expense` / `edit_expense`) uses **separate pages** with `x-card`
   forms. Mirroring the module's established pattern was the explicit goal.
3. **The app's modal-CRUD pattern is the legacy style, not the baseline.** The one
   simple-entity modal CRUD in the codebase ([`settings/tax_list.blade.php`](resources/views/module/settings/tax_list.blade.php:138))
   is the *old* hand-rolled `bg-white rounded-2xl` modal pattern — not the Phase-6
   design-system baseline. Adopting it would have made Categories *less*
   consistent with the module it belongs to.

**Compact list specifics (the "compact and user-friendly" ask):**
- Row height reduced: cells changed from `px-6 py-2.5` → `px-4 py-1.5`; header
  from `px-6 py-3` → `px-4 py-2.5`.
- One redundant column removed: the **bulk-select checkbox column** (it was
  non-functional decoration — no bulk action exists for categories, matching the
  Phase-6 decision to remove dead bulk-select UI). Column count 6 → 5.
- Decorative-dead controls removed from the toolbar: the static "Show 10" select
  (no `per_page` wire-up and out of scope) and the dead Copy/Excel/PDF buttons
  (no handlers here; export was not in scope for categories). The toolbar now
  shows a category count + the working search form only.
- Table wrapper uses the shared `card` component class instead of the one-off
  `bg-white dark:bg-dark-card rounded-3xl border border-slate-100 …` markup.
- Pagination uses the design-system `{{ $categories->links() }}` instead of the
  hand-rolled Prev/Next loop.

---

## 3. Shared design-system classes/components now used

Confirmed identical to `expenses_list` / `create_expense` / `edit_expense`:

| Token / component | Where used in the redesigned category views |
|---|---|
| `card` (component class → `bg-card dark:bg-dark-card border-border rounded-card shadow-card`) | list table wrapper |
| `btn-primary` | "New Category" button (list), Save/Update submit buttons |
| `btn-secondary` | "Close"/"Back to List" buttons |
| `input-base` | every text input, textarea and select in add/edit forms, and the search box |
| `text-text-primary` / `text-text-secondary` / `text-text-muted` tokens | all headings, labels, cell text (replaces `slate-800/600/400`) |
| `bg-card dark:bg-dark-card` (floating-label backing) | every floating label chip |
| `bg-success/10` `text-success` / `bg-danger/10` `text-danger` tokens | active/inactive status pills, header icon chips |
| `<x-card>` component | add/edit form shells |
| `<x-dropdown>` + `<x-dropdown-link>` | row Action menu (Edit/Delete) — replaces the hand-rolled Alpine dropdown |
| `{{ $categories->links() }}` | pagination |
| `border-border` / `border-border-light` / `divide-border-light` tokens | table dividers |
| Submit-disable pattern `x-data="{ isSubmitting: false }"` + `:disabled="isSubmitting"` | add/edit forms (same as `create_expense`/`edit_expense`) |

---

## 4. Protected regions — explicitly preserved

- **Phase-4 in-use delete guard:** the delete form still posts to
  `route('expenses.categories.delete', $id)` with `@method('DELETE')`; the
  controller's `DbExpense::…where('category_id', $category->id)->exists()` check
  and its error message are **untouched** (no controller change at all).
- **Gap-1 permission gates:** no controller change; the views still call the same
  named routes, so `expense_category_view/add/edit/delete` gating is unaffected.
- **Store scoping + cross-store redirect+error-flash:** untouched (controller
  unchanged); the edit/delete links target the same routes.
- **Name/uniqueness validation:** form field names (`category_name`,
  `category_code`, `description`, `status`) and `@csrf` are identical, so the
  existing `Rule::unique(...)` validation is unaffected.

**No new fields, bulk actions, or filters were added.**

---

## 5. Verification

### 5.1 Gap-1 category tests still pass **unmodified**
Command: `php artisan test --filter="ExpensesGuardsCoverageTest|ExpensesRolloutTest"`
Result: **30 passed (148 assertions)** — including:
- `test_gap1_item5a_category_index_and_create_require_permission_and_are_store_scoped` ✓
- `test_gap1_item5b_category_store_requires_permission_and_is_store_scoped` ✓
- `test_gap1_item5c_category_edit_update_require_permission_and_store_scope` ✓
- `test_gap1_item5d_category_destroy_requires_permission_and_store_scope` ✓

### 5.2 Phase-4 in-use delete-block test still passes **unmodified**
- `test_phase4_category_delete_blocked_when_expenses_reference_it` ✓
- `test_phase4_unused_category_still_deletes` ✓

### 5.3 Render/visual smoke
- `PageTitleTest` (28 passed) confirms the expense pages still render without
  Blade errors.
- The category views now share the exact `card` / `btn-primary` / `input-base` /
  `x-card` / `x-dropdown` / `{{ $categories->links() }}` classes and components as
  `expenses_list` / `create_expense` / `edit_expense` (cited in §3).

---

## 6. Files changed

| File | Change |
|---|---|
| [`resources/views/module/expenses/categories_list.blade.php`](resources/views/module/expenses/categories_list.blade.php:1) | Compact redesign: `card` wrapper, tighter rows (`px-4 py-1.5`), bulk-select column removed (6→5 cols), dead toolbar controls removed, `x-dropdown` action menu, `{{ $categories->links() }}`, design tokens. |
| [`resources/views/module/expenses/add_category.blade.php`](resources/views/module/expenses/add_category.blade.php:1) | Rebuilt on `create_expense` baseline: `<x-card>`, floating labels, `input-base`, `btn-primary`/`btn-secondary`, submit-disable; same field names. |
| [`resources/views/module/expenses/edit_category.blade.php`](resources/views/module/expenses/edit_category.blade.php:1) | Rebuilt on `edit_expense` baseline: `<x-card>`, floating labels, `input-base`, `btn-primary`/`btn-secondary`, submit-disable; same field names + pre-fill. |
| Controllers / models / routes | **None changed.** |

Both verification suites pass with the test files untouched.
