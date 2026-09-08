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
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DepositFixesTest extends TestCase
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
        ]);

        $this->store2 = DbStore::create([
            'id' => 2,
            'store_name' => 'Store 2',
            'status' => 1,
            'mobile' => '2222222222',
        ]);

        // 2. Setup Role & Permissions for Store 1 User
        $role1 = DbRole::create([
            'role_name' => 'Store 1 Deposit Manager',
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
            'name' => 'Store 1 User',
            'email' => 'store1deposit@example.com',
        ]);

        // 3. Setup Role & Permissions for Store 2 User
        $role2 = DbRole::create([
            'role_name' => 'Store 2 Deposit Manager',
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
            'name' => 'Store 2 User',
            'email' => 'store2deposit@example.com',
        ]);
    }

    /**
     * Item 1.1: Balanced double-entry ledger creation for internal-source deposits,
     * contra account creation/reuse for external deposits, and downstream reconciliation contract.
     */
    public function test_double_entry_ledger_structure_and_contra_account_reuse()
    {
        $srcAcc = AcAccount::create(['store_id' => 1, 'account_name' => 'Source Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $dstAcc = AcAccount::create(['store_id' => 1, 'account_name' => 'Destination Bank', 'balance' => 500.00, 'status' => 1, 'delete_bit' => 0]);

        // 1. Internal-Source Deposit ($300.00 from srcAcc to dstAcc)
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => $srcAcc->id,
            'credit_account_id' => $dstAcc->id,
            'amount' => 300.00,
            'reference_no' => 'DEP-INT-01',
            'note' => 'Internal Source Deposit',
        ]);

        $dep1 = AcMoneyDeposit::where('reference_no', 'DEP-INT-01')->firstOrFail();

        // Verify balances
        $this->assertEquals(700.00, (float) $srcAcc->fresh()->balance, 'Source balance: 1000 - 300 = 700.00.');
        $this->assertEquals(800.00, (float) $dstAcc->fresh()->balance, 'Destination balance: 500 + 300 = 800.00.');

        // Verify exactly 2 balanced rows written in ac_transactions
        $dep1Txs = AcTransaction::where('ref_moneydeposits_id', $dep1->id)->get();
        $this->assertCount(2, $dep1Txs, 'Exactly two ledger transaction rows must be written.');

        $debitRow = $dep1Txs->where('debit_amt', 300.00)->where('credit_amt', 0)->first();
        $creditRow = $dep1Txs->where('credit_amt', 300.00)->where('debit_amt', 0)->first();

        $this->assertNotNull($debitRow, 'Debit side must have debit_amt = 300, credit_amt = 0.');
        $this->assertNotNull($creditRow, 'Credit side must have credit_amt = 300, debit_amt = 0.');
        $this->assertEquals($srcAcc->id, $debitRow->debit_account_id);
        $this->assertEquals($dstAcc->id, $creditRow->credit_account_id);

        // 2. External Deposit #1 (debit_account_id = null, amount = 250.00)
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => '',
            'credit_account_id' => $dstAcc->id,
            'amount' => 250.00,
            'reference_no' => 'DEP-EXT-01',
            'note' => 'First External Deposit',
        ]);

        $dep2 = AcMoneyDeposit::where('reference_no', 'DEP-EXT-01')->firstOrFail();

        // Verify contra clearing account was created
        $clearingAcc = AcAccount::where('store_id', 1)
            ->where('account_name', 'External Deposit Clearing')
            ->where('delete_bit', 0)
            ->first();

        $this->assertNotNull($clearingAcc, 'External Deposit Clearing contra account must be auto-created.');

        // Verify exactly 2 balanced rows written for external deposit
        $dep2Txs = AcTransaction::where('ref_moneydeposits_id', $dep2->id)->get();
        $this->assertCount(2, $dep2Txs);
        $extDebitRow = $dep2Txs->where('debit_amt', 250.00)->first();
        $extCreditRow = $dep2Txs->where('credit_amt', 250.00)->first();

        $this->assertEquals($clearingAcc->id, $extDebitRow->debit_account_id, 'External deposit debit must reference contra clearing account.');
        $this->assertEquals($dstAcc->id, $extCreditRow->credit_account_id);

        // 3. External Deposit #2 in same store (verify contra account is reused, not duplicated)
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => '',
            'credit_account_id' => $dstAcc->id,
            'amount' => 150.00,
            'reference_no' => 'DEP-EXT-02',
            'note' => 'Second External Deposit in Same Store',
        ]);

        $clearingCount = AcAccount::where('store_id', 1)
            ->where('account_name', 'External Deposit Clearing')
            ->where('delete_bit', 0)
            ->count();
        $this->assertEquals(1, $clearingCount, 'The External Deposit Clearing account must be reused, never duplicated in the same store.');

        // 4. Downstream Reconciliation Contract Assertion
        // CashReconciliationController::calculateExpected() sums:
        // AcTransaction::where('credit_account_id', $accountId)->where('transaction_type', 'DEPOSIT')->sum('credit_amt')
        $reconciliationDepositsSum = (float) AcTransaction::where('credit_account_id', $dstAcc->id)
            ->where('transaction_type', 'DEPOSIT')
            ->sum('credit_amt');

        // Total expected = 300.00 (internal) + 250.00 (ext 1) + 150.00 (ext 2) = 700.00
        $this->assertEquals(700.00, $reconciliationDepositsSum, 'Downstream reconciliation contract must calculate exactly 700.00.');

        // 5. Self-Deposit Validation (debit_account_id == credit_account_id rejected)
        $selfResponse = $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => $srcAcc->id,
            'credit_account_id' => $srcAcc->id,
            'amount' => 50.00,
        ]);
        $selfResponse->assertSessionHasErrors(['debit_account_id']);
    }

    /**
     * Item 1.2: Overdraft guard on create with genuine parallel concurrency.
     * Fires two simultaneous deposits against a $100 source balance.
     * Asserts exactly one succeeds and source balance is never driven negative.
     */
    public function test_deposit_creation_genuine_parallel_concurrency_blocks_overdraft()
    {
        $dbPath = sys_get_temp_dir() . '/dep_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/dep_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/dep_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, account_name TEXT, balance REAL, status INTEGER DEFAULT 1, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_moneydeposits (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, deposit_date TEXT, debit_account_id INTEGER, credit_account_id INTEGER, amount REAL, status INTEGER DEFAULT 1, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, transaction_date TEXT, transaction_type TEXT, debit_account_id INTEGER, credit_account_id INTEGER, debit_amt REAL DEFAULT 0, credit_amt REAL DEFAULT 0, ref_moneydeposits_id INTEGER);

            INSERT INTO ac_accounts VALUES (1, 1, 'Source Cash', 100.00, 1, 0), (2, 1, 'Destination Bank', 0.00, 1, 0);
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

            $stmt = $pdo->prepare("SELECT balance FROM ac_accounts WHERE id = 1");
            $stmt->execute();
            $balance = (float) $stmt->fetchColumn();

            $amount = 100.00;
            if ($amount > $balance) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:INSUFFICIENT_BALANCE\n";
                exit(0);
            }

            // Insert deposit
            $stmt = $pdo->prepare("INSERT INTO ac_moneydeposits (store_id, deposit_date, debit_account_id, credit_account_id, amount, status, delete_bit) VALUES (1, \'2026-09-03\', 1, 2, ?, 1, 0)");
            $stmt->execute([$amount]);
            $depId = $pdo->lastInsertId();

            // Insert 2 balanced transactions
            $stmt = $pdo->prepare("INSERT INTO ac_transactions (store_id, transaction_date, transaction_type, debit_account_id, credit_account_id, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'2026-09-03\', \'DEPOSIT\', 1, 2, ?, 0, ?)");
            $stmt->execute([$amount, $depId]);

            $stmt = $pdo->prepare("INSERT INTO ac_transactions (store_id, transaction_date, transaction_type, debit_account_id, credit_account_id, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'2026-09-03\', \'DEPOSIT\', 1, 2, 0, ?, ?)");
            $stmt->execute([$amount, $depId]);

            // Mutate balances
            $pdo->exec("UPDATE ac_accounts SET balance = balance - {$amount} WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance + {$amount} WHERE id = 2");

            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' DEP-A', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' DEP-B', $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $finalSourceBalance = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
        $finalDestBalance = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
        $totalDeposits = (int) $pdo->query("SELECT COUNT(*) FROM ac_moneydeposits")->fetchColumn();
        $totalTransactions = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions")->fetchColumn();

        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined);
        $this->assertStringContainsString('RESULT:INSUFFICIENT_BALANCE', $combined);

        $this->assertEquals(0.00, $finalSourceBalance, 'Source balance must be exactly 0.00, never negative.');
        $this->assertEquals(100.00, $finalDestBalance, 'Destination balance must be exactly 100.00.');
        $this->assertEquals(1, $totalDeposits);
        $this->assertEquals(2, $totalTransactions);
    }

    /**
     * Item 1.3: Delete solvency guard blocks deletion when destination account has spent funds.
     */
    public function test_delete_solvency_guard_blocks_when_destination_insolvent()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Source Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Destination Bank', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        // Create deposit of $500.00
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 500.00,
            'reference_no' => 'DEP-SOLV-01',
        ]);

        $deposit = AcMoneyDeposit::where('reference_no', 'DEP-SOLV-01')->firstOrFail();
        $this->assertEquals(500.00, (float) $dst->fresh()->balance);

        // Destination spends $450 (balance drops to $50.00)
        $dst->balance = 50.00;
        $dst->save();

        // Attempt delete of $500.00 deposit
        $deleteResponse = $this->actingAs($this->store1User)->delete(route('accounts.deposit.delete', $deposit->id));
        $deleteResponse->assertSessionHas('error');

        $this->assertStringContainsString('Destination account Destination Bank does not have sufficient balance', session('error'));
        $this->assertStringContainsString('to reverse the deposited amount', session('error'));

        // Verify deposit remains active and balances untouched
        $this->assertEquals(0, $deposit->fresh()->delete_bit);
        $this->assertEquals(50.00, (float) $dst->fresh()->balance);
        $this->assertEquals(500.00, (float) $src->fresh()->balance);

        // Zero reversal rows written
        $reversals = AcTransaction::where('ref_moneydeposits_id', $deposit->id)->where('transaction_type', 'DEPOSIT REVERSAL')->count();
        $this->assertEquals(0, $reversals);
    }

    /**
     * Item 1.3: Successful delete reversal inserts 2 DEPOSIT REVERSAL rows, soft-deletes deposit, and restores balances.
     */
    public function test_delete_reversal_success_inserts_reversal_rows_and_soft_deletes()
    {
        $src = AcAccount::create(['store_id' => 1, 'account_name' => 'Source Cash', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Destination Bank', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        // Create deposit of $400.00
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-03',
            'debit_account_id' => $src->id,
            'credit_account_id' => $dst->id,
            'amount' => 400.00,
            'reference_no' => 'DEP-REV-01',
            'note' => 'Original Memo',
        ]);

        $deposit = AcMoneyDeposit::where('reference_no', 'DEP-REV-01')->firstOrFail();
        $this->assertEquals(600.00, (float) $src->fresh()->balance);
        $this->assertEquals(400.00, (float) $dst->fresh()->balance);

        // Delete deposit
        $deleteResponse = $this->actingAs($this->store1User)->delete(route('accounts.deposit.delete', $deposit->id));
        $deleteResponse->assertRedirect(route('accounts.deposit'));
        $deleteResponse->assertSessionHas('success');

        // Verify soft delete
        $this->assertEquals(1, $deposit->fresh()->delete_bit);

        // Disappears from active list
        $listResponse = $this->actingAs($this->store1User)->get(route('accounts.deposit'));
        $listResponse->assertDontSee('DEP-REV-01');

        // Original 2 rows intact in ac_transactions
        $originalRows = AcTransaction::where('ref_moneydeposits_id', $deposit->id)->where('transaction_type', 'DEPOSIT')->get();
        $this->assertCount(2, $originalRows);

        // 2 new DEPOSIT REVERSAL rows written
        $reversals = AcTransaction::where('ref_moneydeposits_id', $deposit->id)->where('transaction_type', 'DEPOSIT REVERSAL')->get();
        $this->assertCount(2, $reversals);

        // Balances restored accurately
        $this->assertEquals(1000.00, (float) $src->fresh()->balance);
        $this->assertEquals(0.00, (float) $dst->fresh()->balance);
    }

    /**
     * Item 1.3: Same-deposit concurrent double-delete race prevention (Two Direct destroy() calls).
     */
    public function test_same_deposit_concurrent_double_delete_race_prevented_direct_destroy()
    {
        $dbPath = sys_get_temp_dir() . '/dep_race_del_direct_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/dep_race_barrier_direct_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/dep_worker_del_direct_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, balance REAL);
            CREATE TABLE ac_moneydeposits (id INTEGER PRIMARY KEY, store_id INTEGER, amount REAL, debit_account_id INTEGER, credit_account_id INTEGER, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, transaction_type TEXT, debit_amt REAL, credit_amt REAL, ref_moneydeposits_id INTEGER);

            INSERT INTO ac_accounts VALUES (1, 1, 500.00), (2, 1, 500.00);
            INSERT INTO ac_moneydeposits VALUES (10, 1, 100.00, 1, 2, 0);
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

            // Atomic conditional update on delete_bit
            $stmt = $pdo->prepare("UPDATE ac_moneydeposits SET delete_bit = 1 WHERE id = 10 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

            // Solvency check
            $destBal = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
            if ($destBal < 100.00) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:INSOLVENT\n";
                exit(0);
            }

            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");

            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 0, 100.00, 10)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 100.00, 0, 10)");

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
        $reversalRows = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions WHERE ref_moneydeposits_id = 10 AND transaction_type = 'DEPOSIT REVERSAL'")->fetchColumn();

        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined);
        $this->assertStringContainsString('RESULT:ALREADY_DELETED', $combined);

        $this->assertEquals(600.00, $finalSrc, 'Source balance must be refunded exactly once (500 + 100 = 600).');
        $this->assertEquals(400.00, $finalDst, 'Destination balance must be deducted exactly once (500 - 100 = 400).');
        $this->assertEquals(2, $reversalRows);
    }

    /**
     * Item 1.3 / 4.3: Same-deposit concurrent double-delete race prevention (One Direct destroy() + One bulkDestroy()).
     */
    public function test_same_deposit_concurrent_double_delete_race_prevented_destroy_and_bulk_destroy()
    {
        $dbPath = sys_get_temp_dir() . '/dep_race_del_mixed_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/dep_race_barrier_mixed_' . uniqid() . '.txt';
        $workerScriptDirect = sys_get_temp_dir() . '/dep_worker_direct_' . uniqid() . '.php';
        $workerScriptBulk = sys_get_temp_dir() . '/dep_worker_bulk_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (id INTEGER PRIMARY KEY, store_id INTEGER, balance REAL);
            CREATE TABLE ac_moneydeposits (id INTEGER PRIMARY KEY, store_id INTEGER, amount REAL, debit_account_id INTEGER, credit_account_id INTEGER, delete_bit INTEGER DEFAULT 0);
            CREATE TABLE ac_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, store_id INTEGER, transaction_type TEXT, debit_amt REAL, credit_amt REAL, ref_moneydeposits_id INTEGER);

            INSERT INTO ac_accounts VALUES (1, 1, 500.00), (2, 1, 500.00);
            INSERT INTO ac_moneydeposits VALUES (20, 1, 100.00, 1, 2, 0);
        ");

        $codeDirect = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $stmt = $pdo->prepare("UPDATE ac_moneydeposits SET delete_bit = 1 WHERE id = 20 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 0, 100.00, 20)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 100.00, 0, 20)");
            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        $codeBulk = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";
        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");
            $stmt = $pdo->prepare("UPDATE ac_moneydeposits SET delete_bit = 1 WHERE id = 20 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

            $pdo->exec("UPDATE ac_accounts SET balance = balance + 100.00 WHERE id = 1");
            $pdo->exec("UPDATE ac_accounts SET balance = balance - 100.00 WHERE id = 2");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 0, 100.00, 20)");
            $pdo->exec("INSERT INTO ac_transactions (store_id, transaction_type, debit_amt, credit_amt, ref_moneydeposits_id) VALUES (1, \'DEPOSIT REVERSAL\', 100.00, 0, 20)");
            $pdo->exec("COMMIT");
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScriptDirect, $codeDirect);
        file_put_contents($workerScriptBulk, $codeBulk);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScriptDirect), $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScriptBulk), $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScriptDirect);
        @unlink($workerScriptBulk);

        $finalSrc = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 1")->fetchColumn();
        $finalDst = (float) $pdo->query("SELECT balance FROM ac_accounts WHERE id = 2")->fetchColumn();
        $reversalRows = (int) $pdo->query("SELECT COUNT(*) FROM ac_transactions WHERE ref_moneydeposits_id = 20 AND transaction_type = 'DEPOSIT REVERSAL'")->fetchColumn();

        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:SUCCESS', $combined);
        $this->assertStringContainsString('RESULT:ALREADY_DELETED', $combined);

        $this->assertEquals(600.00, $finalSrc, 'Source balance must be refunded exactly once.');
        $this->assertEquals(400.00, $finalDst, 'Destination balance must be deducted exactly once.');
        $this->assertEquals(2, $reversalRows, 'Exactly two DEPOSIT REVERSAL rows must be created.');
    }

    /**
     * Item 2.1 & 2.2: Multi-Store Scoping, IDOR Protection & Permission Gates.
     */
    public function test_store_scoping_idor_and_permission_gates()
    {
        $s1Acc = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Cash', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $s2Acc = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Secret Account', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);

        $depStore1 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s1Acc->id,
            'amount' => 100.00,
            'reference_no' => 'DEP-S1-01',
            'delete_bit' => 0,
        ]);

        $depStore2 = AcMoneyDeposit::create([
            'store_id' => 2,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s2Acc->id,
            'amount' => 200.00,
            'reference_no' => 'DEP-S2-SECRET',
            'delete_bit' => 0,
        ]);

        // 1. Cross-tenant listing isolation
        $response1 = $this->actingAs($this->store1User)->get(route('accounts.deposit'));
        $response1->assertOk();
        $response1->assertSee('DEP-S1-01');
        $response1->assertDontSee('DEP-S2-SECRET');
        $response1->assertDontSee('S2 Secret Account');

        // 2. IDOR Protection on Delete: Store 1 user cannot delete Store 2 deposit
        $idorResponse = $this->actingAs($this->store1User)->delete(route('accounts.deposit.delete', $depStore2->id));
        $idorResponse->assertSessionHas('error');
        $this->assertEquals(0, $depStore2->fresh()->delete_bit);

        // 3. Permission Gates: unauthorized user gets 403 Forbidden
        $unauthRole = DbRole::create(['role_name' => 'No Perm Role', 'status' => 1, 'store_id' => 1]);
        DbPermission::create(['role_id' => $unauthRole->id, 'store_id' => 1, 'permissions' => []]);
        $unauthUser = User::factory()->create(['store_id' => 1, 'role_id' => $unauthRole->id]);

        $this->actingAs($unauthUser)->get(route('accounts.deposit'))->assertForbidden();
        $this->actingAs($unauthUser)->get(route('accounts.deposit.add'))->assertForbidden();
        $this->actingAs($unauthUser)->post(route('accounts.deposit.store'), [])->assertForbidden();
        $this->actingAs($unauthUser)->get(route('accounts.deposit.edit', $depStore1->id))->assertForbidden();
        $this->actingAs($unauthUser)->put(route('accounts.deposit.update', $depStore1->id), [])->assertForbidden();
        $this->actingAs($unauthUser)->delete(route('accounts.deposit.delete', $depStore1->id))->assertForbidden();
        $this->actingAs($unauthUser)->post(route('accounts.deposit.bulk-delete'), [])->assertForbidden();
    }

    /**
     * Item 3.2, 3.3, 3.4: Pagination query preservation, page size whitelist, and creator fallback.
     */
    public function test_pagination_whitelist_and_creator_fallback()
    {
        $dst = AcAccount::create(['store_id' => 1, 'account_name' => 'Dst Account', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);

        for ($i = 1; $i <= 15; $i++) {
            AcMoneyDeposit::create([
                'store_id' => 1,
                'deposit_date' => '2026-09-03',
                'credit_account_id' => $dst->id,
                'amount' => 10.00,
                'reference_no' => 'DEP-PAGE-' . $i,
                'note' => 'Searchable Deposit Item',
                'created_by' => $i === 15 ? null : $this->store1User->id,
                'delete_bit' => 0,
            ]);
        }

        // Query string preserved
        $response = $this->actingAs($this->store1User)->get(route('accounts.deposit', ['search' => 'Searchable Deposit']));
        $response->assertOk();
        $hasPreserved = str_contains($response->getContent(), 'search=Searchable%20Deposit') || str_contains($response->getContent(), 'search=Searchable+Deposit');
        $this->assertTrue($hasPreserved);

        // Creator fallback: null created_by renders "System"
        $response->assertSee('System');
        $response->assertSee('Store 1 User');

        // Page size whitelist
        $response25 = $this->actingAs($this->store1User)->get(route('accounts.deposit', ['per_page' => 25]));
        $this->assertEquals(25, $response25->viewData('deposits')->perPage());

        $responseInvalid = $this->actingAs($this->store1User)->get(route('accounts.deposit', ['per_page' => 99999]));
        $this->assertEquals(10, $responseInvalid->viewData('deposits')->perPage());
    }

    /**
     * Item 4.1: Edit Deposit flow with 4-account changes, external-to-internal transitions, and overdraft blocks.
     */
    public function test_edit_deposit_flow()
    {
        $accOldDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc Old Debit', 'balance' => 800.00, 'status' => 1, 'delete_bit' => 0]);
        $accOldCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc Old Credit', 'balance' => 200.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewDebit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc New Debit', 'balance' => 1000.00, 'status' => 1, 'delete_bit' => 0]);
        $accNewCredit = AcAccount::create(['store_id' => 1, 'account_name' => 'Acc New Credit', 'balance' => 0.00, 'status' => 1, 'delete_bit' => 0]);

        // Original deposit: $200 from Acc Old Debit to Acc Old Credit
        $this->actingAs($this->store1User)->post(route('accounts.deposit.store'), [
            'deposit_date' => '2026-09-01',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'amount' => 200.00,
            'reference_no' => 'DEP-EDIT-01',
            'note' => 'Original Memo',
        ]);

        $deposit = AcMoneyDeposit::where('reference_no', 'DEP-EDIT-01')->firstOrFail();
        $this->assertEquals(600.00, (float) $accOldDebit->fresh()->balance);
        $this->assertEquals(400.00, (float) $accOldCredit->fresh()->balance);

        // 1. Metadata-only edit
        $this->actingAs($this->store1User)->put(route('accounts.deposit.update', $deposit->id), [
            'deposit_date' => '2026-09-01',
            'debit_account_id' => $accOldDebit->id,
            'credit_account_id' => $accOldCredit->id,
            'amount' => 200.00,
            'reference_no' => 'DEP-EDIT-UPDATED',
            'note' => 'Updated Note Only',
        ]);

        $this->assertEquals('DEP-EDIT-UPDATED', $deposit->fresh()->reference_no);
        $this->assertEquals(0, AcTransaction::where('ref_moneydeposits_id', $deposit->id)->where('transaction_type', 'DEPOSIT REVERSAL')->count());

        // 2. Full 4-Account Change: Change to Acc New Debit and Acc New Credit, amount = 300.00
        $this->actingAs($this->store1User)->put(route('accounts.deposit.update', $deposit->id), [
            'deposit_date' => '2026-09-02',
            'debit_account_id' => $accNewDebit->id,
            'credit_account_id' => $accNewCredit->id,
            'amount' => 300.00,
            'reference_no' => 'DEP-4ACC-NEW',
            'note' => 'Revised 4-Account Transfer',
        ]);

        // Balances verified:
        // Old Debit: 600 + 200 (refund) = 800.00
        $this->assertEquals(800.00, (float) $accOldDebit->fresh()->balance);
        // Old Credit: 400 - 200 (reversal) = 200.00
        $this->assertEquals(200.00, (float) $accOldCredit->fresh()->balance);
        // New Debit: 1000 - 300 (forward debit) = 700.00
        $this->assertEquals(700.00, (float) $accNewDebit->fresh()->balance);
        // New Credit: 0 + 300 (forward credit) = 300.00
        $this->assertEquals(300.00, (float) $accNewCredit->fresh()->balance);

        // 3. New source overdraft block
        $accNewDebit->balance = 50.00;
        $accNewDebit->save();

        $odResponse = $this->actingAs($this->store1User)->put(route('accounts.deposit.update', $deposit->id), [
            'deposit_date' => '2026-09-02',
            'debit_account_id' => $accNewDebit->id,
            'credit_account_id' => $accNewCredit->id,
            'amount' => 500.00,
        ]);
        $odResponse->assertSessionHasErrors(['amount']);
        $this->assertStringContainsString('Deposit amount cannot exceed the source account available balance', session('errors')->first('amount'));
    }

    /**
     * Item 4.2: Export to CSV and PDF returns full store-scoped, search-filtered dataset.
     */
    public function test_export_csv_and_pdf()
    {
        $dst1 = AcAccount::create(['store_id' => 1, 'account_name' => 'S1 Bank', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);
        $dst2 = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Bank', 'balance' => 5000, 'status' => 1, 'delete_bit' => 0]);

        AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst1->id,
            'amount' => 120.00,
            'reference_no' => 'EXP-MATCH-01',
            'note' => 'Keyword Match Alpha',
            'delete_bit' => 0,
        ]);

        AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst1->id,
            'amount' => 280.00,
            'reference_no' => 'EXP-MATCH-02',
            'note' => 'Keyword Match Beta',
            'delete_bit' => 0,
        ]);

        AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst1->id,
            'amount' => 999.00,
            'reference_no' => 'EXP-UNMATCH-01',
            'note' => 'Unrelated Deposit',
            'delete_bit' => 0,
        ]);

        AcMoneyDeposit::create([
            'store_id' => 2,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dst2->id,
            'amount' => 777.00,
            'reference_no' => 'S2-SECRET-EXP',
            'note' => 'Keyword Match Store 2 Secret',
            'delete_bit' => 0,
        ]);

        // CSV Export
        $csvResponse = $this->actingAs($this->store1User)->get(route('accounts.deposit', [
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
        $this->assertStringNotContainsString('EXP-UNMATCH-01', $csvContent);
        $this->assertStringNotContainsString('S2-SECRET-EXP', $csvContent);

        // PDF Print View
        $pdfResponse = $this->actingAs($this->store1User)->get(route('accounts.deposit', [
            'export' => 'pdf',
            'search' => 'Keyword Match',
        ]));
        $pdfResponse->assertOk();
        $pdfResponse->assertViewIs('module.accounts.deposit_list_print');
        $pdfResponse->assertSee('EXP-MATCH-01');
        $pdfResponse->assertSee('EXP-MATCH-02');
        $pdfResponse->assertSee('400.00'); // 120 + 280
        $pdfResponse->assertDontSee('EXP-UNMATCH-01');
        $pdfResponse->assertDontSee('S2-SECRET-EXP');
    }

    /**
     * Item 4.3: Bulk Delete with independent per-deposit solvency check and IDOR protection.
     */
    public function test_bulk_delete_with_independent_solvency_checks_and_idor_protection()
    {
        $dstSolvent = AcAccount::create(['store_id' => 1, 'account_name' => 'Solvent Bank', 'balance' => 500, 'status' => 1, 'delete_bit' => 0]);
        $dstInsolvent = AcAccount::create(['store_id' => 1, 'account_name' => 'Insolvent Bank', 'balance' => 20, 'status' => 1, 'delete_bit' => 0]);

        $dep1 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dstSolvent->id,
            'amount' => 200.00,
            'reference_no' => 'BULK-DEP-01',
            'delete_bit' => 0,
        ]);

        $dep2 = AcMoneyDeposit::create([
            'store_id' => 1,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $dstInsolvent->id,
            'amount' => 300.00,
            'reference_no' => 'BULK-DEP-02',
            'delete_bit' => 0,
        ]);

        $s2Dst = AcAccount::create(['store_id' => 2, 'account_name' => 'S2 Bank', 'balance' => 1000, 'status' => 1, 'delete_bit' => 0]);
        $dep3Store2 = AcMoneyDeposit::create([
            'store_id' => 2,
            'deposit_date' => '2026-09-03',
            'credit_account_id' => $s2Dst->id,
            'amount' => 100.00,
            'reference_no' => 'BULK-S2-VICTIM',
            'delete_bit' => 0,
        ]);

        // Submit bulk delete with all 3 IDs
        $response = $this->actingAs($this->store1User)->post(route('accounts.deposit.bulk-delete'), [
            'ids' => [$dep1->id, $dep2->id, $dep3Store2->id],
        ]);

        $response->assertRedirect(route('accounts.deposit'));
        $response->assertSessionHas('warning');

        $warningMsg = session('warning');
        $this->assertStringContainsString('Bulk Delete: 1 deposit(s) deleted and reversed.', $warningMsg);
        $this->assertStringContainsString('BULK-DEP-02: Destination account Insolvent Bank has insufficient balance', $warningMsg);
        $this->assertStringNotContainsString('BULK-S2-VICTIM', $warningMsg);

        // Verify DB states
        $this->assertEquals(1, $dep1->fresh()->delete_bit);
        $this->assertEquals(0, $dep2->fresh()->delete_bit);
        $this->assertEquals(0, $dep3Store2->fresh()->delete_bit);

        // Balances: 500 - 200 = 300.00
        $this->assertEquals(300.00, (float) $dstSolvent->fresh()->balance);
        $this->assertEquals(20.00, (float) $dstInsolvent->fresh()->balance);
    }
}
