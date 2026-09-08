<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\AcAccount;
use App\Models\DbWarehouse;
use App\Models\DbCustomer;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbSalesReturn;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbExpense;
use App\Models\DbExpenseCategory;
use App\Models\CashDrawerReconciliation;
use Carbon\Carbon;

function getUiTestSuperAdmin(): User {
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'UI Verification Store',
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
        'permissions' => [
            'accounts_view',
            'cash_reconciliation_view',
            'cash_reconciliation_add',
            'cash_reconciliation_adjust',
            'cash_reconciliation_delete',
            'cash_reconciliation_report',
            'reports_view',
        ],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('UI Flow 1: Open form renders morning float confirmation view', function () {
    $user = getUiTestSuperAdmin();

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main POS Drawer',
        'account_code' => 'ACC-UI-001',
        'balance' => 1000.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);

    $response = $this->actingAs($user)->get(route('accounts.cash-reconciliation.open-form'));

    $response->assertOk();
    $response->assertSee('Open Cash Drawer');
    $response->assertSee('Starting Cash Float Confirmation');
    $response->assertSee('Physical Opening Cash Confirmed');
    $response->assertSee('System Auto-Suggestion');
    $response->assertSee('Open Cash Drawer');
});

test('UI Flow 2: Form submission opens drawer, displays live estimate show page, and enables close flow', function () {
    $user = getUiTestSuperAdmin();
    $today = Carbon::today()->format('Y-m-d');

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Showroom Cash',
        'account_code' => 'ACC-UI-002',
        'balance' => 1500.00,
        'status' => 1,
        'delete_bit' => 0,
    ]);
    $warehouse = DbWarehouse::create(['warehouse_name' => 'Showroom WH', 'status' => 1]);
    $customer = DbCustomer::create(['customer_name' => 'Walk-in', 'customer_code' => 'CUST-UI-1', 'status' => 1]);

    // 1. Morning Open: Open drawer with $200 float
    $openResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.open'), [
        'reconciliation_date' => $today,
        'account_id' => $account->id,
        'warehouse_id' => $warehouse->id,
        'opening_balance' => 200.00,
    ]);

    $recon = CashDrawerReconciliation::where('account_id', $account->id)->first();
    expect($recon)->not->toBeNull();
    $openResponse->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));

    // 2. View show page for Open drawer: live running estimate banner
    $showOpenResponse = $this->actingAs($user)->get(route('accounts.cash-reconciliation.show', $recon->id));
    $showOpenResponse->assertOk();
    $showOpenResponse->assertSee('Drawer Currently Open and Active');
    $showOpenResponse->assertSee('Live Running Estimate');
    $showOpenResponse->assertSee('Close Drawer (Evening Count)');

    // 3. Daytime Activity: Seed Sale ($750), Refund ($80), Expense ($45) -> Live Expected: 200 + 750 - 80 - 45 = $825.00
    $sale = DbSale::create(['store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id, 'sales_code' => 'SA-UI-01', 'sales_date' => $today, 'grand_total' => 750, 'paid_amount' => 750, 'payment_status' => 'Paid']);
    DbSalePayment::create(['store_id' => 1, 'sales_id' => $sale->id, 'account_id' => $account->id, 'payment_type' => 'Cash', 'payment' => 750.00, 'payment_date' => $today, 'created_by' => $user->id]);

    $ret = DbSalesReturn::create(['store_id' => 1, 'sales_id' => $sale->id, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id, 'return_code' => 'RTN-UI-01', 'return_date' => $today, 'grand_total' => 80, 'paid_amt' => 80]);
    DbSalesPaymentReturn::create(['store_id' => 1, 'sales_id' => $sale->id, 'return_id' => $ret->id, 'account_id' => $account->id, 'payment_type' => 'Cash', 'payment' => 80.00, 'payment_date' => $today, 'created_by' => $user->id]);

    $cat = DbExpenseCategory::create(['category_name' => 'Supplies', 'status' => 1]);
    DbExpense::create(['store_id' => 1, 'expense_code' => 'EXP-UI-01', 'category_id' => $cat->id, 'expense_date' => $today, 'expense_for' => 'Cleaning', 'expense_amt' => 45.00, 'payment_type' => 'Cash', 'account_id' => $account->id, 'created_by' => $user->id]);

    // 4. Access Evening Close Form
    $closeFormResponse = $this->actingAs($user)->get(route('accounts.cash-reconciliation.close-form', $recon->id));
    $closeFormResponse->assertOk();
    $closeFormResponse->assertSee('Close and Reconcile Cash Drawer (Evening)');
    $closeFormResponse->assertSee('825.00'); // Expected

    // 5. Submit Evening Close with $820 counted (-$5 shortage)
    $closePostResponse = $this->actingAs($user)->post(route('accounts.cash-reconciliation.close', $recon->id), [
        'counted_amount' => 820.00,
        'denominations' => ['c100' => 8, 'c20' => 1],
        'notes' => 'Evening count: verified $5 shortage with cashier',
        'post_adjustment' => 1,
    ]);
    $closePostResponse->assertRedirect(route('accounts.cash-reconciliation.show', $recon->id));

    // 6. View finalized closing slip
    $showFinalResponse = $this->actingAs($user)->get(route('accounts.cash-reconciliation.show', $recon->id));
    $showFinalResponse->assertOk();
    $showFinalResponse->assertSee('Daily Cash Drawer Closing Slip');
    $showFinalResponse->assertSee('Main Showroom Cash');
    $showFinalResponse->assertSee('200.00'); // Confirmed Starting Float
    $showFinalResponse->assertSee('750.00'); // Sales
    $showFinalResponse->assertSee('80.00');  // Refund
    $showFinalResponse->assertSee('45.00');  // Expense
    $showFinalResponse->assertSee('825.00'); // Expected
    $showFinalResponse->assertSee('820.00'); // Counted
    $showFinalResponse->assertSee('5.00');   // Variance
    $showFinalResponse->assertSee('Evening count: verified $5 shortage with cashier');
});

test('UI Flow 3: Reports view renders KPI cards and data table', function () {
    $user = getUiTestSuperAdmin();

    $response = $this->actingAs($user)->get(route('reports.cash_reconciliation'));
    $response->assertOk();
    $response->assertSee('Cash Reconciliation Report');
    $response->assertSee('Total Reconciled');
    $response->assertSee('Expected Cash');
    $response->assertSee('Counted Cash');
    $response->assertSee('Net Variance');
});
