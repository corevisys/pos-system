<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbSalesReturn;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\CashDrawerReconciliation;
use Carbon\Carbon;

function getReconciliationTestUser(array $customPermissions = []): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Reconciliation Test Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    // Ensure Super Admin role id=1 exists so test role gets id >= 2
    DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    $role = DbRole::create([
        'store_id' => 1,
        'role_name' => 'Test Role ' . uniqid(),
        'status' => 1,
    ]);

    $permissions = array_merge([
        'accounts_view',
        'cash_reconciliation_view',
        'cash_reconciliation_add',
        'cash_reconciliation_adjust',
        'cash_reconciliation_delete',
        'cash_reconciliation_report',
    ], $customPermissions);

    DbPermission::create([
        'role_id' => $role->id,
        'store_id' => 1,
        'permissions' => $permissions,
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => $role->role_name,
    ]);
}

test('1. Expected closing balance calculation formula accurately aggregates all 5 cash data sources', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Counter Cash Drawer',
        'account_code' => 'ACC-REC-001',
        'balance' => 0.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Flagship Store', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Customer A', 'customer_code' => 'CUST-001', 'status' => 1]);

    // Data Source 1: Cash Sales Payments (+$500.00)
    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-REC-001',
        'sales_date' => $today,
        'grand_total' => 500.00,
        'paid_amount' => 500.00,
        'payment_status' => 'Paid',
    ]);
    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 500.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Data Source 2: Cash Refund from Return (-$100.00)
    $saleReturn = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'RTN-REC-001',
        'return_date' => $today,
        'grand_total' => 100.00,
        'paid_amt' => 100.00,
    ]);
    DbSalesPaymentReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $saleReturn->id,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 100.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Data Source 3: Cash Expense (-$50.00)
    $category = DbExpenseCategory::create(['store_id' => 1, 'category_name' => 'Cleaning', 'status' => 1]);
    DbExpense::create([
        'store_id' => 1,
        'expense_code' => 'EXP-REC-001',
        'category_id' => $category->id,
        'expense_date' => $today,
        'expense_for' => 'Floor sanitizer',
        'expense_amt' => 50.00,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'created_by' => $user->id,
    ]);

    // Data Source 4: Cash Deposit (+$200.00)
    AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => $today,
        'transaction_type' => 'DEPOSIT',
        'payment_code' => 'Cash',
        'credit_account_id' => $account->id,
        'credit_amt' => 200.00,
        'debit_amt' => 0.00,
        'note' => 'Cash Deposit In',
        'created_by' => $user->id,
    ]);

    // Data Source 5: Cash Transfer In (+$150.00) and Cash Transfer Out (-$75.00)
    AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => $today,
        'transaction_type' => 'TRANSFER',
        'payment_code' => 'Cash',
        'credit_account_id' => $account->id,
        'credit_amt' => 150.00,
        'debit_amt' => 0.00,
        'note' => 'Transfer In from Safe',
        'created_by' => $user->id,
    ]);
    AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => $today,
        'transaction_type' => 'TRANSFER',
        'payment_code' => 'Cash',
        'debit_account_id' => $account->id,
        'debit_amt' => 75.00,
        'credit_amt' => 0.00,
        'note' => 'Transfer Out to Bank Courier',
        'created_by' => $user->id,
    ]);

    // Expected Calculation: 0 + 500 (Sales) + 200 (Deposit) + 150 (Transfer In) - 100 (Refund) - 50 (Expense) - 75 (Transfer Out) = $625.00
    $response = $this->actingAs($user)->getJson(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $today,
        'warehouse_id' => $warehouse->id,
    ]));

    $response->assertOk();
    $data = $response->json('data');

    expect($data['is_first_reconciliation'])->toBeTrue();
    expect((float)$data['system_opening_balance'])->toBe(0.0);
    expect((float)$data['opening_balance'])->toBe(0.0);
    expect((float)$data['cash_sales_amount'])->toBe(500.0);
    expect((float)$data['cash_refunds_amount'])->toBe(100.0);
    expect((float)$data['cash_expenses_amount'])->toBe(50.0);
    expect((float)$data['cash_deposits_amount'])->toBe(200.0);
    expect((float)$data['cash_transfers_in'])->toBe(150.0);
    expect((float)$data['cash_transfers_out'])->toBe(75.0);
    expect((float)$data['expected_closing_balance'])->toBe(625.0);
});

test('2. Default log-only reconciliation logs variance without altering account ledger balance', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 2',
        'account_code' => 'ACC-REC-002',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Expected is $0 (no transactions today), opening float $0.00, user counts $980.00
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 0.00,
        'counted_amount' => 980.00,
        'notes' => 'Minor cash deficit at end of day',
        'post_adjustment' => 0, // Default log-only
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();
    expect($recon)->not->toBeNull();
    expect((float)$recon->opening_balance)->toBe(0.0);
    expect((float)$recon->counted_amount)->toBe(980.0);
    expect((float)$recon->expected_closing_balance)->toBe(0.0);
    expect((float)$recon->variance)->toBe(980.0); // counted - expected
    expect($recon->status)->toBe('Reconciled');
    expect($recon->adjustment_transaction_id)->toBeNull();

    // Verify account balance is UNTOUCHED
    $account->refresh();
    expect((float)$account->balance)->toBe(1000.0);

    // Verify NO AcTransaction was created
    expect(AcTransaction::where('transaction_type', 'CASH SHORTAGE')->orWhere('transaction_type', 'CASH OVERAGE')->count())->toBe(0);
});

test('3. Elevated user posting shortage adjustment creates CASH SHORTAGE debit and deducts account balance', function () {
    $user = getReconciliationTestUser(['cash_reconciliation_adjust']);
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 3',
        'account_code' => 'ACC-REC-003',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Seed a cash sale of $1000 -> Expected is $1000
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 1000.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Counted is $970.00 (Shortage of -$30.00) with post_adjustment = 1
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 0.00,
        'counted_amount' => 970.00,
        'notes' => 'Shortage verified by manager',
        'post_adjustment' => 1,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();
    expect($recon)->not->toBeNull();
    expect((float)$recon->variance)->toBe(-30.0);
    expect($recon->status)->toBe('Adjusted');
    expect($recon->adjustment_transaction_id)->not->toBeNull();

    // Verify AcTransaction debit entry
    $tx = AcTransaction::find($recon->adjustment_transaction_id);
    expect($tx)->not->toBeNull();
    expect($tx->transaction_type)->toBe('CASH SHORTAGE');
    expect($tx->debit_account_id)->toBe($account->id);
    expect((float)$tx->debit_amt)->toBe(30.0);
    expect((float)$tx->credit_amt)->toBe(0.0);

    // Account Balance decremented: $1,000.00 - $30.00 = $970.00
    $account->refresh();
    expect((float)$account->balance)->toBe(970.0);
});

test('4. Elevated user posting overage adjustment creates CASH OVERAGE credit and increases account balance', function () {
    $user = getReconciliationTestUser(['cash_reconciliation_adjust']);
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 4',
        'account_code' => 'ACC-REC-004',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Expected is $0, Counted is $50.00 (Surplus/Overage of +$50.00)
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 0.00,
        'counted_amount' => 50.00,
        'notes' => 'Found unrecorded loose cash surplus',
        'post_adjustment' => 1,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();
    expect($recon)->not->toBeNull();
    expect((float)$recon->variance)->toBe(50.0);
    expect($recon->status)->toBe('Adjusted');

    $tx = AcTransaction::find($recon->adjustment_transaction_id);
    expect($tx)->not->toBeNull();
    expect($tx->transaction_type)->toBe('CASH OVERAGE');
    expect($tx->credit_account_id)->toBe($account->id);
    expect((float)$tx->credit_amt)->toBe(50.0);

    // Account Balance incremented: $500.00 + $50.00 = $550.00
    $account->refresh();
    expect((float)$account->balance)->toBe(550.0);
});

test('5. Cashier without cash_reconciliation_adjust cannot post ledger adjustment', function () {
    DbRole::firstOrCreate(['id' => 1], ['store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1]);
    $role = DbRole::create(['store_id' => 1, 'role_name' => 'Cashier Role ' . uniqid(), 'status' => 1]);
    DbPermission::create([
        'role_id' => $role->id,
        'store_id' => 1,
        'permissions' => ['cash_reconciliation_add', 'cash_reconciliation_view'],
    ]);
    $cashier = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'role_name' => $role->role_name]);

    $today = Carbon::today()->format('Y-m-d');
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Drawer 5',
        'account_code' => 'ACC-REC-005',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $response = $this->actingAs($cashier)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 0.00,
        'counted_amount' => 450.00,
        'notes' => 'Attempting adjustment without permission',
        'post_adjustment' => 1,
    ]);

    // Should fail and redirect back with error
    $response->assertSessionHas('error');

    // No reconciliation created
    expect(CashDrawerReconciliation::where('account_id', $account->id)->count())->toBe(0);
    $account->refresh();
    expect((float)$account->balance)->toBe(500.0);
});

test('6. Multi-warehouse isolation scopes cash sales payments strictly to the selected warehouse', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Shared Cash Drawer', 'account_code' => 'ACC-REC-006', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);
    $warehouseA = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Branch North', 'status' => 1]);
    $warehouseB = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'Branch South', 'status' => 1]);
    $customer = DbCustomer::create(['store_id' => 1, 'customer_name' => 'Customer B', 'customer_code' => 'CUST-002', 'status' => 1]);

    // Sale in Warehouse A = $300.00
    $saleA = DbSale::create(['store_id' => 1, 'warehouse_id' => $warehouseA->id, 'customer_id' => $customer->id, 'sales_code' => 'SA-WH-A', 'sales_date' => $today, 'grand_total' => 300, 'paid_amount' => 300]);
    DbSalePayment::create(['store_id' => 1, 'sales_id' => $saleA->id, 'account_id' => $account->id, 'payment_type' => 'Cash', 'payment' => 300.00, 'payment_date' => $today, 'created_by' => $user->id]);

    // Sale in Warehouse B = $200.00
    $saleB = DbSale::create(['store_id' => 1, 'warehouse_id' => $warehouseB->id, 'customer_id' => $customer->id, 'sales_code' => 'SA-WH-B', 'sales_date' => $today, 'grand_total' => 200, 'paid_amount' => 200]);
    DbSalePayment::create(['store_id' => 1, 'sales_id' => $saleB->id, 'account_id' => $account->id, 'payment_type' => 'Cash', 'payment' => 200.00, 'payment_date' => $today, 'created_by' => $user->id]);

    // Warehouse A calculation should be $300.00
    $responseA = $this->actingAs($user)->getJson(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $today,
        'warehouse_id' => $warehouseA->id,
    ]));
    $responseA->assertOk();
    expect((float)$responseA->json('data.cash_sales_amount'))->toBe(300.0);

    // Warehouse B calculation should be $200.00
    $responseB = $this->actingAs($user)->getJson(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $today,
        'warehouse_id' => $warehouseB->id,
    ]));
    $responseB->assertOk();
    expect((float)$responseB->json('data.cash_sales_amount'))->toBe(200.0);
});

test('7. Duplicate reconciliation prevention prevents saving twice for same date, account, and warehouse', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Drawer 7', 'account_code' => 'ACC-REC-007', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);
    $warehouse = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'WH 7', 'status' => 1]);

    // First reconciliation
    $firstResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 0.00,
        'counted_amount' => 500.00,
    ]);
    expect(CashDrawerReconciliation::where('account_id', $account->id)->count())->toBe(1);

    // Duplicate attempt
    $duplicateResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 0.00,
        'counted_amount' => 520.00,
    ]);

    $duplicateResponse->assertSessionHas('error');
    expect(CashDrawerReconciliation::where('account_id', $account->id)->count())->toBe(1);
});

test('8. Subsequent day calculation picks up previous days counted closing cash as system suggested opening balance', function () {
    $user = getReconciliationTestUser();
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Drawer 8', 'account_code' => 'ACC-REC-008', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

    // Day 1: Reconcile with counted cash $1,200.00
    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-DAY-1',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'reconciliation_date' => $yesterday,
        'system_opening_balance' => 0.00,
        'opening_balance' => 0.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'expected_closing_balance' => 1200.00,
        'counted_amount' => 1200.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // Day 2: Call calculateExpected for today -> system_opening_balance must be $1,200.00
    $response = $this->actingAs($user)->getJson(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $today,
    ]));

    $response->assertOk();
    expect($response->json('data.is_first_reconciliation'))->toBeFalse();
    expect((float)$response->json('data.system_opening_balance'))->toBe(1200.0);
    expect((float)$response->json('data.expected_closing_balance'))->toBe(1200.0);
});

test('9. Deleting an adjusted reconciliation reverses the ledger AcTransaction and restores account balance', function () {
    $user = getReconciliationTestUser(['cash_reconciliation_adjust', 'cash_reconciliation_delete']);
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Drawer 9',
        'account_code' => 'ACC-REC-009',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Create reconciliation with -$40 shortage and post adjustment -> Balance becomes $960.00
    $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 0.00,
        'counted_amount' => 0.00,
        'notes' => 'Shortage adjustment to delete',
        'post_adjustment' => 1,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();

    // Create an explicit adjusted recon with $50 overage
    $recon->update(['variance' => 50.00, 'status' => 'Adjusted']);
    $tx = AcTransaction::create([
        'store_id' => 1,
        'transaction_date' => $today,
        'transaction_type' => 'CASH OVERAGE',
        'credit_account_id' => $account->id,
        'credit_amt' => 50.00,
        'debit_amt' => 0.00,
        'created_by' => $user->id,
    ]);
    $account->increment('balance', 50.00); // Balance = 1050
    $recon->update(['adjustment_transaction_id' => $tx->id]);

    $account->refresh();
    expect((float)$account->balance)->toBe(1050.0);

    // Delete reconciliation
    $delResponse = $this->actingAs($user)->delete(route('accounts.cash-reconciliation.delete', $recon->id));
    $delResponse->assertRedirect(route('accounts.cash-reconciliation.index'));

    // Balance should be restored to $1,000.00 and transaction deleted
    $account->refresh();
    expect((float)$account->balance)->toBe(1000.0);
    expect(AcTransaction::find($tx->id))->not->toBeNull(); // Non-destructive: reversal row remains (CASH OVERAGE REVERSAL)
    expect(CashDrawerReconciliation::find($recon->id))->not->toBeNull(); // Soft-deleted via delete_bit, not hard-deleted
});

test('10. Reports endpoint returns aggregated cash reconciliation metrics, summaries, and records', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create(['store_id' => 1, 'account_name' => 'Report Drawer', 'account_code' => 'ACC-REC-010', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-REP-001',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'reconciliation_date' => $today,
        'system_opening_balance' => 100.00,
        'opening_balance' => 100.00,
        'opening_variance' => 0.00,
        'is_initial' => false,
        'cash_sales_amount' => 500.00,
        'expected_closing_balance' => 600.00,
        'counted_amount' => 610.00,
        'variance' => 10.00,
        'status' => 'Reconciled',
    ]);

    $response = $this->actingAs($user)->getJson(route('reports.cash_reconciliation_data', [
        'account_id' => $account->id,
        'start_date' => $today,
        'end_date' => $today,
    ]));

    $response->assertOk();
    $summary = $response->json('summary');
    expect($summary['total_reconciliations'])->toBe(1);
    expect((float)$summary['total_expected'])->toBe(600.0);
    expect((float)$summary['total_counted'])->toBe(610.0);
    expect((float)$summary['total_variance'])->toBe(10.0);
    expect((float)$summary['total_overage'])->toBe(10.0);
    expect((float)$summary['total_shortage'])->toBe(0.0);
});

test('11. First-ever reconciliation initializes baseline float with is_initial=true and opening_variance=0', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'New Branch Drawer',
        'account_code' => 'ACC-REC-011',
        'balance' => 300.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Cashier enters initial starting float of $300.00 on Day 1
    // And seeds a $150 cash sale -> Expected closing is $450.00
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 150.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Cashier counts $450.00
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 300.00, // Initial float
        'counted_amount' => 450.00,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();
    expect($recon)->not->toBeNull();
    expect($recon->is_initial)->toBeTrue();
    expect((float)$recon->system_opening_balance)->toBe(0.0);
    expect((float)$recon->opening_balance)->toBe(300.0);
    expect((float)$recon->opening_variance)->toBe(0.0); // Baseline initialization
    expect((float)$recon->expected_closing_balance)->toBe(450.0); // 300 + 150
    expect((float)$recon->counted_amount)->toBe(450.0);
    expect((float)$recon->variance)->toBe(0.0);
});

test('12. Test Case A: Day 2 normal continuation accepting $1,000 suggested opening balance', function () {
    $user = getReconciliationTestUser();
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Case A Drawer',
        'account_code' => 'ACC-CASE-A',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Day 1 Close: Counted Cash = $1,000.00
    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-CASE-A-D1',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'reconciliation_date' => $yesterday,
        'system_opening_balance' => 0.00,
        'opening_balance' => 500.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'cash_sales_amount' => 500.00,
        'expected_closing_balance' => 1000.00,
        'counted_amount' => 1000.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // Day 2 API Query: System suggestion should be $1,000.00
    $calcResponse = $this->actingAs($user)->getJson(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $today,
    ]));
    $calcResponse->assertOk();
    expect((float)$calcResponse->json('data.system_opening_balance'))->toBe(1000.0);

    // Day 2 Sales: $400 cash sale
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 400.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Cashier confirms $1,000.00 opening float (matched suggestion) and counts $1,400.00 at close
    $postResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 1000.00,
        'counted_amount' => 1400.00,
    ]);

    $reconDay2 = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $today)
        ->first();

    expect($reconDay2)->not->toBeNull();
    expect($reconDay2->is_initial)->toBeFalse();
    expect((float)$reconDay2->system_opening_balance)->toBe(1000.0);
    expect((float)$reconDay2->opening_balance)->toBe(1000.0);
    expect((float)$reconDay2->opening_variance)->toBe(0.0); // Exact match
    expect((float)$reconDay2->expected_closing_balance)->toBe(1400.0); // 1000 + 400
    expect((float)$reconDay2->counted_amount)->toBe(1400.0);
    expect((float)$reconDay2->variance)->toBe(0.0); // Balanced closing
});

test('13. Test Case B: Day 2 overriding suggested opening balance from $1,000 to $200 (overnight float reduction -$800)', function () {
    $user = getReconciliationTestUser();
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Case B Drawer',
        'account_code' => 'ACC-CASE-B',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Day 1 Close: Counted Cash = $1,000.00
    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-CASE-B-D1',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'reconciliation_date' => $yesterday,
        'system_opening_balance' => 0.00,
        'opening_balance' => 500.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'cash_sales_amount' => 500.00,
        'expected_closing_balance' => 1000.00,
        'counted_amount' => 1000.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // Day 2 Sales: $400 cash sale
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 400.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Manager withdrew $800 overnight to vault, leaving $200 starting float
    // Cashier enters $200.00 opening float (overriding $1,000 suggestion)
    // Cashier counts $600.00 at closing (which matches $200 float + $400 sales = $600 expected)
    $postResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 200.00, // Overridden starting float
        'opening_notes' => 'Manager withdrew $800 to store safe overnight, starting float reset to $200.',
        'counted_amount' => 600.00,
    ]);

    $reconDay2 = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $today)
        ->first();

    expect($reconDay2)->not->toBeNull();
    expect($reconDay2->is_initial)->toBeFalse();
    expect((float)$reconDay2->system_opening_balance)->toBe(1000.0);
    expect((float)$reconDay2->opening_balance)->toBe(200.0);
    expect((float)$reconDay2->opening_variance)->toBe(-800.0); // Overnight difference flagged
    expect($reconDay2->opening_notes)->toContain('Manager withdrew $800');
    // Expected closing balance is accurately calculated from $200 base: 200 + 400 = $600.00!
    expect((float)$reconDay2->expected_closing_balance)->toBe(600.0);
    expect((float)$reconDay2->counted_amount)->toBe(600.0);
    expect((float)$reconDay2->variance)->toBe(0.0); // Shift itself was balanced!
});

test('14. Elevated ledger adjustment uses recalculated closing variance after opening float override', function () {
    $user = getReconciliationTestUser(['cash_reconciliation_adjust']);
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Adjusted Override Drawer',
        'account_code' => 'ACC-ADJ-OVR',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Day 1 Close: $500.00
    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-ADJ-D1',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'reconciliation_date' => $yesterday,
        'system_opening_balance' => 0.00,
        'opening_balance' => 500.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'expected_closing_balance' => 500.00,
        'counted_amount' => 500.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // Day 2: Float topped up to $600 (+100 opening variance), $300 cash sale -> Expected closing is $900.00
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 300.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Cashier counts $880.00 (Shortage of -$20.00 against $900 expected) and posts adjustment
    $postResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.store'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 600.00,
        'opening_notes' => 'Added $100 change float in morning',
        'counted_amount' => 880.00,
        'notes' => 'Shortage of $20 at close',
        'post_adjustment' => 1,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $today)
        ->first();

    expect($recon)->not->toBeNull();
    expect((float)$recon->opening_variance)->toBe(100.0);
    expect((float)$recon->expected_closing_balance)->toBe(900.0);
    expect((float)$recon->counted_amount)->toBe(880.0);
    expect((float)$recon->variance)->toBe(-20.0);
    expect($recon->status)->toBe('Adjusted');

    // Ledger adjustment specifically debits -$20.00 shortage
    $tx = AcTransaction::find($recon->adjustment_transaction_id);
    expect($tx)->not->toBeNull();
    expect($tx->transaction_type)->toBe('CASH SHORTAGE');
    expect((float)$tx->debit_amt)->toBe(20.0);

    // Balance decremented: $500.00 - $20.00 = $480.00
    $account->refresh();
    expect((float)$account->balance)->toBe(480.0);
});

test('15. Morning openDrawer action creates record with status Open and captured float', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Morning Open Drawer',
        'account_code' => 'ACC-OPEN-001',
        'balance' => 250.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 250.00,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $today)
        ->first();

    expect($recon)->not->toBeNull();
    expect($recon->status)->toBe('Open');
    expect($recon->opened_by)->toBe($user->id);
    expect($recon->opened_at)->not->toBeNull();
    expect($recon->closed_at)->toBeNull();
    expect((float)$recon->opening_balance)->toBe(250.0);
    $response->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));
});

test('16. Evening closeDrawer action finalizes Open drawer and sets status to Reconciled', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Evening Close Drawer',
        'account_code' => 'ACC-CLOSE-001',
        'balance' => 200.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Morning Open
    $recon = CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-TWOSTEP-01',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'opened_by' => $user->id,
        'opened_at' => Carbon::now()->subHours(8),
        'reconciliation_date' => $today,
        'system_opening_balance' => 0.00,
        'opening_balance' => 200.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'status' => 'Open',
    ]);

    // Daytime activity: $350 cash sale
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 350.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // Evening Close: Counted $550.00 (Balanced: 200 + 350 = 550)
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.close', $recon->id), [
        'counted_amount' => 550.00,
        'notes' => 'Evening shift closed on time',
    ]);

    $recon->refresh();
    expect($recon->status)->toBe('Reconciled');
    expect($recon->closed_by)->toBe($user->id);
    expect($recon->closed_at)->not->toBeNull();
    expect((float)$recon->expected_closing_balance)->toBe(550.0);
    expect((float)$recon->counted_amount)->toBe(550.0);
    expect((float)$recon->variance)->toBe(0.0);
    $response->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));
});

test('17. Strict Q2 Restriction: A different user cannot close a drawer they did not open', function () {
    $cashierA = getReconciliationTestUser();
    $cashierB = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Restricted Close Drawer',
        'account_code' => 'ACC-RESTRICT-01',
        'balance' => 150.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Cashier A opens drawer in morning
    $recon = CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-RESTRICT-01',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $cashierA->id,
        'opened_by' => $cashierA->id,
        'opened_at' => Carbon::now()->subHours(8),
        'reconciliation_date' => $today,
        'system_opening_balance' => 0.00,
        'opening_balance' => 150.00,
        'opening_variance' => 0.00,
        'is_initial' => true,
        'status' => 'Open',
    ]);

    // Cashier B attempts to access close form -> redirected with unauthorized error
    $formResponse = $this->actingAs($cashierB)->get(route('accounts.cash-reconciliation.close-form', $recon->id));
    $formResponse->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));
    $formResponse->assertSessionHas('error');

    // Cashier B attempts to submit closeDrawer -> rejected with unauthorized error
    $closeResponse = $this->actingAs($cashierB)->post(route('accounts.cash-reconciliation.close', $recon->id), [
        'counted_amount' => 150.00,
    ]);
    $closeResponse->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));
    $closeResponse->assertSessionHas('error');

    // Drawer remains Open
    $recon->refresh();
    expect($recon->status)->toBe('Open');
    expect($recon->closed_at)->toBeNull();
});

test('18. Cannot close an already-closed cash drawer', function () {
    $user = getReconciliationTestUser();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Double Close Prevention Drawer',
        'account_code' => 'ACC-DBL-CLOSE',
        'balance' => 100.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $recon = CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-ALREADY-CLOSED',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'opened_by' => $user->id,
        'opened_at' => Carbon::now()->subHours(8),
        'closed_by' => $user->id,
        'closed_at' => Carbon::now(),
        'reconciliation_date' => $today,
        'system_opening_balance' => 0.00,
        'opening_balance' => 100.00,
        'expected_closing_balance' => 100.00,
        'counted_amount' => 100.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // Attempt to close again
    $response = $this->actingAs($user)->post(route('accounts.cash-reconciliation.close', $recon->id), [
        'counted_amount' => 100.00,
    ]);

    $response->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));
    $response->assertSessionHas('error');
});

test('19. Two-step flow end-to-end for Test Case B with overnight override and evening close', function () {
    $user = getReconciliationTestUser();
    $yesterday = Carbon::yesterday()->format('Y-m-d');
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Two-Step Case B Drawer',
        'account_code' => 'ACC-2STEP-B',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Day 1 Close: $1,000.00
    CashDrawerReconciliation::create([
        'store_id' => 1,
        'reconciliation_code' => 'REC-2STEP-D1',
        'store_id' => 1,
        'account_id' => $account->id,
        'user_id' => $user->id,
        'opened_by' => $user->id,
        'closed_by' => $user->id,
        'reconciliation_date' => $yesterday,
        'system_opening_balance' => 0.00,
        'opening_balance' => 500.00,
        'expected_closing_balance' => 1000.00,
        'counted_amount' => 1000.00,
        'variance' => 0.00,
        'status' => 'Reconciled',
    ]);

    // STEP 1 (Morning): Cashier opens drawer with $200 starting float (-$800 overnight vault drop)
    $openResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'opening_balance' => 200.00,
        'opening_notes' => 'Overnight manager vault drop of $800, starting float set to $200',
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $today)
        ->first();

    expect($recon)->not->toBeNull();
    expect($recon->status)->toBe('Open');
    expect((float)$recon->system_opening_balance)->toBe(1000.0);
    expect((float)$recon->opening_balance)->toBe(200.0);
    expect((float)$recon->opening_variance)->toBe(-800.0);

    // DAYTIME ACTIVITY: $400 cash sale
    DbSalePayment::create([
        'store_id' => 1,
        'account_id' => $account->id,
        'payment_type' => 'Cash',
        'payment' => 400.00,
        'payment_date' => $today,
        'created_by' => $user->id,
    ]);

    // STEP 2 (Evening): Cashier counts $600.00 at closing and finalizes reconciliation
    $closeResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.close', $recon->id), [
        'counted_amount' => 600.00,
        'notes' => 'Evening count verified and balanced against $200 starting float + $400 sales',
    ]);

    $recon->refresh();
    expect($recon->status)->toBe('Reconciled');
    expect((float)$recon->expected_closing_balance)->toBe(600.0);
    expect((float)$recon->counted_amount)->toBe(600.0);
    expect((float)$recon->variance)->toBe(0.0); // Shift itself was balanced!
    expect($recon->closed_at)->not->toBeNull();
});

test('it rejects opening a new drawer on a different date when another drawer is already open for the same account', function () {
    $user = getReconciliationTestUser();
    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Duplicate Open Test Drawer',
        'account_code' => 'ACC-DUP-OPEN',
        'balance' => 500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $day1 = '2026-08-20';
    $day2 = '2026-08-21';

    // Step 1: Open Day 1 drawer
    $openRes1 = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $day1,
        'account_id' => $account->id,
        'opening_balance' => 500.00,
    ]);

    $openRes1->assertRedirect();
    $day1Recon = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $day1)
        ->first();

    expect($day1Recon)->not->toBeNull();
    expect($day1Recon->status)->toBe('Open');

    // Step 2: Attempt to open Day 2 drawer while Day 1 is still Open
    $openRes2 = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $day2,
        'account_id' => $account->id,
        'opening_balance' => 500.00,
    ]);

    $openRes2->assertSessionHas('error');
    $errorMessage = session('error');
    expect($errorMessage)->toContain('already open from');
    expect($errorMessage)->toContain($day1Recon->reconciliation_code);

    // Ensure no Day 2 record was created
    $day2Recon = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $day2)
        ->first();
    expect($day2Recon)->toBeNull();

    // Step 3: calculateExpected returns active open drawer metadata
    $calcRes = $this->actingAs($user)->get(route('accounts.cash-reconciliation.calculate-expected', [
        'account_id' => $account->id,
        'reconciliation_date' => $day2,
    ]));

    $calcRes->assertOk();
    $calcJson = $calcRes->json();
    expect($calcJson['data']['open_drawer'])->not->toBeNull();
    expect($calcJson['data']['open_drawer']['id'])->toBe($day1Recon->id);
    expect($calcJson['data']['open_drawer']['code'])->toBe($day1Recon->reconciliation_code);

    // Step 4: Close Day 1 drawer
    $closeRes = $this->actingAs($user)->post(route('accounts.cash-reconciliation.close', $day1Recon->id), [
        'counted_amount' => 500.00,
    ]);
    $closeRes->assertRedirect();
    expect($day1Recon->fresh()->status)->toBe('Reconciled');

    // Step 5: Now Day 2 drawer opens successfully
    $openRes3 = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $day2,
        'account_id' => $account->id,
        'opening_balance' => 500.00,
    ]);

    $openRes3->assertRedirect();
    $day2ReconCreated = CashDrawerReconciliation::where('account_id', $account->id)
        ->whereDate('reconciliation_date', $day2)
        ->first();

    expect($day2ReconCreated)->not->toBeNull();
    expect($day2ReconCreated->status)->toBe('Open');
});

