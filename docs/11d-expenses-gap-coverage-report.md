# Expenses Rollout — Gap 1 & Gap 2 Coverage Report

> **Scope:** Test-only pass closing two coverage gaps flagged in the completion report.
> **No production code was changed** in this pass. Every initial failure was a
> test-side issue (test-helper role-id collision, wrong assertion target, wrong
> JSON field, or wrong seed value) — **no guard or `delete_bit=0` filter was
> actually missing**. The assertions below are the exact ones used; all pass.
>
> **Suite results:** `ExpensesGuardsCoverageTest` (8 tests) +
> `ExpensesConsumerDeleteBitTest` (5 tests) + existing `ExpensesRolloutTest` (22) +
> `ExpenseAccountingLedgerTest` (4) = **39 tests / 226 assertions, all pass**.
> New files: [`tests/Feature/ExpensesGuardsCoverageTest.php`](tests/Feature/ExpensesGuardsCoverageTest.php:1),
> [`tests/Feature/ExpensesConsumerDeleteBitTest.php`](tests/Feature/ExpensesConsumerDeleteBitTest.php:1).

---

## GAP 1 — Permission + store-scoping on routes OTHER than destroy()

**Verdict: all guards already existed and were correct. No controller code was
changed.** The report's original claim was accurate; the only reason the first
test run failed was a test-helper bug (a Limited role could receive `id=1`, which
`User::isSuperAdmin()` treats as Super Admin — fixed by always seeding role 1
first) and two assertion-shape mistakes. Per item:

### Item 1 — `store()` requires `expense_add`
Test: `test_gap1_item1_no_expense_add_gets_403_on_store_and_no_row`
- User with `expense_view` only POSTs `expenses.store` → `assertForbidden()`, then
  `DbExpense::where('expense_for','ShouldNotExist')->count() === 0` (row NOT created).
- **Passed on first run** (the gate at [`ExpenseController.php:258`](app/Http/Controllers/ExpenseController.php:258) fired).
- Control: user with `expense_add`+`expense_view` → `assertRedirect(route('expenses.list'))`, row count 1.

### Item 2 — `edit()`/`update()` require `expense_edit`
Test: `test_gap1_item2_no_expense_edit_gets_403_on_edit_and_update_row_unchanged`
- View-only+add user: GET `expenses.edit/{id}` → `assertForbidden()`; POST `expenses.update/{id}` → `assertForbidden()`; `expense_amt` still `30.0`, `delete_bit` still 0.
- **Passed on first run** (gates at [`ExpenseController.php:334`](app/Http/Controllers/ExpenseController.php:334) and [:364](app/Http/Controllers/ExpenseController.php:364)).
- Control: user with `expense_edit` → `assertRedirect`, amount becomes `45.0`.

### Item 3 — `index()`/`create()` require `expense_view`/`expense_add`
Test: `test_gap1_item3_no_expense_view_gets_403_on_index_and_create`
- User with NO expense perms → GET `expenses.list` `assertForbidden()`, GET `expenses.add` `assertForbidden()`.
- User with `expense_view` only → `expenses.list` OK (200), `expenses.add` still `assertForbidden()` (create gates on `expense_add`).
- **Required a test fix on first run** — the original test asserted 403 on `create()` for a user who HAD `expense_add`. That was a wrong expectation (the controller correctly allows add-permissioned users), not a missing gate. The `index()` 403 assertion passed first run.
- Control: `expense_view`+`expense_add` → both `assertOk()`.

### Item 4 — Store-B cannot edit a Store-A expense
Test: `test_gap1_item4_store_b_cannot_edit_store_a_expense`
- Store-B super admin: GET `expenses.edit/{storeA_id}` → `assertNotFound()`; POST `expenses.update` → `assertNotFound()`; Store-A row `expense_amt` still `50.0`, `delete_bit` 0.
- **Passed on first run** (store-scoped `findOrFail` at [`ExpenseController.php:370`](app/Http/Controllers/ExpenseController.php:370)).
- Control: Store-A user → `assertRedirect`, amount becomes `60.0`.

### Item 5 — `ExpenseCategoryController` — all six methods
- `test_gap1_item5a_category_index_and_create_require_permission_and_are_store_scoped`: no `expense_category_view` → GET `expenses.categories` + `expenses.categories.add` `assertForbidden()`. Store-B list `assertOk()` but `assertDontSee('CatA-Only')`. Control: Store-A with `expense_category_view` `assertSee('CatA-Only')`. **Passed first run.**
- `test_gap1_item5b_category_store_requires_permission_and_is_store_scoped`: no `expense_category_add` → POST `categories.store` `assertForbidden()` + `count('ShouldNotCreate') === 0`. Control: with `expense_category_add` → `assertRedirect`, count 1. **Passed first run.**
- `test_gap1_item5c_category_edit_update_require_permission_and_store_scope`: no `expense_category_edit` → GET edit + POST update `assertForbidden()`, name unchanged (`'CatA-EditTarget'`). Store-B: GET edit `assertNotFound()`; POST update `assertRedirect()->assertSessionHas('error')` (store-scoped `findOrFail` inside the controller's try/catch redirects with an error rather than 404 — category untouched). **Required a test fix:** the original asserted 404 on the POST; the 404 only applies to the GET edit path (outside try/catch). The block is real either way — name unchanged after both attempts. Control: with `expense_category_edit` → `assertRedirect`, name becomes `'CatA-Edited'`.
- `test_gap1_item5d_category_destroy_requires_permission_and_store_scope`: no `expense_category_delete` → DELETE `categories.delete` `assertForbidden()`, row exists. Store-B → `assertRedirect()->assertSessionHas('error')`, Store-A category still exists. **Required the same test fix** (POST/delete redirect-with-error vs 404). Control: with `expense_category_delete` → `assertSessionHas('success')`, row gone.

### Item 6 — Control cases
Every item above includes a permissioned same-store control that **succeeds**
(asserted inline), so the guard assertions cannot pass because "everything is
broken."

**Gap 1 conclusion:** all permission gates and store scoping were already in
place on every route. No production change was required.

---

## GAP 2 — `delete_bit=0` filter on the 5 non-reconciliation consumers

**Verdict: the filter already existed and was correct on all 5 consumers. No
query was missing or broken.** The pattern is identical on each: create a live
expense via the real `store()` route, assert it IS counted, soft-delete via the
real `destroy()`, assert it is now EXCLUDED with an exact value. Per consumer:

### Consumer 1 — P&L profit-loss report
Test: `test_gap2_consumer1_profit_loss_excludes_soft_deleted_expense`
- Live: `res->json('data.expenses.total') === 100.0`.
- After soft-delete: `=== 0.0` (drops by exactly $100).
- **Passed first run** — filter already present at [`ReportController.php:290`](app/Http/Controllers/ReportController.php:290).

### Consumer 2 — Expense report
Test: `test_gap2_consumer2_expense_report_excludes_soft_deleted_expense`
- Live: `count(records) === 1`, `records.0.amount === '75.00'`.
- After soft-delete: `count(records) === 0`.
- **Passed first run** — filter at [`ReportController.php:1377`](app/Http/Controllers/ReportController.php:1377).

### Consumer 3 — Cash-flow report (by_expense_category breakdown)
Test: `test_gap2_consumer3_cash_flow_excludes_soft_deleted_expense`
- Live: `operating_activities.by_expense_category[0].amount === 200.0` (non-empty).
- After soft-delete: `by_expense_category === []`.
- **Required a test fix:** the first version asserted `operating_activities.expenses`, which is the wrong field for this consumer — that total derives from `ac_transactions` ([`ReportController.php:2384`](app/Http/Controllers/ReportController.php:2384)) and correctly retains the original EXPENSE row under non-destructive soft-delete. The actual `db_expense` consumer is `by_expense_category` ([`ReportController.php:2393`](app/Http/Controllers/ReportController.php:2393)), where the `delete_bit=0` filter was present and correct from the start.
- Filter confirmed at [`ReportController.php:2393`](app/Http/Controllers/ReportController.php:2393).

### Consumer 4 — Dashboard today net profit
Test: `test_gap2_consumer4_dashboard_net_profit_excludes_soft_deleted_expense`
- Seeds a zero-discount sale with `total_cost=500` (revenue) so net profit = revenue − expenses.
- Live: dashboard renders `400.00` (500 − 100 expense).
- After soft-delete: renders `500.00`, `assertDontSee('400.00')`.
- **Required a test fix:** the first version seeded `total_cost=0`, but the dashboard's revenue reads `SUM(db_salesitems.total_cost)` ([`DashboardController.php:59`](app/Http/Controllers/DashboardController.php:59)), so revenue was 0. Correcting the seed to 500 made the exact-value assertion valid. The `delete_bit=0` filter at [`DashboardController.php:77`](app/Http/Controllers/DashboardController.php:77) was present and correct.

### Consumer 5 — Global search
Test: `test_gap2_consumer5_global_search_excludes_soft_deleted_expense`
- Live: `categories.expenses.label === 'Expenses'`, `categories.expenses.items` non-empty.
- After soft-delete: `assertArrayNotHasKey('expenses', categories)`.
- **Passed first run** — filter at [`GlobalSearchController.php:518`](app/Http/Controllers/GlobalSearchController.php:518).

**Gap 2 conclusion:** all five `delete_bit=0` filters already existed and behave
correctly. No production query was missing or broken.

---

## Summary of changes in this pass

| File | Change |
|---|---|
| [`tests/Feature/ExpensesGuardsCoverageTest.php`](tests/Feature/ExpensesGuardsCoverageTest.php:1) | **New** — 8 tests covering Gap 1 items 1–6 (permission + store scope on all non-destroy Expense routes and all six category routes, with control cases). |
| [`tests/Feature/ExpensesConsumerDeleteBitTest.php`](tests/Feature/ExpensesConsumerDeleteBitTest.php:1) | **New** — 5 tests covering Gap 2 consumers 1–5 with exact before/after values. |
| Production code | **None changed.** |

## Honest per-item status

| Item | First-run result | Fix needed? |
|---|---|---|
| GAP 1.1 store() gate | ✅ passed | no |
| GAP 1.2 edit()/update() gate | ✅ passed | no |
| GAP 1.3 index()/create() gate | ⚠️ test expectation fixed (create gates on `expense_add`, not `expense_view`); index 403 passed | test-only |
| GAP 1.4 store-B edit block | ✅ passed | no |
| GAP 1.5a category index/create | ✅ passed | no |
| GAP 1.5b category store | ✅ passed | no |
| GAP 1.5c category edit/update | ⚠️ test expectation fixed (POST update 404 → redirect+error) | test-only |
| GAP 1.5d category destroy | ⚠️ test expectation fixed (DELETE 404 → redirect+error) | test-only |
| GAP 2.1 P&L | ✅ passed | no |
| GAP 2.2 Expense report | ✅ passed | no |
| GAP 2.3 Cash-flow breakdown | ⚠️ test target fixed (asserted wrong JSON field) | test-only |
| GAP 2.4 Dashboard net profit | ⚠️ test seed fixed (`total_cost` must equal revenue) | test-only |
| GAP 2.5 Global search | ✅ passed | no |

**Bottom line:** the completion report's claims were correct. No behavior change
was needed; the gaps were purely missing test coverage, now closed.