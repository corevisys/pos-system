<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use Carbon\Carbon;

function getExpenseAccountingTestUser(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Expense Accounting Test Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['expense_add', 'expense_view', 'expense_delete', 'accounts_view', 'accounts_cash_transactions'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. Creating expense with cash account creates AcTransaction debit entry and decrements account balance', function () {
    $user = getExpenseAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Petty Cash Drawer',
        'account_code' => 'ACC-EXP-001',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $category = DbExpenseCategory::create([
        'store_id' => 1,
        'category_name' => 'Office Supplies',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('expenses.store'), [
        'expense_date' => Carbon::today()->format('Y-m-d'),
        'category_id' => $category->id,
        'expense_for' => 'Printer Paper & Ink',
        'expense_amt' => 150.00,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'note' => 'Monthly stationery',
    ]);

    $response->assertRedirect(route('expenses.list'));

    // Verify Expense record created
    $expense = DbExpense::where('account_id', $account->id)->latest()->first();
    expect($expense)->not->toBeNull();
    expect((float)$expense->expense_amt)->toBe(150.0);

    // Verify AcTransaction debit entry created
    $transaction = AcTransaction::where('ref_expense_id', $expense->id)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_type)->toBe('EXPENSE');
    expect($transaction->debit_account_id)->toBe($account->id);
    expect($transaction->credit_account_id)->toBeNull();
    expect((float)$transaction->debit_amt)->toBe(150.0);
    expect((float)$transaction->credit_amt)->toBe(0.0);
    expect($transaction->note)->toContain('Printer Paper & Ink');

    // Verify Account Balance decremented: $1,000.00 - $150.00 = $850.00
    $account->refresh();
    expect((float)$account->balance)->toBe(850.0);
});

test('2. Creating expense without account_id does not touch AcTransaction or account balances', function () {
    $user = getExpenseAccountingTestUser();

    $category = DbExpenseCategory::create([
        'store_id' => 1,
        'category_name' => 'Utilities',
        'status' => 1,
    ]);

    $response = $this->actingAs($user)->post(route('expenses.store'), [
        'expense_date' => Carbon::today()->format('Y-m-d'),
        'category_id' => $category->id,
        'expense_for' => 'Electricity Bill Pending',
        'expense_amt' => 300.00,
        'payment_type' => 'Cash',
        'account_id' => null,
    ]);

    $response->assertRedirect(route('expenses.list'));

    $expense = DbExpense::where('expense_for', 'Electricity Bill Pending')->latest()->first();
    expect($expense)->not->toBeNull();

    // No AcTransaction should exist for this expense
    $transaction = AcTransaction::where('ref_expense_id', $expense->id)->first();
    expect($transaction)->toBeNull();
});

test('3. Deleting expense removes AcTransaction and restores account balance', function () {
    $user = getExpenseAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Operations Cash',
        'account_code' => 'ACC-EXP-003',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $category = DbExpenseCategory::create([
        'store_id' => 1,
        'category_name' => 'Maintenance',
        'status' => 1,
    ]);

    // Step 1: Create expense of $250 -> Balance becomes 1000 - 250 = 750
    $this->actingAs($user)->post(route('expenses.store'), [
        'expense_date' => Carbon::today()->format('Y-m-d'),
        'category_id' => $category->id,
        'expense_for' => 'AC Repair',
        'expense_amt' => 250.00,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
    ]);

    $account->refresh();
    expect((float)$account->balance)->toBe(750.0);

    $expense = DbExpense::where('account_id', $account->id)->latest()->first();
    expect($expense)->not->toBeNull();

    // Step 2: Delete expense -> Balance should be restored to $1,000.00
    $deleteResponse = $this->actingAs($user)->delete(route('expenses.delete', ['id' => $expense->id]));
    $deleteResponse->assertRedirect(route('expenses.list'));

    $account->refresh();
    expect((float)$account->balance)->toBe(1000.0);

    // Phase 1 (Expenses rollout): soft-delete + non-destructive reversal. The row
    // is retained with delete_bit=1 and an offsetting EXPENSE REVERSAL entry is
    // posted (audit trail) — it is no longer hard-deleted as before.
    $expense->refresh();
    expect((int)$expense->delete_bit)->toBe(1);

    $reversal = AcTransaction::where('ref_expense_id', $expense->id)
        ->where('transaction_type', 'EXPENSE REVERSAL')
        ->where('credit_amt', 250.00)
        ->first();
    expect($reversal)->not->toBeNull();
    expect(AcTransaction::where('ref_expense_id', $expense->id)->where('transaction_type', 'EXPENSE')->count())->toBe(1);
});

test('4. Cash transactions ledger displays EXPENSE entries with account name and debit amount', function () {
    $user = getExpenseAccountingTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Shop Cash Drawer',
        'account_code' => 'ACC-EXP-004',
        'balance' => 2000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $category = DbExpenseCategory::create([
        'store_id' => 1,
        'category_name' => 'Refreshments',
        'status' => 1,
    ]);

    $this->actingAs($user)->post(route('expenses.store'), [
        'expense_date' => Carbon::today()->format('Y-m-d'),
        'category_id' => $category->id,
        'expense_for' => 'Tea & Snacks',
        'expense_amt' => 80.00,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
    ]);

    $response = $this->actingAs($user)->get(route('accounts.transactions'));
    $response->assertOk();
    $response->assertSee('EXPENSE');
    $response->assertSee('Shop Cash Drawer');
    $response->assertSee('Tea &amp; Snacks', false);
    $response->assertSee('80.00');
});
