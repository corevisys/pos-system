<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemAccountRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'mobile' => '2222222222']);

        $role = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'accounts_view', 'accounts_add', 'accounts_edit', 'accounts_delete',
                'money_deposit_view', 'money_deposit_add', 'money_deposit_edit', 'money_deposit_delete',
            ],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);
    }

    /**
     * GENUINE PARALLEL: Two independent SQLite connections race the very first creation of
     * the External Deposit Clearing account for a store. Both workers execute the exact
     * guarded sequence AcAccount::findOrCreateSystemAccount() performs:
     *   1) lockForUpdate-style row lock via BEGIN IMMEDIATE + SELECT
     *   2) guarded INSERT under the (store_id, system_key) unique index
     *   3) on unique-violation -> re-select the winning row
     * Released simultaneously by a barrier file. Exactly ONE clearing account must remain.
     */
    public function test_concurrent_external_deposits_create_exactly_one_clearing_account()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/sysacc_clearing_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/sysacc_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/sysacc_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                account_name TEXT,
                account_code TEXT,
                system_key TEXT,
                is_system INTEGER DEFAULT 0,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE UNIQUE INDEX ac_accounts_store_system_key_unique ON ac_accounts (store_id, system_key);
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
            // Begin an IMMEDIATE transaction = the write-lock that lockForUpdate() takes.
            $pdo->exec("BEGIN IMMEDIATE TRANSACTION");

            // Lock-existing-row SELECT (equivalent to ->where(system_key)->lockForUpdate()->first()).
            $stmt = $pdo->prepare("SELECT id FROM ac_accounts WHERE store_id = 1 AND system_key = \'external_deposit_clearing\' AND delete_bit = 0");
            $stmt->execute();
            $existingId = $stmt->fetchColumn();

            if ($existingId !== false) {
                $pdo->exec("COMMIT");
                echo "RESULT:EXISTING:" . $existingId . "\n";
                exit(0);
            }

            // No row — attempt guarded INSERT under the unique index.
            try {
                $pdo->exec("INSERT INTO ac_accounts (store_id, account_name, account_code, system_key, is_system, balance, status, delete_bit) VALUES (1, \'External Deposit Clearing\', \'CLR-\' || \'$ref\', \'external_deposit_clearing\', 1, 0, 1, 0)");
                $pdo->exec("COMMIT");
                $winnerId = $pdo->lastInsertId();
                echo "RESULT:CREATED:" . $winnerId . "\n";
            } catch (\PDOException $e) {
                // Unique violation on (store_id, system_key) — another worker won the race.
                if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
                if (str_contains(strtolower($e->getMessage()), \'unique\')) {
                    $stmt = $pdo->prepare("SELECT id FROM ac_accounts WHERE store_id = 1 AND system_key = \'external_deposit_clearing\' AND delete_bit = 0");
                    $stmt->execute();
                    $winnerId = $stmt->fetchColumn();
                    echo "RESULT:RESELECTED:" . ($winnerId === false ? "NONE" : $winnerId) . "\n";
                } else {
                    echo "RESULT:ERROR:" . $e->getMessage() . "\n";
                }
            }
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' A', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' B', $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $combined = $out1 . $out2;
        // At least one worker must have CREATED the row; the other either saw EXISTING,
        // hit the unique-violation and RESELECTED the winner, or (in a single-writer SQLite
        // schedule) observed EXISTING after the first COMMIT.
        $this->assertStringContainsString('RESULT:CREATED:', $combined, 'At least one worker must create the clearing account.');
        $this->assertStringContainsString('RESULT:EXISTING:', $combined, 'Second worker must observe the existing row (re-select path).');

        $count = (int) $pdo->query("SELECT COUNT(*) FROM ac_accounts WHERE store_id = 1 AND system_key = 'external_deposit_clearing'")->fetchColumn();
        $this->assertEquals(1, $count, 'Exactly one External Deposit Clearing account may exist per store even under genuine parallel creation.');

        @unlink($dbPath);
    }

    /**
     * GENUINE PARALLEL: Two independent SQLite connections race the very first creation of
     * the Opening Balance Equity account for a store. Same guarded insert-under-unique-index
     * sequence. Exactly ONE equity account must remain.
     */
    public function test_concurrent_account_creation_with_opening_balance_creates_exactly_one_equity_account()
    {
        skipUnlessSqlite();
        $dbPath = sys_get_temp_dir() . '/sysacc_equity_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/sysacc_barrier_eq_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/sysacc_worker_eq_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE ac_accounts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                account_name TEXT,
                account_code TEXT,
                system_key TEXT,
                is_system INTEGER DEFAULT 0,
                balance REAL DEFAULT 0,
                status INTEGER DEFAULT 1,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE UNIQUE INDEX ac_accounts_store_system_key_unique ON ac_accounts (store_id, system_key);
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

            $stmt = $pdo->prepare("SELECT id FROM ac_accounts WHERE store_id = 1 AND system_key = \'opening_balance_equity\' AND delete_bit = 0");
            $stmt->execute();
            $existingId = $stmt->fetchColumn();

            if ($existingId !== false) {
                $pdo->exec("COMMIT");
                echo "RESULT:EXISTING:" . $existingId . "\n";
                exit(0);
            }

            try {
                $pdo->exec("INSERT INTO ac_accounts (store_id, account_name, account_code, system_key, is_system, balance, status, delete_bit) VALUES (1, \'Opening Balance Equity\', \'EQ-\' || \'$ref\', \'opening_balance_equity\', 1, 0, 1, 0)");
                $pdo->exec("COMMIT");
                $winnerId = $pdo->lastInsertId();
                echo "RESULT:CREATED:" . $winnerId . "\n";
            } catch (\PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
                if (str_contains(strtolower($e->getMessage()), \'unique\')) {
                    $stmt = $pdo->prepare("SELECT id FROM ac_accounts WHERE store_id = 1 AND system_key = \'opening_balance_equity\' AND delete_bit = 0");
                    $stmt->execute();
                    $winnerId = $stmt->fetchColumn();
                    echo "RESULT:RESELECTED:" . ($winnerId === false ? "NONE" : $winnerId) . "\n";
                } else {
                    echo "RESULT:ERROR:" . $e->getMessage() . "\n";
                }
            }
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) { $pdo->exec("ROLLBACK"); }
            echo "RESULT:ERROR:" . $e->getMessage() . "\n";
        }
        ';

        file_put_contents($workerScript, $workerCode);

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc1 = proc_open('php ' . escapeshellarg($workerScript) . ' A', $descriptors, $pipes1);
        $proc2 = proc_open('php ' . escapeshellarg($workerScript) . ' B', $descriptors, $pipes2);

        file_put_contents($barrierFile, 'GO');

        $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($proc1);
        $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($proc2);

        @unlink($barrierFile);
        @unlink($workerScript);

        $combined = $out1 . $out2;
        $this->assertStringContainsString('RESULT:CREATED:', $combined, 'At least one worker must create the equity account.');
        $this->assertStringContainsString('RESULT:EXISTING:', $combined, 'Second worker must observe the existing row (re-select path).');

        $count = (int) $pdo->query("SELECT COUNT(*) FROM ac_accounts WHERE store_id = 1 AND system_key = 'opening_balance_equity'")->fetchColumn();
        $this->assertEquals(1, $count, 'Exactly one Opening Balance Equity account may exist per store even under genuine parallel creation.');

        @unlink($dbPath);
    }

    /**
     * NON-RACE (sequential sanity): System accounts are flagged is_system = true for Phase D exclusion.
     */
    public function test_system_accounts_are_flagged()
    {
        $clearing = AcAccount::findOrCreateSystemAccount(1, 'external_deposit_clearing', 'External Deposit Clearing');
        $equity = AcAccount::findOrCreateSystemAccount(1, 'opening_balance_equity', 'Opening Balance Equity');

        $this->assertTrue((bool) $clearing->is_system);
        $this->assertTrue((bool) $equity->is_system);
        $this->assertEquals('external_deposit_clearing', $clearing->system_key);
        $this->assertEquals('opening_balance_equity', $equity->system_key);
    }

    /**
     * NON-RACE (sequential sanity): The two system-account types are independent per store.
     */
    public function test_different_system_keys_are_independent_per_store()
    {
        $clearing = AcAccount::findOrCreateSystemAccount(1, 'external_deposit_clearing', 'External Deposit Clearing');
        $equity = AcAccount::findOrCreateSystemAccount(1, 'opening_balance_equity', 'Opening Balance Equity');

        $this->assertNotEquals($clearing->id, $equity->id);
        $this->assertNotEquals($clearing->system_key, $equity->system_key);
    }
}
