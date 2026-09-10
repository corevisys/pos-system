# Expenses Rollout — Phase-by-Phase Implementation Report

> **Scope:** Fix + build on the Expenses module (ExpenseController, db_expense,
> expenses_list/create/edit/category views) per the six-phase rollout. **No code
> changed outside the documented module and its direct consumers.** Every numbered
> item below reports exact file/line before-after and the exact test assertion
> used to verify it. All assertions are from `tests/Feature/ExpensesRolloutTest.php`
> (22 tests, 95 assertions — all pass) unless otherwise cited.

---

## DEFAULT DECISIONS TAKEN (stated explicitly)

1. **Phase 1 — soft-delete, not hard-delete+reversal-only.** Added `delete_bit`
   (default 0) to `db_expense` and made `destroy()` transition `delete_bit 0→1`
   atomically, retaining the original row + an offsetting `EXPENSE REVERSAL`
   ledger row (mirrors the established Deposit module convention
   `ac_moneydeposits.delete_bit` + `'DEPOSIT REVERSAL'`). Rationale: preserves an
   audit record of the reversal and the original row; a hard delete + reversal
   with no link back was explicitly the riskier alternative.
   Consequence handled: because deleted rows remain in `db_expense`, every
   `db_expense` consumer query now adds `where('delete_bit', 0)` (see Phase 1,
   Item 1b) so deleted expenses are never re-counted.
2. **Phase 3 — 'Cash' kept as default selected value**; the hard-coded fallback
   `$request->payment_type ?? 'Cash'` is unchanged for already-existing rows and
   for requests that omit the field. No new payment-type values invented — the
   enum is derived from the app's canonical `db_paymenttypes` table (status=1),
   with `'CASH'`-cased entries canonicalized to the exact literal `'Cash'` the
   Cash Reconciliation contract requires.
3. **Phase 5 — bulk delete, receipts/attachments, detail view, and
   category/date-range filters are OUT OF SCOPE** (net-new feature requests).
   I did **not** build partial/stub versions of them. The previously decorative
   bulk-select checkboxes were **removed** (Phase 6, Item 10) rather than left
   non-functional.

---

## PHASE 1 — Delete reversal (CRITICAL — ledger/balance corruption)

### Item 1a: `destroy()` now reverses the ledger/balance effect before soft-deleting

**Before** — [`ExpenseController.php`](app/Http/Controllers/ExpenseController.php:123) (original):
```php
public function destroy($id) {
    $expense = DbExpense::findOrFail($id);
    // Revert Account Balances and Delete Matching AcTransactions
    $transactions = AcTransaction::where('ref_expense_id', $expense->id)->get();
    foreach ($transactions as $tx) { ... $acc->increment('balance', $tx->debit_amt); }
    AcTransaction::where('ref_expense_id', $expense->id)->delete();
    $expense->delete();
}
```

**After** — the reversal is extracted into a reusable protected helper
[`reverseExpenseLedger()`](app/Http/Controllers/ExpenseController.php:84):
- Inserts an `EXPENSE REVERSAL` `ac_transactions` row (`credit_account_id` =
  expense account, `credit_amt` = original amount, `ref_expense_id` links back —
  mirroring the Deposit `'DEPOSIT REVERSAL'` shape with `ref_moneydeposits_id`).
- Re-increments `ac_accounts.balance` by the original `expense_amt`.
- Does **not** delete the original `EXPENSE` row (audit trail).
- Skips when `account_id` is null or amount ≤ 0 (standalone expenses have no
  ledger effect, matching the original `store()` guard).

`destroy()` ([`ExpenseController.php:460`](app/Http/Controllers/ExpenseController.php:460)) now:
1. Atomically transitions `delete_bit 0→1` (guarded `$affected !== 1` — also
   eliminates concurrent double-delete).
2. Calls `reverseExpenseLedger()`.
3. On any exception, rolls back and resets `delete_bit` to 0.

**Schema** — new migration [`2026_02_09_000001_add_delete_bit_to_db_expense_table.php`](database/migrations/2026_02_09_000001_add_delete_bit_to_db_expense_table.php:1)
adds `delete_bit` (default 0, indexed) and `ledger_version` (Phase 5).
[`DbExpense.php`](app/Models/DbExpense.php:14) `$fillable` gains both.

### Item 1b: every `db_expense` consumer now excludes soft-deleted rows
Soft-delete leaves rows in the table, so all readers must filter `delete_bit=0`:
- [`CashReconciliationController::getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1096) — added `where('delete_bit', 0)` **without touching the protected filter shape** (store_id, payment_type='Cash', account_id, expense_date, sum).
- [`ReportController.php`](app/Http/Controllers/ReportController.php:289) (P&L), [:1375](app/Http/Controllers/ReportController.php:1375) (expense report), [:2391](app/Http/Controllers/ReportController.php:2391) (cash-flow category breakdown).
- [`DashboardController.php:76`](app/Http/Controllers/DashboardController.php:76).
- [`GlobalSearchController.php:518`](app/Http/Controllers/GlobalSearchController.php:518).

### Verify (Phase 1)
`test_phase1_delete_reverses_balance_and_ledger_with_soft_delete` — creates via the
real `store()` route (EXPENSE debit posted, balance 1000→850), deletes, then
asserts:
- `(int)$expense->delete_bit === 1` (row retained),
- `(float)$acc->balance === 1000.0` (restored),
- exactly **1** `EXPENSE REVERSAL` row with `credit_amt = 150` exists.

`test_phase1_delete_without_account_has_no_reversal_and_soft_deletes` — asserts 0
`ac_transactions` rows for a standalone expense.

---

## PHASE 2 — Delete guards (CRITICAL — unguarded destructive action)

### Item 2: permission gate on `destroy()` (and all module methods)
`destroy()` now aborts 403 without `expense_delete`
([`ExpenseController.php:462`](app/Http/Controllers/ExpenseController.php:462)).
Same gates added to `index()` (`expense_view`), `create()`/`store()`
(`expense_add`), `edit()`/`update()` (`expense_edit`), and all six
`ExpenseCategoryController` methods (`expense_category_*` slugs).

### Item 3: store scoping
- `destroy()` lookup: `DbExpense::where('store_id', $storeId)->where('delete_bit', 0)->find($id)` (was unscoped `findOrFail`).
- `index()`: `->where('store_id', current_store_id())->where('delete_bit', 0)` (was unscoped).
- `create()`/`edit()` account dropdown: `where('store_id', $storeId)->where('status',1)->where('delete_bit',0)`.
- `create()`/`edit()` category dropdown: own-store **OR** legacy `store_id IS NULL`
  rows (multi-store migration compatibility — legacy rows predate multi-store).
- `ExpenseCategoryController`: all index/edit/update/destroy lookups store-scoped.

### Item 4: closed-reconciliation-period lock
New protected helper
[`isInClosedReconciliationPeriod()`](app/Http/Controllers/ExpenseController.php:54)
returns true when a `CashDrawerReconciliation` exists for `(store_id, account_id)`
with status `Reconciled` or `Adjusted` and its `reconciliation_date` (or
`period_start`/`period_end` range, when set) covers the expense date. Only
account-linked expenses can affect a reconciliation's expected balance, so the
check is scoped by `account_id`. `destroy()` and `update()` both consult it.

### Verify (Phase 2)
- `test_phase2_unauthorized_delete_returns_403_and_row_untouched`: view-only user →
  `assertForbidden()`, `delete_bit` stays 0.
- `test_phase2_store_scoped_delete_cannot_touch_other_store_expense`: store-B user
  cannot delete store-A expense (row still `delete_bit=0`); store-A user can.
- `test_phase2_index_is_store_scoped`: store-A list sees only store-A rows.
- `test_phase2_delete_blocked_inside_closed_reconciliation_period`: expense dated
  inside a `Reconciled` period → delete rejected, `delete_bit` stays 0, balance
  stays 940 (not reversed).
- `test_phase2_delete_allowed_outside_closed_period_control`: expense on a date
  with only an unrelated-account closed reconciliation → deletes normally,
  balance restored to 1000.

---

## PHASE 3 — payment_type correctness (HIGH — fragile hidden contract)

### Item 5: real payment_type field + enum validation
- [`create_expense.blade.php`](resources/views/module/expenses/create_expense.blade.php) now renders a **Payment Type** select defaulting to `Cash`.
- `store()`/`update()` validate `payment_type => ['nullable','string', Rule::in($validPaymentTypes)]`.
- `$validPaymentTypes` = canonical `DbPaymentType` list (status=1) with `'CASH'`
  canonicalized to `'Cash'` ([`paymentTypeOptions()`](app/Http/Controllers/ExpenseController.php:27)).
- Default remains `'Cash'` for omitted field and for all existing rows.

**PROTECTED CONTRACT preserved** — [`getCalculationBreakdown()`](app/Http/Controllers/CashReconciliationController.php:1096)
still filters `payment_type='Cash'` + `account_id` + `store_id` + `expense_date`
(exact literal `'Cash'`), with the added `delete_bit=0`.

### Verify (Phase 3)
- `test_phase3_cash_expense_still_counts_in_cash_reconciliation`: creating a Cash
  expense → `calculate-expected` returns `cash_expenses_amount = 75.0`.
- `test_phase3_non_cash_expense_excluded_from_cash_reconciliation`: creating a
  `BANK TRANSFER` expense → `cash_expenses_amount = 0.0`.
- `test_phase3_invalid_payment_type_rejected`: `NOT_A_REAL_TYPE` →
  `assertSessionHasErrors('payment_type')`, 0 rows inserted.
- `test_phase3_payment_type_defaults_to_cash`: omitted field → stored `'Cash'`.

---

## PHASE 4 — Category-orphan guard + footer total fix (MEDIUM)

### Item 6: category destroy blocked while expenses reference it
[`ExpenseCategoryController@destroy()`](app/Http/Controllers/ExpenseCategoryController.php:126)
now checks `DbExpense::where('store_id',$storeId)->where('delete_bit',0)->where('category_id',$category->id)->exists()`
and returns an error when in use (reassign-or-delete-first message). Unused
categories still hard-delete (category rows themselves have no ledger impact).

### Item 7: footer total sums the FULL filtered set
`index()` computes `$totalExpenses = (clone $query)->sum('expense_amt')` **before**
pagination ([`ExpenseController.php:174`](app/Http/Controllers/ExpenseController.php:174)),
and the list footer renders `Total: {{ format_currency($totalExpenses) }}`
([`expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php)).

### Verify (Phase 4)
- `test_phase4_category_delete_blocked_when_expenses_reference_it`: delete → error
  session flash, category still exists, expense still references it.
- `test_phase4_unused_category_still_deletes`: delete → success, category gone.
- `test_phase4_footer_total_sums_all_filtered_rows_not_just_page`: 12 × $10
  expenses (2 pages) → page shows `120.00` (not the page-1 sum `100.00`).

---

## PHASE 5 — Build: Expense Edit (net-new feature)

### Item 8: edit()/update() + routes + view
- Routes added: `GET expenses/edit/{id}` → `edit()`, `POST expenses/update/{id}` →
  `update()` ([`routes/web.php:311-312`](routes/web.php:311)).
- [`edit_expense.blade.php`](resources/views/module/expenses/edit_expense.blade.php) —
  new view following the same form conventions as create; list row Edit link
  re-enabled via `x-dropdown-link` in [`expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php).

`update()` ([`ExpenseController.php:362`](app/Http/Controllers/ExpenseController.php:362)):
1. **Guards first**: `expense_edit` permission, store-scoped `findOrFail`, then
   `isInClosedReconciliationPeriod()` against **both** the original
   (date/account) and the new (date/account) — blocked if either falls in a
   closed period.
2. **Double-submit guard (server-side, race-safe)**: atomic optimistic-lock claim
   `where('ledger_version',$expected)->update(['ledger_version'=>$expected+1])`;
   `$claimed !== 1` → abort (this is what makes a genuine parallel double-submit
   impossible, since SQLite `lockForUpdate` is a no-op and the existing Alpine
   `submitting` flag is client-only).
3. **Reverse OLD effect** — reuses the exact Phase 1 `reverseExpenseLedger()` helper
   (not re-derived).
4. Persist edited fields.
5. **Apply NEW effect** — `applyExpenseLedger()`.
All inside one `DB::transaction`.

### Verify (Phase 5)
- `test_phase5_edit_amount_reverses_old_and_applies_new_exact_balance`:
  create $100 (balance 900) → edit to $250 → asserts **exactly 750.0**, exactly 1
  `EXPENSE REVERSAL` (credit 100) + 1 new `EXPENSE` (debit 250).
- `test_phase5_edit_moves_ledger_effect_between_accounts`:
  oldAcc 1000→920, move to newAcc(2000) → oldAcc restored to **1000.0**, newAcc
  **1920.0**.
- `test_phase5_edit_blocked_inside_closed_reconciliation_period`: `Adjusted`
  period → `assertSessionHas('error')`, amount still 50, balance still 950.
- `test_phase5_edit_requires_permission_and_store_scope`: view-only → 403 (GET +
  POST), row untouched; store-B user → 404 on edit GET.
- `test_phase5_genuine_parallel_double_submit_applies_ledger_exactly_once`:
  two real OS processes boot the real app against a shared file-backed SQLite DB
  and dispatch the real `POST /expenses/update/{id}` simultaneously (spinlock
  barrier). Final DB state asserted directly via PDO: balance **750.0** (not 500),
  exactly 1 forward `EXPENSE` (250), exactly 1 `EXPENSE REVERSAL` (100),
  `ledger_version = 1`.

---

## PHASE 6 — Redesign / visual pass (LAST — presentation only)

### Item 9: design-system baseline
- [`expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php) —
  rebuilt on the Deposit list baseline: `card`, `btn-primary`, `input-base`,
  `x-dropdown`/`x-dropdown-link`, `{{ $expenses->links() }}`, `text-text-primary`/
  `text-text-muted` token classes, `$currencySymbol` header.
- [`create_expense.blade.php`](resources/views/module/expenses/create_expense.blade.php) and
  [`edit_expense.blade.php`](resources/views/module/expenses/edit_expense.blade.php) —
  rebuilt on the add_deposit/edit_deposit baseline (`x-card`, floating labels,
  `btn-primary`/`btn-secondary` actions, `x-data="{ isSubmitting: false }"`
  submit-disable).
- **No formula/ledger/guard/validation behavior changed in this phase** — only
  markup/styling/layout.

### Item 10: decorative-dead controls
- **Copy/Excel/PDF → wired, store-scoped.** CSV (`?export=csv`) reuses the Deposit
  stream-response convention (UTF-8 BOM, `fputcsv`, `Content-Disposition`);
  PDF/print (`?export=pdf`) renders a new [`expenses_list_print.blade.php`](resources/views/module/expenses/expenses_list_print.blade.php).
  Both run on the already-store-scoped query.
- **Per-page "Show" → wired** to `per_page` (whitelist `[10,25,50,100]`), matching
  the Deposit `onchange` redirect pattern.
- **Bulk-select checkboxes → removed** (bulk-delete is out of scope, Phase 5
  decision). The header/row checkboxes and their Alpine `selectAll`/`x-model="selected"`
  handlers are fully gone from [`expenses_list.blade.php`](resources/views/module/expenses/expenses_list.blade.php) —
  no orphaned JS references remain.

### Verify (Phase 6)
- `test_phase6_csv_export_is_store_scoped`: store-1 CSV contains `ExportS1` and
  does **not** contain store-2's `ExportS2` (`assertStringNotContainsString`).
- `test_phase6_per_page_whitelist_and_pagination`: default, `per_page=25`, and
  invalid `per_page=999` all render `of 12 entries`; whitelist falls back to 10.
- `test_phase6_bulk_select_checkboxes_removed`: list page `assertDontSee('selectAll')`
  and `assertDontSee('x-model="selected"')`.

---

## Regression verification

- [`tests/Feature/ExpensesRolloutTest.php`](tests/Feature/ExpensesRolloutTest.php) —
  **22 tests / 95 assertions, all pass** (including the genuine-parallel
  double-submit test that boots two real OS processes against a shared file DB).
- [`tests/Feature/ExpenseAccountingLedgerTest.php`](tests/Feature/ExpenseAccountingLedgerTest.php:114)
  — updated test 3 to the new intended Phase 1 semantics (soft-delete + retained
  `EXPENSE REVERSAL` audit row instead of hard-deleting the transaction); balance
  restoration assertion unchanged. 4/4 pass.
- Cash Reconciliation (`CashDrawerReconciliationTest`,
  `CashReconciliationFixesTest`, `CashReconciliationUiFlowTest`),
  `DashboardAndProfitLossSyncTest`, `CashFlowStatementReportTest`, `GlobalSearchTest`,
  `PageTitleTest`, `NavigationShortcutTest`, `CodeGeneratorServiceTest` — all pass
  (the Cash Reconciliation `delete_bit=0` addition preserves the protected
  `payment_type='Cash'` contract).
- Full suite: **852 passed**. The 16 failures are all pre-existing, load-sensitive
  OS-worker parallel/node-check tests (`SupplierRedesignAndRisksTest`,
  `ItemImportTest`, `MoneyTransferFixesTest`, `PosSerial*`, `SerialUniqueness*`,
  `StockAdjustmentDeleteAndRaceTest`, `SystemAccountRaceConditionTest`,
  `ItemsListRedesignTest`, `StockCreateFormRedesignBrowserCheckTest`,
  `ManualLiveVerificationTest`) — each passes when run in isolation (verified for
  `SupplierRedesignAndRisksTest` and `ItemImportTest`). None touch Expenses.

---

## Out-of-scope items (explicitly not built)

Per the Phase 5 DEFAULT DECISION: **bulk delete, receipts/attachments, detail
view, category/date-range filters** were not built and no partial/stub versions
were added. They remain future module-addition proposals. No SMS trigger was
added for expenses, and the other `db_expense` consumers (P&L, Expense Report,
cash-flow breakdown, Dashboard, Global Search, Account delete-guard) were not
refactored — only given the minimal `delete_bit=0` filter required by the
soft-delete decision so they keep excluding deleted expenses correctly.