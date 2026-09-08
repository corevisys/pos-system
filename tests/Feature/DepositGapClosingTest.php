<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcMoneyDeposit;
use App\Models\AcTransaction;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepositGapClosingTest extends TestCase
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

        // Full permission role for Store 1 User
        $role1 = DbRole::create([
            'role_name' => 'Store 1 Full Manager',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view',
                'money_deposit_view',
                'money_deposit_add',
                'money_deposit_edit',
                'money_deposit_delete',
            ],
        ]);

        $this->store1User = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role1->id,
            'name' => 'Store 1 Full User',
            'email' => 's1full@example.com',
        ]);

        // Full permission role for Store 2 User
        $role2 = DbRole::create([
            'role_name' => 'Store 2 Full Manager',
            'status' => 1,
            'store_id' => 2,
        ]);

        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => [
                'accounts_view',
                'money_deposit_view',
                'money_deposit_add',
                'money_deposit_edit',
                'money_deposit_delete',
            ],
        ]);

        $this->store2User = User::factory()->create([
            'store_id' => 2,
            'role_id' => $role2->id,
            'name' => 'Store 2 Full User',
            'email' => 's2full@example.com',
        ]);
    }

    /**
     * Helper to create a user in Store 1 with an exact list of permissions.
     */
    protected function createUserWithPermissions(array $permissions): User
    {
        $role = DbRole::create([
            'role_name' => 'Custom Role ' . uniqid(),
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => $permissions,
        ]);

        return User::factory()->create([
            'store_id' => 1,
            'role_id' => $role->id,
            'email' => 'custom_' . uniqid() . '@example.com',
        ]);
    }

    /**
     * Gap 1: Granular Permission Gates (403 without permission, success with permission).
     */
    public function test_gap_1_permission_gates_isolated_and_control_success()
    {
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Bank Account', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $dep = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst->id,
            'amount' => 100.00,
            'reference_no' => 'DEP-PERM-TEST',
            'delete_bit' => 0,
        ]);

        // A. money_deposit_view
        $noViewUser = $this->createUserWithPermissions(['accounts_view']);
        $this->actingAs($noViewUser)->get(route('accounts.deposit'))->assertForbidden();

        $withViewUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view']);
        $this->actingAs($withViewUser)->get(route('accounts.deposit'))->assertOk();

        // B. money_deposit_add
        $noAddUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view']);
        $this->actingAs($noAddUser)->get(route('accounts.deposit.add'))->assertForbidden();
        $this->actingAs($noAddUser)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst->id,
            'amount' => 50.00,
        ])->assertForbidden();

        $withAddUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view', 'money_deposit_add']);
        $this->actingAs($withAddUser)->get(route('accounts.deposit.add'))->assertOk();
        $this->actingAs($withAddUser)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst->id,
            'amount' => 50.00,
        ])->assertRedirect(route('accounts.deposit'));

        // C. money_deposit_edit
        $noEditUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view']);
        $this->actingAs($noEditUser)->get(route('accounts.deposit.edit', $dep->id))->assertForbidden();
        $this->actingAs($noEditUser)->put(route('accounts.deposit.update', $dep->id), [
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst->id,
            'amount' => 100.00,
            'note' => 'Unauthorized update attempt',
        ])->assertForbidden();

        $withEditUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view', 'money_deposit_edit']);
        $this->actingAs($withEditUser)->get(route('accounts.deposit.edit', $dep->id))->assertOk();
        $this->actingAs($withEditUser)->put(route('accounts.deposit.update', $dep->id), [
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst->id,
            'amount' => 100.00,
            'note' => 'Authorized update',
        ])->assertRedirect(route('accounts.deposit'));

        // D. money_deposit_delete
        $noDeleteUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view']);
        $this->actingAs($noDeleteUser)->delete(route('accounts.deposit.delete', $dep->id))->assertForbidden();
        $this->actingAs($noDeleteUser)->post(route('accounts.deposit.bulk-delete'), ['ids' => [$dep->id]])->assertForbidden();

        $withDeleteUser = $this->createUserWithPermissions(['accounts_view', 'money_deposit_view', 'money_deposit_delete']);
        $this->actingAs($withDeleteUser)->delete(route('accounts.deposit.delete', $dep->id))->assertRedirect(route('accounts.deposit'));
    }

    /**
     * Gap 2: Multi-Store IDOR Protection.
     * Deposit created in Store A. Authenticated Store B user cannot view, edit, update, or delete it.
     */
    public function test_gap_2_multi_store_idor_complete_isolation()
    {
        $s1Dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Store 1 Bank', 'balance' => 800.00, 'status' => 1, 'delete_bit' => 0]);
        $depStore1 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s1Dst->id,
            'amount' => 350.00,
            'reference_no' => 'DEP-S1-PRIVATE',
            'note' => 'Top Secret Store 1 Deposit',
            'delete_bit' => 0,
        ]);

        // 1. Index does not show Store 1's deposit to Store 2 user
        $indexResponse = $this->actingAs($this->store2User)->get(route('accounts.deposit'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee('DEP-S1-PRIVATE');
        $indexResponse->assertDontSee('Top Secret Store 1 Deposit');

        // 2. GET edit route returns 404 (ModelNotFoundException)
        $editResponse = $this->actingAs($this->store2User)->get(route('accounts.deposit.edit', $depStore1->id));
        $editResponse->assertNotFound();

        // 3. PUT update returns 404, row is NOT mutated, balance NOT changed
        $s2Dst = AcAccount::create(['store_id' => 2, 'account_name' => 'Store 2 Bank', 'balance' => 500.00, 'status' => 1, 'delete_bit' => 0]);
        $updateResponse = $this->actingAs($this->store2User)->put(route('accounts.deposit.update', $depStore1->id), [
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s2Dst->id,
            'amount' => 999.00,
            'note' => 'Hacked deposit',
        ]);
        $updateResponse->assertNotFound();

        $depStore1->refresh();
        $this->assertEquals(350.00, (float) $depStore1->amount, 'Deposit amount must not be altered.');
        $this->assertEquals('Top Secret Store 1 Deposit', $depStore1->note, 'Deposit note must not be altered.');
        $this->assertEquals(800.00, (float) $s1Dst->fresh()->balance, 'Store 1 account balance must remain untouched.');

        // 4. DELETE destroy returns error, row is NOT mutated, balance NOT changed
        $deleteResponse = $this->actingAs($this->store2User)->delete(route('accounts.deposit.delete', $depStore1->id));
        $deleteResponse->assertRedirect(route('accounts.deposit'));
        $deleteResponse->assertSessionHas('error');

        $this->assertEquals(0, $depStore1->fresh()->delete_bit, 'Store 1 deposit must remain active (delete_bit = 0).');
        $this->assertEquals(800.00, (float) $s1Dst->fresh()->balance, 'Store 1 account balance must remain untouched.');
    }

    /**
     * Gap 3: Self-Deposit Guard.
     * Attempting credit_account_id === debit_account_id rejects and writes zero ledger rows.
     */
    public function test_gap_3_self_deposit_guard_rejects_and_writes_zero_transactions()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Checking Account',
            'balance' => 500.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $initialTxCount = AcTransaction::count();
        $initialDepCount = AcMoneyDeposit::count();

        $response = $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => $account->id,
            'credit_account_id' => $account->id,
            'amount' => 100.00,
            'reference_no' => 'SELF-DEP-FAIL',
        ]);

        $response->assertSessionHasErrors(['debit_account_id']);

        $this->assertEquals($initialTxCount, AcTransaction::count(), 'Zero transaction rows must be written upon validation failure.');
        $this->assertEquals($initialDepCount, AcMoneyDeposit::count(), 'Zero deposit rows must be created upon validation failure.');
        $this->assertEquals(500.00, (float) $account->fresh()->balance, 'Account balance must remain unchanged.');
    }

    /**
     * Gap 4: Edit Flow — External to Internal Switch & Internal to External Switch.
     */
    public function test_gap_4_edit_flow_switching_between_external_and_internal_source()
    {
        $dest = AcAccount::create(['store_id' => 1, 'account_name' => 'Destination Bank', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);
        $internalSource = AcAccount::create(['store_id' => 1, 'account_name' => 'Vault Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);

        // Step 1: Create an external deposit ($400.00 into destination)
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-01',
            'debit_account_id' => '',
            'credit_account_id' => $dest->id,
            'amount' => 400.00,
            'reference_no' => 'DEP-EXT-INITIAL',
            'note' => 'Initial External Deposit',
        ]);

        $deposit = AcMoneyDeposit::where('reference_no', 'DEP-EXT-INITIAL')->firstOrFail();

        $contraAcc = AcAccount::where('store_id', 1)
            ->where('account_name', 'External Deposit Clearing')
            ->where('delete_bit', 0)
            ->firstOrFail();

        $this->assertEquals(400.00, (float) $dest->fresh()->balance);
        $this->assertEquals(1000.00, (float) $internalSource->fresh()->balance);

        // Step 2: Edit Deposit — Switch FROM External TO Internal Source ($400 from Vault Cash)
        $editResponse1 = $this->actingAs($this->store1User)->put(route('accounts.deposit.update', $deposit->id), [
            'deposit_date' => '2026-09-02',
            'debit_account_id' => $internalSource->id,
            'credit_account_id' => $dest->id,
            'amount' => 400.00,
            'reference_no' => 'DEP-SWITCHED-INTERNAL',
            'note' => 'Switched to Internal Source',
        ]);

        $editResponse1->assertRedirect(route('accounts.deposit'));

        // Balances verified:
        // Internal source was debited $400: 1000 - 400 = 600.00
        $this->assertEquals(600.00, (float) $internalSource->fresh()->balance, 'Vault Cash must be debited $400.00.');
        // Destination balance: reversed 400, then reapplied 400 = 400.00
        $this->assertEquals(400.00, (float) $dest->fresh()->balance, 'Destination balance must remain 400.00.');

        // Verify ledger entries: exactly 2 reversal rows + 2 new forward rows
        $reversals1 = AcTransaction::where('ref_moneydeposits_id', $deposit->id)->where('transaction_type', 'DEPOSIT REVERSAL')->get();
        $this->assertCount(2, $reversals1, 'Exactly 2 reversal rows must be created for old external deposit.');

        // Reversal row against contra clearing account
        $contraRefund = $reversals1->where('debit_account_id', $contraAcc->id)->first();
        $this->assertNotNull($contraRefund, 'Old reversal must reverse against External Deposit Clearing contra account.');

        // Forward rows against new internal source
        $forwards1 = AcTransaction::where('ref_moneydeposits_id', $deposit->id)
            ->where('transaction_type', 'DEPOSIT')
            ->where('note', 'like', '%Revised%')
            ->get();
        $this->assertCount(2, $forwards1, 'Exactly 2 new forward rows must be created.');

        $srcDebitRow = $forwards1->where('debit_account_id', $internalSource->id)->where('debit_amt', 400.00)->first();
        $this->assertNotNull($srcDebitRow, 'New forward row must debit internal source Vault Cash.');

        // Step 3: Edit Deposit — Switch FROM Internal Source BACK TO External
        $editResponse2 = $this->actingAs($this->store1User)->put(route('accounts.deposit.update', $deposit->id), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => '',
            'credit_account_id' => $dest->id,
            'amount' => 400.00,
            'reference_no' => 'DEP-SWITCHED-EXTERNAL',
            'note' => 'Switched back to External',
        ]);

        $editResponse2->assertRedirect(route('accounts.deposit'));

        // Balances verified:
        // Internal source was refunded: 600 + 400 = 1000.00
        $this->assertEquals(1000.00, (float) $internalSource->fresh()->balance, 'Vault Cash must be refunded back to 1000.00.');
        // Destination balance: reversed 400, then reapplied 400 = 400.00
        $this->assertEquals(400.00, (float) $dest->fresh()->balance);

        // Verify latest forward entries debit the contra clearing account
        $latestForwards = AcTransaction::where('ref_moneydeposits_id', $deposit->id)
            ->where('transaction_type', 'DEPOSIT')
            ->latest('id')
            ->take(2)
            ->get();

        $clearingForward = $latestForwards->where('debit_account_id', $contraAcc->id)->where('debit_amt', 400.00)->first();
        $this->assertNotNull($clearingForward, 'Forward entry must debit External Deposit Clearing contra account.');
    }

    /**
     * Gap 5: Export Store-Scoping Strict Isolation.
     * With deposits in Store A and Store B, CSV and PDF exports return only Store A rows.
     */
    public function test_gap_5_export_strict_store_scoping()
    {
        $s1Acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Store 1 Bank', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc = AcAccount::create(['store_id' => 2, 'account_name' => 'Store 2 Bank', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);

        AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s1Acc->id,
            'amount' => 111.00,
            'reference_no' => 'S1-EXCLUSIVE-DEP',
            'delete_bit' => 0,
        ]);

        AcMoneyDeposit::create([
            'store_id' => 2,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s2Acc->id,
            'amount' => 222.00,
            'reference_no' => 'S2-CONFIDENTIAL-DEP',
            'delete_bit' => 0,
        ]);

        // CSV Export
        $csvResponse = $this->actingAs($this->store1User)->get(route('accounts.deposit', ['export' => 'csv']));
        $csvResponse->assertOk();

        ob_start();
        $csvResponse->sendContent();
        $csvOutput = ob_get_clean();

        $this->assertStringContainsString('S1-EXCLUSIVE-DEP', $csvOutput);
        $this->assertStringNotContainsString('S2-CONFIDENTIAL-DEP', $csvOutput);

        // PDF / Print View
        $pdfResponse = $this->actingAs($this->store1User)->get(route('accounts.deposit', ['export' => 'pdf']));
        $pdfResponse->assertOk();
        $pdfResponse->assertSee('S1-EXCLUSIVE-DEP');
        $pdfResponse->assertDontSee('S2-CONFIDENTIAL-DEP');
    }

    /**
     * Gap 6: Bulk Delete Partial Insolvency — Skip-With-Report Behavior.
     * Selects 3 deposits: Dep 1 (solvent), Dep 2 (insolvent), Dep 3 (solvent).
     * Proves: Dep 1 & 3 delete successfully, Dep 2 is skipped with informative warning,
     * no double-deletions or corrupted balances occur.
     */
    public function test_gap_6_bulk_delete_partial_insolvency_skip_with_report()
    {
        $dst1 = AcAccount::create(['store_id' => 1, 'account_name' => 'Solvent Bank 1', 'balance' => 500.00, 'status' => 1, 'delete_bit' => 0]);
        $dst2 = AcAccount::create(['store_id' => 1, 'account_name' => 'Insolvent Bank 2', 'balance' => 50.00, 'status' => 1, 'delete_bit' => 0]);
        $dst3 = AcAccount::create(['store_id' => 1, 'account_name' => 'Solvent Bank 3', 'balance' => 600.00, 'status' => 1, 'delete_bit' => 0]);

        // Deposit 1: $100 into dst1 (has $500) -> Solvent
        $dep1 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst1->id,
            'amount' => 100.00,
            'reference_no' => 'BULK-SOLV-1',
            'delete_bit' => 0,
        ]);

        // Deposit 2: $300 into dst2 (has $50) -> Insolvent!
        $dep2 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst2->id,
            'amount' => 300.00,
            'reference_no' => 'BULK-INSOLV-2',
            'delete_bit' => 0,
        ]);

        // Deposit 3: $150 into dst3 (has $600) -> Solvent
        $dep3 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst3->id,
            'amount' => 150.00,
            'reference_no' => 'BULK-SOLV-3',
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->post(route('accounts.deposit.bulk-delete'), [
            'ids' => [$dep1->id, $dep2->id, $dep3->id],
        ]);

        $response->assertRedirect(route('accounts.deposit'));
        $response->assertSessionHas('warning');

        $warning = session('warning');
        $this->assertStringContainsString('Bulk Delete: 2 deposit(s) deleted and reversed.', $warning);
        $this->assertStringContainsString('Skipped 1 deposit(s)', $warning);
        $this->assertStringContainsString('BULK-INSOLV-2: Destination account Insolvent Bank 2 has insufficient balance', $warning);

        // Assert DB status: Dep 1 & 3 soft-deleted, Dep 2 untouched
        $this->assertEquals(1, $dep1->fresh()->delete_bit, 'Dep 1 must be deleted (delete_bit = 1).');
        $this->assertEquals(0, $dep2->fresh()->delete_bit, 'Dep 2 must be preserved (delete_bit = 0).');
        $this->assertEquals(1, $dep3->fresh()->delete_bit, 'Dep 3 must be deleted (delete_bit = 1).');

        // Assert balances:
        // dst1: 500 - 100 = 400.00
        $this->assertEquals(400.00, (float) $dst1->fresh()->balance);
        // dst2: untouched = 50.00
        $this->assertEquals(50.00, (float) $dst2->fresh()->balance);
        // dst3: 600 - 150 = 450.00
        $this->assertEquals(450.00, (float) $dst3->fresh()->balance);

        // Reversal row count: exactly 2 for Dep 1, 0 for Dep 2, 2 for Dep 3
        $this->assertEquals(2, AcTransaction::where('ref_moneydeposits_id', $dep1->id)->where('transaction_type', 'DEPOSIT REVERSAL')->count());
        $this->assertEquals(0, AcTransaction::where('ref_moneydeposits_id', $dep2->id)->where('transaction_type', 'DEPOSIT REVERSAL')->count());
        $this->assertEquals(2, AcTransaction::where('ref_moneydeposits_id', $dep3->id)->where('transaction_type', 'DEPOSIT REVERSAL')->count());
    }
}
