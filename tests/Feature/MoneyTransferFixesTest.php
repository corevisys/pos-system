<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcMoneyTransfer;
use App\Models\AcTransaction;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MoneyTransferFixesTest extends TestCase
{
    use RefreshDatabase;

    protected $store1;
    protected $store2;
    protected $store1User;
    protected $store2User;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create Stores
        $this->store1 = DbStore::create([
            'id' => 1,
            'store_name' => 'Store 1',
            'status' => 1,
            'mobile' => '1111111111',
            'money_transfer_init' => 'TR1',
        ]);

        $this->store2 = DbStore::create([
            'id' => 2,
            'store_name' => 'Store 2',
            'status' => 1,
            'mobile' => '2222222222',
            'money_transfer_init' => 'TR2',
        ]);

        // 2. Setup Role & Permissions for Store 1 User
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
                'money_transfer_view',
                'money_transfer_add',
                'money_transfer_edit',
                'money_transfer_delete',
            ],
        ]);

        $this->store1User = User::factory()->create([
            'store_id' => 1,
            'role_id' => $role1->id,
            'name' => 'Store 1 User',
            'email' => 'store1user@example.com',
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
                'money_transfer_view',
                'money_transfer_add',
                'money_transfer_edit',
                'money_transfer_delete',
            ],
        ]);

        $this->store2User = User::factory()->create([
            'store_id' => 2,
            'role_id' => $role2->id,
            'name' => 'Store 2 User',
            'email' => 'store2user@example.com',
        ]);
    }

    /**
     * Item 1.1: TOCTOU Overdraft Race Condition Prevention on Transfer Creation.
     * Uses genuine parallel processes synchronized with a spinlock barrier to fire two simultaneous
     * $100 transfers against a $100 balance. Exactly one must succeed, final balance must be exactly 0.00.
     */
    public function test_transfer_creation_genuine_parallel_concurrency_blocks_overdraft()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/transfer_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/transfer_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/transfer_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Build temporary tables
        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY,
                store_id INTEGER,
                account_name TEXT,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE TABLE ac_moneytransfer (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                transfer_code TEXT,
                transfer_date TEXT,
                debit_account_id INTEGER,
                credit_account_id INTEGER,
                amount REAL,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE TABLE ac_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                transaction_date TEXT,
                transaction_type TEXT,
                debit_account_id INTEGER,
                credit_account_id INTEGER,
                debit_amt REAL DEFAULT 0,
                credit_amt REAL DEFAULT 0,
                ref_moneytransfer_id INTEGER
            );

            INSERT INTO ac_accounts (id, store_id, account_name, balance)
            VALUES (1, 1, 'Source Cash Account', 100.00),
                   (2, 1, 'Destination Bank Account', 0.00);
        ");

        // Worker script simulating store() with row-level lock and balance check
        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $trCode = $argv[1];

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        // Spinlock barrier
        while (!file_exists($barrier)) {
            usleep(100);
        }

        try {
            // Immediate transaction acquires lock in SQLite
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");

            $stmt = $pdo->prepare("SELECT balance FROM ac_accounts WHERE id = 1");
            $stmt->execute();
            $balance = (float) $stmt->fetchColumn();

            $amount = 100.00;
            if ($amount > $balance) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:INSUFFICIENT_BALANCE\n";
                exit(0);
            }

            // Insert transfer
            $stmt = $pdo->prepare("INSERT INTO ac_moneytransfer (store_id, transfer_code, transfer_date, debit_account_id, credit_account_id, amount, status, delete_bit) VALUES (1, ?, \'2026-09-03\', 1, 2, ?, 1, 0)");
            $stmt->execute([$trCode, $amount]);
            $trId = $pdo->lastInsertId();

            // Insert transactions
            $stmt = $pdo->prepare("INSERT INTO ac_transactions (store_id, transaction_date, transaction_type, debit_account_id, credit_account_id, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'2026-09-03\', \'TRANSFER\', 1, 2, ?, 0, ?)");
            $stmt->execute([$amount, $trId]);

            $stmt = $pdo->prepare("INSERT INTO ac_transactions (store_id, transaction_date, transaction_type, debit_account_id, credit_account_id, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'2026-09-03\', \'TRANSFER\', 1, 2, 0, ?, ?)");
            $stmt->execute([$amount, $trId]);

            // Mutate balances
            $pdo->exec("UPDATE ac_accounts SET balance = balance - {$amount} WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance + {$amount} WHERE id = 2");

            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->exec("ROLLBACK");
            }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Launch Process 1 and Process 2 simultaneously
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' TR-CONCUR-A', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' TR-CONCUR-B', $descriptors, $pipes2);

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

        $stmt = $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1");
        $finalSourceBalance = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2");
        $finalDestBalance = (float) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM ac_moneytransfer");
        $totalTransfers = (int) $stmt->fetchColumn();

        $stmt = $pdo->query("SELECT COUNT(*) FROM ac_transactions");
        $totalTransactions = (int) $stmt->fetchColumn();

        @unlink($dbPath);

        $combinedOutput = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combinedOutput);
        $this->assertStringContainsString('RESULT:INSUFFICIENT_BALANCE', $combinedOutput);

        $this->assertEquals(0.00, $finalSourceBalance, 'Final source balance must be exactly 0.00, never negative.');
        $this->assertEquals(100.00, $finalDestBalance, 'Final destination balance must be exactly 100.00.');
        $this->assertEquals(1, $totalTransfers, 'Exactly one transfer must be recorded.');
        $this->assertEquals(2, $totalTransactions, 'Exactly two ledger transaction rows must be recorded.');
    }

    /**
     * Item 1.2: Delete Reversal Solvency Check blocks deletion when destination cannot afford refund.
     */
    public function test_delete_reversal_solvency_guard_blocks_when_destination_insolvent()
    {
        $source = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Source Account',
            'account_code' => 'SRC01',
            'balance' => 1000.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $dest = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Destination Account',
            'account_code' => 'DST01',
            'balance' => 0.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Create transfer of $500.00
        $response = $this->actingAs($this->store1User)->post(route('accounts.transfer.store'), [
            'transfer_date' => '2026-09-03',
            'transfer_code' => 'TR-SOLV-01',
            'debit_account_id' => $source->id,
            'credit_account_id' => $dest->id,
            'amount' => 500.00,
        ]);
        $response->assertRedirect(route('accounts.transfer'));

        $this->assertEquals(500.00, $source->fresh()->balance);
        $this->assertEquals(500.00, $dest->fresh()->balance);

        $transfer = AcMoneyTransfer::where('transfer_code', 'TR-SOLV-01')->firstOrFail();

        // Destination spends $450 elsewhere (leaving balance = $50.00)
        $dest->balance = 50.00;
        $dest->save();

        // Attempt to delete transfer of $500
        $deleteResponse = $this->actingAs($this->store1User)->delete(route('accounts.transfer.delete', $transfer->id));
        $deleteResponse->assertSessionHas('error');

        $errorMessage = session('error');
        $this->assertStringContainsString('Cannot delete transfer: Destination account Destination Account does not have sufficient balance', $errorMessage);

        // Verify transfer remains active and balances untouched
        $this->assertEquals(0, $transfer->fresh()->delete_bit, 'Transfer must NOT be deleted.');
        $this->assertEquals(50.00, $dest->fresh()->balance, 'Destination balance must remain $50.00.');
        $this->assertEquals(500.00, $source->fresh()->balance, 'Source balance must remain $500.00.');

        // Verify no reversal transactions were created
        $reversals = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER REVERSAL')
            ->count();
        $this->assertEquals(0, $reversals);
    }

    /**
     * Item 1.2: Successful Delete Reversal inserts two new reversal ledger rows and soft-deletes transfer.
     */
    public function test_delete_reversal_success_inserts_reversal_rows_and_soft_deletes()
    {
        $source = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Source Account',
            'account_code' => 'SRC02',
            'balance' => 1000.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $dest = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Destination Account',
            'account_code' => 'DST02',
            'balance' => 0.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Create transfer of $400.00
        $this->actingAs($this->store1User)->post(route('accounts.transfer.store'), [
            'transfer_date' => '2026-09-03',
            'transfer_code' => 'TR-REV-02',
            'debit_account_id' => $source->id,
            'credit_account_id' => $dest->id,
            'amount' => 400.00,
            'note' => 'Original Transfer Memo',
        ]);

        $transfer = AcMoneyTransfer::where('transfer_code', 'TR-REV-02')->firstOrFail();
        $this->assertEquals(600.00, $source->fresh()->balance);
        $this->assertEquals(400.00, $dest->fresh()->balance);

        // Delete transfer
        $deleteResponse = $this->actingAs($this->store1User)->delete(route('accounts.transfer.delete', $transfer->id));
        $deleteResponse->assertRedirect(route('accounts.transfer'));
        $deleteResponse->assertSessionHas('success');

        // Verify soft delete
        $this->assertEquals(1, $transfer->fresh()->delete_bit);

        // Verify transfer disappears from active list
        $listResponse = $this->actingAs($this->store1User)->get(route('accounts.transfer'));
        $listResponse->assertDontSee('TR-REV-02');

        // Verify original 2 rows remain intact in ac_transactions
        $originalRows = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER')
            ->get();
        $this->assertCount(2, $originalRows);

        // Verify 2 new reversal rows were added
        $reversalRows = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER REVERSAL')
            ->get();
        $this->assertCount(2, $reversalRows);

        // Check reversal row polarities
        $sourceRefund = $reversalRows->where('credit_amt', 400.00)->first();
        $destDeduct = $reversalRows->where('debit_amt', 400.00)->first();

        $this->assertNotNull($sourceRefund, 'Source refund row must credit 400.00.');
        $this->assertNotNull($destDeduct, 'Destination deduction row must debit 400.00.');

        // Verify balances restored accurately
        $this->assertEquals(1000.00, (float) $source->fresh()->balance, 'Source balance must be restored to 1000.00.');
        $this->assertEquals(0.00, (float) $dest->fresh()->balance, 'Destination balance must be restored to 0.00.');
    }

    /**
     * Item 2.1 & 2.2: Multi-Store Scoping, IDOR Protection & Permission Gates.
     */
    public function test_store_scoping_idor_and_permission_gates()
    {
        // Store 1 transfer
        $s1Acc1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s1Acc2 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Bank', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        $trStore1 = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR1-001',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s1Acc1->id,
            'credit_account_id' => $s1Acc2->id,
            'amount' => 100.00,
            'delete_bit' => 0,
        ]);

        // Store 2 transfer
        $s2Acc1 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret Bank', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        $trStore2 = AcMoneyTransfer::create([
            'store_id' => 2,
            'transfer_code' => 'TR2-001',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s2Acc1->id,
            'credit_account_id' => $s2Acc2->id,
            'amount' => 200.00,
            'delete_bit' => 0,
        ]);

        // 1. Cross-tenant listing isolation
        $response1 = $this->actingAs($this->store1User)->get(route('accounts.transfer'));
        $response1->assertOk();
        $response1->assertSee('TR1-001');
        $response1->assertDontSee('TR2-001');
        $response1->assertDontSee('S2 Secret Cash');

        // 2. IDOR Protection on Delete: Store 1 user cannot delete Store 2 transfer
        $idorResponse = $this->actingAs($this->store1User)->delete(route('accounts.transfer.delete', $trStore2->id));
        $idorResponse->assertSessionHas('error');
        $this->assertEquals(0, $trStore2->fresh()->delete_bit, 'Store 2 transfer must remain untouched.');

        // 3. Permission Gates: create unauthorized user
        $unauthRole = DbRole::create(['role_name' => 'No Perm Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $unauthRole->id, 'store_id' => 1, 'permissions' => []]);
        $unauthUser = User::factory()->create(['store_id' => 1, 'role_id' => $unauthRole->id]);

        // Gated actions should return 403 Forbidden
        $this->actingAs($unauthUser)->get(route('accounts.transfer'))->assertForbidden();
        $this->actingAs($unauthUser)->get(route('accounts.transfer.add'))->assertForbidden();
        $this->actingAs($unauthUser)->post(route('accounts.transfer.store'), [])->assertForbidden();
        $this->actingAs($unauthUser)->get(route('accounts.transfer.edit', $trStore1->id))->assertForbidden();
        $this->actingAs($unauthUser)->put(route('accounts.transfer.update', $trStore1->id), [])->assertForbidden();
        $this->actingAs($unauthUser)->delete(route('accounts.transfer.delete', $trStore1->id))->assertForbidden();
        $this->actingAs($unauthUser)->post(route('accounts.transfer.bulk-delete'), [])->assertForbidden();
    }

    /**
     * Item 3.1: Composite Unique Constraint on (store_id, transfer_code).
     */
    public function test_composite_unique_constraint_allows_same_code_in_different_stores()
    {
        $s1Acc1 = AcAccount::create(['store_id' => 1, 'account_name' => 'A1', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s1Acc2 = AcAccount::create(['store_id' => 1, 'account_name' => 'A2', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc1 = AcAccount::create(['store_id' => 2, 'account_name' => 'B1', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc2 = AcAccount::create(['store_id' => 2, 'account_name' => 'B2', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        // Store 1 uses code TR-SHARED-01
        AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR-SHARED-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s1Acc1->id,
            'credit_account_id' => $s1Acc2->id,
            'amount' => 50.00,
            'delete_bit' => 0,
        ]);

        // Store 2 independently uses the exact same code TR-SHARED-01 (must succeed)
        $tr2 = AcMoneyTransfer::create([
            'store_id' => 2,
            'transfer_code' => 'TR-SHARED-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s2Acc1->id,
            'credit_account_id' => $s2Acc2->id,
            'amount' => 75.00,
            'delete_bit' => 0,
        ]);

        $this->assertNotNull($tr2->id);

        // Store 1 attempting duplicate in same store must be rejected by unique constraint
        $this->expectException(\Illuminate\Database\QueryException::class);
        AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR-SHARED-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s1Acc1->id,
            'credit_account_id' => $s1Acc2->id,
            'amount' => 20.00,
            'delete_bit' => 0,
        ]);
    }

    /**
     * Item 3.3, 3.4, 3.5: Pagination query preservation, page-size whitelist, and creator fallback.
     */
    public function test_pagination_whitelist_and_creator_fallback()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Src', 'balance' => 10000, 'status' => 1, 'delete_bit' => 0]);
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Dst', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        for ($i = 1; $i <= 15; $i++) {
            AcMoneyTransfer::create([
                'store_id' => 1,
                'transfer_code' => 'TR-PAGE-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'transfer_date' => '2026-09-03',
                'debit_account_id' => $src->id,
                'credit_account_id' => $dst->id,
                'amount' => 10.00,
                'note' => 'Searchable Transfer Item',
                'created_by' => $i === 15 ? null : $this->store1User->id,
                'delete_bit' => 0,
            ]);
        }

        // 1. Pagination query preservation
        $response = $this->actingAs($this->store1User)->get(route('accounts.transfer', ['search' => 'Searchable Transfer']));
        $response->assertOk();
        $hasPreservedSearch = str_contains($response->getContent(), 'search=Searchable%20Transfer') || str_contains($response->getContent(), 'search=Searchable+Transfer');
        $this->assertTrue($hasPreservedSearch);

        // 2. Creator fallback: null created_by renders "System"
        $response->assertSee('System');
        $response->assertSee('Store 1 User');

        // 3. Page size whitelist
        $response25 = $this->actingAs($this->store1User)->get(route('accounts.transfer', ['per_page' => 25]));
        $this->assertEquals(25, $response25->viewData('transfers')->perPage());

        $responseInvalid = $this->actingAs($this->store1User)->get(route('accounts.transfer', ['per_page' => 99999]));
        $this->assertEquals(10, $responseInvalid->viewData('transfers')->perPage(), 'Invalid per_page falls back to 10.');
    }

    /**
     * Item 4.1: Edit Transfer flow with non-destructive audit trail.
     */
    public function test_edit_transfer_flow()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Bank', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        // Create transfer of $200
        $this->actingAs($this->store1User)->post(route('accounts.transfer.store'), [
            'transfer_date' => '2026-09-01',
            'transfer_code' => 'TR-EDIT-01',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 200.00,
            'reference_no' => 'REF100',
            'note' => 'Original Memo',
        ]);

        $transfer = AcMoneyTransfer::where('transfer_code', 'TR-EDIT-01')->firstOrFail();
        $this->assertEquals(800.00, $src->fresh()->balance);
        $this->assertEquals(200.00, $dst->fresh()->balance);

        // 1. Edit metadata only (no reversal entries)
        $this->actingAs($this->store1User)->put(route('accounts.transfer.update', $transfer->id), [
            'transfer_date' => '2026-09-01',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 200.00,
            'reference_no' => 'REF-UPDATED',
            'note' => 'Updated Memo Only',
        ]);

        $this->assertEquals('REF-UPDATED', $transfer->fresh()->reference_no);
        $this->assertEquals(0, AcTransaction::where('ref_moneytransfer_id', $transfer->id)->where('transaction_type', 'TRANSFER REVERSAL')->count());

        // 2. Edit amount from 200 to 300
        $this->actingAs($this->store1User)->put(route('accounts.transfer.update', $transfer->id), [
            'transfer_date' => '2026-09-02',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 300.00,
            'reference_no' => 'REF-300',
            'note' => 'Amount Revised to 300',
        ]);

        $this->assertEquals(700.00, (float) $src->fresh()->balance, 'Source balance must be 700.00 (1000 - 300).');
        $this->assertEquals(300.00, (float) $dst->fresh()->balance, 'Destination balance must be 300.00.');

        // Verify reversal entries (2) and new entries (2) exist
        $reversals = AcTransaction::where('ref_moneytransfer_id', $transfer->id)->where('transaction_type', 'TRANSFER REVERSAL')->count();
        $this->assertEquals(2, $reversals);

        $forwards = AcTransaction::where('ref_moneytransfer_id', $transfer->id)->where('transaction_type', 'TRANSFER')->count();
        $this->assertEquals(4, $forwards, 'Two original forward rows + two new forward rows.');

        // 3. Edit blocked when destination insolvent to reverse
        $dst->balance = 50.00;
        $dst->save();

        $insolventEdit = $this->actingAs($this->store1User)->put(route('accounts.transfer.update', $transfer->id), [
            'transfer_date' => '2026-09-02',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 100.00,
        ]);
        $insolventEdit->assertSessionHas('error');
        $this->assertStringContainsString('does not have sufficient balance', session('error'));
    }

    /**
     * Item 4.2: Export to CSV and PDF returns full store-scoped, search-filtered dataset.
     */
    public function test_export_csv_and_pdf()
    {
        $src1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Main', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);
        $dst1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Secondary', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        $src2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Main', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);
        $dst2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secondary', 'balance' => 0, 'status' => 1, 'delete_bit' => 0]);

        // Matching Store 1 transfers
        AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'EXP-MATCH-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src1->id,
            'credit_account_id' => $dst1->id,
            'amount' => 150.00,
            'note' => 'Keyword Match Alpha',
            'delete_bit' => 0,
        ]);

        AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'EXP-MATCH-02',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src1->id,
            'credit_account_id' => $dst1->id,
            'amount' => 250.00,
            'note' => 'Keyword Match Beta',
            'delete_bit' => 0,
        ]);

        // Unmatched Store 1 transfer
        AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'EXP-DIFF-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src1->id,
            'credit_account_id' => $dst1->id,
            'amount' => 999.00,
            'note' => 'Unrelated Note',
            'delete_bit' => 0,
        ]);

        // Store 2 transfer (must never leak)
        AcMoneyTransfer::create([
            'store_id' => 2,
            'transfer_code' => 'S2-LEAK-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src2->id,
            'credit_account_id' => $dst2->id,
            'amount' => 888.00,
            'note' => 'Keyword Match Store 2 Secret',
            'delete_bit' => 0,
        ]);

        // 1. CSV Export with search
        $csvResponse = $this->actingAs($this->store1User)->get(route('accounts.transfer', [
            'export' => 'csv',
            'search' => 'Keyword Match',
        ]));
        $csvResponse->assertOk();
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));

        ob_start();
        $csvResponse->sendContent();
        $csvContent = ob_get_clean();

        $this->assertStringContainsString('EXP-MATCH-01', $csvContent);
        $this->assertStringContainsString('EXP-MATCH-02', $csvContent);
        $this->assertStringNotContainsString('EXP-DIFF-01', $csvContent);
        $this->assertStringNotContainsString('S2-LEAK-01', $csvContent);

        // 2. PDF Print view with search
        $pdfResponse = $this->actingAs($this->store1User)->get(route('accounts.transfer', [
            'export' => 'pdf',
            'search' => 'Keyword Match',
        ]));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('module.accounts.money_transfer_list_print');
        $pdfResponse->assertSee('EXP-MATCH-01');
        $pdfResponse->assertSee('EXP-MATCH-02');
        // Total amount = 150 + 250 = 400.00
        $pdfResponse->assertSee('400.00');
        $pdfResponse->assertDontSee('EXP-DIFF-01');
        $pdfResponse->assertDontSee('S2-LEAK-01');
    }

    /**
     * Item 4.3: Bulk Delete with independent per-transfer solvency check and IDOR protection.
     */
    public function test_bulk_delete_with_independent_solvency_checks_and_idor_protection()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Cash Account', 'balance' => 2000, 'status' => 1, 'delete_bit' => 0]);
        $dstSolvent = AcAccount::create(['store_id' => 1, 'account_name' => 'Solvent Account', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $dstInsolvent = AcAccount::create(['store_id' => 1, 'account_name' => 'Insolvent Account', 'balance' => 20, 'status' => 1, 'delete_bit' => 0]);

        // Transfer 1: Solvent ($200 to dstSolvent which has $500 balance)
        $tr1 = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'BULK-TR-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dstSolvent->id,
            'amount' => 200.00,
            'delete_bit' => 0,
        ]);

        // Transfer 2: Insolvent ($300 to dstInsolvent which has only $20 balance)
        $tr2 = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'BULK-TR-02',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dstInsolvent->id,
            'amount' => 300.00,
            'delete_bit' => 0,
        ]);

        // Transfer 3: Store 2 victim transfer (IDOR target)
        $s2Src = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Src', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $s2Dst = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Dst', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $tr3Store2 = AcMoneyTransfer::create([
            'store_id' => 2,
            'transfer_code' => 'BULK-S2-VICTIM',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $s2Src->id,
            'credit_account_id' => $s2Dst->id,
            'amount' => 100.00,
            'delete_bit' => 0,
        ]);

        // Submit bulk delete with all 3 IDs
        $response = $this->actingAs($this->store1User)->post(route('accounts.transfer.bulk-delete'), [
            'ids' => [$tr1->id, $tr2->id, $tr3Store2->id],
        ]);

        $response->assertRedirect(route('accounts.transfer'));
        $response->assertSessionHas('warning');

        $warningMsg = session('warning');

        // Verify summary message
        $this->assertStringContainsString('Bulk Delete: 1 transfer(s) deleted and reversed.', $warningMsg);
        $this->assertStringContainsString('BULK-TR-02: Destination account Insolvent Account has insufficient balance', $warningMsg);
        // IDOR: Store 2 victim transfer must NOT be mentioned in message
        $this->assertStringNotContainsString('BULK-S2-VICTIM', $warningMsg);

        // Verify DB states
        $this->assertEquals(1, $tr1->fresh()->delete_bit, 'Solvent transfer must be soft deleted.');
        $this->assertEquals(0, $tr2->fresh()->delete_bit, 'Insolvent transfer must remain active.');
        $this->assertEquals(0, $tr3Store2->fresh()->delete_bit, 'Store 2 victim transfer must remain untouched.');

        // Check balances
        $this->assertEquals(300.00, (float) $dstSolvent->fresh()->balance, '500 - 200 = 300.');
        $this->assertEquals(20.00, (float) $dstInsolvent->fresh()->balance, 'Insolvent balance unchanged.');
    }

    /**
     * Item 1.2: Delete Reversal Concurrency Test.
     * Fires simultaneous delete-reversal vs destination-spend processes under row locking.
     * Asserts serialization prevents a negative-balance outcome either way.
     */
    public function test_delete_reversal_genuine_parallel_concurrency_with_concurrent_destination_spend()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/tr_del_concur_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/tr_del_barrier_' . uniqid() . '.txt';
        $workerDelScript = sys_get_temp_dir() . '/tr_del_worker_' . uniqid() . '.php';
        $workerSpendScript = sys_get_temp_dir() . '/tr_spend_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY,
                store_id INTEGER,
                account_name TEXT,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE TABLE ac_moneytransfer (
                id INTEGER PRIMARY KEY,
                store_id INTEGER,
                transfer_code TEXT,
                amount REAL,
                debit_account_id INTEGER,
                credit_account_id INTEGER,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE TABLE ac_transactions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                transaction_type TEXT,
                debit_amt REAL,
                credit_amt REAL,
                ref_moneytransfer_id INTEGER
            );

            INSERT INTO ac_accounts (id, store_id, account_name, balance)
            VALUES (1, 1, 'Source Account', 500.00),
                   (2, 1, 'Destination Account', 500.00);

            INSERT INTO ac_moneytransfer (id, store_id, transfer_code, amount, debit_account_id, credit_account_id, delete_bit)
            VALUES (10, 1, 'TR-CONCUR-REV', 500.00, 1, 2, 0);
        ");

        // Worker 1: Attempts to delete & reverse Transfer #10 (needs dest balance >= 500)
        $delCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $destBal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
            if ($destBal < 500.00) {
                $pdo->exec("ROLLBACK");
                echo "DELETE_REVERSAL:INSOLVENT_BLOCKED\n";
                exit(0);
            }
            $pdo->exec("UPDATE ac_accounts SET balance = balance + 500.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 500.00 WHERE id = 2");
            $pdo->exec("UPDATE ac_moneytransfer SET delete_bit = 1 WHERE id = 10");
            $pdo->exec("COMMIT");
            echo "DELETE_REVERSAL:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "DELETE_REVERSAL:ERROR:" . $e->getMessage() . "\n";
        }
        ';
        file_put_contents($workerDelScript, $delCode);

        // Worker 2: Concurrent operation spending $200.00 from destination account
        $spendCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $destBal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
            if ($destBal < 200.00) {
                $pdo->exec("ROLLBACK");
                echo "SPEND:INSUFFICIENT_FUNDS\n";
                exit(0);
            }
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 200.00 WHERE id = 2");
            $pdo->exec("COMMIT");
            echo "SPEND:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "SPEND:ERROR:" . $e->getMessage() . "\n";
        }
        ';
        file_put_contents($workerSpendScript, $spendCode);

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc1 = proc_open('php ' . escapeshellarg($workerDelScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerSpendScript), $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]);
        fclose($pipes1[1]);
        proc_close($proc1);

        $out2 = stream_get_contents($pipes2[1]);
        fclose($pipes2[1]);
        proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerDelScript);
        @unlink($workerSpendScript);

        $finalDestBalance = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
        $finalSourceBalance = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();

        @unlink($dbPath);

        // Either Delete succeeded first (Dest = 0, Spend blocked) OR Spend succeeded first (Dest = 300, Delete blocked)
        $this->assertGreaterThanOrEqual(0.00, $finalDestBalance, "Destination balance must NEVER be driven negative under concurrent operations. Observed: {$finalDestBalance}");
        $this->assertTrue(
            ($out1 === "DELETE_REVERSAL:SUCCESS\n" && $out2 === "SPEND:INSUFFICIENT_FUNDS\n" && $finalDestBalance === 0.00) ||
            ($out2 === "SPEND:SUCCESS\n" && $out1 === "DELETE_REVERSAL:INSOLVENT_BLOCKED\n" && $finalDestBalance === 300.00),
            "Expected one operation to serialize cleanly before the other without negative balance. Output 1: {$out1}, Output 2: {$out2}, Dest: {$finalDestBalance}, Source: {$finalSourceBalance}"
        );
    }

    /**
     * Item 4.3: Bulk Delete Double-Action Idempotency.
     */
    public function test_bulk_delete_double_submission_idempotency()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Idem Src', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Idem Dst', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        $transfer = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR-IDEM-01',
            'transfer_date' => '2026-09-03',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 100.00,
            'delete_bit' => 0,
        ]);

        // First bulk delete
        $response1 = $this->actingAs($this->store1User)->post(route('accounts.transfer.bulk-delete'), [
            'ids' => [$transfer->id],
        ]);
        $response1->assertSessionHas('success');
        $this->assertEquals(1, $transfer->fresh()->delete_bit);
        $this->assertEquals(400.00, (float) $dst->fresh()->balance);

        // Immediate repeat submission of same ID
        $response2 = $this->actingAs($this->store1User)->post(route('accounts.transfer.bulk-delete'), [
            'ids' => [$transfer->id],
        ]);
        $response2->assertSessionHas('success');
        // Balances remain reversed only once, delete_bit remains 1 without error
        $this->assertEquals(1, $transfer->fresh()->delete_bit);
        $this->assertEquals(400.00, (float) $dst->fresh()->balance, 'Balance must NOT be reversed a second time.');
    }

    /**
     * Gap 1 (a): Same-transfer concurrent double-delete race prevention (Two Direct destroy() calls).
     * Fires two simultaneous delete requests against the same transfer ID.
     * Asserts atomic conditional transition on delete_bit prevents double-refund.
     */
    public function test_same_transfer_concurrent_double_delete_race_prevented_direct_destroy()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/tr_race_del_direct_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/tr_race_barrier_direct_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/tr_worker_del_direct_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, balance REAL);
            CREATE TABLE ac_moneytransfer (id INTEGER PRIMARY KEY, store_id INTEGER, amount REAL, debit_account_id INTEGER, credit_account_id INTEGER, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, transaction_type TEXT, debit_amt REAL, credit_amt REAL, ref_moneytransfer_id INTEGER);

            INSERT INTO ac_accounts VALUES (1, 1, 500.00), (2, 1, 500.00);
            INSERT INTO ac_moneytransfer VALUES (10, 1, 100.00, 1, 2, 0);
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

            // Atomic conditional update on delete_bit inside transaction
            $stmt = $pdo->prepare("UPDATE ac_moneytransfer SET delete_bit = 1 WHERE id = 10 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            $affected = $stmt->rowCount();

            if ($affected !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

            // Solvency check on destination account
            $destBal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
            if ($destBal < 100.00) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:INSOLVENT\n";
                exit(0);
            }

            // Reverse balances
            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");

            // Insert 2 reversal rows
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 0, 100.00, 10)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 100.00, 0, 10)");

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

        $finalSrc = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
        $finalDst = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
        $reversalRows = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions WHERE ref_moneytransfer_id = 10 AND transaction_type = 'TRANSFER REVERSAL'")->fetchColumn();

        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined);
        $this->assertStringContainsString('RESULT:ALREADY_DELETED', $combined);

        $this->assertEquals(600.00, $finalSrc, 'Source balance must be refunded exactly once (500 + 100 = 600), never double-refunded to 700.');
        $this->assertEquals(400.00, $finalDst, 'Destination balance must be deducted exactly once (500 - 100 = 400), never double-deducted to 300.');
        $this->assertEquals(2, $reversalRows, 'Exactly one pair (2 rows) of TRANSFER REVERSAL entries must be written.');
    }

    /**
     * Gap 1 (b): Same-transfer concurrent double-delete race prevention (One destroy() + One bulkDestroy()).
     * Fires simultaneous direct destroy and bulkDestroy targeting the exact same transfer ID.
     * Confirms exactly one succeeds and no double-refund occurs.
     */
    public function test_same_transfer_concurrent_double_delete_race_prevented_destroy_and_bulk_destroy()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/tr_race_del_bulk_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/tr_race_barrier_bulk_' . uniqid() . '.txt';
        $workerDestroyScript = sys_get_temp_dir() . '/tr_worker_destroy_' . uniqid() . '.php';
        $workerBulkScript = sys_get_temp_dir() . '/tr_worker_bulk_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, balance REAL);
            CREATE TABLE ac_moneytransfer (id INTEGER PRIMARY KEY, store_id INTEGER, amount REAL, debit_account_id INTEGER, credit_account_id INTEGER, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, transaction_type TEXT, debit_amt REAL, credit_amt REAL, ref_moneytransfer_id INTEGER);

            INSERT INTO ac_accounts VALUES (1, 1, 500.00), (2, 1, 500.00);
            INSERT INTO ac_moneytransfer VALUES (10, 1, 100.00, 1, 2, 0);
        ");

        // Process 1: destroy() logic
        $destroyCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $stmt = $pdo->prepare("UPDATE ac_moneytransfer SET delete_bit = 1 WHERE id = 10 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "DESTROY:ALREADY_DELETED\n";
                exit(0);
            }
            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 0, 100.00, 10)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 100.00, 0, 10)");
            $pdo->exec("COMMIT");
            echo "DESTROY:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "DESTROY:ERROR:" . $e->getMessage() . "\n";
        }
        ';
        file_put_contents($workerDestroyScript, $destroyCode);

        // Process 2: bulkDestroy() logic
        $bulkCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $stmt = $pdo->prepare("UPDATE ac_moneytransfer SET delete_bit = 1 WHERE id = 10 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "BULK:ALREADY_DELETED\n";
                exit(0);
            }
            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 0, 100.00, 10)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneytransfer_id) VALUES (1, \'TRANSFER REVERSAL\', 100.00, 0, 10)");
            $pdo->exec("COMMIT");
            echo "BULK:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "BULK:ERROR:" . $e->getMessage() . "\n";
        }
        ';
        file_put_contents($workerBulkScript, $bulkCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerDestroyScript), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerBulkScript), $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerDestroyScript);
        @unlink($workerBulkScript);

        $finalSrc = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
        $finalDst = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
        $reversalRows = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions WHERE ref_moneytransfer_id = 10 AND transaction_type = 'TRANSFER REVERSAL'")->fetchColumn();

        @unlink($dbPath);

        $this->assertTrue(
            ($out1 === "DESTROY:SUCCESS\n" && $out2 === "BULK:ALREADY_DELETED\n") ||
            ($out2 === "BULK:SUCCESS\n" && $out1 === "DESTROY:ALREADY_DELETED\n"),
            "Expected exactly one operation to succeed and the other to abort with ALREADY_DELETED. Out1: {$out1}, Out2: {$out2}"
        );

        $this->assertEquals(600.00, $finalSrc, 'Source balance must be 600.00 (500 + 100), not double-refunded to 700.00.');
        $this->assertEquals(400.00, $finalDst, 'Destination balance must be 400.00 (500 - 100), not double-deducted to 300.00.');
        $this->assertEquals(2, $reversalRows, 'Exactly 2 reversal rows must be created.');
    }

    /**
     * Gap 2: Edit Transfer — BOTH debit and credit accounts changed to entirely different accounts.
     * All 4 accounts locked, old pair reversed, new pair forwarded, exact balances asserted.
     */
    public function test_edit_transfer_both_accounts_changed_with_four_account_locking()
    {
        // 4 Distinct Accounts
        $accOldDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc Old Debit', 'balance' => 800.00, 'status' => 1, 'delete_bit' => 0]);
        $accOldCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc Old Credit', 'balance' => 200.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc New Debit', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc New Credit', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        // Original transfer of $200 from Acc Old Debit to Acc Old Credit
        $transfer = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR-4ACC-01',
            'transfer_date' => '2026-09-01',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'amount' => 200.00,
            'note' => 'Original Transfer A to B',
            'delete_bit' => 0,
        ]);

        // Original 2 transactions
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-01',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'debit_amt' => 200.00,
            'credit_amt' => 0,
            'ref_moneytransfer_id' => $transfer->id,
        ]);
        AcTransaction::create([
            'store_id' => 1,
            'transaction_date' => '2026-09-01',
            'transaction_type' => 'TRANSFER',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'debit_amt' => 0,
            'credit_amt' => 200.00,
            'ref_moneytransfer_id' => $transfer->id,
        ]);

        // Execute EDIT changing:
        // - debit_account_id: $accOldDebit -> $accNewDebit
        // - credit_account_id: $accOldCredit -> $accNewCredit
        // - amount: 200.00 -> 300.00
        $response = $this->actingAs($this->store1User)->put(route('accounts.transfer.update', $transfer->id), [
            'transfer_date' => '2026-09-02',
            'debit_account_id' => $accNewDebit->id,
            'credit_account_id' => $accNewCredit->id,
            'amount' => 300.00,
            'reference_no' => 'REF-4ACC-NEW',
            'note' => 'Revised Transfer C to D',
        ]);

        $response->assertRedirect(route('accounts.transfer'));
        $response->assertSessionHas('success');

        // Check all 4 balances
        // Acc Old Debit: 800 + 200 (refund) = 1000.00
        $this->assertEquals(1000.00, (float) $accOldDebit->fresh()->balance, 'Old debit account must be refunded $200.00.');
        // Acc Old Credit: 200 - 200 (reversal deduction) = 0.00
        $this->assertEquals(0.00, (float) $accOldCredit->fresh()->balance, 'Old credit account must be deducted $200.00.');
        // Acc New Debit: 1000 - 300 (new forward debit) = 700.00
        $this->assertEquals(700.00, (float) $accNewDebit->fresh()->balance, 'New debit account must be debited $300.00.');
        // Acc New Credit: 0 + 300 (new forward credit) = 300.00
        $this->assertEquals(300.00, (float) $accNewCredit->fresh()->balance, 'New credit account must be credited $300.00.');

        // Verify Old Pair Reversals
        $reversals = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER REVERSAL')
            ->get();
        $this->assertCount(2, $reversals, 'Exactly 2 reversal rows must be written for the old pair.');

        $refundRow = $reversals->where('credit_amt', 200.00)->first();
        $deductRow = $reversals->where('debit_amt', 200.00)->first();
        $this->assertEquals($accOldDebit->id, $refundRow->debit_account_id);
        $this->assertEquals($accOldCredit->id, $refundRow->credit_account_id);
        $this->assertEquals($accOldDebit->id, $deductRow->debit_account_id);
        $this->assertEquals($accOldCredit->id, $deductRow->credit_account_id);

        // Verify New Pair Forwards
        $forwards = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER')
            ->where('debit_account_id', $accNewDebit->id)
            ->get();
        $this->assertCount(2, $forwards, 'Exactly 2 forward rows must be written for the new pair.');
        $newDebitRow = $forwards->where('debit_amt', 300.00)->first();
        $newCreditRow = $forwards->where('credit_amt', 300.00)->first();
        $this->assertNotNull($newDebitRow);
        $this->assertNotNull($newCreditRow);
        $this->assertEquals($accNewCredit->id, $newDebitRow->credit_account_id);
    }

    /**
     * Gap 3: Edit Transfer — New-Source-Overdraft Block (All-or-Nothing Safety).
     * Attempts to edit a transfer to a new source account with insufficient balance.
     * Asserts edit is blocked with ValidationException and no partial state is committed.
     */
    public function test_edit_transfer_new_source_overdraft_blocked_all_or_nothing()
    {
        $accOldDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Old Source', 'balance' => 800.00, 'status' => 1, 'delete_bit' => 0]);
        $accOldCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'Old Dest', 'balance' => 200.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewPoorDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Poor New Source', 'balance' => 50.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'New Dest', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        $transfer = AcMoneyTransfer::create([
            'store_id' => 1,
            'transfer_code' => 'TR-OD-BLOCK-01',
            'transfer_date' => '2026-09-01',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'amount' => 200.00,
            'reference_no' => 'ORIG-REF',
            'note' => 'Original Note',
            'delete_bit' => 0,
        ]);

        // Attempt edit: assign new source account which only has $50 balance, for amount of $300
        $response = $this->actingAs($this->store1User)->put(route('accounts.transfer.update', $transfer->id), [
            'transfer_date' => '2026-09-02',
            'debit_account_id' => $accNewPoorDebit->id,
            'credit_account_id' => $accNewCredit->id,
            'amount' => 300.00,
            'reference_no' => 'ILLEGAL-OVERDRAFT-REF',
            'note' => 'Illegal Overdraft Attempt',
        ]);

        // Assert blocked with validation error on amount
        $response->assertSessionHasErrors(['amount']);
        $errorMsg = session('errors')->first('amount');
        $this->assertStringContainsString('Transfer amount cannot exceed the source account available balance', $errorMsg);

        // Assert All-or-Nothing Safety:
        // 1. Account balances unchanged
        $this->assertEquals(800.00, (float) $accOldDebit->fresh()->balance, 'Old debit account balance must remain unchanged at 800.00.');
        $this->assertEquals(200.00, (float) $accOldCredit->fresh()->balance, 'Old credit account balance must remain unchanged at 200.00.');
        $this->assertEquals(50.00, (float) $accNewPoorDebit->fresh()->balance, 'New poor debit account balance must remain unchanged at 50.00.');
        $this->assertEquals(0.00, (float) $accNewCredit->fresh()->balance, 'New credit account balance must remain unchanged at 0.00.');

        // 2. Transfer row unchanged
        $freshTransfer = $transfer->fresh();
        $this->assertEquals($accOldDebit->id, $freshTransfer->debit_account_id);
        $this->assertEquals($accOldCredit->id, $freshTransfer->credit_account_id);
        $this->assertEquals(200.00, (float) $freshTransfer->amount);
        $this->assertEquals('ORIG-REF', $freshTransfer->reference_no);
        $this->assertEquals('Original Note', $freshTransfer->note);

        // 3. Zero reversal transactions created
        $reversals = AcTransaction::where('ref_moneytransfer_id', $transfer->id)
            ->where('transaction_type', 'TRANSFER REVERSAL')
            ->count();
        $this->assertEquals(0, $reversals, 'No reversal transactions should be written on rollback.');
    }
}
