<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAccountDropdownExclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);

        $role = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view', 'accounts_add', 'accounts_edit', 'accounts_delete',
                'money_transfer_view', 'money_transfer_add',
                'money_deposit_view', 'money_deposit_add',
                'cash_transactions',
                'cash_reconciliation_view', 'cash_reconciliation_add',
            ],
        ]);
        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);
    }

    /**
     * Assert that the External Deposit Clearing and Opening Balance Equity contra/equity
     * accounts are NOT offered in the Money Transfer / Deposit / Cash Transactions /
     * Cash Reconciliation account dropdowns, while a normal account still is.
     */
    public function test_system_accounts_excluded_from_user_facing_dropdowns()
    {
        $normal = AcAccount::create([
            'store_id' => 1, 'account_name' => 'Petty Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0,
        ]);
        $clearing = AcAccount::findOrCreateSystemAccount(1, 'external_deposit_clearing', 'External Deposit Clearing');
        $equity = AcAccount::findOrCreateSystemAccount(1, 'opening_balance_equity', 'Opening Balance Equity');

        // Money Transfer index + create dropdowns
        $resp = $this->actingAs($this->user)->get(route('accounts.transfer'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        $resp = $this->actingAs($this->user)->get(route('accounts.transfer.add'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        // Deposit index + create dropdowns
        $resp = $this->actingAs($this->user)->get(route('accounts.deposit'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        $resp = $this->actingAs($this->user)->get(route('accounts.deposit.add'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        // Cash Transactions filter dropdown
        $resp = $this->actingAs($this->user)->get(route('accounts.transactions'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        // Cash Reconciliation index + open-form dropdowns
        $resp = $this->actingAs($this->user)->get(route('accounts.cash-reconciliation.index'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);

        $resp = $this->actingAs($this->user)->get(route('accounts.cash-reconciliation.open-form'));
        $resp->assertOk();
        $accIds = collect($resp->viewData('accounts'))->pluck('id')->all();
        $this->assertContains($normal->id, $accIds);
        $this->assertNotContains($clearing->id, $accIds);
        $this->assertNotContains($equity->id, $accIds);
    }

    /**
     * Despite exclusion from dropdowns, the system STILL finds/uses the contra account
     * internally for external deposits (full end-to-end still works).
     */
    public function test_external_deposit_still_uses_clearing_account_end_to_end()
    {
        $dst = AcAccount::create([
            'store_id' => 1, 'account_name' => 'Bank A/C', 'balance' => 100, 'status' => 1, 'delete_bit' => 0,
        ]);

        $this->actingAs($this->user)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => '',
            'credit_account_id' => $dst->id,
            'amount' => 250.00,
            'note' => 'External deposit',
        ]);

        // The system created/used exactly one clearing account.
        $clearingCount = AcAccount::where('store_id', 1)
            ->where('system_key', 'external_deposit_clearing')
            ->count();
        $this->assertEquals(1, $clearingCount);

        $clearing = AcAccount::where('store_id', 1)->where('system_key', 'external_deposit_clearing')->first();
        $this->assertNotNull($clearing);
        $this->assertEquals(350.00, (float) $dst->fresh()->balance, 'Destination balance: 100 + 250 = 350.');
    }
}
