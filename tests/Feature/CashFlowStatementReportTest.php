<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbSupplier;
use App\Models\DbWarehouse;
use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\DbPurchase;
use Carbon\Carbon;

function getCashFlowTestUser(array $extraPerms = []): User {
    store_settings(true);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Cash Flow Test Store',
        'status' => 1,
        'mobile' => '01700000000',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    $permissions = array_unique(array_merge([
        'reports_view',
        'reports_cash_flow_view',
        'accounts_view',
    ], $extraPerms));

    DbPermission::updateOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => $permissions,
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. Cash Flow Statement page renders with authentication and permissions', function () {
    $user = getCashFlowTestUser();

    $response = $this->actingAs($user)->get(route('reports.cash_flow'));
    $response->assertStatus(200);
    $response->assertSee('Cash Flow Statement');
    $response->assertSee('Statement of Cash Flows');
    $response->assertSee('Account-Wise Cash Movement Breakdown');
});

test('2. Cash Flow Statement API returns structured JSON and exact mathematical reconciliation for all transaction types', function () {
    $user = getCashFlowTestUser();

    // Create 2 active accounts
    $cashAccount = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer',
        'account_code' => 'ACC-CF-001',
        'balance' => 8850.00, // Resulting balance after all transactions
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $bankAccount = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Prime Commercial Bank',
        'account_code' => 'ACC-CF-002',
        'balance' => 83500.00, // Resulting balance after all transactions
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $category = DbExpenseCategory::create([
        'store_id' => 1,
        'category_name' => 'Utilities & Rent',
        'status' => 1,
    ]);

    $startDate = '2026-08-01';
    $endDate = '2026-08-31';

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'John Customer',
        'customer_code' => 'CUST-001',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Main Warehouse',
        'status' => 1,
    ]);

    $sale1 = DbSale::create([
        'store_id' => 1,
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_code' => 'SA-00001',
        'sales_date' => '2026-08-05',
        'grand_total' => 5000.00,
        'paid_amount' => 5000.00,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    $sale2 = DbSale::create([
        'store_id' => 1,
        'customer_id' => $customer->id,
        'warehouse_id' => $warehouse->id,
        'sales_code' => 'SA-00002',
        'sales_date' => '2026-08-10',
        'grand_total' => 12000.00,
        'paid_amount' => 12000.00,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    // 1. Sales Payment into Cash Account: $5,000
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'SP-001',
        'transaction_date' => '2026-08-05',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $cashAccount->id,
        'credit_amt' => 5000.00,
        'debit_amt' => 0.00,
        'note' => 'POS Cash Sales',
        'created_by' => $user->id,
    ]);
    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale1->id,
        'customer_id' => $customer->id,
        'account_id' => $cashAccount->id,
        'payment_date' => '2026-08-05',
        'payment' => 5000.00,
        'payment_type' => 'Cash',
    ]);

    // 2. Sales Payment into Bank Account: $12,000
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'SP-002',
        'transaction_date' => '2026-08-10',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $bankAccount->id,
        'credit_amt' => 12000.00,
        'debit_amt' => 0.00,
        'note' => 'Card Online Sales',
        'created_by' => $user->id,
    ]);
    DbSalePayment::create([
        'store_id' => 1,
        'sales_id' => $sale2->id,
        'customer_id' => $customer->id,
        'account_id' => $bankAccount->id,
        'payment_date' => '2026-08-10',
        'payment' => 12000.00,
        'payment_type' => 'Bank Transfer',
    ]);

    // 3. Sales Return Refund from Cash Account: $1,500
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'SR-001',
        'transaction_date' => '2026-08-12',
        'transaction_type' => 'SALES RETURN REFUND',
        'debit_account_id' => $cashAccount->id,
        'debit_amt' => 1500.00,
        'credit_amt' => 0.00,
        'note' => 'Customer Refund',
        'created_by' => $user->id,
    ]);

    // 4. Operating Expense from Cash Account: $800
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'EXP-001',
        'transaction_date' => '2026-08-15',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $cashAccount->id,
        'debit_amt' => 800.00,
        'credit_amt' => 0.00,
        'note' => 'Office Refreshment',
        'created_by' => $user->id,
    ]);
    DbExpense::create([
        'store_id' => 1,
        'category_id' => $category->id,
        'account_id' => $cashAccount->id,
        'expense_date' => '2026-08-15',
        'expense_amt' => 800.00,
        'payment_type' => 'Cash',
        'expense_for' => 'Refreshment',
    ]);

    // 5. Operating Expense from Bank Account: $2,500
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'EXP-002',
        'transaction_date' => '2026-08-18',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $bankAccount->id,
        'debit_amt' => 2500.00,
        'credit_amt' => 0.00,
        'note' => 'Electricity Bill',
        'created_by' => $user->id,
    ]);
    DbExpense::create([
        'store_id' => 1,
        'category_id' => $category->id,
        'account_id' => $bankAccount->id,
        'expense_date' => '2026-08-18',
        'expense_amt' => 2500.00,
        'payment_type' => 'Bank Transfer',
        'expense_for' => 'Utilities',
    ]);

    // 6. External Capital Deposit into Bank Account: $20,000
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'DEP-001',
        'transaction_date' => '2026-08-20',
        'transaction_type' => 'DEPOSIT',
        'credit_account_id' => $bankAccount->id,
        'credit_amt' => 20000.00,
        'debit_amt' => 0.00,
        'note' => 'Owner Capital Inflow',
        'created_by' => $user->id,
    ]);

    // 7. Internal Transfer from Cash Account to Bank Account: $4,000
    // Debit from Cash Account
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TRF-001A',
        'transaction_date' => '2026-08-22',
        'transaction_type' => 'TRANSFER',
        'debit_account_id' => $cashAccount->id,
        'credit_account_id' => $bankAccount->id,
        'debit_amt' => 4000.00,
        'credit_amt' => 0.00,
        'note' => 'Cash to Bank Transfer (From)',
        'created_by' => $user->id,
    ]);
    // Credit to Bank Account
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TRF-001B',
        'transaction_date' => '2026-08-22',
        'transaction_type' => 'TRANSFER',
        'debit_account_id' => $cashAccount->id,
        'credit_account_id' => $bankAccount->id,
        'debit_amt' => 0.00,
        'credit_amt' => 4000.00,
        'note' => 'Cash to Bank Transfer (To)',
        'created_by' => $user->id,
    ]);

    // 8. Cash Drawer Adjustments (Option C hybrid variance)
    // Overage: $200
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'ADJ-001',
        'transaction_date' => '2026-08-24',
        'transaction_type' => 'CASH OVERAGE',
        'credit_account_id' => $cashAccount->id,
        'credit_amt' => 200.00,
        'debit_amt' => 0.00,
        'note' => 'Drawer Cash Overage',
        'created_by' => $user->id,
    ]);
    // Shortage: $50
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'ADJ-002',
        'transaction_date' => '2026-08-25',
        'transaction_type' => 'CASH SHORTAGE',
        'debit_account_id' => $cashAccount->id,
        'debit_amt' => 50.00,
        'credit_amt' => 0.00,
        'note' => 'Drawer Cash Shortage',
        'created_by' => $user->id,
    ]);

    // --- EXECUTE CONSOLIDATED REPORT API ---
    $response = $this->actingAs($user)->getJson(route('reports.cash_flow_data', [
        'start_date' => $startDate,
        'end_date' => $endDate,
    ]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['status'])->toBe('success');

    // 1. Check Operating Activities
    expect((float)$data['operating_activities']['sales_payments'])->toBe(17000.00); // 5000 + 12000
    expect((float)$data['operating_activities']['sales_refunds'])->toBe(1500.00);
    expect((float)$data['operating_activities']['expenses'])->toBe(3300.00); // 800 + 2500
    expect((float)$data['operating_activities']['cash_overage'])->toBe(200.00);
    expect((float)$data['operating_activities']['cash_shortage'])->toBe(50.00);
    expect((float)$data['operating_activities']['net_drawer_adjustment'])->toBe(150.00); // 200 - 50
    expect((float)$data['operating_activities']['net_operating_cash_flow'])->toBe(12350.00); // 17000 - 1500 - 3300 + 150

    // 2. Check Financing Activities & Internal Transfers
    expect((float)$data['financing_activities']['deposits'])->toBe(20000.00);
    expect((float)$data['financing_activities']['transfers_in'])->toBe(4000.00);
    expect((float)$data['financing_activities']['transfers_out'])->toBe(4000.00);
    expect((float)$data['financing_activities']['net_internal_transfers'])->toBe(0.00); // Net 0 for consolidated
    expect((float)$data['financing_activities']['net_financing_cash_flow'])->toBe(20000.00);

    // 3. Check Summary & Exact Reconciliation
    // Opening Balance derived dynamically:
    // Cash opening = 8850 - (5000 + 200 - 1500 - 800 - 4000 - 50) = 8850 - (-1150) = 10000.00
    // Bank opening = 83500 - (12000 + 20000 + 4000 - 2500) = 83500 - 33500 = 50000.00
    // Total opening = 60000.00
    expect((float)$data['summary']['opening_balance'])->toBe(60000.00);
    expect((float)$data['summary']['total_inflow'])->toBe(41200.00); // 17000 + 200 + 20000 + 4000
    expect((float)$data['summary']['total_outflow'])->toBe(8850.00); // 1500 + 3300 + 50 + 4000
    expect((float)$data['summary']['net_cash_flow'])->toBe(32350.00); // 12350 + 20000 = 32350
    expect((float)$data['summary']['closing_balance'])->toBe(92350.00); // 60000 + 32350 = 92350
    expect($data['summary']['is_reconciled'])->toBeTrue();
    expect((float)$data['summary']['reconciliation_diff'])->toBe(0.00);

    // 4. Check Account Breakdown
    $breakdown = collect($data['account_breakdown']);
    $cashRow = $breakdown->firstWhere('id', $cashAccount->id);
    $bankRow = $breakdown->firstWhere('id', $bankAccount->id);

    expect((float)$cashRow['opening_balance'])->toBe(10000.00);
    expect((float)$cashRow['total_inflow'])->toBe(5200.00);
    expect((float)$cashRow['total_outflow'])->toBe(6350.00);
    expect((float)$cashRow['net_change'])->toBe(-1150.00);
    expect((float)$cashRow['closing_balance'])->toBe(8850.00);

    expect((float)$bankRow['opening_balance'])->toBe(50000.00);
    expect((float)$bankRow['total_inflow'])->toBe(36000.00);
    expect((float)$bankRow['total_outflow'])->toBe(2500.00);
    expect((float)$bankRow['net_change'])->toBe(33500.00);
    expect((float)$bankRow['closing_balance'])->toBe(83500.00);

    // Sum of breakdown closing balances equals consolidated closing balance
    expect(round((float)$cashRow['closing_balance'] + (float)$bankRow['closing_balance'], 2))->toBe(92350.00);
});

test('3. Single Account scope filter reflects real transfer outflow and maintains exact reconciliation', function () {
    $user = getCashFlowTestUser();

    $cashAccount = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer Scope',
        'account_code' => 'ACC-CF-003',
        'balance' => 4500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $bankAccount = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Secondary Account',
        'account_code' => 'ACC-CF-004',
        'balance' => 20000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $startDate = '2026-08-01';
    $endDate = '2026-08-31';

    // Inflow to Cash: 2000
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'SP-003',
        'transaction_date' => '2026-08-05',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $cashAccount->id,
        'credit_amt' => 2000.00,
        'debit_amt' => 0.00,
        'created_by' => $user->id,
    ]);

    // Transfer Out of Cash: 1500 (debit cash, credit bank)
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TRF-002A',
        'transaction_date' => '2026-08-10',
        'transaction_type' => 'TRANSFER',
        'debit_account_id' => $cashAccount->id,
        'credit_account_id' => $bankAccount->id,
        'debit_amt' => 1500.00,
        'credit_amt' => 0.00,
        'created_by' => $user->id,
    ]);
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TRF-002B',
        'transaction_date' => '2026-08-10',
        'transaction_type' => 'TRANSFER',
        'debit_account_id' => $cashAccount->id,
        'credit_account_id' => $bankAccount->id,
        'debit_amt' => 0.00,
        'credit_amt' => 1500.00,
        'created_by' => $user->id,
    ]);

    // Query API filtered strictly to Cash Account
    $response = $this->actingAs($user)->getJson(route('reports.cash_flow_data', [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'account_id' => $cashAccount->id,
    ]));

    $response->assertStatus(200);
    $data = $response->json();

    expect($data['status'])->toBe('success');
    expect($data['period']['is_consolidated'])->toBeFalse();

    // Opening = 4500 - (2000 - 1500) = 4000
    expect((float)$data['summary']['opening_balance'])->toBe(4000.00);
    expect((float)$data['operating_activities']['sales_payments'])->toBe(2000.00);
    expect((float)$data['financing_activities']['transfers_out'])->toBe(1500.00);
    expect((float)$data['financing_activities']['net_internal_transfers'])->toBe(-1500.00);
    expect((float)$data['summary']['net_cash_flow'])->toBe(500.00); // 2000 - 1500 = +500
    expect((float)$data['summary']['closing_balance'])->toBe(4500.00); // 4000 + 500 = 4500
    expect($data['summary']['is_reconciled'])->toBeTrue();
});

test('4. Date boundary windowing correctly calculates historical opening and excludes out-of-period transactions', function () {
    $user = getCashFlowTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Timeline Cash Account',
        'account_code' => 'ACC-CF-005',
        'balance' => 15000.00, // Today's balance
        'status' => 1,
        'delete_bit' => 0,
    ]);

    // Period we want to inspect: August 10 to August 20
    $startDate = '2026-08-10';
    $endDate = '2026-08-20';

    // 1. Transaction BEFORE period start (Aug 05): +$3,000
    // Should be part of Opening Balance on Aug 10, but NOT in period inflow.
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TX-PRE',
        'transaction_date' => '2026-08-05',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $account->id,
        'credit_amt' => 3000.00,
        'debit_amt' => 0.00,
        'created_by' => $user->id,
    ]);

    // 2. Transaction IN period (Aug 15): +$5,000 sales, -$1,000 expense
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TX-IN-1',
        'transaction_date' => '2026-08-15',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $account->id,
        'credit_amt' => 5000.00,
        'debit_amt' => 0.00,
        'created_by' => $user->id,
    ]);
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TX-IN-2',
        'transaction_date' => '2026-08-18',
        'transaction_type' => 'EXPENSE',
        'debit_account_id' => $account->id,
        'debit_amt' => 1000.00,
        'credit_amt' => 0.00,
        'created_by' => $user->id,
    ]);

    // 3. Transaction AFTER period end (Aug 25): +$8,000
    // Occurs after Aug 20, so must NOT affect Aug 20 closing balance.
    AcTransaction::create([
        'store_id' => 1,
        'payment_code' => 'TX-POST',
        'transaction_date' => '2026-08-25',
        'transaction_type' => 'SALES PAYMENT',
        'credit_account_id' => $account->id,
        'credit_amt' => 8000.00,
        'debit_amt' => 0.00,
        'created_by' => $user->id,
    ]);

    // Balance today = 15000.
    // Post-Aug 10 movements: +5000 -1000 +8000 = +12000.
    // Opening balance at Aug 10 = 15000 - 12000 = 3000.
    // In-period movements (Aug 10 - Aug 20): +5000 - 1000 = +4000.
    // Closing balance at Aug 20 = 3000 + 4000 = 7000.

    $response = $this->actingAs($user)->getJson(route('reports.cash_flow_data', [
        'start_date' => $startDate,
        'end_date' => $endDate,
        'account_id' => $account->id,
    ]));

    $response->assertStatus(200);
    $data = $response->json();

    expect((float)$data['summary']['opening_balance'])->toBe(3000.00);
    expect((float)$data['operating_activities']['sales_payments'])->toBe(5000.00);
    expect((float)$data['operating_activities']['expenses'])->toBe(1000.00);
    expect((float)$data['summary']['net_cash_flow'])->toBe(4000.00);
    expect((float)$data['summary']['closing_balance'])->toBe(7000.00);
    expect($data['summary']['is_reconciled'])->toBeTrue();
});

test('5. Empty transaction period returns exact matching opening and closing balances with zero net flow', function () {
    $user = getCashFlowTestUser();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Static Account',
        'account_code' => 'ACC-CF-006',
        'balance' => 12500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $response = $this->actingAs($user)->getJson(route('reports.cash_flow_data', [
        'start_date' => '2025-01-01',
        'end_date' => '2025-01-31',
        'account_id' => $account->id,
    ]));

    $response->assertStatus(200);
    $data = $response->json();

    expect((float)$data['summary']['opening_balance'])->toBe(12500.00);
    expect((float)$data['summary']['total_inflow'])->toBe(0.00);
    expect((float)$data['summary']['total_outflow'])->toBe(0.00);
    expect((float)$data['summary']['net_cash_flow'])->toBe(0.00);
    expect((float)$data['summary']['closing_balance'])->toBe(12500.00);
    expect($data['summary']['is_reconciled'])->toBeTrue();
});

test('6. Unauthenticated request redirects to login', function () {
    $response = $this->get(route('reports.cash_flow'));
    $response->assertRedirect(route('login'));

    $jsonResponse = $this->getJson(route('reports.cash_flow_data'));
    $jsonResponse->assertStatus(401);
});

