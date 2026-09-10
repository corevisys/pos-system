# Expenses Module — Functional & High-Risk Audit (Pre-Redesign)

> **Status:** Documentation/discovery + functional audit pass. **No code was changed.**
> **Scope parity:** Accounts cluster / Stock modules rigor. Expenses is financial data and directly
> feeds `CashReconciliationController::getCalculationBreakdown()` (the `cash_expenses_amount` line is
> summed **directly from `db_expense`**, NOT from `ac_transactions`).
>
> Everything below marked **CONFIRMED BY CODE** was verified by reading the cited file/line.
> Anything that needs a live runtime check is explicitly labelled **NEEDS RUNTIME TEST**.

---

## 1. Scope Framing

### 1.1 Purpose / scope of Expenses

- **Purpose (confirmed):** One-off business operating expenses (utilities, payroll, maintenance, supplies)
  tracked separately from inventory purchasing so gross margin stays clean.
  - Docs: [`docs/11-expenses-and-messaging.md`](docs/11-expenses-and-messaging.md:5)
  - View copy: "Record business expenditures" — [`create_expense.blade.php`](resources/views/module/expenses/create_expense.blade.php:33)
- **Recurring?** No recurring/schedule model. Each row is a standalone dated one-off entry
  (`db_expense.expense_date`).
- **Category:** Yes — optional-but-recommended `category_id` → `db_expense_category`.
  Required by validation on create ([`ExpenseController.php`](app/Http/Controllers/ExpenseController.php:56)).
- **Vendor:** No vendor entity. There is only a free-text `expense_for` + `reference_no`.
- **Account linkage:** Optional `account_id` → `ac_accounts`. When present, the expense creates an
  `ac_transactions` `EXPENSE` debit and decrements that account's `balance` (see §2.4).

### 1.2 Routes / controllers / views (confirmed)

| Route name | Method | URI | Controller@method | View |
|---|---|---|---|---|
| `expenses.list` | GET | `expenses/list` | [`ExpenseController@index`](app/Http/Controllers/ExpenseController.php:17) | [`module/expenses/expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php:1) |
| `expenses.add` | GET | `expenses/add` | [`ExpenseController@create`](app/Http/Controllers/ExpenseController.php:38) | [`module/expenses/create_expense.blade.php`](resources/views/module/expenses/create_expense.blade.php:1) |
| `expenses.store` | POST | `expenses/store` | [`ExpenseController@store`](app/Http/Controllers/ExpenseController.php:52) | — (redirect) |
| `expenses.delete` | DELETE | `expenses/delete/{id}` | [`ExpenseController@destroy`](app/Http/Controllers/ExpenseController.php:123) | — (redirect) |
| `expenses.categories` | GET | `expenses/categories` | [`ExpenseCategoryController@index`](app/Http/Controllers/ExpenseCategoryController.php:14) | [`module/expenses/categories_list.blade.php`](resources/views/module/expenses/categories_list.blade.php:1) |
| `expenses.categories.add` | GET | `expenses/categories/add` | [`ExpenseCategoryController@create`](app/Http/Controllers/ExpenseCategoryController.php:32) | [`module/expenses/add_category.blade.php`](resources/views/module/expenses/add_category.blade.php:1) |
| `expenses.categories.store` | POST | `expenses/categories/store` | [`ExpenseCategoryController@store`](app/Http/Controllers/ExpenseCategoryController.php:40) | — (redirect) |
| `expenses.categories.edit` | GET | `expenses/categories/edit/{id}` | [`ExpenseCategoryController@edit`](app/Http/Controllers/ExpenseCategoryController.php:72) | [`module/expenses/edit_category.blade.php`](resources/views/module/expenses/edit_category.blade.php:1) |
| `expenses.categories.update` | POST | `expenses/categories/update/{id}` | [`ExpenseCategoryController@update`](app/Http/Controllers/ExpenseCategoryController.php:81) | — (redirect) |
| `expenses.categories.delete` | DELETE | `expenses/categories/delete/{id}` | [`ExpenseCategoryController@destroy`](app/Http/Controllers/ExpenseCategoryController.php:112) | — (redirect) |

Route definitions: [`routes/web.php`](routes/web.php:307) (group at lines 307–319, inside the
`auth`+`verified` group at line 44).

**CRITICAL STRUCTURAL FINDING:** `ExpenseController` has **NO `edit()` / `update()` methods and NO
`edit_expense` view**. There is **no route** for editing an expense. The list page even has the Edit
link **commented out** ([`expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php:104)).
=> **Expense editing does not exist at all** — see §5.3 for the reconciliation implication.

### 1.3 Downstream consumer — the query that must never break

**CONFIRMED BY CODE — [`CashReconciliationController::getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1041):**

```php
// 4. Cash Expenses
$cashExpenses = (float) DbExpense::where('store_id', $storeId)      // line 1096
    ->where('payment_type', 'Cash')                                 // line 1097
    ->where('account_id', $accountId)                               // line 1098
    ->whereDate('expense_date', $date)                              // line 1099
    ->sum('expense_amt');                                           // line 1100
```

- Reads columns: `store_id`, `payment_type`, `account_id`, `expense_date`, `expense_amt`.
- **Only `payment_type = 'Cash'` rows are counted** — Bank/Cheque expenses are excluded from the drawer.
- Result feeds `cash_expenses_amount` ([line 1167](app/Http/Controllers/CashReconciliationController.php:1167))
  and the expected-closing formula ([lines 1124–1127](app/Http/Controllers/CashReconciliationController.php:1124)).
- This method is called from: `openDrawer` ([206](app/Http/Controllers/CashReconciliationController.php:206)),
  `store` ([334](app/Http/Controllers/CashReconciliationController.php:334)), `closeForm` ([481](app/Http/Controllers/CashReconciliationController.php:481)),
  `closeDrawer` ([537](app/Http/Controllers/CashReconciliationController.php:537)), `calculateExpected` ([758](app/Http/Controllers/CashReconciliationController.php:758)),
  `show` ([790](app/Http/Controllers/CashReconciliationController.php:790)).
- Existing tests lock this behaviour: [`CashDrawerReconciliationTest.php`](tests/Feature/CashDrawerReconciliationTest.php:124),
  [`CashReconciliationFixesTest.php`](tests/Feature/CashReconciliationFixesTest.php:219) (store-scope surface),
  [`CashReconciliationUiFlowTest.php`](tests/Feature/CashReconciliationUiFlowTest.php:115).

---

## 2. Locate — Schema & Code

### 2.1 `db_expense` schema — [`2026_02_07_090710_create_db_expense_table.php`](database/migrations/2026_02_07_090710_create_db_expense_table.php:14)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint PK | |
| `store_id` | unsignedBigInteger **nullable** | indexed — **no `delete_bit` column** |
| `count_id` | integer nullable | |
| `expense_code` | string nullable | indexed |
| `category_id` | unsignedBigInteger nullable | indexed — **no FK constraint** on `db_expense_category` |
| `expense_date` | date nullable | indexed — used by Cash Reconciliation |
| `reference_no` | string nullable | |
| `expense_for` | string nullable | |
| `expense_amt` | decimal(16,2) default 0 | |
| `payment_type` | string nullable | 'Cash' vs anything else — **critical for Cash Recon** |
| `account_id` | unsignedBigInteger nullable | indexed; FK → `ac_accounts` `onDelete('set null')` |
| `note` | text nullable | |
| `created_by` / `created_date` / `created_time` / `system_ip` / `system_name` | audit trail | |
| `status` | integer default 1 | |
| `created_at` / `updated_at` | timestamps | |

**Findings:**
- **No `delete_bit` / soft-delete column** on `db_expense`. `destroy()` is a **hard delete** (`$expense->delete()`).
- **No FK from `category_id` to `db_expense_category`** (only an index). Deleting a category will NOT be
  blocked by the DB and will silently orphan expenses (see §5.5).

### 2.2 `db_expense_category` schema — [`2026_02_07_090712_create_db_expense_category_table.php`](database/migrations/2026_02_07_090712_create_db_expense_category_table.php:14)

`id`, `store_id` (nullable, indexed), `category_code`, `category_name`, `description`, `created_by`,
`status` (default 1), timestamps. **No `delete_bit`.** Also no FK back-references.

### 2.3 Models

- [`DbExpense`](app/Models/DbExpense.php:8) — `belongsTo(DbExpenseCategory, 'category_id')`,
  `belongsTo(AcAccount, 'account_id')`. **No global scopes** (verified: no `addGlobalScope` anywhere in `app/`).
- [`DbExpenseCategory`](app/Models/DbExpenseCategory.php:8) — no relations at all.
- [`AcTransaction`](app/Models/AcTransaction.php:8) — `ref_expense_id` is fillable (line 33).

### 2.4 Does Expense create/edit/delete write to `ac_transactions`? — **CONFIRMED: YES (partially)**

- **`store()`** — [`ExpenseController.php:88-109`](app/Http/Controllers/ExpenseController.php:88):
  - Creates an `AcTransaction` with `transaction_type = 'EXPENSE'`, `debit_account_id = account_id`,
    `debit_amt = expense_amt`, `transaction_date = expense_date`, `ref_expense_id = expense->id`
    (lines 90–103).
  - Decrements `ac_accounts.balance` (line 107).
  - **BUT only when `account_id` is set AND `expense_amt > 0`** (line 89). Expenses with no account
    are standalone `db_expense` rows only.
  - Confirmed by tests: [`ExpenseAccountingLedgerTest.php`](tests/Feature/ExpenseAccountingLedgerTest.php:38) and (no-account case) [:87](tests/Feature/ExpenseAccountingLedgerTest.php:87).
- **`destroy()`** — [`ExpenseController.php:123-151`](app/Http/Controllers/ExpenseController.php:123):
  - Reverts account balance (increments back) for each matching `AcTransaction` (lines 131–139).
  - Hard-deletes the `AcTransaction` rows by `ref_expense_id` (line 140).
  - Hard-deletes the expense (line 142).
- **`edit`/`update`:** don't exist — nothing to write.

---

## 3. Full UI Inventory

### 3.1 Expenses List — [`module/expenses/expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php)

| Element | Location | Notes |
|---|---|---|
| Breadcrumb "Home" link | line 8 | → `route('dashboard')` |
| **"New Expense" button** | line 17 | → `route('expenses.add')` — **NOT permission-gated** in blade |
| "Show" per-page select | lines 33–35 | static `<option>10</option>` — no `per_page` param, no JS handler |
| **Copy** button | line 40 | no handler |
| **Excel** button | line 41 | no handler |
| **PDF** button | line 42 | no handler |
| **Search** box | lines 44–47 | GET form → `expenses.list`; server-side `search` filter |
| **Header checkbox (bulk-select)** | line 57 | no JS handler |
| **Row checkbox** | line 73 | no JS handler |
| Columns: Date / Category / Reference No. / Expense for / Amount / Account / Note / Action | lines 59–66 | display only |
| Category badge | lines 79–81 | `$expense->category->category_name` / 'N/A' |
| Amount | line 90 | `format_currency()` |
| Account | lines 92–93 | `$expense->account->account_name` / '---' |
| Note (truncated) | line 95 | `Str::limit($expense->note, 20)` |
| **Row "Action" dropdown** | lines 97–112 | Alpine `x-data` dropdown |
| └ **Edit link** | line 104 | **COMMENTED OUT** (`{{-- ... --}}`) — no edit route exists |
| └ **Delete form** | lines 105–109 | `DELETE expenses.delete/{id}` with JS `confirm()` |
| **tfoot "Total Expenses"** | lines 121–131 | `$expenses->sum('expense_amt')` — **sums only the CURRENT page's rows** |
| **Pagination** (Prev / page links / Next) | lines 136–161 | Laravel paginator links |

### 3.2 Add Expense form — [`module/expenses/create_expense.blade.php`](resources/views/module/expenses/create_expense.blade.php)

| Field | Location | Notes |
|---|---|---|
| Expense Date (required) | lines 43–51 | `type="date"`, default today |
| Category (required) | lines 53–59 | `<x-searchable-select>` from `$categories` (status=1) |
| Expense For (required) | lines 61–67 | free text |
| Reference No. (optional) | lines 69–75 | free text |
| Amount (required) | lines 77–84 | `type="number" step="0.01"` |
| **Account (optional)** | lines 86–96 | `<x-searchable-select>` from `$accounts` (status=1); hidden empty input if no accounts |
| Note (optional) | lines 98–104 | textarea |
| **Save Expense** button | lines 108–112 | submit → `expenses.store` |
| **Close** button | lines 113–115 | → `expenses.list` |

> **Note:** The form does NOT render a `payment_type` field at all, even though
> [`store()`](app/Http/Controllers/ExpenseController.php:77) defaults it to `'Cash'` and the Cash
> Reconciliation query depends on `payment_type = 'Cash'`. There is **no UI to record a Bank/Cheque
> expense** (confirmed by full read of the view). Also `payment_type` is **not validated**.

### 3.3 Category screens (inventory for completeness)

- [`categories_list.blade.php`](resources/views/module/expenses/categories_list.blade.php) — same
  dead `Copy/Excel/PDF` buttons (43–45), dead per-page select (36–38), dead bulk checkboxes (60, 73),
  working search (47–50), working Edit link (97), working Delete form with confirm (98–102).
- [`add_category.blade.php`](resources/views/module/expenses/add_category.blade.php) — Category Name*,
  Category Code, Description, Status select, Save/Close.
- [`edit_category.blade.php`](resources/views/module/expenses/edit_category.blade.php) — same fields pre-filled.

---

## 4. Functional Audit — Classification

Legend: ✅ WORKS · ⚠️ PARTIALLY WORKS · 🗑️ DECORATIVE-DEAD · ❌ MISSING

### 4.1 Expenses List

| Element | Class | Evidence / handler chain |
|---|---|---|
| New Expense button | ✅ WORKS | → `expenses.add` (route + controller exist) |
| Search | ✅ WORKS | GET → `index()` filters `expense_code`/`expense_for`/`reference_no` ([ExpenseController.php:21-28](app/Http/Controllers/ExpenseController.php:21)) |
| Pagination (Prev/Next/numbers) | ✅ WORKS | standard `paginate(10)` links |
| Row Delete | ✅ WORKS (unguarded — see §5) | form → `destroy()`; hard delete |
| Category badge / Account / Date / Amount / Note columns | ✅ WORKS | eager-loaded relations render |
| Total Expenses footer | ⚠️ PARTIALLY WORKS | **Only sums the current page's rows** (`$expenses->sum()` on a paginator returns the current-page collection), mislabeled as "Total Expenses" |
| "Show" per-page select | 🗑️ DECORATIVE-DEAD | static; no `per_page` param, no handler |
| Copy / Excel / PDF buttons | 🗑️ DECORATIVE-DEAD | no handler of any kind (same pattern already flagged in [`PROJECT_KNOWLEDGE_BASE.md`](PROJECT_KNOWLEDGE_BASE.md:429)) |
| Bulk-select header checkbox | 🗑️ DECORATIVE-DEAD | no JS; does not select rows, no bulk action exists |
| Row checkbox | 🗑️ DECORATIVE-DEAD | no JS; purely visual |
| Row Edit action | ❌ MISSING | commented-out link; no `edit` route/controller method exists |
| Category / date-range filters | ❌ MISSING | only free-text search exists; no category or date-range filter on the list |
| Export (working) | ❌ MISSING | Copy/Excel/PDF are dead decorations |
| Receipt / attachment upload | ❌ MISSING | no file upload field anywhere in create form or schema |
| Bulk delete | ❌ MISSING | no bulk action |
| View / Show detail | ❌ MISSING | no show/detail route or view |

### 4.2 Add Expense form

| Element | Class | Evidence |
|---|---|---|
| Expense Date field | ✅ WORKS | posted & validated `required|date` ([ExpenseController.php:55](app/Http/Controllers/ExpenseController.php:55)) |
| Category dropdown | ✅ WORKS | validated `exists:db_expense_category,id` ([ExpenseController.php:56](app/Http/Controllers/ExpenseController.php:56)) — **not store-scoped** (see §5.1) |
| Expense For | ✅ WORKS | validated `required|string` |
| Reference No. | ✅ WORKS | saved (nullable) |
| Amount | ✅ WORKS | validated `required|numeric|min:0` |
| Account dropdown | ✅ WORKS | validated `exists:ac_accounts,id` (nullable); drives `AcTransaction` creation |
| Note | ✅ WORKS | saved (nullable) |
| Save Expense | ✅ WORKS | POST → `store()`; writes `db_expense` + `ac_transactions` |
| **Payment Type selector** | ❌ MISSING | no field in the form; `store()` hard-defaults `'Cash'` ([ExpenseController.php:77](app/Http/Controllers/ExpenseController.php:77)); `payment_type` is **not validated** |
| **Double-submit guard** | ❌ MISSING | no `@submit` disable, no idempotency key, no client/server lock (see §5.4) |

### 4.3 Category screens

| Element | Class | Evidence |
|---|---|---|
| Category list + search | ✅ WORKS | [`ExpenseCategoryController@index`](app/Http/Controllers/ExpenseCategoryController.php:14) |
| Category add / edit / delete forms | ✅ WORKS | routes + controller methods + views all present |
| Copy / Excel / PDF | 🗑️ DECORATIVE-DEAD | no handlers |
| Bulk checkboxes | 🗑️ DECORATIVE-DEAD | no handlers, no bulk action |

---

## 5. High-Risk Flow Trace (Cash Reconciliation dependency)

### 5.1 Store scoping — **CONFIRMED GAP**

| Operation | Store-scoped? | Evidence |
|---|---|---|
| `index()` list | ❌ **NO** | [`ExpenseController.php:19`](app/Http/Controllers/ExpenseController.php:19) — `DbExpense::with(...)->orderBy('id','desc')` with **no** `where('store_id', ...)` |
| `create()` category dropdown | ❌ **NO** | [`ExpenseController.php:40`](app/Http/Controllers/ExpenseController.php:40) — `DbExpenseCategory::where('status', 1)` — **no store filter** |
| `create()` account dropdown | ❌ **NO** | [`ExpenseController.php:44`](app/Http/Controllers/ExpenseController.php:44) — `AcAccount::where('status', 1)` — **no store filter** |
| `store()` write | ✅ partially | sets `store_id = current_store_id()` ([ExpenseController.php:69](app/Http/Controllers/ExpenseController.php:69)) — write side is scoped |
| `destroy()` lookup | ❌ **NO** | [`ExpenseController.php:128`](app/Http/Controllers/ExpenseController.php:128) — `DbExpense::findOrFail($id)` — **no store_id where clause** → cross-store delete possible if you know the id |
| Category `index` | ❌ NO | [`ExpenseCategoryController.php:16`](app/Http/Controllers/ExpenseCategoryController.php:16) — no store filter |
| Category `edit`/`update`/`destroy` lookup | ❌ NO | [`ExpenseCategoryController.php:74`](app/Http/Controllers/ExpenseCategoryController.php:74), [:93](app/Http/Controllers/ExpenseCategoryController.php:93), [:115](app/Http/Controllers/ExpenseCategoryController.php:115) — all `findOrFail($id)` with no store scoping |

**Contrast:** the downstream consumer [`getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1096)
IS store-scoped (`where('store_id', $storeId)`) — so a cross-store delete would remove data the
reconciliation for the *current* store never counted, but could remove *another store's* history.

### 5.2 Delete flow vs. already-closed/adjusted reconciliation periods — **CONFIRMED: UNGUARDED**

- `destroy()` ([`ExpenseController.php:123-151`](app/Http/Controllers/ExpenseController.php:123)) has:
  - ❌ **No check** whether a `CashDrawerReconciliation` exists for `expense->expense_date` /
    `account_id` / `store_id` with status `Reconciled` or `Adjusted`.
  - ❌ **No permission gate** at all (see §5.5).
  - ✅ It does revert account balance + delete `AcTransaction`, but **it does NOT touch the already
    persisted `cash_expenses_amount` snapshot** on the closed reconciliation row.
- **Risk (exactly as flagged in the brief):** if a reconciliation for that date was closed, the
  `cash_expenses_amount` snapshot on `cash_drawer_reconciliation` was computed from the live `db_expense`
  sum at close time. Deleting the expense afterwards:
  1. Leaves the stored snapshot stale (the reconciliation row still shows the old amount), while
  2. Any **recalculation** of `getCalculationBreakdown()` for that date (e.g., the `show()` "live"
     refresh at [CashReconciliationController.php:790](app/Http/Controllers/CashReconciliationController.php:790) — although that path only runs for `status === 'Open'`) would now produce a **lower** number.
  3. There is **no audit record** of the deletion.
- **NEEDS RUNTIME TEST** to confirm the exact post-close UI surface (e.g., whether the closed record's
  show page ever re-runs `getCalculationBreakdown` — by code it only runs for `Open` records, so the
  stored snapshot is what users see; the silent corruption is therefore to *future recalculations*
  and to the **opening balance of the NEXT day's reconciliation**, which reads the previous
  `Reconciled`/`Adjusted` row's `counted_amount` at [CashReconciliationController.php:1046-1058](app/Http/Controllers/CashReconciliationController.php:1046)).

### 5.3 Edit flow — **CONFIRMED: DOES NOT EXIST**

- No `edit()`/`update()` methods on [`ExpenseController`](app/Http/Controllers/ExpenseController.php:12);
  no edit route in [`routes/web.php`](routes/web.php:307); no edit view; Edit link commented out at
  [`expenses_list.blade.php:104`](resources/views/module/expenses/expenses_list.blade.php:104).
- **Implication:** amount/date of an expense **cannot** be changed today. So the "edit after close"
  corruption vector is currently closed by absence — but any redesign that *adds* edit without a
  closed-period guard will open it. (The risk exists **today via delete**, and would exist via edit
  the moment edit is added.)

### 5.4 Double-submit / race protection — **CONFIRMED: ABSENT**

- `store()`: no idempotency key, no `lockForUpdate`, no unique constraint on `(store_id, expense_code,
  expense_date, expense_amt, expense_for)`, no client-side submit disable in the blade. A double click
  creates **two identical expense rows + two `AcTransaction` debits + double balance decrement**.
- `destroy()`: `findOrFail` then `delete()` — a concurrent double-delete would make the second call
  404 (graceful, but not a guarded atomic transition). No `delete_bit` means no optimistic-lock
  opportunity.
- **NEEDS RUNTIME TEST** to prove the double-submit symptom (server-side there is nothing preventing it).

### 5.5 Permission gates — **CONFIRMED: ABSENT at controller level**

- Slugs exist in seeders: `expense_add`, `expense_edit`, `expense_delete`, `expense_view`,
  `expense_category_add/_edit/_delete/_view` ([`PermissionSeeder.php:60-62`](database/seeders/PermissionSeeder.php:60),
  [`RolePermissionSeeder.php:50-52`](database/seeders/RolePermissionSeeder.php:50)).
- But **no controller method in `ExpenseController` or `ExpenseCategoryController` calls
  `hasPermission(...)`** — grep found the only `expense_view` reference in app code at
  [`GlobalSearchController.php:517`](app/Http/Controllers/GlobalSearchController.php:517) (search only)
  and the Blade nav gate at [`app.blade.php:594`](resources/views/layouts/app.blade.php:594).
- Routes are only inside the `auth`+`verified` group ([`routes/web.php:44`](routes/web.php:44)); no
  `permission:` middleware alias is registered in [`bootstrap/app.php`](bootstrap/app.php:13).
- **Result:** any authenticated user (even with zero expense permissions) can hit
  `expenses/list`, `expenses/add`, `expenses/store`, `expenses/delete/{id}`, and all category routes
  directly. This matches the codebase-wide finding in
  [`PROJECT_KNOWLEDGE_BASE.md:430`](PROJECT_KNOWLEDGE_BASE.md:430).
- **Contrast:** Cash Reconciliation has proper gates (`cash_reconciliation_add` at
  [CashReconciliationController.php:266](app/Http/Controllers/CashReconciliationController.php:266), etc.)
  — so a *less*-privileged user can delete expenses that feed a *more*-guarded reconciliation.

### 5.6 Category management — **CONFIRMED: ORPHANING POSSIBLE**

- `destroy()` ([`ExpenseCategoryController.php:112-122`](app/Http/Controllers/ExpenseCategoryController.php:112))
  hard-deletes the category with **no check** for `DbExpense::where('category_id', $id)->exists()`.
- Schema has **no FK** from `db_expense.category_id` → `db_expense_category` (migration line 38 only
  adds an index). Deleting a category therefore succeeds and leaves expenses with a dangling
  `category_id`; the list view shows 'N/A' ([`expenses_list.blade.php:80`](resources/views/module/expenses/expenses_list.blade.php:80)),
  and the expense report join at [`ReportController.php:2394`](app/Http/Controllers/ReportController.php:2394)
  will silently drop those rows from the category breakdown.
- `status` toggle is the intended soft-disable, but destroy is reachable and unguarded.

---

## 6. Non-Standard / Protected Regions

### 6.1 Query-shape contract that any redesign must preserve

The exact contract consumed by [`getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1096):

| Aspect | Current value | File:line |
|---|---|---|
| Table | `db_expense` | [1096](app/Http/Controllers/CashReconciliationController.php:1096) |
| Store filter | `where('store_id', $storeId)` | [1096](app/Http/Controllers/CashReconciliationController.php:1096) |
| Payment type | `where('payment_type', 'Cash')` — **string literal 'Cash'** | [1097](app/Http/Controllers/CashReconciliationController.php:1097) |
| Account filter | `where('account_id', $accountId)` | [1098](app/Http/Controllers/CashReconciliationController.php:1098) |
| Date filter | `whereDate('expense_date', $date)` — **exact single day** | [1099](app/Http/Controllers/CashReconciliationController.php:1099) |
| Aggregation | `sum('expense_amt')` | [1100](app/Http/Controllers/CashReconciliationController.php:1100) |

**Any future fix/redesign must not change these semantics without updating this consumer too:**
- If expenses gain a `delete_bit`/soft-delete, this query **must** add `where('delete_bit', 0)` or the
  reconciliation will count deleted expenses.
- If `payment_type` values change casing/keys (e.g., `cash` or `1`), this literal must be updated in
  sync. Note `store()` writes whatever the request sends or defaults to `'Cash'`
  ([ExpenseController.php:77](app/Http/Controllers/ExpenseController.php:77)) — a future UI payment-type
  selector must emit exactly `Cash` for the reconciliation to keep working.
- If `expense_date` semantics change (e.g., timezone-aware timestamps), the `whereDate` behaviour changes.

### 6.2 Other `db_expense` consumers (must be kept consistent)

| Consumer | What it reads | File:line |
|---|---|---|
| `ReportController::getProfitLossData` (P&L) | `DbExpense::query()` sum by `expense_date` range — **NOT store-scoped** | [ReportController.php:289-292](app/Http/Controllers/ReportController.php:289) |
| `ReportController::getExpenseReportData` | `whereBetween('expense_date', ...)` + optional category — **NOT store-scoped** | [ReportController.php:1375-1380](app/Http/Controllers/ReportController.php:1375) |
| `ReportController` cash-flow category breakdown | join with `db_expense_category`, `whereIn('account_id', ...)` — **NOT store-scoped** | [ReportController.php:2391-2397](app/Http/Controllers/ReportController.php:2391) |
| `DashboardController::index` | `DbExpense::whereDate('expense_date', $today)->sum('expense_amt')` — **NOT store-scoped** | [DashboardController.php:76](app/Http/Controllers/DashboardController.php:76) |
| `GlobalSearchController::search` | `expense_code`/`reference_no`/`expense_for` like — **NOT store-scoped** | [GlobalSearchController.php:518-524](app/Http/Controllers/GlobalSearchController.php:518) |
| `AccountController` delete-guard | `DB::table('db_expense')->where('account_id', $accountId)->exists()` | [AccountController.php:372-377](app/Http/Controllers/AccountController.php:372) |

### 6.3 SMS / SmsTriggerService — **CONFIRMED: NO expense trigger**

- [`SmsTriggerService::mapVariables()`](app/SMS/Services/SmsTriggerService.php:102) handles
  `InvoiceCreated`, `CustomerAdded`, `PaymentReceived`, `Emi*`, `SalesReturn*`, `PurchaseCreated`,
  `LowStock`/`WarehouseLowStock`, `StockAdjustmentAlert`, `EmiDue/Overdue`, `ServiceDueReminder`,
  `CustomerBirthday`, `FestivalCampaign`, `CouponExpiry`, `EodSummary`, `LargeTransactionAlert`,
  `BackupCompletedAlert`.
- **There is NO `Expense*` event case and no `resolvePhone` branch for expenses** — expense create/delete
  does **not** trigger SMS (unlike `StockAdjustmentAlert`, which reads `DbStockAdjustmentItems` at
  [SmsTriggerService.php:218-225](app/SMS/Services/SmsTriggerService.php:218)). No observer is registered
  for `DbExpense` (only `SMSObserver` on `DbSale` at [`app/Observers/SMSObserver.php:16`](app/Observers/SMSObserver.php:16)).

---

## 7. Confirmed-by-Code vs Needs-Runtime-Test

**Confirmed by code reading:**
1. No expense edit exists (route, controller, view) — `ExpenseController` has only `index/create/store/destroy`.
2. `destroy()` is an unguarded hard delete (no permission gate, no store scope, no closed-period guard, no `delete_bit`).
3. `store()` writes `ac_transactions` (EXPENSE debit) + decrements account balance when `account_id` is set.
4. `index()` list, category/account dropdowns, and all category lookups are **not** store-scoped.
5. No controller-level permission checks anywhere in the Expenses module; routes only gated by `auth`+`verified`.
6. Category destroy can orphan expenses (no FK, no usage check).
7. No SMS trigger reads `db_expense`; no expense SMS event exists.
8. `payment_type` UI is missing entirely — every expense created via UI is `'Cash'`, which is exactly
   what the Cash Reconciliation query counts.

**NEEDS RUNTIME TEST to fully confirm:**
1. Double-click on Save Expense producing duplicate rows (server has no guard; symptom to observe live).
2. Delete of an expense dated inside an already-closed reconciliation: confirm the stored
   `cash_expenses_amount` snapshot on the reconciliation row stays stale while a fresh
   `getCalculationBreakdown()` for that date returns the reduced value.
3. Cross-store id leak: confirm a user of store B can delete store A's expense by id (code shows
   `findOrFail($id)` with no store filter; end-to-end proof needs two stores).
4. Whether the closed-record show page surfaces any "recalculate" affordance that would expose the drift.

---

## 8. Redesign Constraints (so the Cash Reconciliation consumer never breaks)

1. **Never change `db_expense.expense_date` semantics or `payment_type='Cash'` literal** without
   updating [`getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1096).
2. **If soft-delete is introduced** (`delete_bit`), update the breakdown query to filter it, and decide
   whether reconciliation should exclude deleted expenses retroactively.
3. **If edit is added**, it MUST be guarded: disallow changing `expense_date`/`account_id`/`expense_amt`
   (or at minimum `expense_date` crossing a closed `Reconciled`/`Adjusted` window) and keep
   `ac_transactions` + account balances in sync transactionally.
4. **If delete is hardened**, it MUST check `CashDrawerReconciliation` for `(store_id, account_id,
   expense_date)` in `Reconciled`/`Adjusted` and either block or require an explicit override with an
   audit trail; it MUST also clean up `ac_transactions` by `ref_expense_id` (already done) inside the
   same transaction.
5. **Store-scope everything**: `index()`, category/account dropdowns, `destroy()`, and all category
   lookups must add `where('store_id', current_store_id())`.
6. **Add controller-level permission gates** matching the pattern already used by Cash Reconciliation
   (`cash_reconciliation_add` etc.) using the existing `expense_*` / `expense_category_*` slugs.
7. **Guard category delete** against expenses still referencing it (soft-disable via `status` is the
   existing mechanism).