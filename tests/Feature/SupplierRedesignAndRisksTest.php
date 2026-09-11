<?php

namespace Tests\Feature;

use App\Models\AcTransaction;
use App\Models\DbPermission;
use App\Models\DbPurchase;
use App\Models\DbPurchaseReturn;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbSupplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierRedesignAndRisksTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Supplier Test Store',
            'status' => 1,
            'mobile' => '+8801700000000',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 1], [
            'store_id' => 1,
            'permissions' => ['suppliers_view', 'suppliers_add', 'suppliers_edit', 'suppliers_delete'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);
    }

    public function test_suppliers_list_page_renders_with_search_and_pagination()
    {
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Acme Supplies',
            'supplier_code' => 'SUP-001',
            'mobile' => '01711111111',
            'email' => 'acme@example.com',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Global Logistics',
            'supplier_code' => 'SUP-002',
            'mobile' => '01822222222',
            'email' => 'global@example.com',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['search' => 'Acme']));
        $response->assertStatus(200);
        $response->assertSee('Acme Supplies');
        $response->assertDontSee('Global Logistics');
    }

    public function test_suppliers_list_export_csv_and_print()
    {
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Export Supplier',
            'supplier_code' => 'SUP-EXP',
            'mobile' => '01933333333',
            'email' => 'export@example.com',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $csvResponse = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['export' => 'csv']));
        $csvResponse->assertStatus(200);
        $this->assertStringContainsString('text/csv', $csvResponse->headers->get('content-type'));

        $printResponse = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['export' => 'print']));
        $printResponse->assertStatus(200);
        $printResponse->assertSee('Export Supplier');
    }

    public function test_supplier_store_and_update_with_vatin_and_unique_mobile_email()
    {
        // 1. Create initial supplier
        $storeResponse = $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'First Supplier',
            'mobile' => '01712345678',
            'email' => 'supplier1@test.com',
            'vatin' => 'VAT-12345',
            'gstin' => 'GST-999',
            'tax_number' => 'TAX-888',
        ]);
        $storeResponse->assertRedirect(route('contacts.suppliers.list'));

        $supplier = DbSupplier::where('mobile', '01712345678')->first();
        $this->assertNotNull($supplier);
        $this->assertEquals('VAT-12345', $supplier->vatin);

        // 2. Reject duplicate mobile
        $dupMobileResponse = $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Second Supplier',
            'mobile' => '01712345678',
            'email' => 'supplier2@test.com',
        ]);
        $dupMobileResponse->assertSessionHasErrors(['mobile']);

        // 3. Reject duplicate email
        $dupEmailResponse = $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Third Supplier',
            'mobile' => '01799999999',
            'email' => 'supplier1@test.com',
        ]);
        $dupEmailResponse->assertSessionHasErrors(['email']);

        // 4. Update own supplier without duplicate collision
        $updateResponse = $this->actingAs($this->user)->post(route('contacts.suppliers.update', $supplier->id), [
            'supplier_name' => 'First Supplier Updated',
            'mobile' => '01712345678',
            'email' => 'supplier1@test.com',
            'vatin' => 'VAT-UPDATED',
        ]);
        $updateResponse->assertRedirect(route('contacts.suppliers.list'));
        $supplier->refresh();
        $this->assertEquals('First Supplier Updated', $supplier->supplier_name);
        $this->assertEquals('VAT-UPDATED', $supplier->vatin);
    }

    public function test_quick_add_ajax_contract_preserved_for_new_purchase()
    {
        $response = $this->actingAs($this->user)->postJson(route('contacts.suppliers.store'), [
            'supplier_name' => 'Quick Add Supplier',
            'mobile' => '01500000000',
            'email' => 'quick@supplier.com',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'id',
            'supplier_name',
        ]);
        $response->assertJson([
            'success' => true,
            'supplier_name' => 'Quick Add Supplier',
        ]);
    }

    public function test_supplier_delete_protection_when_purchase_history_exists()
    {
        $supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Supplier With History',
            'supplier_code' => 'SUP-HIST',
            'mobile' => '01611111111',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Attach a purchase
        DbPurchase::create([
            'store_id' => 1,
            'purchase_code' => 'PUR-001',
            'supplier_id' => $supplier->id,
            'purchase_date' => date('Y-m-d'),
            'purchase_status' => 'Received',
            'grand_total' => 1000,
            'paid_amount' => 500,
            'store_id' => 1,
        ]);

        $deleteResponse = $this->actingAs($this->user)->delete(route('contacts.suppliers.delete', $supplier->id));
        $deleteResponse->assertSessionHas('error');

        $supplier->refresh();
        $this->assertEquals(0, $supplier->delete_bit);
        $this->assertNull($supplier->deleted_at);
    }

    public function test_supplier_delete_block_message_names_record_type_and_count()
    {
        $supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Supplier Two Purchases',
            'supplier_code' => 'SUP-TWO',
            'mobile' => '01633333333',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Attach two purchases → guard must name type + count
        DbPurchase::create([
            'store_id' => 1,
            'purchase_code' => 'PUR-101',
            'supplier_id' => $supplier->id,
            'purchase_date' => date('Y-m-d'),
            'purchase_status' => 'Received',
            'grand_total' => 500,
            'paid_amount' => 0,
            'store_id' => 1,
        ]);
        DbPurchase::create([
            'store_id' => 1,
            'purchase_code' => 'PUR-102',
            'supplier_id' => $supplier->id,
            'purchase_date' => date('Y-m-d'),
            'purchase_status' => 'Received',
            'grand_total' => 300,
            'paid_amount' => 0,
            'store_id' => 1,
        ]);

        $deleteResponse = $this->actingAs($this->user)->delete(route('contacts.suppliers.delete', $supplier->id));
        $deleteResponse->assertSessionHas('error', function (string $error) {
            return str_contains($error, '2 purchase(s)') && str_contains($error, 'cannot be deleted');
        });

        $supplier->refresh();
        $this->assertEquals(0, $supplier->delete_bit, 'delete_bit must remain 0 when delete is blocked.');
        $this->assertNull($supplier->deleted_at);
    }

    public function test_destroy_is_store_scoped_blocking_cross_store_delete()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        $store2Supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Store 2 Secret Supplier',
            'supplier_code' => 'SUP-S2',
            'mobile' => '01644444444',
            'store_id' => 2,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Store-1 user attempts to delete a Store-2 supplier id → not found, untouched.
        $deleteResponse = $this->actingAs($this->user)->delete(route('contacts.suppliers.delete', $store2Supplier->id));
        $deleteResponse->assertSessionHas('error');

        $store2Supplier->refresh();
        $this->assertEquals(0, $store2Supplier->delete_bit, 'Cross-store delete must not flip delete_bit.');
        $this->assertNull($store2Supplier->deleted_at);
    }

    public function test_genuine_parallel_double_delete_exactly_one_succeeds()
    {
        $dbPath = sys_get_temp_dir() . '/supplier_del_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/supplier_del_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/supplier_del_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $pdo->exec("
            CREATE TABLE db_suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                supplier_name TEXT,
                delete_bit INTEGER DEFAULT 0
            );
            INSERT INTO db_suppliers (store_id, supplier_name, delete_bit) VALUES (1, 'Parallel Delete Supplier', 0);
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

            // Atomic conditional soft-delete — the exact pattern in SupplierController::destroy().
            $stmt = $pdo->prepare("UPDATE db_suppliers SET delete_bit = 1 WHERE id = 1 AND store_id = 1 AND delete_bit = 0");
            $stmt->execute();
            if ($stmt->rowCount() !== 1) {
                $pdo->exec("ROLLBACK");
                echo "RESULT:ALREADY_DELETED\n";
                exit(0);
            }

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

        $deleteBit = (int) $pdo->query("SELECT delete_bit FROM db_suppliers WHERE id = 1")->fetchColumn();
        $succeeds = substr_count($out1 . $out2, 'RESULT:SUCCESS');
        $already = substr_count($out1 . $out2, 'RESULT:ALREADY_DELETED');
        @unlink($dbPath);

        $this->assertEquals(1, $succeeds, "Exactly one parallel delete must succeed.\nOut1: {$out1}\nOut2: {$out2}");
        $this->assertEquals(1, $already, "One parallel delete must observe already-deleted.\nOut1: {$out1}\nOut2: {$out2}");
        $this->assertEquals(1, $deleteBit, 'Final delete_bit must be 1 after the genuine parallel race.');
    }

    public function test_supplier_clean_deletion_when_no_history()
    {
        $supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Clean Supplier',
            'supplier_code' => 'SUP-CLEAN',
            'mobile' => '01622222222',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $deleteResponse = $this->actingAs($this->user)->delete(route('contacts.suppliers.delete', $supplier->id));
        $deleteResponse->assertRedirect(route('contacts.suppliers.list'));
        $deleteResponse->assertSessionHas('success');

        $supplier = DbSupplier::withTrashed()->find($supplier->id);
        $this->assertEquals(1, $supplier->delete_bit);
        $this->assertNull($supplier->deleted_at, 'New destroy() only flips delete_bit and never calls Eloquent delete().');
    }

    // ─────────────────────────── PHASE 2 ───────────────────────────

    public function test_live_dues_are_store_scoped_and_match_ac_transactions_hand_calculation()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        $s1 = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Live Dues S1',
            'supplier_code' => 'SUP-LIVE1',
            'mobile' => '01655555555',
            'opening_balance' => 100.00,
            // Stale static columns — intentionally DIFFERENT from live figures below.
            'purchase_due' => 9999.00,
            'purchase_return_due' => 8888.00,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $s2 = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Live Dues S2 (other store)',
            'supplier_code' => 'SUP-LIVE2',
            'mobile' => '01666666666',
            'opening_balance' => 1000.00,
            'purchase_due' => 12345.00,
            'purchase_return_due' => 1111.00,
            'store_id' => 2,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // ── Store 1 ledger hand-calculation for $s1 ──
        // PURCHASE PAYABLE (credit): +1000
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $s1->id, 'transaction_type' => 'PURCHASE PAYABLE',
            'transaction_date' => date('Y-m-d'), 'credit_amt' => 1000.00, 'debit_amt' => 0,
        ]);
        // PURCHASE PAYMENT (debit): -300
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $s1->id, 'transaction_type' => 'PURCHASE PAYMENT',
            'transaction_date' => date('Y-m-d'), 'debit_amt' => 300.00, 'credit_amt' => 0,
        ]);
        // PURCHASE RETURN PAYABLE (debit): -100
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $s1->id, 'transaction_type' => 'PURCHASE RETURN PAYABLE',
            'transaction_date' => date('Y-m-d'), 'debit_amt' => 100.00, 'credit_amt' => 0,
        ]);
        // Opening balance (100) + 1000 - 300 - 100 = 700 live purchase due.

        // DbPurchaseReturn live return due: grand_total 500 - paid 150 = 350.
        DbPurchaseReturn::create([
            'store_id' => 1,
            'purchase_id' => null, 'return_code' => 'RET-LIVE1', 'supplier_id' => $s1->id,
            'return_date' => date('Y-m-d'), 'return_status' => 'Received',
            'grand_total' => 500.00, 'paid_amount' => 150.00, 'store_id' => 1,
        ]);

        // ── Store 2 ledger rows that MUST NOT leak into store 1 ──
        AcTransaction::create([
            'store_id' => 2, 'supplier_id' => $s2->id, 'transaction_type' => 'PURCHASE PAYABLE',
            'transaction_date' => date('Y-m-d'), 'credit_amt' => 5000.00, 'debit_amt' => 0,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.suppliers.list'));
        $response->assertStatus(200);
        $response->assertSee('Live Dues S1');
        $response->assertDontSee('Live Dues S2 (other store)');

        // Inspect the rendered values via the print view which exposes formatted live dues.
        $print = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['export' => 'print']));
        $print->assertStatus(200);
        $printHtml = $print->getContent();
        // 700.00 live purchase due and 350.00 live return due (computed live, not the stale 9999/8888).
        $this->assertStringContainsString('700.00', $printHtml, 'Live purchase due must be 700.00 from hand-calculated ledger.');
        $this->assertStringContainsString('350.00', $printHtml, 'Live return due must be 350.00 from DbPurchaseReturn.');
        $this->assertStringNotContainsString('9999.00', $printHtml, 'Stale static purchase_due column must NOT be used for display.');
        $this->assertStringNotContainsString('8888.00', $printHtml, 'Stale static purchase_return_due column must NOT be used for display.');
        // Store 2's giant payable must not leak into store 1 totals either.
        $this->assertStringNotContainsString('5000.00', $printHtml, 'Store 2 ledger rows must not inflate store 1 dues.');
    }

    public function test_index_is_store_scoped_excluding_other_store_suppliers()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Visible S1 Supplier',
            'supplier_code' => 'SUP-VIS',
            'mobile' => '01677777777',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Hidden S2 Supplier',
            'supplier_code' => 'SUP-HID',
            'mobile' => '01688888888',
            'store_id' => 2,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.suppliers.list'));
        $response->assertStatus(200);
        $response->assertSee('Visible S1 Supplier');
        $response->assertDontSee('Hidden S2 Supplier');
    }

    public function test_account_payable_filter_returns_only_suppliers_with_live_purchase_due()
    {
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'AP Positive Supplier',
            'supplier_code' => 'SUP-AP1',
            'mobile' => '01699990001',
            'opening_balance' => 0,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'AP Zero Supplier',
            'supplier_code' => 'SUP-AP2',
            'mobile' => '01699990002',
            'opening_balance' => 0,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'AP Fully Paid Supplier',
            'supplier_code' => 'SUP-AP3',
            'mobile' => '01699990003',
            'opening_balance' => 0,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $positive = DbSupplier::where('supplier_code', 'SUP-AP1')->first();
        $fullyPaid = DbSupplier::where('supplier_code', 'SUP-AP3')->first();

        // Positive: payable 1000, paid 0 → live purchase due 1000.
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $positive->id, 'transaction_type' => 'PURCHASE PAYABLE',
            'transaction_date' => date('Y-m-d'), 'credit_amt' => 1000.00, 'debit_amt' => 0,
        ]);
        // Fully paid: payable 500, paid 500 → live purchase due 0.
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $fullyPaid->id, 'transaction_type' => 'PURCHASE PAYABLE',
            'transaction_date' => date('Y-m-d'), 'credit_amt' => 500.00, 'debit_amt' => 0,
        ]);
        AcTransaction::create([
            'store_id' => 1, 'supplier_id' => $fullyPaid->id, 'transaction_type' => 'PURCHASE PAYMENT',
            'transaction_date' => date('Y-m-d'), 'debit_amt' => 500.00, 'credit_amt' => 0,
        ]);

        $response = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['account_payable' => 1]));
        $response->assertStatus(200);
        $response->assertSee('AP Positive Supplier');
        $response->assertDontSee('AP Zero Supplier');
        $response->assertDontSee('AP Fully Paid Supplier');

        // CSV export under the same flag must mirror the filter.
        $csv = $this->actingAs($this->user)->get(route('contacts.suppliers.list', ['export' => 'csv', 'account_payable' => 1]));
        $csv->assertStatus(200);
        $this->assertStringContainsString('AP Positive Supplier', $csv->streamedContent());
        $this->assertStringNotContainsString('AP Fully Paid Supplier', $csv->streamedContent());
    }

    public function test_store_persists_current_store_id_for_web_and_ajax()
    {
        // Non-ajax form submit
        $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Store Aware Web',
            'mobile' => '01699991111',
            'email' => 'web-aware@example.com',
        ])->assertRedirect(route('contacts.suppliers.list'));

        $web = DbSupplier::where('supplier_name', 'Store Aware Web')->first();
        $this->assertNotNull($web);
        $this->assertEquals(1, (int) $web->store_id, 'Non-ajax store() must persist current store_id.');

        // AJAX quick-add
        $this->actingAs($this->user)->postJson(route('contacts.suppliers.store'), [
            'supplier_name' => 'Store Aware Ajax',
            'mobile' => '01699992222',
            'email' => 'ajax-aware@example.com',
        ])->assertStatus(200);

        $ajax = DbSupplier::where('supplier_name', 'Store Aware Ajax')->first();
        $this->assertNotNull($ajax);
        $this->assertEquals(1, (int) $ajax->store_id, 'AJAX quick-add store() must persist current store_id.');
    }

    // ─────────────────────────── PHASE 3 ───────────────────────────

    public function test_add_and_edit_forms_render_currency_symbol_and_supplier_forms_open()
    {
        // DbCurrency seeded so resolveCurrencySymbol() can return a symbol.
        $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Bangladeshi Taka',
            'currency_code' => 'BDT',
            'symbol' => '৳',
            'status' => 1,
        ]);
        if (function_exists('flush_store_settings_cache')) {
            flush_store_settings_cache();
        }
        if ($this->store->currency_id !== $currency->id) {
            $this->store->update(['currency_id' => $currency->id]);
        }

        $createResponse = $this->actingAs($this->user)->get(route('contacts.suppliers.add'));
        $createResponse->assertStatus(200);
        $createResponse->assertSee('৳');

        $supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Edit Form Supplier',
            'supplier_code' => 'SUP-EDIT',
            'mobile' => '01699993333',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $editResponse = $this->actingAs($this->user)->get(route('contacts.suppliers.edit', $supplier->id));
        $editResponse->assertStatus(200);
        $editResponse->assertSee('৳');
        $editResponse->assertSee('Edit Form Supplier');
    }

    public function test_quick_add_without_vatin_succeeds_with_null_vatin()
    {
        $response = $this->actingAs($this->user)->postJson(route('contacts.suppliers.store'), [
            'supplier_name' => 'No Vatin Ajax Supplier',
            'mobile' => '01699994444',
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['success', 'message', 'id', 'supplier_name']);

        $supplier = DbSupplier::where('supplier_name', 'No Vatin Ajax Supplier')->first();
        $this->assertNotNull($supplier);
        $this->assertNull($supplier->vatin, 'vatin must remain null when the quick-add modal omits it.');
    }

    public function test_full_form_saves_vatin_and_edit_scope_blocks_other_store()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        // Full form save with a vatin value
        $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Vatin Supplier',
            'mobile' => '01699995555',
            'email' => 'vatin@example.com',
            'vatin' => 'VAT-FULL-001',
        ])->assertRedirect(route('contacts.suppliers.list'));

        $vatinSupplier = DbSupplier::where('supplier_name', 'Vatin Supplier')->first();
        $this->assertNotNull($vatinSupplier);
        $this->assertEquals('VAT-FULL-001', $vatinSupplier->vatin);

        // Store-2 supplier is not editable by the store-1 user.
        $store2Supplier = DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'S2 Not Editable',
            'supplier_code' => 'SUP-S2EDIT',
            'mobile' => '01699996666',
            'store_id' => 2,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $this->actingAs($this->user)->get(route('contacts.suppliers.edit', $store2Supplier->id))
            ->assertRedirect(route('contacts.suppliers.list'));

        $this->actingAs($this->user)->post(route('contacts.suppliers.update', $store2Supplier->id), [
            'supplier_name' => 'S2 Hacked',
            'mobile' => '01699996666',
        ])->assertRedirect(route('contacts.suppliers.list'));

        $store2Supplier->refresh();
        $this->assertEquals('S2 Not Editable', $store2Supplier->supplier_name, 'Cross-store update must be blocked.');
    }

    // ─────────────────────────── PHASE 4 ───────────────────────────

    public function test_db_constraint_enforces_per_store_unique_mobile_and_email()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        // Same store 1 phone — first insert is fine.
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Constraint S1 A',
            'supplier_code' => 'SUP-C1A',
            'mobile' => '01700000001',
            'email' => 'constraint-a@example.com',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Same store 1 phone → DB-level unique violation on (store_id, mobile).
        try {
            DbSupplier::create([
                'store_id' => 1,
                'supplier_name' => 'Constraint S1 B',
                'supplier_code' => 'SUP-C1B',
                'mobile' => '01700000001',
                'email' => 'constraint-b@example.com',
                'store_id' => 1,
                'status' => 1,
                'delete_bit' => 0,
            ]);
            $this->fail('Same-store duplicate mobile must violate the (store_id, mobile) unique index.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }

        // Same phone in store 2 → allowed (per-store scope).
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Constraint S2',
            'supplier_code' => 'SUP-C2',
            'mobile' => '01700000001',
            'email' => 'constraint-c@example.com',
            'store_id' => 2,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $this->assertEquals(2, DbSupplier::where('mobile', '01700000001')->count(), 'Same mobile must be allowed across stores.');

        // Same-store duplicate email → DB violation.
        try {
            DbSupplier::create([
                'store_id' => 1,
                'supplier_name' => 'Constraint S1 D',
                'supplier_code' => 'SUP-C1D',
                'mobile' => '01700000009',
                'email' => 'constraint-a@example.com',
                'store_id' => 1,
                'status' => 1,
                'delete_bit' => 0,
            ]);
            $this->fail('Same-store duplicate email must violate the (store_id, email) unique index.');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertTrue(true);
        }

        // Blank email is exempt — multiple suppliers may share an empty email.
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Blank Email One',
            'supplier_code' => 'SUP-BE1',
            'mobile' => '01700000010',
            'email' => null,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Blank Email Two',
            'supplier_code' => 'SUP-BE2',
            'mobile' => '01700000011',
            'email' => null,
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);
        $this->assertEquals(2, DbSupplier::whereNull('email')->where('store_id', 1)->count(), 'Blank/null email must remain exempt from uniqueness.');
    }

    public function test_same_store_duplicate_mobile_rejected_and_cross_store_allowed_via_http()
    {
        DbStore::firstOrCreate(['id' => 2], [
            'store_name' => 'Supplier Test Store 2',
            'status' => 1,
            'mobile' => '+8801711111111',
        ]);

        // Seed a store-1 supplier with this phone.
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Existing Phone Owner',
            'supplier_code' => 'SUP-PH',
            'mobile' => '01711112222',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Same-store duplicate phone → clean validation error.
        $dup = $this->actingAs($this->user)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Duplicate Phone',
            'mobile' => '01711112222',
        ]);
        $dup->assertSessionHasErrors(['mobile' => 'A supplier with this phone number already exists.']);

        // Same phone in a DIFFERENT store → allowed (store-2 user).
        $store2User = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 2,
            'status' => 1,
        ]);
        $cross = $this->actingAs($store2User)->post(route('contacts.suppliers.store'), [
            'supplier_name' => 'Cross Store Phone',
            'mobile' => '01711112222',
        ]);
        $cross->assertRedirect(route('contacts.suppliers.list'));
        $this->assertNotNull(DbSupplier::where('mobile', '01711112222')->where('store_id', 2)->first());
    }

    public function test_ajax_quick_add_duplicate_returns_clean_json_error()
    {
        DbSupplier::create([
            'store_id' => 1,
            'supplier_name' => 'Ajax Dup Original',
            'supplier_code' => 'SUP-AJAXDUP',
            'mobile' => '01722223333',
            'store_id' => 1,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        // Quick-add posts JSON — must NOT be a raw 500; must be a clean {success:false,message}.
        $response = $this->actingAs($this->user)->postJson(route('contacts.suppliers.store'), [
            'supplier_name' => 'Ajax Dup Attempt',
            'mobile' => '01722223333',
        ]);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message']);
        $response->assertJson([
            'message' => 'A supplier with this phone number already exists.',
        ]);
        $this->assertSame(false, $response->json('success'));
    }

    public function test_genuine_parallel_same_store_same_phone_exactly_one_succeeds()
    {
        $dbPath = sys_get_temp_dir() . '/supplier_phone_race_' . uniqid() . '.sqlite';
        $barrierFile = sys_get_temp_dir() . '/supplier_phone_barrier_' . uniqid() . '.txt';
        $workerScript = sys_get_temp_dir() . '/supplier_phone_worker_' . uniqid() . '.php';

        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Recreate the exact per-store composite unique index from the migration.
        $pdo->exec("
            CREATE TABLE db_suppliers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                store_id INTEGER,
                supplier_name TEXT,
                mobile TEXT,
                email TEXT,
                delete_bit INTEGER DEFAULT 0
            );
            CREATE UNIQUE INDEX db_suppliers_store_mobile_unique ON db_suppliers (store_id, mobile);
        ");

        $workerCode = '<?php
        $dbPath = "' . addslashes($dbPath) . '";
        $barrier = "' . addslashes($barrierFile) . '";

        $pdo = new \PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(\PDO::ATTR_TIMEOUT, 10);

        while (!file_exists($barrier)) { usleep(100); }

        try {
            $stmt = $pdo->prepare("INSERT INTO db_suppliers (store_id, supplier_name, mobile, delete_bit) VALUES (1, \'Parallel Phone\', \'01777778888\', 0)");
            $stmt->execute();
            echo "RESULT:SUCCESS\n";
        } catch (\Exception $e) {
            echo "RESULT:FAILED:" . $e->getMessage() . "\n";
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

        $count = (int) $pdo->query("SELECT COUNT(*) FROM db_suppliers WHERE store_id = 1 AND mobile = '01777778888'")->fetchColumn();
        @unlink($dbPath);

        $combined = $out1 . $out2;
        $this->assertEquals(1, substr_count($combined, 'RESULT:SUCCESS'), "Exactly one parallel insert must win.\nOut1: {$out1}\nOut2: {$out2}");
        $this->assertStringContainsString('UNIQUE constraint failed', $combined, 'The losing worker must fail on the per-store unique index.');
        $this->assertEquals(1, $count, 'Exactly one supplier row may exist for the same store + phone under genuine parallelism.');
    }
}
