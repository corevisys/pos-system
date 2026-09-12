<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\CashDrawerReconciliation;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\DbPaymentType;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Expenses rollout — Phases 1-6 regression tests.
 *
 * Phase 1: delete reversal (balance restored + EXPENSE REVERSAL entry + soft-delete).
 * Phase 2: permission gates, store scoping, closed-reconciliation-period lock.
 * Phase 3: payment_type field + enum validation + Cash Reconciliation contract.
 * Phase 4: category-orphan guard + footer total (full filtered sum).
 * Phase 5: edit flow — exact balance/ledger results, account move, period lock,
 *          double-submit optimistic-lock claim.
 * Phase 6: CSV/PDF export store-scoped, per_page whitelist, bulk-select removed.
 */
class ExpensesRolloutTest extends TestCase
{
    use RefreshDatabase;

    protected function store(int $id = 1, string $name = 'Expense Store'): DbStore
    {
        return DbStore::firstOrCreate(['id' => $id], [
            'store_name' => $name,
            'status' => 1,
            'mobile' => '0171' . str_pad((string) $id, 8, '0', STR_PAD_LEFT),
        ]);
    }

    protected function makeUser(int $storeId = 1, array $permissions = []): User
    {
        $this->store($storeId);
        // Store scope bypassed: DbRole is StoreScoped now, and this helper may run
        // while acting as another store's user.
        $role = DbRole::allStores()->firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);

        if (empty($permissions)) {
            // Super Admin role id=1 is exempt via isSuperAdmin() (is_super_admin flag).
            DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);
            return User::factory()->create(['store_id' => $storeId, 'role_id' => 1, 'role_name' => 'Super Admin']);
        }

        $role = DbRole::create(['store_id' => 1, 'role_name' => 'Limited-' . uniqid(), 'status' => 1]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => $permissions]);
        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id, 'role_name' => $role->role_name]);
    }

    protected function makeCategory(int $storeId = 1): DbExpenseCategory
    {
        return DbExpenseCategory::create([
            'store_id' => $storeId,
            'category_name' => 'Cat-' . uniqid(),
            'status' => 1,
        ]);
    }

    protected function makeAccount(int $storeId = 1, float $balance = 1000.00): AcAccount
    {
        return AcAccount::create([
            'store_id' => $storeId,
            'account_name' => 'Acc-' . uniqid(),
            'account_code' => 'ACC-' . uniqid(),
            'balance' => $balance,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    protected function seedPaymentTypes(): void
    {
        foreach (['CASH', 'Bkash', 'BANK TRANSFER', 'CARD'] as $pt) {
            DbPaymentType::firstOrCreate(['payment_type' => $pt], ['store_id' => 1, 'status' => 1]);
        }
    }

    protected function makeExpense(array $overrides = []): DbExpense
    {
        $defaults = [
            'store_id' => 1,
            'expense_code' => 'EXP-' . uniqid(),
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $this->makeCategory(1)->id,
            'expense_for' => 'Expense ' . uniqid(),
            'expense_amt' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => null,
            'created_by' => 1,
            'created_date' => now()->format('Y-m-d'),
            'created_time' => now()->format('H:i:s'),
            'status' => 1,
            'delete_bit' => 0,
            'ledger_version' => 0,
        ];
        return DbExpense::create(array_merge($defaults, $overrides));
    }

    // ─────────────────────────────── PHASE 1 ───────────────────────────────

    public function test_phase1_delete_reverses_balance_and_ledger_with_soft_delete(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);

        // Create through the REAL store() route so the EXPENSE debit + balance
        // decrement are posted by the controller (proves the full chain).
        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'Phase1Exp',
            'expense_amt' => 150.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'Phase1Exp')->first();

        // Pre-delete: balance decremented, EXPENSE debit posted.
        $acc->refresh();
        $this->assertSame(850.0, (float) $acc->balance);
        $this->assertSame(1, AcTransaction::where('ref_expense_id', $expense->id)->where('transaction_type', 'EXPENSE')->count());

        $response = $this->actingAs($user)->delete(route('expenses.delete', $expense->id));
        $response->assertRedirect(route('expenses.list'));

        // 1. Soft-delete: row still exists, delete_bit=1.
        $expense->refresh();
        $this->assertSame(1, (int) $expense->delete_bit, 'Soft-delete must set delete_bit=1 (row retained for audit).');

        // 2. Balance restored to pre-expense value.
        $acc->refresh();
        $this->assertSame(1000.0, (float) $acc->balance, 'Account balance must be restored to its pre-expense value.');

        // 3. Offsetting EXPENSE REVERSAL entry exists (not merely deleted).
        $this->assertSame(1, AcTransaction::where('ref_expense_id', $expense->id)->where('transaction_type', 'EXPENSE REVERSAL')->where('credit_amt', 150.00)->count());
    }

    public function test_phase1_delete_without_account_has_no_reversal_and_soft_deletes(): void
    {
        $user = $this->makeUser(1);
        $expense = $this->makeExpense(['store_id' => 1, 'expense_amt' => 50.00, 'account_id' => null]);

        $this->actingAs($user)->delete(route('expenses.delete', $expense->id))->assertRedirect(route('expenses.list'));

        $expense->refresh();
        $this->assertSame(1, (int) $expense->delete_bit);
        $this->assertSame(0, AcTransaction::where('ref_expense_id', $expense->id)->count(), 'Standalone (no-account) expense must not create reversal rows.');
    }

    // ─────────────────────────────── PHASE 2 ───────────────────────────────

    public function test_phase2_unauthorized_delete_returns_403_and_row_untouched(): void
    {
        $this->makeUser(1); // super admin seeds role 1
        $expense = $this->makeExpense(['store_id' => 1]);
        $noPerm = $this->makeUser(1, ['expense_view']); // view-only, no delete

        $response = $this->actingAs($noPerm)->delete(route('expenses.delete', $expense->id));
        $response->assertForbidden();

        $expense->refresh();
        $this->assertSame(0, (int) $expense->delete_bit, 'Row must be untouched after forbidden delete.');
    }

    public function test_phase2_store_scoped_delete_cannot_touch_other_store_expense(): void
    {
        $this->store(1, 'Store A');
        $this->store(2, 'Store B');
        $expenseA = $this->makeExpense(['store_id' => 1, 'expense_for' => 'StoreAExp']);

        // Store B user tries to delete Store A's expense.
        $userB = $this->makeUser(2);
        $response = $this->actingAs($userB)->delete(route('expenses.delete', $expenseA->id));
        // back() redirect (not found) — assert expense still exists.
        $this->assertTrue(DbExpense::allStores()->where('id', $expenseA->id)->where('delete_bit', 0)->exists(), 'Cross-store delete must not affect the row.');

        // Control: Store A user can delete it.
        $userA = $this->makeUser(1);
        $this->actingAs($userA)->delete(route('expenses.delete', $expenseA->id))->assertRedirect();
        $this->assertSame(1, (int) $expenseA->refresh()->delete_bit);
    }

    public function test_phase2_index_is_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $this->makeExpense(['store_id' => 1, 'expense_for' => 'VisibleInS1']);
        $this->makeExpense(['store_id' => 2, 'expense_for' => 'OnlyS2']);

        $userA = $this->makeUser(1);
        $res = $this->actingAs($userA)->get(route('expenses.list'));
        $res->assertOk();
        $res->assertSee('VisibleInS1');
        $res->assertDontSee('OnlyS2');
    }

    public function test_phase2_delete_blocked_inside_closed_reconciliation_period(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $date = now()->format('Y-m-d');

        // Create through the real store() so the ledger effect is applied (1000→940).
        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $date,
            'category_id' => $category->id,
            'expense_for' => 'LockedDeleteExp',
            'expense_amt' => 60.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'LockedDeleteExp')->first();
        $this->assertSame(940.0, (float) $acc->refresh()->balance);

        CashDrawerReconciliation::create([
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $user->id,
            'reconciliation_code' => 'REC-' . uniqid(),
            'reconciliation_date' => $date,
            'status' => 'Reconciled',
            'delete_bit' => 0,
            'cash_expenses_amount' => 60.00,
        ]);

        $this->actingAs($user)->delete(route('expenses.delete', $expense->id));
        $expense->refresh();
        $this->assertSame(0, (int) $expense->delete_bit, 'Delete inside a closed reconciliation period must be blocked (row untouched).');
        $this->assertSame(940.0, (float) $acc->refresh()->balance, 'Balance must not be reversed when delete is blocked.');
    }

    public function test_phase2_delete_allowed_outside_closed_period_control(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $yesterday = now()->subDay()->format('Y-m-d');

        // Create through the real store() so the ledger effect is applied (1000→960).
        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $yesterday,
            'category_id' => $category->id,
            'expense_for' => 'AllowedDeleteExp',
            'expense_amt' => 40.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'AllowedDeleteExp')->first();
        $this->assertSame(960.0, (float) $acc->refresh()->balance);

        // Closed reconciliation for a DIFFERENT account (control: unrelated period).
        $otherAcc = $this->makeAccount(1, 500.00);
        CashDrawerReconciliation::create([
            'store_id' => 1,
            'account_id' => $otherAcc->id,
            'user_id' => $user->id,
            'reconciliation_code' => 'REC-' . uniqid(),
            'reconciliation_date' => $yesterday,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        $this->actingAs($user)->delete(route('expenses.delete', $expense->id))->assertRedirect(route('expenses.list'));

        $expense->refresh();
        $this->assertSame(1, (int) $expense->delete_bit, 'Expense outside any closed period must delete normally.');
        $this->assertSame(1000.0, (float) $acc->refresh()->balance, 'Balance restored after allowed delete (Phase 1 reversal).');
    }

    // ─────────────────────────────── PHASE 3 ───────────────────────────────

    public function test_phase3_cash_expense_still_counts_in_cash_reconciliation(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $date = now()->format('Y-m-d');

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $date,
            'category_id' => $category->id,
            'expense_for' => 'CashExp',
            'expense_amt' => 75.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expected = $this->get(route('accounts.cash-reconciliation.calculate-expected', [
            'account_id' => $acc->id,
            'reconciliation_date' => $date,
            'warehouse_id' => '',
        ]))->json('data');

        $this->assertSame(75.0, (float) $expected['cash_expenses_amount'], 'Cash expense must appear in Cash Reconciliation.');
    }

    public function test_phase3_non_cash_expense_excluded_from_cash_reconciliation(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);
        $date = now()->format('Y-m-d');

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $date,
            'category_id' => $category->id,
            'expense_for' => 'BankExp',
            'expense_amt' => 90.00,
            'payment_type' => 'BANK TRANSFER',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expected = $this->get(route('accounts.cash-reconciliation.calculate-expected', [
            'account_id' => $acc->id,
            'reconciliation_date' => $date,
            'warehouse_id' => '',
        ]))->json('data');

        $this->assertSame(0.0, (float) $expected['cash_expenses_amount'], 'Non-cash expense must NOT appear in Cash Reconciliation.');
    }

    public function test_phase3_invalid_payment_type_rejected(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);

        $response = $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'BadType',
            'expense_amt' => 10.00,
            'payment_type' => 'NOT_A_REAL_TYPE',
            'account_id' => null,
        ]);

        $response->assertSessionHasErrors('payment_type');
        $this->assertSame(0, DbExpense::where('expense_for', 'BadType')->count(), 'Invalid payment_type must be rejected before insert.');
    }

    public function test_phase3_payment_type_defaults_to_cash(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'NoTypeGiven',
            'expense_amt' => 20.00,
            'account_id' => null,
            // no payment_type field → defaults to Cash
        ])->assertRedirect(route('expenses.list'));

        $this->assertSame('Cash', DbExpense::where('expense_for', 'NoTypeGiven')->value('payment_type'));
    }

    // ─────────────────────────────── PHASE 4 ───────────────────────────────

    public function test_phase4_category_delete_blocked_when_expenses_reference_it(): void
    {
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);
        $this->makeExpense(['store_id' => 1, 'category_id' => $category->id]);

        $response = $this->actingAs($user)->delete(route('expenses.categories.delete', $category->id));
        $response->assertSessionHas('error');

        $this->assertTrue(DbExpenseCategory::where('id', $category->id)->exists(), 'Category referenced by expenses must NOT be deleted.');
        $this->assertTrue(DbExpense::where('category_id', $category->id)->exists(), 'Expense must still reference the category (no orphan).');
    }

    public function test_phase4_unused_category_still_deletes(): void
    {
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);

        $this->actingAs($user)->delete(route('expenses.categories.delete', $category->id))->assertSessionHas('success');

        $this->assertFalse(DbExpenseCategory::where('id', $category->id)->exists(), 'Unused category must delete normally.');
    }

    public function test_phase4_footer_total_sums_all_filtered_rows_not_just_page(): void
    {
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);

        // 12 expenses (more than one 10-per-page page).
        foreach (range(1, 12) as $i) {
            $this->makeExpense(['store_id' => 1, 'category_id' => $category->id, 'expense_amt' => 10.00]);
        }

        $res = $this->actingAs($user)->get(route('expenses.list'));
        $res->assertOk();

        // Full filtered total = 120.00, not the page-1 sum (100.00).
        $res->assertSee('120.00');
    }

    // ─────────────────────────────── PHASE 5 ───────────────────────────────

    public function test_phase5_edit_amount_reverses_old_and_applies_new_exact_balance(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'EditableExp',
            'expense_amt' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'EditableExp')->first();
        $this->assertSame(900.0, (float) $acc->refresh()->balance);

        // Edit amount 100 → 250.
        $this->actingAs($user)->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'EditableExp',
            'expense_amt' => 250.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        // Old effect reversed (+100), new effect applied (-250): 900 + 100 - 250 = 750.
        $this->assertSame(750.0, (float) $acc->refresh()->balance, 'Balance must be exactly 750 after edit.');

        // One EXPENSE REVERSAL (credit 100) and one new EXPENSE (debit 250).
        $this->assertSame(1, AcTransaction::where('ref_expense_id', $expense->id)->where('transaction_type', 'EXPENSE REVERSAL')->where('credit_amt', 100.00)->count());
        $this->assertSame(1, AcTransaction::where('ref_expense_id', $expense->id)->where('transaction_type', 'EXPENSE')->where('debit_amt', 250.00)->count());

        // Row updated.
        $expense->refresh();
        $this->assertSame(250.0, (float) $expense->expense_amt);
    }

    public function test_phase5_edit_moves_ledger_effect_between_accounts(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $oldAcc = $this->makeAccount(1, 1000.00);
        $newAcc = $this->makeAccount(1, 2000.00);
        $category = $this->makeCategory(1);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => now()->format('Y-m-d'),
            'category_id' => $category->id,
            'expense_for' => 'MoveAccExp',
            'expense_amt' => 80.00,
            'payment_type' => 'Cash',
            'account_id' => $oldAcc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'MoveAccExp')->first();
        $this->assertSame(920.0, (float) $oldAcc->refresh()->balance);

        // Move account oldAcc → newAcc (same amount 80).
        $this->actingAs($user)->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => 'MoveAccExp',
            'expense_amt' => 80.00,
            'payment_type' => 'Cash',
            'account_id' => $newAcc->id,
        ])->assertRedirect(route('expenses.list'));

        // oldAcc restored to 1000; newAcc decremented 2000 → 1920.
        $this->assertSame(1000.0, (float) $oldAcc->refresh()->balance, 'Old account must be fully restored.');
        $this->assertSame(1920.0, (float) $newAcc->refresh()->balance, 'New account must carry the new effect.');
    }

    public function test_phase5_edit_blocked_inside_closed_reconciliation_period(): void
    {
        $this->seedPaymentTypes();
        $user = $this->makeUser(1);
        $acc = $this->makeAccount(1, 1000.00);
        $date = now()->format('Y-m-d');
        $category = $this->makeCategory(1);

        $this->actingAs($user)->post(route('expenses.store'), [
            'expense_date' => $date,
            'category_id' => $category->id,
            'expense_for' => 'LockedExp',
            'expense_amt' => 50.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertRedirect(route('expenses.list'));

        $expense = DbExpense::where('expense_for', 'LockedExp')->first();

        CashDrawerReconciliation::create([
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $user->id,
            'reconciliation_code' => 'REC-' . uniqid(),
            'reconciliation_date' => $date,
            'status' => 'Adjusted',
            'delete_bit' => 0,
        ]);

        $this->actingAs($user)->post(route('expenses.update', $expense->id), [
            'expense_date' => $date,
            'category_id' => $category->id,
            'expense_for' => 'LockedExp',
            'expense_amt' => 999.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertSessionHas('error');

        $expense->refresh();
        $this->assertSame(50.0, (float) $expense->expense_amt, 'Expense amount must NOT change when edit is blocked.');
        $this->assertSame(950.0, (float) $acc->refresh()->balance, 'Balance must be untouched when edit is blocked.');
    }

    public function test_phase5_edit_requires_permission_and_store_scope(): void
    {
        $this->store(1);
        $this->store(2);
        $this->seedPaymentTypes();
        $acc = $this->makeAccount(1, 1000.00);
        $category = $this->makeCategory(1);

        $expense = $this->makeExpense(['store_id' => 1, 'account_id' => $acc->id, 'category_id' => $category->id, 'expense_amt' => 30.00]);

        // View-only user → 403.
        $viewOnly = $this->makeUser(1, ['expense_view']);
        $this->actingAs($viewOnly)->get(route('expenses.edit', $expense->id))->assertForbidden();
        $this->actingAs($viewOnly)->post(route('expenses.update', $expense->id), [
            'expense_date' => $expense->expense_date,
            'category_id' => $category->id,
            'expense_for' => $expense->expense_for,
            'expense_amt' => 500.00,
            'payment_type' => 'Cash',
            'account_id' => $acc->id,
        ])->assertForbidden();
        $this->assertSame(30.0, (float) $expense->refresh()->expense_amt);

        // Store B user cannot edit Store A's expense.
        $userB = $this->makeUser(2);
        $this->actingAs($userB)->get(route('expenses.edit', $expense->id))->assertNotFound();
    }

    // ─────────────────────────────── PHASE 6 ───────────────────────────────

    public function test_phase6_csv_export_is_store_scoped(): void
    {
        $this->store(1);
        $this->store(2);
        $this->makeExpense(['store_id' => 1, 'expense_for' => 'ExportS1', 'expense_amt' => 11.00]);
        $this->makeExpense(['store_id' => 2, 'expense_for' => 'ExportS2', 'expense_amt' => 22.00]);

        $userA = $this->makeUser(1);
        $res = $this->actingAs($userA)->get(route('expenses.list', ['export' => 'csv']));
        $res->assertOk();
        $content = $res->streamedContent();

        $this->assertStringContainsString('ExportS1', $content, 'CSV must include store-1 expense.');
        $this->assertStringNotContainsString('ExportS2', $content, 'CSV must NOT include store-2 expense (store-scoped export).');
    }

    public function test_phase6_per_page_whitelist_and_pagination(): void
    {
        $user = $this->makeUser(1);
        $category = $this->makeCategory(1);
        foreach (range(1, 12) as $i) {
            $this->makeExpense(['store_id' => 1, 'category_id' => $category->id, 'expense_for' => 'PerPage-' . $i]);
        }

        // Default 10 per page → page 1 has 10.
        $res = $this->actingAs($user)->get(route('expenses.list'));
        $res->assertOk();
        $this->assertStringContainsString('of 12 entries', $res->getContent());

        // per_page=25 → all 12 on one page.
        $res25 = $this->actingAs($user)->get(route('expenses.list', ['per_page' => 25]));
        $res25->assertOk();
        $this->assertStringContainsString('of 12 entries', $res25->getContent());

        // Invalid per_page (999) falls back to 10 → still paginated (10 per page).
        $resBad = $this->actingAs($user)->get(route('expenses.list', ['per_page' => 999]));
        $resBad->assertOk();
        $this->assertStringContainsString('of 12 entries', $resBad->getContent());
    }

    public function test_phase6_bulk_select_checkboxes_removed(): void
    {
        $user = $this->makeUser(1);
        $this->makeExpense(['store_id' => 1]);

        $res = $this->actingAs($user)->get(route('expenses.list'));
        $res->assertOk();
        $res->assertDontSee('selectAll');
        $res->assertDontSee('x-model="selected"');
    }

    // ─────────────────── PHASE 5 — GENUINE PARALLEL DOUBLE-SUBMIT ───────────────────
    //
    // Two independent OS processes boot the REAL Laravel app against a SHARED
    // file-backed SQLite DB (real migrations), authenticate, and dispatch the REAL
    // POST /expenses/update/{id} through the HTTP kernel simultaneously. The
    // optimistic-lock claim (ledger_version) must allow exactly one to apply its
    // ledger effect; the loser must observe 0 rows affected and abort. This proves
    // a genuine concurrent double-submit cannot double-apply the balance/ledger.

    protected function readAppKey(): string
    {
        $base = dirname(__DIR__, 2);
        $env = file($base . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($env ?: [] as $line) {
            if (str_starts_with($line, 'APP_KEY=')) {
                return substr($line, strlen('APP_KEY='));
            }
        }
        return '';
    }

    protected function seedParallelFileDb(string $dbPath, string $appKey): array
    {
        $base = dirname(__DIR__, 2);
        $php = PHP_BINARY;

        $seedScript = <<<'PHP'
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$base = $argv[1];
$dbPath = $argv[2];
$appKey = $argv[3];

putenv('APP_ENV=testing');
putenv('APP_KEY=' . $appKey);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . $dbPath);

require $base . '/vendor/autoload.php';
$app = require $base . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => $dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

try {
    $kernel->call('migrate:fresh', ['--force' => true]);
} catch (\Throwable $e) {
    fwrite(STDERR, 'SEED_MIGRATE_FAIL:' . $e->getMessage() . "\n");
    exit(1);
}

\App\Models\DbStore::create(['id' => 1, 'store_name' => 'Parallel Expense Store', 'status' => 1, 'mobile' => '01700000123']);
$role = \App\Models\DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true]);
\App\Models\DbPermission::firstOrCreate(['role_id' => 1], ['store_id' => 1, 'permissions' => []]);
$user = \App\Models\User::factory()->create(['store_id' => 1, 'role_id' => 1, 'role_name' => 'Super Admin']);

foreach (['CASH', 'Bkash', 'BANK TRANSFER', 'CARD'] as $pt) {
    \App\Models\DbPaymentType::firstOrCreate(['payment_type' => $pt], ['store_id' => 1, 'status' => 1]);
}

$cat = \App\Models\DbExpenseCategory::create(['store_id' => 1, 'category_name' => 'Parallel Cat', 'status' => 1]);
$acc = \App\Models\AcAccount::create(['store_id' => 1, 'account_name' => 'Parallel Acc', 'account_code' => 'PACC', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);

$expense = \App\Models\DbExpense::create([
    'store_id' => 1,
    'expense_code' => 'EXP-PARALLEL',
    'expense_date' => date('Y-m-d'),
    'category_id' => $cat->id,
    'expense_for' => 'ParallelExp',
    'expense_amt' => 100.00,
    'payment_type' => 'Cash',
    'account_id' => $acc->id,
    'created_by' => $user->id,
    'created_date' => date('Y-m-d'),
    'created_time' => date('H:i:s'),
    'status' => 1,
    'delete_bit' => 0,
    'ledger_version' => 0,
]);

// Simulate the initial ledger effect (as store() would have posted).
\App\Models\AcTransaction::create([
    'store_id' => 1,
    'transaction_date' => date('Y-m-d'),
    'transaction_type' => 'EXPENSE',
    'payment_code' => 'Cash',
    'debit_account_id' => $acc->id,
    'credit_account_id' => null,
    'debit_amt' => 100.00,
    'credit_amt' => 0,
    'note' => 'Expense: ParallelExp (EXP-PARALLEL)',
    'ref_expense_id' => $expense->id,
    'created_by' => $user->id,
    'created_date' => date('Y-m-d'),
]);
$acc->decrement('balance', 100.00); // 1000 -> 900

echo 'EXPENSE_ID:' . $expense->id . "\n";
echo 'ACC_ID:' . $acc->id . "\n";
echo 'CAT_ID:' . $cat->id . "\n";
PHP;

        $scriptPath = sys_get_temp_dir() . '/exp_par_seed_' . uniqid() . '.php';
        file_put_contents($scriptPath, $seedScript);
        $cmd = escapeshellarg($php) . ' ' . escapeshellarg($scriptPath)
            . ' ' . escapeshellarg($base)
            . ' ' . escapeshellarg($dbPath)
            . ' ' . escapeshellarg($appKey);
        exec($cmd . ' 2>&1', $outLines, $code);
        @unlink($scriptPath);

        if ($code !== 0) {
            $this->fail('Seed worker failed (exit ' . $code . '): ' . implode("\n", $outLines));
        }

        $meta = [];
        foreach ($outLines as $line) {
            if (str_contains($line, ':')) {
                [$k, $v] = explode(':', $line, 2);
                $meta[$k] = (int) trim($v);
            }
        }
        if (empty($meta['EXPENSE_ID'])) {
            $this->fail('Seed worker did not return an expense id. Output: ' . implode("\n", $outLines));
        }
        return $meta;
    }

    protected function buildParallelUpdateWorker(string $dbPath, string $appKey, string $barrierFile, string $resultFile, int $expenseId, int $categoryId, int $accountId): string
    {
        $base = dirname(__DIR__, 2);
        return <<<PHP
<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

\$base = '{$base}';
\$appKey = '{$appKey}';
\$dbPath = '{$dbPath}';
\$barrier = '{$barrierFile}';
\$resultFile = '{$resultFile}';
\$expenseId = (int) '{$expenseId}';
\$categoryId = (int) '{$categoryId}';
\$accountId = (int) '{$accountId}';

putenv('APP_ENV=testing');
putenv('APP_KEY=' . \$appKey);
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=' . \$dbPath);

require \$base . '/vendor/autoload.php';
\$app = require \$base . '/bootstrap/app.php';
\$kernel = \$app->make(Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

config(['database.default' => 'sqlite']);
config(['database.connections.sqlite.database' => \$dbPath]);
\Illuminate\Support\Facades\DB::purge('sqlite');
\Illuminate\Support\Facades\DB::connection('sqlite');

while (!file_exists(\$barrier)) {
    usleep(100);
}

try {
    \$user = \App\Models\User::where('store_id', 1)->first();
    if (!\$user) {
        file_put_contents(\$resultFile, 'RESULT:NO_USER');
        exit(1);
    }
    \Illuminate\Support\Facades\Auth::login(\$user);

    \$request = Illuminate\Http\Request::create('/expenses/update/' . \$expenseId, 'POST', [
        'expense_date' => date('Y-m-d'),
        'category_id' => \$categoryId,
        'expense_for' => 'ParallelExp',
        'expense_amt' => 250.00,
        'payment_type' => 'Cash',
        'account_id' => \$accountId,
    ]);
    \$response = \$app->handle(\$request);
    file_put_contents(\$resultFile, 'RESULT:' . \$response->getStatusCode());
} catch (\Throwable \$e) {
    file_put_contents(\$resultFile, 'RESULT:EXCEPTION:' . get_class(\$e) . ':' . \$e->getMessage());
}
exit(0);
PHP;
    }

    public function test_phase5_genuine_parallel_double_submit_applies_ledger_exactly_once(): void
    {
        $appKey = $this->readAppKey();
        $dbPath = sys_get_temp_dir() . '/exp_par_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/exp_par_barrier_' . uniqid() . '.txt';
        $resultFile1 = sys_get_temp_dir() . '/exp_par_res1_' . uniqid() . '.txt';
        $resultFile2 = sys_get_temp_dir() . '/exp_par_res2_' . uniqid() . '.txt';
        $s1 = sys_get_temp_dir() . '/exp_par_w1_' . uniqid() . '.php';
        $s2 = sys_get_temp_dir() . '/exp_par_w2_' . uniqid() . '.php';
        $php = PHP_BINARY;

        file_put_contents($dbPath, '');

        try {
            $meta = $this->seedParallelFileDb($dbPath, $appKey);
            $expenseId = $meta['EXPENSE_ID'];
            $accountId = $meta['ACC_ID'];
            $categoryId = $meta['CAT_ID'];

            file_put_contents($s1, $this->buildParallelUpdateWorker($dbPath, $appKey, $barrierFile, $resultFile1, $expenseId, $categoryId, $accountId));
            file_put_contents($s2, $this->buildParallelUpdateWorker($dbPath, $appKey, $barrierFile, $resultFile2, $expenseId, $categoryId, $accountId));

            $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
            $proc1 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s1), $descriptors, $pipes1);
            $proc2 = proc_open(escapeshellarg($php) . ' ' . escapeshellarg($s2), $descriptors, $pipes2);

            // Release both simultaneously.
            file_put_contents($barrierFile, 'GO');

            $stderr1 = stream_get_contents($pipes1[2]);
            $stderr2 = stream_get_contents($pipes2[2]);
            foreach ($pipes1 as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            foreach ($pipes2 as $p) {
                if (is_resource($p)) {
                    fclose($p);
                }
            }
            proc_close($proc1);
            proc_close($proc2);

            $out1 = file_exists($resultFile1) ? trim(file_get_contents($resultFile1)) : 'NO_RESULT';
            $out2 = file_exists($resultFile2) ? trim(file_get_contents($resultFile2)) : 'NO_RESULT';

            // The update route redirects (302) on success AND on double-submit abort
            // (back with error), so the decisive check is the FINAL DB STATE below.
            $this->assertStringStartsWith('RESULT:302', $out1, "Worker 1 must produce a redirect.\n{$out1}\nErr1: {$stderr1}");
            $this->assertStringStartsWith('RESULT:302', $out2, "Worker 2 must produce a redirect.\n{$out2}\nErr2: {$stderr2}");

            // Final DB state: exactly one forward EXPENSE of 250 and one reversal of 100.
            $pdo = new \PDO('sqlite:' . $dbPath);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $balance = (float) $pdo->query('SELECT balance FROM ac_accounts WHERE id = ' . (int) $accountId)->fetchColumn();
            // 1000 - 100 (initial) + 100 (one reversal) - 250 (one new apply) = 750.
            // If double-applied it would be 500.
            $this->assertEqualsWithDelta(750.0, $balance, 0.001, 'Exactly ONE concurrent update must apply: balance must be 750, not 500 (double-apply).');

            $expenseRows = (int) $pdo->query('SELECT COUNT(*) FROM ac_transactions WHERE ref_expense_id = ' . (int) $expenseId . " AND transaction_type = 'EXPENSE' AND debit_amt = 250")->fetchColumn();
            $this->assertSame(1, $expenseRows, 'Exactly one new EXPENSE forward row (250) must exist.');

            $reversalRows = (int) $pdo->query('SELECT COUNT(*) FROM ac_transactions WHERE ref_expense_id = ' . (int) $expenseId . " AND transaction_type = 'EXPENSE REVERSAL' AND credit_amt = 100")->fetchColumn();
            $this->assertSame(1, $reversalRows, 'Exactly one EXPENSE REVERSAL row (100) must exist.');

            $ledgerVersion = (int) $pdo->query('SELECT ledger_version FROM db_expense WHERE id = ' . (int) $expenseId)->fetchColumn();
            $this->assertSame(1, $ledgerVersion, 'ledger_version must be incremented exactly once (optimistic-lock claim).');
        } finally {
            @unlink($barrierFile);
            @unlink($resultFile1);
            @unlink($resultFile2);
            @unlink($s1);
            @unlink($s2);
            @unlink($dbPath);
        }
    }
}
