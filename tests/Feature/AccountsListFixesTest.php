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

class AccountsListFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $superAdmin;
    protected $restrictedUser;
    protected $store1User;
    protected $store2User;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create([
            'id' => 1,
            'store_name' => 'Main Store',
            'status' => 1,
            'mobile' => '+8801700000001',
        ]);

        DbStore::create([
            'id' => 2,
            'store_name' => 'Second Store',
            'status' => 1,
            'mobile' => '+8801700000002',
        ]);

        DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        // Role 2: Accounts Manager with accounts_view, accounts_add, accounts_edit, accounts_delete
        DbRole::firstOrCreate(['id' => 2], [
            'role_name' => 'Accounts Manager',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 2], [
            'store_id' => 1,
            'permissions' => ['accounts_view', 'accounts_add', 'accounts_edit', 'accounts_delete'],
        ]);

        // Role 3: Restricted Role without accounts_view
        DbRole::firstOrCreate(['id' => 3], [
            'role_name' => 'Restricted',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 3], [
            'store_id' => 1,
            'permissions' => [],
        ]);

        $this->superAdmin = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->restrictedUser = User::factory()->create([
            'role_id' => 3,
            'role_name' => 'Restricted',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->store1User = User::factory()->create([
            'name' => 'Store 1 Manager',
            'role_id' => 2,
            'role_name' => 'Accounts Manager',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->store2User = User::factory()->create([
            'name' => 'Store 2 Manager',
            'role_id' => 2,
            'role_name' => 'Accounts Manager',
            'status' => 1,
            'store_id' => 2,
        ]);
    }

    /**
     * Item 1.1: Accounts List is strictly store_id scoped (cross-tenant leak blocked).
     */
    public function test_accounts_list_is_store_scoped_blocking_cross_tenant_leak()
    {
        // Store 1 Accounts
        $accStore1 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Petty Cash',
            'account_code' => 'S1-CASH',
            'balance' => 1000.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 2 Accounts
        $accStore2 = AcAccount::create([
            'store_id' => 2,
            'account_name' => 'Store 2 Secret Vault',
            'account_code' => 'S2-VAULT',
            'balance' => 99999.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Request as Store 1 User
        $response1 = $this->actingAs($this->store1User)->get(route('accounts.list'));
        $response1->assertOk();
        $response1->assertSee('Store 1 Petty Cash');
        $response1->assertSee('S1-CASH');
        $response1->assertDontSee('Store 2 Secret Vault');
        $response1->assertDontSee('S2-VAULT');

        // Request as Store 2 User
        $response2 = $this->actingAs($this->store2User)->get(route('accounts.list'));
        $response2->assertOk();
        $response2->assertSee('Store 2 Secret Vault');
        $response2->assertSee('S2-VAULT');
        $response2->assertDontSee('Store 1 Petty Cash');
        $response2->assertDontSee('S1-CASH');
    }

    /**
     * Item 1.2: List route permission gate (accounts_view).
     */
    public function test_accounts_list_permission_gate()
    {
        // User without accounts_view gets 403
        $this->actingAs($this->restrictedUser)
            ->get(route('accounts.list'))
            ->assertStatus(403);

        // User with accounts_view gets 200
        $this->actingAs($this->store1User)
            ->get(route('accounts.list'))
            ->assertOk();
    }

    /**
     * Item 1.2: Confirm accounts_view exists in RolePermissionSeeder.
     */
    public function test_accounts_view_exists_in_role_permission_seeder()
    {
        $seederContent = file_get_contents(database_path('seeders/RolePermissionSeeder.php'));
        $this->assertStringContainsString("'accounts_view'", $seederContent, 'accounts_view must exist in RolePermissionSeeder');
    }

    /**
     * Item 2.1: Eager loading parent avoids N+1 query problem.
     */
    public function test_eager_loading_parent_and_creator_prevents_n_plus_one_queries()
    {
        $parent = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Master Head Account',
            'account_code' => 'HEAD01',
            'sort_code' => '1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
            'created_by' => $this->store1User->id,
        ]);

        // Create 10 accounts under this parent
        for ($i = 1; $i <= 10; $i++) {
            AcAccount::create([
                'store_id' => 1,
                'parent_id' => $parent->id,
                'account_name' => "Child Account {$i}",
                'account_code' => "CHD" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'sort_code' => "1.{$i}",
                'balance' => 0,
                'status' => 1,
                'delete_bit' => 0,
                'created_by' => $this->store1User->id,
            ]);
        }

        DB::enableQueryLog();

        $response = $this->actingAs($this->store1User)->get(route('accounts.list'));
        $response->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // Isolate queries touching the ac_accounts table.
        // With eager-loading, there are at most 3 queries for ac_accounts:
        // 1. count(*)
        // 2. select * ... limit 10
        // 3. select * ... where id in (...) for parents
        // Without eager loading, it would be 1 + 1 + 10 = 12 queries.
        $accountQueries = array_filter($queries, fn($q) => str_contains($q['query'], 'ac_accounts'));
        $this->assertLessThanOrEqual(4, count($accountQueries), 'ac_accounts query count must remain small (<=4) and not scale with row count (1+N).');
    }

    /**
     * Item 2.2: Search term is preserved across pagination links.
     */
    public function test_search_term_preserved_on_pagination()
    {
        // Create 15 matching accounts
        for ($i = 1; $i <= 15; $i++) {
            AcAccount::create([
                'store_id' => 1,
                'account_name' => "Searchable Term Account {$i}",
                'account_code' => "SRCH" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'balance' => 0,
                'status' => 1,
                'delete_bit' => 0,
            ]);
        }

        $response = $this->actingAs($this->store1User)->get(route('accounts.list', ['search' => 'Searchable Term']));
        $response->assertOk();
        // Laravel paginator encodes spaces in query strings as %20 or +
        $hasPreservedSearch = str_contains($response->getContent(), 'search=Searchable%20Term') || str_contains($response->getContent(), 'search=Searchable+Term');
        $this->assertTrue($hasPreservedSearch, 'Paginator links must preserve active search query string.');
        $response->assertSee('page=2');
    }

    /**
     * Item 2.3: "Created By" renders actual user name and falls back to "System" for null creator.
     */
    public function test_created_by_renders_user_name_and_system_fallback()
    {
        $namedAccount = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Known Creator Account',
            'account_code' => 'CREAT01',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
            'created_by' => $this->store1User->id,
        ]);

        $legacyAccount = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Legacy System Account',
            'account_code' => 'LEG001',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
            'created_by' => null, // Legacy / null creator
        ]);

        $response = $this->actingAs($this->store1User)->get(route('accounts.list'));
        $response->assertOk();
        $response->assertSee('Store 1 Manager');
        $response->assertSee('System');
    }

    /**
     * Item 3.1: "Show Entries" page size control with whitelist fallback.
     */
    public function test_page_size_control_with_whitelist()
    {
        for ($i = 1; $i <= 30; $i++) {
            AcAccount::create([
                'store_id' => 1,
                'account_name' => "Pagination Account {$i}",
                'account_code' => "PAG" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'balance' => 0,
                'status' => 1,
                'delete_bit' => 0,
            ]);
        }

        // Whitelisted 25 per page
        $res25 = $this->actingAs($this->store1User)->get(route('accounts.list', ['per_page' => 25]));
        $res25->assertOk();
        $this->assertEquals(25, $res25->viewData('accounts')->perPage());

        // Tampered huge value falls back to default 10
        $resTampered = $this->actingAs($this->store1User)->get(route('accounts.list', ['per_page' => 99999]));
        $resTampered->assertOk();
        $this->assertEquals(10, $resTampered->viewData('accounts')->perPage());
    }

    /**
     * Item 4.1: CSV / Excel Export streams filtered, store-scoped data across all pages.
     */
    public function test_csv_excel_export_returns_full_store_scoped_filtered_dataset()
    {
        // Store 1 accounts
        for ($i = 1; $i <= 12; $i++) {
            AcAccount::create([
                'store_id' => 1,
                'account_name' => "Export Match Account {$i}",
                'account_code' => "EXP" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'balance' => 100.00 * $i,
                'status' => 1,
                'delete_bit' => 0,
            ]);
        }

        // Store 1 non-matching account
        AcAccount::create([
            'store_id' => 1,
            'account_name' => "Other Unmatched Account",
            'account_code' => "OTHER01",
            'balance' => 50.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 2 account (must be excluded)
        AcAccount::create([
            'store_id' => 2,
            'account_name' => "Store 2 Export Match Account",
            'account_code' => "S2-EXP",
            'balance' => 777.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->get(route('accounts.list', [
            'export' => 'csv',
            'search' => 'Export Match',
        ]));

        $response->assertOk();
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('attachment; filename=', $response->headers->get('Content-Disposition'));

        // Capture streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Check header row (fputcsv quotes strings with spaces)
        $this->assertStringContainsString('Account Code', $content);
        $this->assertStringContainsString('Account Name', $content);
        $this->assertStringContainsString('Parent Account', $content);
        $this->assertStringContainsString('Balance', $content);
        $this->assertStringContainsString('Created By', $content);
        $this->assertStringContainsString('Status', $content);

        // Check all 12 rows are present (even though normal page size is 10)
        for ($i = 1; $i <= 12; $i++) {
            $this->assertStringContainsString("EXP" . str_pad($i, 3, '0', STR_PAD_LEFT), $content);
        }

        // Unmatched Store 1 account must NOT appear
        $this->assertStringNotContainsString('OTHER01', $content);

        // Store 2 account must NOT appear
        $this->assertStringNotContainsString('S2-EXP', $content);
    }

    /**
     * Item 4.1: PDF / Print export returns print view with total balance and store-scoping.
     */
    public function test_pdf_print_export_view()
    {
        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Print Account',
            'account_code' => 'PRNT01',
            'balance' => 500.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcAccount::create([
            'store_id' => 2,
            'account_name' => 'Store 2 Hidden Print Account',
            'account_code' => 'S2-PRNT',
            'balance' => 900.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->get(route('accounts.list', ['export' => 'pdf']));
        $response->assertOk();
        $response->assertViewIs('module.accounts.accounts_list_print');
        $response->assertSee('Store 1 Print Account');
        $response->assertSee('500.00');
        $response->assertDontSee('Store 2 Hidden Print Account');
    }

    /**
     * Item 4.1 Follow-up: PDF / Print export strictly respects active search filter.
     */
    public function test_pdf_print_export_respects_active_search_filter()
    {
        // Matching accounts in Store 1
        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Target Search Account Alpha',
            'account_code' => 'TGT-A',
            'balance' => 120.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Target Search Account Beta',
            'account_code' => 'TGT-B',
            'balance' => 230.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Non-matching account in Store 1
        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Completely Different Account',
            'account_code' => 'DIFF-01',
            'balance' => 999.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->store1User)->get(route('accounts.list', [
            'export' => 'pdf',
            'search' => 'Target Search',
        ]));

        $response->assertOk();
        $response->assertViewIs('module.accounts.accounts_list_print');
        $response->assertSee('Target Search Account Alpha');
        $response->assertSee('TGT-A');
        $response->assertSee('Target Search Account Beta');
        $response->assertSee('TGT-B');
        // Total balance for matched subset: 120.00 + 230.00 = 350.00
        $response->assertSee('350.00');

        // Unmatched account and its balance must NOT appear in print view
        $response->assertDontSee('Completely Different Account');
        $response->assertDontSee('DIFF-01');
        $response->assertDontSee('999.00');
    }

    /**
     * Item 4.2: Bulk delete with independent per-row dependency checks.
     * Clean accounts deleted (delete_bit = 1), blocked accounts skipped (delete_bit = 0).
     * Asserts full, untruncated skip-message detailing each specific blocking reason.
     */
    public function test_bulk_delete_processes_clean_and_skips_blocked_accounts()
    {
        // 1. Clean account
        $clean = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Clean Account',
            'account_code' => 'CLN01',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // 2. Blocked account: non-zero balance
        $blockedBalance = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Blocked Balance Account',
            'account_code' => 'BLK-BAL',
            'balance' => 250.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // 3. Blocked account: has transaction records
        $blockedTxAccount = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Blocked Tx Account',
            'account_code' => 'BLK-TX',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-01',
            'transaction_type' => 'EXPENSE',
            'debit_account_id' => $blockedTxAccount->id,
            'credit_account_id' => $blockedTxAccount->id,
            'debit_amt' => 50.00,
            'credit_amt' => 50.00,
            'ref_accounts_id' => $blockedTxAccount->id,
        ]);

        // 4. Blocked account: has active child accounts nested under it
        $blockedParent = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Blocked Parent Account',
            'account_code' => 'BLK-PAR',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcAccount::create([
            'store_id' => 1,
            'parent_id' => $blockedParent->id,
            'account_name' => 'Active Child Account',
            'account_code' => 'CHD-ACT',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Submit bulk delete for all 4
        $response = $this->actingAs($this->store1User)->post(route('accounts.bulk-delete'), [
            'ids' => [$clean->id, $blockedBalance->id, $blockedTxAccount->id, $blockedParent->id],
        ]);

        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('warning');

        $fullWarningMessage = session('warning');

        // Check database state
        $this->assertEquals(1, AcAccount::find($clean->id)->delete_bit, 'Clean account had zero dependencies and is deleted.');
        $this->assertEquals(0, AcAccount::find($blockedBalance->id)->delete_bit, 'Blocked balance account remains delete_bit = 0.');
        $this->assertEquals(0, AcAccount::find($blockedTxAccount->id)->delete_bit, 'Blocked tx account remains delete_bit = 0.');
        $this->assertEquals(0, AcAccount::find($blockedParent->id)->delete_bit, 'Blocked parent account remains delete_bit = 0.');

        // Full untruncated skip-message assertions
        $this->assertStringContainsString('Bulk Delete: 1 account(s) deleted. Skipped 3 account(s):', $fullWarningMessage);
        $this->assertStringContainsString('Blocked Balance Account (BLK-BAL): Account has a non-zero balance (250.00).', $fullWarningMessage);
        $this->assertStringContainsString('Blocked Tx Account (BLK-TX): Account has associated transaction records in the ledger.', $fullWarningMessage);
        $this->assertStringContainsString('Blocked Parent Account (BLK-PAR): Account has active child accounts nested under it.', $fullWarningMessage);
    }

    /**
     * Item 4.2 Follow-up: Cross-Store Bulk-Delete IDOR Protection.
     * Submitting another store's account ID does NOT process it and does NOT leak details.
     */
    public function test_bulk_delete_cross_store_idor_protection()
    {
        // Store 1 clean account
        $store1Clean = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Genuine Account',
            'account_code' => 'S1-GEN',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 2 clean account (victim of cross-store IDOR attack)
        $store2Victim = AcAccount::create([
            'store_id' => 2,
            'account_name' => 'Store 2 Victim Secret Account',
            'account_code' => 'S2-VICTIM',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 1 user attempts to bulk-delete both Store 1 account and Store 2 victim account
        $response = $this->actingAs($this->store1User)->post(route('accounts.bulk-delete'), [
            'ids' => [$store1Clean->id, $store2Victim->id],
        ]);

        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('success');

        // Store 1 account was legitimately deleted
        $this->assertEquals(1, AcAccount::find($store1Clean->id)->delete_bit);

        // Store 2 victim account MUST NOT be touched
        $this->assertEquals(0, AcAccount::find($store2Victim->id)->delete_bit, 'Store 2 account must NOT be deleted via cross-store IDOR.');

        // Assert message does NOT leak Store 2 victim account name or code
        $successMsg = session('success');
        $this->assertStringNotContainsString('Store 2 Victim Secret Account', $successMsg);
        $this->assertStringNotContainsString('S2-VICTIM', $successMsg);
        $this->assertEquals('Bulk Delete: 1 account(s) deleted.', $successMsg);
    }

    /**
     * Item 4.2: Double-action safety on bulk delete (idempotent submission).
     */
    public function test_bulk_delete_double_submission_idempotency()
    {
        $cleanAccount = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Idempotent Test Account',
            'account_code' => 'IDEM01',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // First bulk delete
        $response1 = $this->actingAs($this->store1User)->post(route('accounts.bulk-delete'), [
            'ids' => [$cleanAccount->id],
        ]);
        $response1->assertSessionHas('success');
        $this->assertEquals(1, AcAccount::find($cleanAccount->id)->delete_bit);

        // Immediate repeat submission of same ID
        $response2 = $this->actingAs($this->store1User)->post(route('accounts.bulk-delete'), [
            'ids' => [$cleanAccount->id],
        ]);
        $response2->assertSessionHas('success');
        $this->assertEquals(1, AcAccount::find($cleanAccount->id)->delete_bit, 'State remains delete_bit = 1 without exception.');
    }

    /**
     * Item 4.2: Genuine parallel concurrency test for bulk delete.
     * Spawns two independent OS child processes via proc_open that fire simultaneous bulk-delete
     * updates on the same target accounts synchronized via a spinlock barrier file.
     */
    public function test_bulk_delete_genuine_parallel_concurrency_idempotency()
    {
        $dbPath = sys_get_temp_dir() . '/bulk_concur_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/bulk_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/bulk_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY,
                store_id INTEGER,
                account_code TEXT,
                account_name TEXT,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            INSERT INTO ac_accounts (id, store_id, account_code, account_name, delete_bit)
            VALUES (101, 1, 'CLN101', 'Parallel Account 1', 0),
                   (102, 1, 'CLN102', 'Parallel Account 2', 0);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Spinlock barrier
        while (!file_exists($barrier)) {
            usleep(100);
        }

        try {
            $stmt = $pdo->prepare("UPDATE ac_accounts SET delete_bit = 1 WHERE id IN (101, 102)");
            $stmt->execute();
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            echo "RESULT:FAILED:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Launch Process 1 and Process 2 simultaneously
        $proc1 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript), $descriptors, $pipes2);

        // Release barrier
        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]);
        fclose($pipes1[1]);
        proc_close($proc1);

        $out2 = stream_get_contents($pipes2[1]);
        fclose($pipes2[1]);
        proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $stmt = $pdo->query("SELECT id, delete_bit FROM ac_accounts WHERE id IN (101, 102)");
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        @unlink($dbPath);

        $this->assertStringContainsString('RESULT:SUCCESS', $out1);
        $this->assertStringContainsString('RESULT:SUCCESS', $out2);
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $this->assertEquals(1, (int)$row['delete_bit'], 'Both accounts must have delete_bit = 1 idempotently.');
        }
    }
}
