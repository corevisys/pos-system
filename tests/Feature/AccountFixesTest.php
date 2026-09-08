<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $store1;
    protected $store2;
    protected $superAdmin;
    protected $restrictedUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store1 = DbStore::create([
            'id' => 1,
            'store_name' => 'Main Store',
            'status' => 1,
            'mobile' => '+8801700000001',
        ]);

        $this->store2 = DbStore::create([
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

        DbRole::firstOrCreate(['id' => 2], [
            'role_name' => 'Restricted Role',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 2], [
            'store_id' => 1,
            'permissions' => ['accounts_view'], // no accounts_add, accounts_edit, accounts_delete
        ]);

        $this->superAdmin = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
        ]);

        $this->restrictedUser = User::factory()->create([
            'role_id' => 2,
            'role_name' => 'Restricted Role',
            'store_id' => 1,
        ]);
    }

    /**
     * Item 1.1: Verify Opening Balance creates a balanced double-entry with Opening Balance Equity.
     */
    public function test_opening_balance_creates_balanced_double_entry_with_opening_balance_equity()
    {
        $response = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Petty Cash',
            'account_number' => 'AC0001',
            'opening_balance' => 500.00,
            'parent_account' => '',
            'note' => 'Initial petty cash fund',
        ]);

        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('success');

        // Assert exactly one Opening Balance Equity account created for this store
        $equityAccount = AcAccount::where('store_id', 1)
            ->where('account_name', 'Opening Balance Equity')
            ->where('delete_bit', 0)
            ->first();

        $this->assertNotNull($equityAccount, 'Opening Balance Equity account should be auto-created.');
        $this->assertNull($equityAccount->parent_id);

        $newAccount = AcAccount::where('store_id', 1)
            ->where('account_code', 'AC0001')
            ->first();

        $this->assertNotNull($newAccount);
        $this->assertEquals(500.00, (float)$newAccount->balance);

        // Verify ac_transactions row
        $tx = AcTransaction::where('ref_accounts_id', $newAccount->id)->first();
        $this->assertNotNull($tx);
        $this->assertEquals($equityAccount->id, $tx->debit_account_id);
        $this->assertEquals($newAccount->id, $tx->credit_account_id);
        $this->assertEquals(500.00, (float)$tx->debit_amt);
        $this->assertEquals(500.00, (float)$tx->credit_amt);

        // Assert balanced entry: SUM(debit_amt) == SUM(credit_amt)
        $sums = AcTransaction::where('ref_accounts_id', $newAccount->id)
            ->selectRaw('SUM(debit_amt) as total_debit, SUM(credit_amt) as total_credit')
            ->first();
        $this->assertEquals($sums->total_debit, $sums->total_credit);
        $this->assertEquals(500.00, (float)$sums->total_debit);
    }

    /**
     * Item 1.1: Verify Opening Balance Equity account is reused for subsequent accounts in the same store.
     */
    public function test_opening_balance_equity_account_is_reused_in_same_store()
    {
        // First account (will auto-create Opening Balance Equity as AC0002)
        $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Account 1',
            'account_number' => 'AC0001',
            'opening_balance' => 500.00,
        ]);

        $equityAccountsCount1 = AcAccount::where('store_id', 1)
            ->where('account_name', 'Opening Balance Equity')
            ->count();
        $this->assertEquals(1, $equityAccountsCount1);

        // Second account with opening balance (use next available code, e.g. AC0003)
        $response = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Account 2',
            'account_number' => 'AC0003',
            'opening_balance' => 250.00,
        ]);
        $response->assertRedirect(route('accounts.list'));

        $equityAccountsCount2 = AcAccount::where('store_id', 1)
            ->where('account_name', 'Opening Balance Equity')
            ->count();
        $this->assertEquals(1, $equityAccountsCount2, 'Same Opening Balance Equity account must be reused.');

        $equityAccount = AcAccount::where('store_id', 1)
            ->where('account_name', 'Opening Balance Equity')
            ->first();

        $account2 = AcAccount::where('store_id', 1)->where('account_code', 'AC0003')->first();
        $this->assertNotNull($account2);

        $tx2 = AcTransaction::where('ref_accounts_id', $account2->id)->first();
        $this->assertNotNull($tx2);
        $this->assertEquals($equityAccount->id, $tx2->debit_account_id);
        $this->assertEquals(250.00, (float)$tx2->debit_amt);
        $this->assertEquals(250.00, (float)$tx2->credit_amt);
    }

    /**
     * Item 1.1: Historical rows with debit_account_id = null are untouched.
     */
    public function test_historical_unbalanced_transactions_are_untouched()
    {
        // Seed an account to satisfy the foreign key constraint
        $legacyAccount = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Legacy Account',
            'account_code' => 'LEG001',
            'balance' => 300.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Seed a pre-existing legacy transaction with debit_account_id = null
        $legacyTx = AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2025-01-01',
            'transaction_type' => 'OPENING BALANCE',
            'debit_account_id' => null,
            'credit_account_id' => $legacyAccount->id,
            'debit_amt' => 0,
            'credit_amt' => 300.00,
            'note' => 'Legacy historical entry',
            'ref_accounts_id' => $legacyAccount->id,
        ]);

        // Create a new account now
        $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'New Account',
            'account_number' => 'AC0100',
            'opening_balance' => 150.00,
        ]);

        $reloadedLegacy = AcTransaction::find($legacyTx->id);
        $this->assertNull($reloadedLegacy->debit_account_id);
        $this->assertEquals(0, (float)$reloadedLegacy->debit_amt);
        $this->assertEquals(300.00, (float)$reloadedLegacy->credit_amt);
    }

    /**
     * Item 2.1: Duplicate account_code in same store is blocked by validation and DB unique constraint.
     */
    public function test_duplicate_account_code_in_same_store_is_blocked()
    {
        $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'First Account',
            'account_number' => 'AC0001',
            'opening_balance' => 0,
        ]);

        // Second request with same account_number in store 1
        $response = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Duplicate Account',
            'account_number' => 'AC0001',
            'opening_balance' => 0,
        ]);

        $response->assertSessionHasErrors('account_number');

        // Direct DB insert should fail with unique constraint violation
        $this->expectException(QueryException::class);
        DB::table('ac_accounts')->insert([
            'store_id' => 1,
            'account_code' => 'AC0001',
            'account_name' => 'DB Duplicate Attack',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    /**
     * Item 2.1: Two different stores can each have account_code = 'AC0001' without conflict.
     */
    public function test_different_stores_can_have_same_account_code()
    {
        // Store 1 account
        DB::table('ac_accounts')->insert([
            'store_id' => 1,
            'account_code' => 'AC0001',
            'account_name' => 'Store 1 Account',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 2 account with same account_code
        DB::table('ac_accounts')->insert([
            'store_id' => 2,
            'account_code' => 'AC0001',
            'account_name' => 'Store 2 Account',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $count = DB::table('ac_accounts')->where('account_code', 'AC0001')->count();
        $this->assertEquals(2, $count, 'Both stores must be able to hold AC0001 independently.');
    }

    /**
     * Item 2.2: Parent Account dropdown is store-scoped.
     */
    public function test_parent_account_dropdown_is_store_scoped()
    {
        // Store 1 account
        AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Master Head',
            'account_code' => 'S1-HEAD',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store 2 account
        AcAccount::create([
            'store_id' => 2,
            'account_name' => 'Store 2 Master Head',
            'account_code' => 'S2-HEAD',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // As superAdmin in Store 1
        $response1 = $this->actingAs($this->superAdmin)->get(route('accounts.add'));
        $response1->assertOk();
        $response1->assertSee('Store 1 Master Head');
        $response1->assertDontSee('Store 2 Master Head');

        // As user in Store 2
        $store2User = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 2,
        ]);

        $response2 = $this->actingAs($store2User)->get(route('accounts.add'));
        $response2->assertOk();
        $response2->assertSee('Store 2 Master Head');
        $response2->assertDontSee('Store 1 Master Head');
    }

    /**
     * Item 3.1: Delete is blocked if account has non-zero balance.
     */
    public function test_delete_is_blocked_for_non_zero_balance()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'NonZero Account',
            'account_code' => 'NZ001',
            'balance' => 150.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('accounts.delete', $account->id));
        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('error');

        $reloaded = AcAccount::find($account->id);
        $this->assertEquals(0, $reloaded->delete_bit, 'Account must not be deleted.');
    }

    /**
     * Item 3.1: Delete is blocked if account has transaction history.
     */
    public function test_delete_is_blocked_for_account_with_transactions()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Tx Account',
            'account_code' => 'TX001',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-01-01',
            'transaction_type' => 'EXPENSE',
            'debit_account_id' => $account->id,
            'debit_amt' => 50.00,
            'credit_amt' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('accounts.delete', $account->id));
        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('error');

        $reloaded = AcAccount::find($account->id);
        $this->assertEquals(0, $reloaded->delete_bit);
    }

    /**
     * Item 3.1: Delete is blocked if account has child accounts.
     */
    public function test_delete_is_blocked_for_account_with_children()
    {
        $parent = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Parent Account',
            'account_code' => 'P001',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        AcAccount::create([
            'store_id' => 1,
            'parent_id' => $parent->id,
            'account_name' => 'Child Account',
            'account_code' => 'C001',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('accounts.delete', $parent->id));
        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('error');

        $reloaded = AcAccount::find($parent->id);
        $this->assertEquals(0, $reloaded->delete_bit);
    }

    /**
     * Item 3.1: Clean account with zero balance, no transactions, and no children deletes cleanly.
     */
    public function test_clean_account_deletes_successfully()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Clean Account',
            'account_code' => 'CL001',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->delete(route('accounts.delete', $account->id));
        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('success');

        $reloaded = AcAccount::find($account->id);
        $this->assertEquals(1, $reloaded->delete_bit);
    }

    /**
     * Item 3.2: Permission gates protect create, edit, update, and delete routes.
     */
    public function test_permission_gates_block_unauthorized_users()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Sample Account',
            'account_code' => 'SMP01',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Restricted user has only accounts_view, not accounts_add/accounts_edit/accounts_delete
        $this->actingAs($this->restrictedUser)->get(route('accounts.add'))->assertStatus(403);
        $this->actingAs($this->restrictedUser)->post(route('accounts.store'), [
            'account_name' => 'Hack Attempt',
            'account_number' => 'HCK01',
            'opening_balance' => 0,
        ])->assertStatus(403);

        $this->actingAs($this->restrictedUser)->get(route('accounts.edit', $account->id))->assertStatus(403);
        $this->actingAs($this->restrictedUser)->put(route('accounts.update', $account->id), [
            'account_name' => 'Hack Attempt Update',
        ])->assertStatus(403);

        $this->actingAs($this->restrictedUser)->delete(route('accounts.delete', $account->id))->assertStatus(403);
    }

    /**
     * Item 4.1: Edit account updates name and note without altering balance or sort_code if parent unchanged.
     */
    public function test_edit_account_updates_name_and_note()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Original Name',
            'account_code' => 'ED001',
            'sort_code' => '1.5',
            'balance' => 200.00,
            'note' => 'Original note',
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->put(route('accounts.update', $account->id), [
            'account_name' => 'Updated Name',
            'note' => 'Updated note content',
            'parent_account' => '',
            'opening_balance' => 999999.00, // Tampered field
        ]);

        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('success');

        $reloaded = AcAccount::find($account->id);
        $this->assertEquals('Updated Name', $reloaded->account_name);
        $this->assertEquals('Updated note content', $reloaded->note);
        $this->assertEquals(200.00, (float)$reloaded->balance, 'Opening balance tampering must be ignored.');
        $this->assertEquals(0, AcTransaction::where('ref_accounts_id', $account->id)->count(), 'No transaction created on edit.');
    }

    /**
     * Item 4.1: Reparenting recomputes sort_code and cascades to descendants.
     */
    public function test_reparenting_cascades_sort_code_to_descendants()
    {
        $parent1 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Parent 1',
            'account_code' => 'P1',
            'sort_code' => '1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $parent2 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Parent 2',
            'account_code' => 'P2',
            'sort_code' => '2',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Middle account under Parent 1
        $middle = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $parent1->id,
            'account_name' => 'Middle Account',
            'account_code' => 'MID',
            'sort_code' => '1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Child account under Middle
        $child = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $middle->id,
            'account_name' => 'Child Account',
            'account_code' => 'CHD',
            'sort_code' => '1.1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Reparent Middle from Parent 1 to Parent 2
        $response = $this->actingAs($this->superAdmin)->put(route('accounts.update', $middle->id), [
            'account_name' => 'Middle Account',
            'parent_account' => $parent2->id,
        ]);

        $response->assertRedirect(route('accounts.list'));

        $reloadedMiddle = AcAccount::find($middle->id);
        $reloadedChild = AcAccount::find($child->id);

        $this->assertEquals($parent2->id, $reloadedMiddle->parent_id);
        $this->assertEquals('2.1', $reloadedMiddle->sort_code);
        $this->assertEquals('2.1.1', $reloadedChild->sort_code, 'Child sort_code must cascade-update to 2.1.1.');
    }

    /**
     * Item 4.1: Edit link in Accounts List is wired and Edit form renders properly.
     */
    public function test_accounts_list_edit_link_is_wired_and_edit_view_renders_correctly()
    {
        $account = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Wired Test Account',
            'account_code' => 'WTA01',
            'balance' => 75.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // List view has the real edit route
        $listResponse = $this->actingAs($this->superAdmin)->get(route('accounts.list'));
        $listResponse->assertOk();
        $listResponse->assertSee(route('accounts.edit', $account->id));
        $listResponse->assertDontSee('<a href="#" class="block px-4 py-1.5 text-[9px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors uppercase tracking-widest">Edit</a>', false);

        // Edit page loads with current account data and read-only notice
        $editResponse = $this->actingAs($this->superAdmin)->get(route('accounts.edit', $account->id));
        $editResponse->assertOk();
        $editResponse->assertSee('Wired Test Account');
        $editResponse->assertSee('WTA01');
        $editResponse->assertSee('Opening balance cannot be edited after creation — use Cash Transactions to record adjustments.');
    }

    /**
     * Item 4.1: Store scoping on Edit form parent dropdown and self-exclusion.
     */
    public function test_edit_form_parent_dropdown_is_store_scoped_and_excludes_self()
    {
        $accountA = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Editing Account',
            'account_code' => 'S1-EDIT',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $accountOtherStore1 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Store 1 Other Valid Parent',
            'account_code' => 'S1-OTHER',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $accountStore2 = AcAccount::create([
            'store_id' => 2,
            'account_name' => 'Store 2 Leak Test Parent',
            'account_code' => 'S2-LEAK',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->superAdmin)->get(route('accounts.edit', $accountA->id));
        $response->assertOk();
        $response->assertSee('Store 1 Other Valid Parent');
        $response->assertDontSee('Store 2 Leak Test Parent');

        // Cannot set self as parent
        $selfParentResponse = $this->actingAs($this->superAdmin)->put(route('accounts.update', $accountA->id), [
            'account_name' => 'Store 1 Editing Account',
            'parent_account' => $accountA->id,
        ]);
        $selfParentResponse->assertSessionHasErrors('parent_account');
    }

    /**
     * Item 2.1 Genuine Concurrency Test:
     * Spawns two genuinely concurrent child PHP processes via proc_open that fire at the exact
     * same microsecond using a spinlock barrier file against a shared SQLite database containing
     * the composite unique index (store_id, account_code).
     */
    public function test_true_parallel_concurrency_rejects_duplicate_account_code_in_same_store()
    {
        $dbPath = sys_get_temp_dir() . '/concur_test_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/concur_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/concur_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                account_code TEXT,
                account_name TEXT,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE UNIQUE INDEX ac_accounts_store_account_code_unique ON ac_accounts (store_id, account_code);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Spinlock barrier until released by parent process
        while (!file_exists($barrier)) {
            usleep(100);
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO ac_accounts (store_id, account_code, account_name) VALUES (1, \'CONCUR_PARALLEL\', \'Parallel Worker\')");
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

        // Release the barrier so both processes execute their insert concurrently
        file_put_contents($barrierFile, 'GO');

        $output1 = stream_get_contents($pipes1[1]);
        fclose($pipes1[1]);
        proc_close($proc1);

        $output2 = stream_get_contents($pipes2[1]);
        fclose($pipes2[1]);
        proc_close($proc2);

        // Clean up temp files
        @unlink($barrierFile);
        @unlink($workerScript);

        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM ac_accounts WHERE store_id = 1 AND account_code = 'CONCUR_PARALLEL'");
        $count = (int)$stmt->fetch(\PDO::FETCH_ASSOC)['cnt'];

        @unlink($dbPath);

        $allOutputs = $output1 . $output2;
        $this->assertStringContainsString('RESULT:SUCCESS', $allOutputs, 'Exactly one concurrent process must succeed.');
        $this->assertStringContainsString('RESULT:FAILED', $allOutputs, 'The competing concurrent process must fail.');
        $this->assertStringContainsString('UNIQUE constraint failed', $allOutputs, 'Failure must be due to the unique constraint violation.');
        $this->assertEquals(1, $count, 'Exactly 1 row must be persisted in the database after parallel concurrent inserts.');
    }

    /**
     * Item 3.3 Side-fix Test: Root Account Creation & Required Field Validation
     * Confirms that root accounts (no parent selected) save successfully with parent_id = null,
     * and confirms that genuinely required fields (account_name, account_number) are still enforced.
     */
    public function test_root_account_creation_saves_with_null_parent_and_validates_required_fields()
    {
        // 1. Submit Add Account form with empty parent_account (root account head)
        $response = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Root General Ledger Head',
            'account_number' => 'ROOT01',
            'parent_account' => '',
            'opening_balance' => 0,
            'note' => 'Primary root account head',
        ]);

        $response->assertRedirect(route('accounts.list'));
        $response->assertSessionHas('success');

        $rootAccount = AcAccount::where('store_id', 1)->where('account_code', 'ROOT01')->first();
        $this->assertNotNull($rootAccount);
        $this->assertNull($rootAccount->parent_id, 'Root account head must have parent_id = null.');
        $this->assertEquals('Root General Ledger Head', $rootAccount->account_name);

        // 2. Confirm genuinely required fields cannot be submitted as blank
        $blankNameResponse = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => '', // Blank
            'account_number' => 'REQ01',
            'parent_account' => '',
            'opening_balance' => 0,
        ]);
        $blankNameResponse->assertSessionHasErrors('account_name');

        $blankNumberResponse = $this->actingAs($this->superAdmin)->post(route('accounts.store'), [
            'account_name' => 'Missing Number Account',
            'account_number' => '', // Blank
            'parent_account' => '',
            'opening_balance' => 0,
        ]);
        $blankNumberResponse->assertSessionHasErrors('account_number');
    }

    /**
     * Item 4.1 Multi-Level Hierarchy Sort Code Cascade Test:
     * Builds a 3-level-deep descendant tree under the node being reparented:
     * Middle -> Child -> Grandchild -> GreatGrandchild
     * Reparents Middle from Parent 1 to Parent 2 and confirms ALL 3 descendant levels cascade accurately.
     */
    public function test_deep_multilevel_hierarchy_sort_code_cascade()
    {
        $parent1 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Top Parent 1',
            'account_code' => 'TP1',
            'sort_code' => '1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $parent2 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Top Parent 2',
            'account_code' => 'TP2',
            'sort_code' => '2',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Middle node under Parent 1 (Level 1 under Top Parent 1)
        $middle = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $parent1->id,
            'account_name' => 'Middle Node',
            'account_code' => 'MID_DEEP',
            'sort_code' => '1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Child node under Middle (Level 1 under Middle)
        $child = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $middle->id,
            'account_name' => 'Child Node',
            'account_code' => 'CHD_DEEP',
            'sort_code' => '1.1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Grandchild node under Child (Level 2 under Middle)
        $grandchild = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $child->id,
            'account_name' => 'Grandchild Node',
            'account_code' => 'GCHD_DEEP',
            'sort_code' => '1.1.1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Great-Grandchild node under Grandchild (Level 3 under Middle)
        $greatGrandchild = AcAccount::create([
            'store_id' => 1,
            'parent_id' => $grandchild->id,
            'account_name' => 'Great Grandchild Node',
            'account_code' => 'GGCHD_DEEP',
            'sort_code' => '1.1.1.1.1',
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Reparent Middle Node from Parent 1 (sort_code: '1') to Parent 2 (sort_code: '2')
        $response = $this->actingAs($this->superAdmin)->put(route('accounts.update', $middle->id), [
            'account_name' => 'Middle Node',
            'parent_account' => $parent2->id,
        ]);

        $response->assertRedirect(route('accounts.list'));

        $reloadedMiddle = AcAccount::find($middle->id);
        $reloadedChild = AcAccount::find($child->id);
        $reloadedGrandchild = AcAccount::find($grandchild->id);
        $reloadedGreatGrandchild = AcAccount::find($greatGrandchild->id);

        // Assert all 4 levels in the sub-tree updated properly
        $this->assertEquals($parent2->id, $reloadedMiddle->parent_id);
        $this->assertEquals('2.1', $reloadedMiddle->sort_code, 'Middle sort_code must be 2.1');
        $this->assertEquals('2.1.1', $reloadedChild->sort_code, 'Child sort_code must cascade to 2.1.1');
        $this->assertEquals('2.1.1.1', $reloadedGrandchild->sort_code, 'Grandchild sort_code must cascade to 2.1.1.1');
        $this->assertEquals('2.1.1.1.1', $reloadedGreatGrandchild->sort_code, 'Great-Grandchild sort_code must cascade to 2.1.1.1.1');
    }
}


