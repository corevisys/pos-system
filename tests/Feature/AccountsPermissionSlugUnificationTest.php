<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use App\Services\NavigationShortcutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountsPermissionSlugUnificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);

        // A normal cash account so the Deposit/Transfer list views render rows.
        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Petty Cash',
            'balance' => 500.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    /**
     * A custom role created through the Roles UI now grants the Vocabulary A/B slugs
     * (money_deposit_view / money_transfer_view / cash_transactions /
     * cash_reconciliation_view/add/adjust/delete). Such a role must be able to open all
     * three Accounts pages (no 403) AND see their sidebar/shortcut/global-search entries.
     */
    public function test_ui_created_role_with_vocabulary_ab_slugs_can_open_accounts_pages_and_see_sidebar()
    {
        // Mirror exactly what the Roles admin UI now submits after Phase A.
        $role = DbRole::create([
            'role_name' => 'Accounts Clerk (UI Created)',
            'description' => 'Created through the Roles UI after Phase A slug unification',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view',
                'money_deposit_view',
                'money_deposit_add',
                'money_transfer_view',
                'money_transfer_add',
                'cash_transactions',
                'cash_reconciliation_view',
                'cash_reconciliation_add',
                'cash_reconciliation_adjust',
                'cash_reconciliation_delete',
            ],
        ]);

        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'name' => 'Clerk']);

        // (i) All three pages open without 403.
        $this->actingAs($user)->get(route('accounts.transfer'))->assertOk();
        $this->actingAs($user)->get(route('accounts.deposit'))->assertOk();
        $this->actingAs($user)->get(route('accounts.transactions'))->assertOk();
        $this->actingAs($user)->get(route('accounts.cash-reconciliation.index'))->assertOk();

        // (ii) Sidebar links are visible for Money Transfer / Deposit / Cash Transactions.
        $response = $this->actingAs($user)->get(route('accounts.transfer'));
        $response->assertOk();
        $response->assertSee('Money Transfer List', false);
        $response->assertSee('Deposit List', false);
        $response->assertSee('Cash Transactions', false);
        $response->assertSee('Cash Reconciliation', false);

        // (iii) Navigation shortcuts resolve has_permission = true for the accounts module items.
        $shortcuts = NavigationShortcutService::getShortcutsForUser($user);
        $accountsItems = $shortcuts['A']['items'] ?? [];
        $lookup = [];
        foreach ($accountsItems as $item) {
            $lookup[$item['page_key']] = $item['has_permission'];
        }
        $this->assertTrue($lookup['T'] ?? false, 'Money Transfer shortcut must be permitted');
        $this->assertTrue($lookup['D'] ?? false, 'Deposit shortcut must be permitted');
        $this->assertTrue($lookup['C'] ?? false, 'Cash Transactions shortcut must be permitted');
    }

    /**
     * A seeded Admin role (granted Vocabulary A/B slugs by the seeders, including
     * money_transfer_view / money_deposit_view / cash_transactions) must now SEE the
     * sidebar/shortcut entries it was previously missing (Phase A unifies the UI onto
     * the controller/seeder slugs).
     */
    public function test_seeded_admin_now_sees_sidebar_and_shortcut_entries_for_accounts_pages()
    {
        // Replicate the seeder's Admin permission list for the Accounts cluster.
        $role = DbRole::create([
            'role_name' => 'Admin Replica',
            'description' => 'Mirrors the seeder Admin permission set for Accounts',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_add',
                'accounts_edit',
                'accounts_delete',
                'accounts_view',
                'money_transfer_add',
                'money_transfer_edit',
                'money_transfer_delete',
                'money_transfer_view',
                'money_deposit_add',
                'money_deposit_edit',
                'money_deposit_delete',
                'money_deposit_view',
                'cash_transactions',
                'cash_reconciliation_view',
                'cash_reconciliation_add',
                'cash_reconciliation_adjust',
                'cash_reconciliation_delete',
                'cash_reconciliation_report',
            ],
        ]);

        $user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id, 'name' => 'Admin Replica']);

        // Sidebar must now expose all three previously-hidden entries.
        $response = $this->actingAs($user)->get(route('accounts.transfer'));
        $response->assertOk();
        $response->assertSee('Money Transfer List', false);
        $response->assertSee('Deposit List', false);
        $response->assertSee('Cash Transactions', false);

        // Navigation shortcuts must resolve has_permission = true.
        $shortcuts = NavigationShortcutService::getShortcutsForUser($user);
        $lookup = [];
        foreach (($shortcuts['A']['items'] ?? []) as $item) {
            $lookup[$item['page_key']] = $item['has_permission'];
        }
        $this->assertTrue($lookup['T'] ?? false);
        $this->assertTrue($lookup['D'] ?? false);
        $this->assertTrue($lookup['C'] ?? false);
    }
}
