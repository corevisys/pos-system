<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CashTransactionsFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $store1;
    protected $store2;
    protected $store1User;
    protected $store2User;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Setup Stores
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

        // 2. Setup Role & Permissions for Store 1 User (using modern slug accounts_cash_transactions)
        $role1 = DbRole::create([
            'role_name' => 'Store 1 Manager',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view',
                'accounts_cash_transactions',
            ],
        ]);

        $this->store1User = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role1->id,
            'name' => 'Alice Manager',
            'email' => 'alice@store1.com',
        ]);

        // 3. Setup Role & Permissions for Store 2 User
        $role2 = DbRole::create([
            'role_name' => 'Store 2 Manager',
            'status' => 1,
            'store_id' => 2,
        ]);

        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => [
                'accounts_view',
                'accounts_cash_transactions',
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
     * Item 1.1: Store-Scope the Main Transaction Query.
     * Seed transactions in Store 1 and Store 2; assert Store 1 user sees only Store 1 IDs and zero Store 2 IDs.
     */
    public function test_item_1_1_main_query_is_store_scoped_by_exact_ids()
    {
        $s1Acc = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Main Bank', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret Bank', 'balance' => 2000, 'status' => 1, 'delete_bit' => 0]);

        $tx1 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'DEPOSIT',
            'credit_account_id' => $s1Acc->id,
            'credit_amt' => 500.00,
            'note' => 'Store 1 Deposit Note',
        ]);

        $tx2 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $s1Acc->id,
            'debit_amt' => 200.00,
            'note' => 'Store 1 Transfer Note',
        ]);

        $txStore2 = AcTransaction::create([
            'store_id' => 2,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'DEPOSIT',
            'credit_account_id' => $s2Acc->id,
            'credit_amt' => 999.00,
            'note' => 'Store 2 Secret Transaction',
        ]);

        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions'));
        $response->assertOk();

        $pageTransactions = $response->viewData('transactions');
        $visibleIds = $pageTransactions->pluck('id')->all();

        $this->assertContains($tx1->id, $visibleIds, 'Store 1 transaction #1 must be visible.');
        $this->assertContains($tx2->id, $visibleIds, 'Store 1 transaction #2 must be visible.');
        $this->assertNotContains($txStore2->id, $visibleIds, 'Store 2 transaction must NOT be visible to Store 1 user.');
        $response->assertDontSee('Store 2 Secret Transaction');
    }

    /**
     * Item 1.2: Store-Scope the Account & User Filter Dropdowns.
     * Assert account dropdown and user dropdown contain zero Store 2 accounts/users by name.
     */
    public function test_item_1_2_filter_dropdowns_are_strictly_store_scoped()
    {
        AcAccount::create(['store_id' => 1, 'account_name' => 'Store 1 Vault', 'status' => 1, 'delete_bit' => 0]);
        AcAccount::create(['store_id' => 2, 'account_name' => 'Store 2 Swiss Vault', 'status' => 1, 'delete_bit' => 0]);

        User::factory()->create(['store_id' => 1, 'name' => 'Charlie S1 Cashier']);
        User::factory()->create(['store_id' => 2, 'name' => 'Dave S2 Spy']);

        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions'));
        $response->assertOk();

        $accounts = $response->viewData('accounts');
        $users = $response->viewData('users');

        // Assert Account dropdown data
        $accountNames = $accounts->pluck('account_name')->all();
        $this->assertContains('Store 1 Vault', $accountNames);
        $this->assertNotContains('Store 2 Swiss Vault', $accountNames);

        // Assert User dropdown data
        $userNames = $users->pluck('name')->all();
        $this->assertContains('Charlie S1 Cashier', $userNames);
        $this->assertNotContains('Dave S2 Spy', $userNames);

        // HTML assertion
        $response->assertSee('Store 1 Vault');
        $response->assertDontSee('Store 2 Swiss Vault');
        $response->assertSee('Charlie S1 Cashier');
        $response->assertDontSee('Dave S2 Spy');
    }

    /**
     * Item 1.3: Permission Gates — test both modern and legacy slugs individually and unauthorized block.
     */
    public function test_item_1_3_permission_gate_honors_both_slugs_individually()
    {
        // 1. User with NEITHER slug -> 403 Forbidden
        $unauthRole = DbRole::create(['role_name' => 'No Slug Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $unauthRole->id, 'store_id' => 1, 'permissions' => ['accounts_view']]);
        $unauthUser = User::factory()->create(['store_id' => 1, 'role_id' => $unauthRole->id]);

        $this->actingAs($unauthUser)->get(route('accounts.transactions'))->assertForbidden();

        // 2. User with modern slug (accounts_cash_transactions) -> 200 OK
        $modernRole = DbRole::create(['role_name' => 'Modern Slug Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $modernRole->id, 'store_id' => 1, 'permissions' => ['accounts_cash_transactions']]);
        $modernUser = User::factory()->create(['store_id' => 1, 'role_id' => $modernRole->id]);

        $this->actingAs($modernUser)->get(route('accounts.transactions'))->assertOk();

        // 3. User with legacy slug (cash_transactions) -> 200 OK
        $legacyRole = DbRole::create(['role_name' => 'Legacy Slug Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $legacyRole->id, 'store_id' => 1, 'permissions' => ['cash_transactions']]);
        $legacyUser = User::factory()->create(['store_id' => 1, 'role_id' => $legacyRole->id]);

        $this->actingAs($legacyUser)->get(route('accounts.transactions'))->assertOk();
    }

    /**
     * Item 2.1: Model Relationship & Creator Resolution & N+1 Prevention.
     */
    public function test_item_2_1_creator_resolution_and_n_plus_one_prevention()
    {
        $creatorUser = User::factory()->create(['store_id' => 1, 'name' => 'Evelyn Cashier']);
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Petty Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        // Transaction with real created_by
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'EXPENSE',
            'debit_account_id' => $acc->id,
            'debit_amt' => 50.00,
            'created_by' => $creatorUser->id,
            'note' => 'Coffee Supplies',
        ]);

        // Transaction with null created_by
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'OPENING BALANCE',
            'credit_account_id' => $acc->id,
            'credit_amt' => 500.00,
            'created_by' => null,
            'note' => 'Initial Seed',
        ]);

        // Create 20 more rows to test N+1 query elimination
        for ($i = 1; $i <= 20; $i++) {
            AcTransaction::create([
                'store_id' => 1,
                'transaction_date' => '2026-09-03',
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $acc->id,
                'debit_amt' => 10.00,
                'created_by' => $creatorUser->id,
                'note' => "Transfer Row #{$i}",
            ]);
        }

        // Measure query count when rendering view with 22 rows
        DB::enableQueryLog();

        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions', ['per_page' => 25]));
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Isolate queries touching users table to verify creator eager-loading eliminates N+1
        $userQueries = array_filter($queries, fn($q) => str_contains($q['query'], 'users'));
        $this->assertLessThanOrEqual(4, count($userQueries), 'User query count must remain small (<=4) and not scale with row count (1+N).');
        $this->assertLessThanOrEqual(16, count($queries), 'Total query count must remain small.');

        // Assert creator name rendered and fallback to "System"
        $response->assertSee('Evelyn Cashier');
        $response->assertSee('System');
        $response->assertDontSee('USER');
    }

    /**
     * Item 3.1: Dynamic Transaction Type Filter.
     * Seed 5 different types (including reversal); assert all appear as selectable options and filter properly.
     */
    public function test_item_3_1_dynamic_transaction_types_and_filtering()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'General Account', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);

        $types = [
            'OPENING BALANCE',
            'DEPOSIT',
            'TRANSFER',
            'DEPOSIT REVERSAL',
            'TRANSFER REVERSAL',
        ];

        foreach ($types as $type) {
            AcTransaction::create([
                'store_id' => 1,
                'transaction_date' => '2026-09-03',
                'transaction_type' => $type,
                'credit_account_id' => $acc->id,
                'credit_amt' => 100.00,
                'note' => "Transaction of type {$type}",
            ]);
        }

        // Hit index without filter: assert all 5 types appear in the dropdown
        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions'));
        $response->assertOk();

        $dropdownTypes = $response->viewData('transactionTypes')->all();
        foreach ($types as $type) {
            $this->assertContains($type, $dropdownTypes, "Dropdown must contain dynamic type {$type}");
            $response->assertSee("<option value=\"{$type}\"", false);
        }

        // Filter by DEPOSIT REVERSAL
        $filterResponse = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'transaction_type' => 'DEPOSIT REVERSAL',
        ]));
        $filterResponse->assertOk();

        $results = $filterResponse->viewData('transactions');
        $this->assertCount(1, $results);
        $this->assertEquals('DEPOSIT REVERSAL', $results->first()->transaction_type);
        $filterResponse->assertSee('Transaction of type DEPOSIT REVERSAL');
        $filterResponse->assertDontSee('Transaction of type OPENING BALANCE');
    }

    /**
     * Item 3.2: Search Preserves Filters + Searches Account Name.
     */
    public function test_item_3_2_search_combines_with_type_filter_and_searches_account_name()
    {
        $accTarget = AcAccount::create(['store_id' => 1, 'account_name' => 'Target Payroll Bank', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $accOther = AcAccount::create(['store_id' => 1, 'account_name' => 'Other Cash Drawer', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        // Row 1: Type TRANSFER, debit account = Target Payroll Bank
        $tx1 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $accTarget->id,
            'debit_amt' => 150.00,
            'note' => 'Routine Shift Transfer',
        ]);

        // Row 2: Type DEPOSIT, credit account = Target Payroll Bank
        $tx2 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'DEPOSIT',
            'credit_account_id' => $accTarget->id,
            'credit_amt' => 250.00,
            'note' => 'Direct Client Deposit',
        ]);

        // Row 3: Type TRANSFER, account = Other Cash Drawer
        $tx3 = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $accOther->id,
            'debit_amt' => 75.00,
            'note' => 'Drawer Adjustment',
        ]);

        // Search for 'Payroll' AND filter by 'TRANSFER'
        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'search' => 'Payroll',
            'transaction_type' => 'TRANSFER',
        ]));
        $response->assertOk();

        $results = $response->viewData('transactions');
        $visibleIds = $results->pluck('id')->all();

        $this->assertContains($tx1->id, $visibleIds, 'Tx 1 matches both type TRANSFER and account name Payroll.');
        $this->assertNotContains($tx2->id, $visibleIds, 'Tx 2 fails type filter (it is DEPOSIT).');
        $this->assertNotContains($tx3->id, $visibleIds, 'Tx 3 fails search (account name does not match Payroll).');
    }

    /**
     * Item 4.1 & 4.2: Pagination Query String & Per-Page Whitelist.
     */
    public function test_item_4_1_and_4_2_pagination_query_string_and_per_page_whitelist()
    {
        $acc = AcAccount::create(['store_id' => 1, 'account_name' => 'Main Account', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);

        for ($i = 1; $i <= 15; $i++) {
            AcTransaction::create([
                'store_id' => 1,
                'transaction_date' => '2026-09-03',
                'transaction_type' => 'TRANSFER',
                'debit_account_id' => $acc->id,
                'debit_amt' => 10.00,
                'note' => "Paginated Note #{$i}",
            ]);
        }

        // Test withQueryString(): filter by transaction_type=TRANSFER on page 2
        $page2Response = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'transaction_type' => 'TRANSFER',
            'page' => 2,
        ]));
        $page2Response->assertOk();

        // Links must include active filter transaction_type=TRANSFER
        $hasPreserved = str_contains($page2Response->getContent(), 'transaction_type=TRANSFER');
        $this->assertTrue($hasPreserved, 'Active filter transaction_type=TRANSFER must be preserved in pagination URLs.');

        // Test per_page whitelist fallback
        $fallbackResponse = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'per_page' => 9999,
        ]));
        $this->assertEquals(10, $fallbackResponse->viewData('transactions')->perPage(), 'Out-of-whitelist per_page must fall back to 10.');

        $valid25Response = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'per_page' => 25,
        ]));
        $this->assertEquals(25, $valid25Response->viewData('transactions')->perPage(), 'Valid per_page=25 must be honored.');
    }

    /**
     * Item 4.3: Export Store-Scoping (CSV & PDF).
     * Assert output contains only Store 1 rows matching active filter, zero Store 2 rows, zero non-matching types.
     */
    public function test_item_4_3_export_strict_store_scoping_and_filter_adherence()
    {
        $s1Acc = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Bank', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Bank', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);

        // S1 Transfer
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $s1Acc->id,
            'debit_amt' => 111.00,
            'note' => 'S1 Matching Transfer',
        ]);

        // S1 Deposit (different type)
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'DEPOSIT',
            'credit_account_id' => $s1Acc->id,
            'credit_amt' => 222.00,
            'note' => 'S1 Unmatched Deposit',
        ]);

        // S2 Transfer (cross-store)
        AcTransaction::create([
            'store_id' => 2,
            'transaction_date' => '2026-09-03',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $s2Acc->id,
            'debit_amt' => 333.00,
            'note' => 'S2 Cross-Store Transfer',
        ]);

        // CSV Export with active filter: transaction_type = TRANSFER
        $csvResponse = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'export' => 'csv',
            'transaction_type' => 'TRANSFER',
        ]));
        $csvResponse->assertOk();

        ob_start();
        $csvResponse->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('S1 Matching Transfer', $csvContent);
        $this->assertStringNotContainsString('S1 Unmatched Deposit', $csvContent);
        $this->assertStringNotContainsString('S2 Cross-Store Transfer', $csvContent);

        // PDF Print View with active filter: transaction_type = TRANSFER
        $pdfResponse = $this->actingAs($this->store1User)->get(route('accounts.transactions', [
            'export' => 'pdf',
            'transaction_type' => 'TRANSFER',
        ]));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('module.accounts.cash_transactions_print');
        $pdfResponse->assertSee('S1 Matching Transfer');
        $pdfResponse->assertDontSee('S1 Unmatched Deposit');
        $pdfResponse->assertDontSee('S2 Cross-Store Transfer');
    }

    /**
     * Item 4.4: Zero Checkbox Markup in View.
     */
    public function test_item_4_4_no_checkbox_markup_in_read_only_journal()
    {
        $response = $this->actingAs($this->store1User)->get(route('accounts.transactions'));
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString('type="checkbox"', $html, 'Read-only journal must contain zero checkbox inputs.');
    }
}
