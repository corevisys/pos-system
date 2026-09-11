<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\CashDrawerReconciliation;
use App\Models\DbExpense;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbSalePayment;
use App\Models\DbSalesPaymentReturn;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashReconciliationFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $store1;
    protected $store2;
    protected $store1User;
    protected $store2User;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store1 = DbStore::create([
            'id' => 1,
            'store_name' => 'Store 1',
            'status' => 1,
            'mobile' => '1111111111',
        ]);

        $this->store2 = DbStore::create([
            'id' => 2,
            'store_name' => 'Store 2',
            'status' => 1,
            'mobile' => '2222222222',
        ]);

        $role1 = DbRole::create([
            'role_name' => 'Store 1 Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view',
                'cash_reconciliation_view',
                'cash_reconciliation_add',
                'cash_reconciliation_adjust',
                'cash_reconciliation_delete',
                'cash_reconciliation_report',
            ],
        ]);

        $this->store1User = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role1->id,
            'name' => 'Alice Manager',
            'email' => 'alice@store1.com',
        ]);

        $role2 = DbRole::create([
            'role_name' => 'Store 2 Admin',
            'status' => 1,
            'store_id' => 2,
        ]);

        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => [
                'accounts_view',
                'cash_reconciliation_view',
                'cash_reconciliation_add',
                'cash_reconciliation_adjust',
                'cash_reconciliation_delete',
                'cash_reconciliation_report',
            ],
        ]);

        $this->store2User = User::factory()->create([
            'store_id' => 2,
            'role_id' => $role2->id,
            'name' => 'Bob Manager',
            'email' => 'bob@store2.com',
        ]);
    }

    /**
     * Item 1.1: Multi-Store Scoping — Assert zero Store 2 records across all 9 query surfaces.
     */
    public function test_item_1_1_multi_store_scoping_across_all_surfaces()
    {
        // 1. Warehouses & Accounts
        $wh1 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'S1 Main Warehouse', 'status' => 1]);
        $wh2 = DbWarehouse::create(['store_id' => 2, 'warehouse_name' => 'S2 Secret Warehouse', 'status' => 1]);
        $acc1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Cash Drawer', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $acc2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret Drawer', 'balance' => 2000, 'status' => 1, 'delete_bit' => 0]);

        // 2. Reconciliations
        $recon1 = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-00001',
            'store_id' => 1,
            'warehouse_id' => $wh1->id,
            'account_id' => $acc1->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-01',
            'opening_balance' => 100.00,
            'expected_closing_balance' => 100.00,
            'counted_amount' => 100.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        $recon2 = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-00002',
            'store_id' => 2,
            'warehouse_id' => $wh2->id,
            'account_id' => $acc2->id,
            'user_id' => $this->store2User->id,
            'opened_by' => $this->store2User->id,
            'reconciliation_date' => '2026-09-01',
            'opening_balance' => 999.00,
            'expected_closing_balance' => 999.00,
            'counted_amount' => 999.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        // Surface 1: index() base query
        $resIndex = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index'));
        $resIndex->assertOk();
        $indexIds = $resIndex->viewData('reconciliations')->pluck('id')->all();
        $this->assertContains($recon1->id, $indexIds, 'Surface 1: Store 1 reconciliation must be visible.');
        $this->assertNotContains($recon2->id, $indexIds, 'Surface 1: Store 2 reconciliation must be excluded.');

        // Surface 2: index() dropdowns
        $whNames = $resIndex->viewData('warehouses')->pluck('warehouse_name')->all();
        $accNames = $resIndex->viewData('accounts')->pluck('account_name')->all();
        $this->assertContains('S1 Main Warehouse', $whNames);
        $this->assertNotContains('S2 Secret Warehouse', $whNames, 'Surface 2: Store 2 warehouse excluded from index dropdown.');
        $this->assertContains('S1 Cash Drawer', $accNames);
        $this->assertNotContains('S2 Secret Drawer', $accNames, 'Surface 2: Store 2 account excluded from index dropdown.');

        // Surface 3: openForm() dropdowns
        $resOpenForm = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.open-form'));
        $resOpenForm->assertOk();
        $openWhNames = $resOpenForm->viewData('warehouses')->pluck('warehouse_name')->all();
        $openAccNames = $resOpenForm->viewData('accounts')->pluck('account_name')->all();
        $this->assertNotContains('S2 Secret Warehouse', $openWhNames, 'Surface 3: Store 2 warehouse excluded from open form.');
        $this->assertNotContains('S2 Secret Drawer', $openAccNames, 'Surface 3: Store 2 account excluded from open form.');

        // Surface 4: openDrawer() active-open-drawer check isolation
        // Open drawer in Store 2 on same date
        $openDrawerS2 = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-00003',
            'store_id' => 2,
            'account_id' => $acc2->id,
            'user_id' => $this->store2User->id,
            'opened_by' => $this->store2User->id,
            'reconciliation_date' => '2026-09-02',
            'opening_balance' => 50.00,
            'status' => 'Open',
            'delete_bit' => 0,
        ]);
        // As Store 1 user, opening a drawer for S1 Cash Drawer on 2026-09-02 must NOT be blocked by S2's open drawer
        $openRes = $this->actingAs($this->store1User)->post(route('accounts.cash-reconciliation.open'), [
            'reconciliation_date' => '2026-09-02',
            'account_id' => $acc1->id,
            'opening_balance' => 100.00,
        ]);
        $openRes->assertRedirect();
        $this->assertDatabaseHas('cash_drawer_reconciliations', [
            'store_id' => 1,
            'account_id' => $acc1->id,
            'status' => 'Open',
        ]);

        // Surface 5: openDrawer() duplicate date check isolation
        // Attempting to open same date/account in Store 1 now fails because S1 drawer exists
        $dupRes = $this->actingAs($this->store1User)->post(route('accounts.cash-reconciliation.open'), [
            'reconciliation_date' => '2026-09-02',
            'account_id' => $acc1->id,
            'opening_balance' => 100.00,
        ]);
        $dupRes->assertSessionHas('error');

        // Close S1 drawer so we can test breakdown calculations
        $s1OpenDrawer = CashDrawerReconciliation::where('store_id', 1)->where('status', 'Open')->first();
        $s1OpenDrawer->update(['status' => 'Reconciled', 'counted_amount' => 100.00]);

        // Surfaces 6-9: getCalculationBreakdown() isolated data sources
        // Seed Customers & Store 2 cross-store records for date 2026-09-03
        $cust1 = \App\Models\DbCustomer::create(['store_id' => 1, 'customer_name' => 'Cust 1', 'status' => 1]);
        $cust2 = \App\Models\DbCustomer::create(['store_id' => 2, 'customer_name' => 'Cust 2', 'status' => 1]);
        $saleS1 = DbSale::create(['store_id' => 1, 'warehouse_id' => $wh1->id, 'sales_date' => '2026-09-03', 'customer_id' => $cust1->id]);
        $saleS2 = DbSale::create(['store_id' => 2, 'warehouse_id' => $wh2->id, 'sales_date' => '2026-09-03', 'customer_id' => $cust2->id]);

        // Cash sales: S1 = 200, S2 = 9000 (leaked if unscoped)
        DbSalePayment::create(['store_id' => 1, 'sale_id' => $saleS1->id, 'payment_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'payment' => 200.00]);
        DbSalePayment::create(['store_id' => 2, 'sale_id' => $saleS2->id, 'payment_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'payment' => 9000.00]);

        // Cash refunds: S1 = 30, S2 = 5000
        DbSalesPaymentReturn::create(['store_id' => 1, 'sale_id' => $saleS1->id, 'payment_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'payment' => 30.00]);
        DbSalesPaymentReturn::create(['store_id' => 2, 'sale_id' => $saleS2->id, 'payment_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'payment' => 5000.00]);

        // Cash expenses: S1 = 25, S2 = 4000
        DbExpense::create(['store_id' => 1, 'expense_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'expense_amt' => 25.00]);
        DbExpense::create(['store_id' => 2, 'expense_date' => '2026-09-03', 'payment_type' => 'Cash', 'account_id' => $acc1->id, 'expense_amt' => 4000.00]);

        // Cash deposits: S1 = 50, S2 = 8000
        AcTransaction::create(['store_id' => 1, 'transaction_date' => '2026-09-03', 'transaction_type' => 'DEPOSIT', 'credit_account_id' => $acc1->id, 'credit_amt' => 50.00]);
        AcTransaction::create(['store_id' => 2, 'transaction_date' => '2026-09-03', 'transaction_type' => 'DEPOSIT', 'credit_account_id' => $acc1->id, 'credit_amt' => 8000.00]);

        // Cash transfers in: S1 = 70, S2 = 7000
        AcTransaction::create(['store_id' => 1, 'transaction_date' => '2026-09-03', 'transaction_type' => 'TRANSFER', 'credit_account_id' => $acc1->id, 'credit_amt' => 70.00]);
        AcTransaction::create(['store_id' => 2, 'transaction_date' => '2026-09-03', 'transaction_type' => 'TRANSFER', 'credit_account_id' => $acc1->id, 'credit_amt' => 7000.00]);

        // Cash transfers out: S1 = 15, S2 = 6000
        AcTransaction::create(['store_id' => 1, 'transaction_date' => '2026-09-03', 'transaction_type' => 'TRANSFER', 'debit_account_id' => $acc1->id, 'debit_amt' => 15.00]);
        AcTransaction::create(['store_id' => 2, 'transaction_date' => '2026-09-03', 'transaction_type' => 'TRANSFER', 'debit_account_id' => $acc1->id, 'debit_amt' => 6000.00]);

        // Hit calculateExpected for Store 1
        $calcRes = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.calculate-expected', [
            'account_id' => $acc1->id,
            'reconciliation_date' => '2026-09-03',
        ]));
        $calcRes->assertOk();
        $data = $calcRes->json('data');

        // Surface 6: Previous-day opening balance
        $this->assertEquals(100.00, $data['system_opening_balance'], 'Surface 6: Must reflect S1 previous counted amount (100.00), not S2.');

        // Surface 7: Cash sales & refunds
        $this->assertEquals(200.00, $data['cash_sales_amount'], 'Surface 7: Cash sales must be exactly 200.00 (S1), excluding S2 (9000.00).');
        $this->assertEquals(30.00, $data['cash_refunds_amount'], 'Surface 7: Cash refunds must be exactly 30.00 (S1), excluding S2 (5000.00).');

        // Surface 8: Cash expenses
        $this->assertEquals(25.00, $data['cash_expenses_amount'], 'Surface 8: Cash expenses must be exactly 25.00 (S1), excluding S2 (4000.00).');

        // Surface 9: Deposits & Transfers
        $this->assertEquals(50.00, $data['cash_deposits_amount'], 'Surface 9: Deposits in must be exactly 50.00 (S1), excluding S2 (8000.00).');
        $this->assertEquals(70.00, $data['cash_transfers_in'], 'Surface 9: Transfers in must be exactly 70.00 (S1), excluding S2 (7000.00).');
        $this->assertEquals(15.00, $data['cash_transfers_out'], 'Surface 9: Transfers out must be exactly 15.00 (S1), excluding S2 (6000.00).');

        // Expected Closing Calculation: 100 + 200 + 50 + 70 - 30 - 25 - 15 = 350.00
        $this->assertEquals(350.00, $data['expected_closing_balance'], 'Expected closing balance must match exact formula using only Store 1 data.');
    }

    /**
     * Item 1.2: Store-Scoped Lookups on show(), closeForm(), closeDrawer(), destroy().
     * Attempt cross-store access against Store 2 record -> 404 and zero mutations.
     */
    public function test_item_1_2_idor_store_scoped_lookups_fail_with_404()
    {
        $s2Acc = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Account', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s2Recon = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-S2-99',
            'store_id' => 2,
            'account_id' => $s2Acc->id,
            'user_id' => $this->store2User->id,
            'opened_by' => $this->store2User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 50.00,
            'status' => 'Open',
            'delete_bit' => 0,
        ]);

        // 1. show()
        $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.show', $s2Recon->id))->assertNotFound();

        // 2. closeForm()
        $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.close-form', $s2Recon->id))->assertNotFound();

        // 3. closeDrawer()
        $closeRes = $this->actingAs($this->store1User)->post(route('accounts.cash-reconciliation.close', $s2Recon->id), [
            'counted_amount' => 60.00,
        ]);
        $closeRes->assertNotFound();
        $this->assertEquals('Open', $s2Recon->fresh()->status, 'Status must remain Open after failed cross-store close attempt.');

        // 4. destroy()
        $delRes = $this->actingAs($this->store1User)->delete(route('accounts.cash-reconciliation.delete', $s2Recon->id));
        $delRes->assertNotFound();
        $this->assertEquals(0, $s2Recon->fresh()->delete_bit, 'delete_bit must remain 0 after failed cross-store delete attempt.');
    }

    /**
     * Item 2.1: Permission Gates — 6 distinct tests for view, add, delete.
     */
    public function test_item_2_1_permission_gates()
    {
        $noPermRole = DbRole::create(['role_name' => 'No Perm', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $noPermRole->id, 'store_id' => 1, 'permissions' => ['accounts_view']]);
        $noPermUser = User::factory()->create(['store_id' => 1, 'role_id' => $noPermRole->id]);

        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Vault Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $recon = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-TEST',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 50.00,
            'status' => 'Open',
            'delete_bit' => 0,
        ]);

        // 1. view permission: unauthorized gets 403 on index
        $this->actingAs($noPermUser)->get(route('accounts.cash-reconciliation.index'))->assertForbidden();

        // 2. view permission: authorized gets 200 on index
        $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index'))->assertOk();

        // 3. add permission: unauthorized gets 403 on open-form
        $this->actingAs($noPermUser)->get(route('accounts.cash-reconciliation.open-form'))->assertForbidden();

        // 4. add permission: authorized gets 200 on open-form
        $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.open-form'))->assertOk();

        // 5. delete permission: unauthorized gets 403 on destroy
        $this->actingAs($noPermUser)->delete(route('accounts.cash-reconciliation.delete', $recon->id))->assertForbidden();

        // 6. delete permission: authorized gets 302 redirect on destroy
        $this->actingAs($this->store1User)->delete(route('accounts.cash-reconciliation.delete', $recon->id))->assertRedirect(route('accounts.cash-reconciliation.index'));
    }

    /**
     * Item 3.1 - 3.4: Solvency Guard & Non-Destructive Reversals on Delete.
     */
    public function test_item_3_1_to_3_4_delete_solvency_and_non_destructive_reversal()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Drawer Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);

        // 1. Reconcile with CASH OVERAGE adjustment (+100 credited to account)
        $txOverage = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'CASH OVERAGE',
            'credit_account_id' => $acc->id,
            'credit_amt' => 100.00,
            'debit_amt' => 0,
        ]);
        $acc->increment('balance', 100.00); // Balance now 1100.00

        $reconOverage = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-OVERAGE',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'expected_closing_balance' => 100.00,
            'counted_amount' => 200.00,
            'variance' => 100.00,
            'status' => 'Adjusted',
            'adjustment_transaction_id' => $txOverage->id,
            'delete_bit' => 0,
        ]);

        // Case A: Solvent Overage Delete -> Success, creates CASH OVERAGE REVERSAL, decrements balance
        $delRes = $this->actingAs($this->store1User)->delete(route('accounts.cash-reconciliation.delete', $reconOverage->id));
        $delRes->assertRedirect(route('accounts.cash-reconciliation.index'));

        $this->assertEquals(1, $reconOverage->fresh()->delete_bit, 'delete_bit must be 1.');
        $this->assertEquals(1000.00, (float) $acc->fresh()->balance, 'Account balance must be decremented back by 100.00.');
        $this->assertDatabaseHas('ac_transactions', ['id' => $txOverage->id]);
        $this->assertDatabaseHas('ac_transactions', [
            'store_id' => 1,
            'transaction_type' => 'CASH OVERAGE REVERSAL',
            'debit_account_id' => $acc->id,
            'debit_amt' => 100.00,
        ]);

        // Case B: Insolvent Overage Delete -> Solvency Guard blocks overdraft
        $txOverage2 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'CASH OVERAGE',
            'credit_account_id' => $acc->id,
            'credit_amt' => 2000.00,
            'debit_amt' => 0,
        ]);
        $reconInsolvent = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-INSOLVENT',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Adjusted',
            'adjustment_transaction_id' => $txOverage2->id,
            'delete_bit' => 0,
        ]);

        // Current account balance is 1000.00. Reversing 2000.00 would cause overdraft (-1000.00)
        $blockRes = $this->actingAs($this->store1User)->delete(route('accounts.cash-reconciliation.delete', $reconInsolvent->id));
        $blockRes->assertSessionHas('error');
        $this->assertStringContainsString('insufficient balance', session('error'));
        $this->assertEquals(0, $reconInsolvent->fresh()->delete_bit, 'delete_bit must remain 0 when solvency check fails.');
        $this->assertEquals(1000.00, (float) $acc->fresh()->balance, 'Account balance must remain untouched.');

        // Case C: Shortage Reversal -> Success, credits account back
        $txShortage = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'CASH SHORTAGE',
            'debit_account_id' => $acc->id,
            'debit_amt' => 50.00,
            'credit_amt' => 0,
        ]);
        $acc->decrement('balance', 50.00); // Balance now 950.00

        $reconShortage = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-SHORTAGE',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Adjusted',
            'adjustment_transaction_id' => $txShortage->id,
            'delete_bit' => 0,
        ]);

        $this->actingAs($this->store1User)->delete(route('accounts.cash-reconciliation.delete', $reconShortage->id));
        $this->assertEquals(1, $reconShortage->fresh()->delete_bit);
        $this->assertEquals(1000.00, (float) $acc->fresh()->balance, 'Account balance must be refunded back by 50.00.');
        $this->assertDatabaseHas('ac_transactions', [
            'store_id' => 1,
            'transaction_type' => 'CASH SHORTAGE REVERSAL',
            'credit_account_id' => $acc->id,
            'credit_amt' => 50.00,
        ]);
    }

    /**
     * Item 3.5: Concurrency Guard on Drawer Open — Genuine parallel test via proc_open() with barrier on dedicated SQLite database.
     */
    public function test_item_3_5_concurrent_drawer_open_race_guard()
    {
        $dbPath = sys_get_temp_dir() . '/recon_open_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/recon_open_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/recon_open_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, account_name TEXT, balance REAL, status INTEGER DEFAULT 1, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE cash_drawer_reconciliations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                reconciliation_code TEXT,
                store_id INTEGER,
                account_id INTEGER,
                user_id INTEGER,
                reconciliation_date TEXT,
                opening_balance REAL,
                status TEXT,
                delete_bit INTEGER DEFAULT 0
            );

            INSERT INTO ac_accounts VALUES (1, 1, 'Race Drawer', 500.00, 1, 0);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $ref = $argv[1];

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");

            // Row lock simulation
            $stmt = $pdo->prepare("SELECT id FROM ac_accounts WHERE id = 1 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            $acc = $stmt->fetchColumn();

            // Active open drawer check
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cash_drawer_reconciliations WHERE store_id = 1 AND account_id = 1 AND status = \'Open\' AND delete_bit = 0");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_OPEN\n";
                exit(0);
            }

            // Duplicate date check
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM cash_drawer_reconciliations WHERE store_id = 1 AND account_id = 1 AND reconciliation_date = \'2026-09-04\' AND delete_bit = 0");
            $stmt->execute();
            if ((int) $stmt->fetchColumn() > 0) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:DUPLICATE_DATE\n";
                exit(0);
            }

            $stmt = $pdo->prepare("INSERT INTO cash_drawer_reconciliations (reconciliation_code, store_id, account_id, user_id, reconciliation_date, opening_balance, status, delete_bit) VALUES (?, 1, 1, 1, \'2026-09-04\', 100.00, \'Open\', 0)");
            $stmt->execute([$ref]);

            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' REC-WORKER-1', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' REC-WORKER-2', $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $openCount = (int) $pdo->query("SELECT COUNT(*) FROM cash_drawer_reconciliations WHERE store_id = 1 AND account_id = 1 AND status = 'Open'")->fetchColumn();
        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined, 'One open request must succeed.');
        $this->assertTrue(
            str_contains($combined, 'RESULT:ALREADY_OPEN') || str_contains($combined, 'RESULT:DUPLICATE_DATE'),
            'One open request must be rejected.'
        );
        $this->assertEquals(1, $openCount, 'Exactly ONE open drawer record must exist.');
    }

    /**
     * Item 3.6: Double-Delete Race Guard — Genuine parallel test via proc_open() with barrier on dedicated SQLite database.
     */
    public function test_item_3_6_concurrent_double_delete_race_guard()
    {
        $dbPath = sys_get_temp_dir() . '/recon_del_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/recon_del_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/recon_del_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, account_name TEXT, balance REAL, status INTEGER DEFAULT 1, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE cash_drawer_reconciliations (
                id INTEGER PRIMARY KEY,
                reconciliation_code TEXT,
                store_id INTEGER,
                account_id INTEGER,
                status TEXT,
                adjustment_transaction_id INTEGER,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE TABLE ac_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                transaction_type TEXT,
                credit_account_id INTEGER,
                debit_account_id INTEGER,
                credit_amt REAL DEFAULT 0,
                debit_amt REAL DEFAULT 0
            );

            INSERT INTO ac_accounts VALUES (1, 1, 'Delete Race Acc', 500.00, 1, 0);
            INSERT INTO ac_transactions VALUES (10, 1, 'CASH OVERAGE', 1, NULL, 50.00, 0);
            INSERT INTO cash_drawer_reconciliations VALUES (1, 'REC-RACE-DEL', 1, 1, 'Adjusted', 10, 0);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");

            // Atomic conditional transition
            $stmt = $pdo->prepare("UPDATE cash_drawer_reconciliations SET delete_bit = 1 WHERE id = 1 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

            // Solvency check on overage reversal
            $bal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
            if ($bal < 50.00) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:INSOLVENT\n";
                exit(0);
            }

            // Non-destructive reversal
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_account_id, credit_account_id, debit_amt, credit_amt) VALUES (1, \'CASH OVERAGE REVERSAL\', 1, NULL, 50.00, 0)");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 50.00 WHERE id = 1");

            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $finalBal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
        $reversalCount = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions WHERE transaction_type = 'CASH OVERAGE REVERSAL'")->fetchColumn();
        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined, 'Exactly one delete call must succeed.');
        $this->assertStringContainsString('RESULT:ALREADY_DELETED', $combined, 'One delete call must be rejected as already deleted.');

        $this->assertEquals(450.00, $finalBal, 'Account balance must be decremented exactly once (500 - 50 = 450).');
        $this->assertEquals(1, $reversalCount, 'Exactly ONE reversal transaction must be inserted.');
    }

    /**
     * Item 4.1: Edit Feature (Guarded Default).
     */
    public function test_item_4_1_edit_feature_guardrails()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Edit Test Acc', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        // Reconciled record (editable)
        $reconReconciled = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-EDIT-1',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'expected_closing_balance' => 100.00,
            'counted_amount' => 95.00,
            'variance' => -5.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        // 1. Successful edit on Reconciled record
        $editRes = $this->actingAs($this->store1User)->put(route('accounts.cash-reconciliation.update', $reconReconciled->id), [
            'counted_amount' => 105.00,
            'notes' => 'Recounted by manager',
        ]);
        $editRes->assertRedirect(route('accounts.cash-reconciliation.show', $reconReconciled->id));
        $this->assertEquals(105.00, (float) $reconReconciled->fresh()->counted_amount);
        $this->assertEquals(5.00, (float) $reconReconciled->fresh()->variance, 'Variance must be recalculated (105 - 100 = 5.00).');

        // 2. Edit on Adjusted record -> Rejected
        $reconAdjusted = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-EDIT-2',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'expected_closing_balance' => 100.00,
            'counted_amount' => 90.00,
            'variance' => -10.00,
            'status' => 'Adjusted',
            'delete_bit' => 0,
        ]);
        $adjRes = $this->actingAs($this->store1User)->put(route('accounts.cash-reconciliation.update', $reconAdjusted->id), [
            'counted_amount' => 95.00,
        ]);
        $adjRes->assertRedirect(route('accounts.cash-reconciliation.show', $reconAdjusted->id));
        $adjRes->assertSessionHas('error');

        // 3. Edit on Open record -> Rejected
        $reconOpen = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-EDIT-3',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Open',
            'delete_bit' => 0,
        ]);
        $openRes = $this->actingAs($this->store1User)->put(route('accounts.cash-reconciliation.update', $reconOpen->id), [
            'counted_amount' => 100.00,
        ]);
        $openRes->assertRedirect(route('accounts.cash-reconciliation.close-form', $reconOpen->id));

        // 4. Edit by user who is NOT opener or admin -> Rejected
        $otherRole = DbRole::create(['role_name' => 'Other Cashier Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $otherRole->id, 'store_id' => 1, 'permissions' => ['cash_reconciliation_add']]);
        $otherCashier = User::factory()->create(['store_id' => 1, 'role_id' => $otherRole->id]);

        $unauthRes = $this->actingAs($otherCashier)->put(route('accounts.cash-reconciliation.update', $reconReconciled->id), [
            'counted_amount' => 110.00,
        ]);
        $unauthRes->assertRedirect(route('accounts.cash-reconciliation.show', $reconReconciled->id));
        $unauthRes->assertSessionHas('error');
    }

    /**
     * Item 4.2: Export Store-Scoping & Filter Adherence (CSV & PDF).
     */
    public function test_item_4_2_export_store_scoping()
    {
        $acc1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Vault', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $acc2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);

        // S1 Reconciled
        CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-S1-EXPORT-MATCH',
            'store_id' => 1,
            'account_id' => $acc1->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        // S1 Adjusted (different status)
        CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-S1-DIFF-STATUS',
            'store_id' => 1,
            'account_id' => $acc1->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Adjusted',
            'delete_bit' => 0,
        ]);

        // S2 Reconciled (cross-store)
        CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-S2-CROSS-STORE',
            'store_id' => 2,
            'account_id' => $acc2->id,
            'user_id' => $this->store2User->id,
            'opened_by' => $this->store2User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 100.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        // CSV Export with active filter: status = Reconciled
        $csvResponse = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', [
            'export' => 'csv',
            'status' => 'Reconciled',
        ]));
        $csvResponse->assertOk();

        ob_start();
        $csvResponse->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('REC-S1-EXPORT-MATCH', $csvContent);
        $this->assertStringNotContainsString('REC-S1-DIFF-STATUS', $csvContent);
        $this->assertStringNotContainsString('REC-S2-CROSS-STORE', $csvContent);

        // PDF View
        $pdfResponse = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', [
            'export' => 'pdf',
            'status' => 'Reconciled',
        ]));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('module.accounts.reconciliation.reconciliation_list_print');
        $pdfResponse->assertSee('REC-S1-EXPORT-MATCH');
        $pdfResponse->assertDontSee('REC-S1-DIFF-STATUS');
        $pdfResponse->assertDontSee('REC-S2-CROSS-STORE');
    }

    /**
     * Item 4.3: Bulk Delete with Partial Insolvency Skip-With-Report.
     */
    public function test_item_4_3_bulk_delete_with_skip_with_report()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Bulk Vault', 'balance' => 500.00, 'status' => 1, 'delete_bit' => 0]);

        // Recon 1: Simple Reconciled record (solvent)
        $r1 = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-B1',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-01',
            'opening_balance' => 50.00,
            'status' => 'Reconciled',
            'delete_bit' => 0,
        ]);

        // Recon 2: Adjusted with Overage of 5000.00 (insolvent because account balance is only 500.00)
        $tx2 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-02',
            'transaction_type' => 'CASH OVERAGE',
            'credit_account_id' => $acc->id,
            'credit_amt' => 5000.00,
        ]);
        $r2Insolvent = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-B2-INSOLVENT',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-02',
            'opening_balance' => 50.00,
            'status' => 'Adjusted',
            'adjustment_transaction_id' => $tx2->id,
            'delete_bit' => 0,
        ]);

        // Recon 3: Adjusted with Shortage of 30.00 (solvent, will credit account 30.00)
        $tx3 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'CASH SHORTAGE',
            'debit_account_id' => $acc->id,
            'debit_amt' => 30.00,
        ]);
        $r3 = CashDrawerReconciliation::create([
            'store_id' => 1,
            'reconciliation_code' => 'REC-B3',
            'store_id' => 1,
            'account_id' => $acc->id,
            'user_id' => $this->store1User->id,
            'opened_by' => $this->store1User->id,
            'reconciliation_date' => '2026-09-03',
            'opening_balance' => 50.00,
            'status' => 'Adjusted',
            'adjustment_transaction_id' => $tx3->id,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->post(route('accounts.cash-reconciliation.bulk-delete'), [
            'ids' => [$r1->id, $r2Insolvent->id, $r3->id],
        ]);
        $response->assertRedirect(route('accounts.cash-reconciliation.index'));

        // Assert r1 and r3 are deleted
        $this->assertEquals(1, $r1->fresh()->delete_bit, 'Solvent record r1 must be deleted.');
        $this->assertEquals(1, $r3->fresh()->delete_bit, 'Solvent record r3 must be deleted.');

        // Assert r2 is skipped with delete_bit = 0
        $this->assertEquals(0, $r2Insolvent->fresh()->delete_bit, 'Insolvent record r2 must be skipped.');

        // Assert warning message reports skipped record
        $response->assertSessionHas('warning');
        $warning = session('warning');
        $this->assertStringContainsString('REC-B2-INSOLVENT', $warning);
        $this->assertStringContainsString('insufficient', $warning);
    }

    /**
     * Phase 5: Pagination Query String & Per-Page Whitelist.
     */
    public function test_phase_5_pagination_and_per_page_whitelist()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Pagination Acc', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        for ($i = 1; $i <= 20; $i++) {
            CashDrawerReconciliation::create([
                'store_id' => 1,
                'reconciliation_code' => "REC-PAG-{$i}",
                'store_id' => 1,
                'account_id' => $acc->id,
                'user_id' => $this->store1User->id,
                'opened_by' => $this->store1User->id,
                'reconciliation_date' => '2026-09-03',
                'opening_balance' => 50.00,
                'status' => 'Reconciled',
                'delete_bit' => 0,
            ]);
        }

        // Test withQueryString(): filter by status=Reconciled on page 2
        $page2Response = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', [
            'status' => 'Reconciled',
            'page' => 2,
        ]));
        $page2Response->assertOk();
        $this->assertStringContainsString('status=Reconciled', $page2Response->getContent());

        // Test fallback to 15 when per_page is out-of-whitelist
        $fallbackRes = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', [
            'per_page' => 9999,
        ]));
        $this->assertEquals(15, $fallbackRes->viewData('reconciliations')->perPage(), 'Out-of-whitelist per_page must fall back to 15.');

        // Test valid whitelist value 25
        $validRes = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', [
            'per_page' => 25,
        ]));
        $this->assertEquals(25, $validRes->viewData('reconciliations')->perPage());
        $p15Res = $this->actingAs($this->store1User)->get(route('accounts.cash-reconciliation.index', ['per_page' => 15]));
        $this->assertEquals(15, $p15Res->viewData('reconciliations')->perPage(), 'per_page=15 must be honored (was previously a whitelist mismatch).');
        // Test explicit 15 is now honored by the whitelist (Phase E)
    }
}
