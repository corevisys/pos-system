<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbCustAdvance;
use App\Models\DbCustomer;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvanceTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $customer;
    protected $account1;
    protected $account2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Advance Test Store',
            'status' => 1,
            'mobile' => '+8801700000000',
            'cust_advance_init' => 'AD',
        ]);

        $role = DbRole::firstOrCreate(['id' => 1], [
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        DbPermission::firstOrCreate(['role_id' => 1], [
            'store_id' => 1,
            'permissions' => [
                'customers_advance_payments_view',
                'customers_advance_payments_add',
                'customers_advance_payments_edit',
                'customers_advance_payments_delete',
            ],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'store_id' => 1,
            'customer_name' => 'Test Customer',
            'customer_code' => 'CUST-001',
            'mobile' => '01711111111',
            'status' => 1,
            'tot_advance' => 0,
        ]);

        $this->account1 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Cash in Hand',
            'account_code' => 'ACC-001',
            'account_number' => '1001',
            'balance' => 1000.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);

        $this->account2 = AcAccount::create([
            'store_id' => 1,
            'account_name' => 'Bank Account',
            'account_code' => 'ACC-002',
            'account_number' => '1002',
            'balance' => 500.00,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    public function test_advance_list_and_create_pages_render_successfully()
    {
        $this->actingAs($this->user);

        $responseList = $this->get(route('advance.list'));
        $responseList->assertStatus(200);
        $responseList->assertSee('Advance Payments');
        $responseList->assertSee('Add Advance');

        $responseAdd = $this->get(route('advance.add'));
        $responseAdd->assertStatus(200);
        $responseAdd->assertSee('New Advance Payment');
        $responseAdd->assertSee('Save Advance');
    }

    public function test_advance_create_stores_record_syncs_ledger_and_updates_account_balance()
    {
        $this->actingAs($this->user);

        $payload = [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 500.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
            'note' => 'Initial deposit',
        ];

        $response = $this->post(route('advance.store'), $payload);
        $response->assertRedirect(route('advance.list'));
        $response->assertSessionHas('success');

        // Check advance record created
        $advance = DbCustAdvance::where('customer_id', $this->customer->id)->first();
        $this->assertNotNull($advance);
        $this->assertEquals(500.00, (float) $advance->amount);
        $this->assertEquals($this->account1->id, $advance->account_id);
        $this->assertEquals(1, $advance->store_id);

        // Check customer total advance incremented
        $this->customer->refresh();
        $this->assertEquals(500.00, (float) $this->customer->tot_advance);

        // Check AcAccount balance incremented
        $this->account1->refresh();
        $this->assertEquals(1500.00, (float) $this->account1->balance);

        // Check AcTransaction created with short_code and double-entry fields
        $transaction = AcTransaction::where('short_code', $advance->payment_code)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals('CUSTOMER ADVANCE', $transaction->transaction_type);
        $this->assertEquals($this->account1->id, $transaction->credit_account_id);
        $this->assertEquals(500.00, (float) $transaction->credit_amt);
        $this->assertEquals($this->customer->id, $transaction->customer_id);
    }

    public function test_advance_update_syncs_ledger_and_updates_account_balance_when_account_or_amount_changes()
    {
        $this->actingAs($this->user);

        // 1. Create initial advance of 500 in account1
        $this->post(route('advance.store'), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 500.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
            'note' => 'Initial deposit',
        ]);

        $advance = DbCustAdvance::where('customer_id', $this->customer->id)->first();

        // 2. Update advance to 300 in account2 (Bank)
        $updatePayload = [
            'payment_date' => '2026-09-04',
            'customer_id' => $this->customer->id,
            'amount' => 300.00,
            'payment_type' => 'Bank Transfer',
            'account_id' => $this->account2->id,
            'note' => 'Adjusted deposit',
        ];

        $response = $this->post(route('advance.update', $advance->id), $updatePayload);
        $response->assertRedirect(route('advance.list'));
        $response->assertSessionHas('success');

        // Verify customer tot_advance updated to 300
        $this->customer->refresh();
        $this->assertEquals(300.00, (float) $this->customer->tot_advance);

        // Verify account1 balance reverted back to 1000
        $this->account1->refresh();
        $this->assertEquals(1000.00, (float) $this->account1->balance);

        // Verify account2 balance increased by 300 (500 + 300 = 800)
        $this->account2->refresh();
        $this->assertEquals(800.00, (float) $this->account2->balance);

        // Verify AcTransaction updated
        $transaction = AcTransaction::where('short_code', $advance->payment_code)->first();
        $this->assertNotNull($transaction);
        $this->assertEquals($this->account2->id, $transaction->credit_account_id);
        $this->assertEquals(300.00, (float) $transaction->credit_amt);
        $this->assertEquals('2026-09-04', $transaction->transaction_date);
    }

    public function test_advance_delete_reverses_ledger_and_account_balance_when_unconsumed()
    {
        $this->actingAs($this->user);

        // 1. Create advance of 400
        $this->post(route('advance.store'), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 400.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
            'note' => 'Advance to delete',
        ]);

        $advance = DbCustAdvance::where('customer_id', $this->customer->id)->first();
        $this->assertEquals(1400.00, (float) $this->account1->fresh()->balance);

        // 2. Delete the advance
        $response = $this->delete(route('advance.delete', $advance->id));
        $response->assertRedirect(route('advance.list'));
        $response->assertSessionHas('success');

        // Verify advance record deleted
        $this->assertDatabaseMissing('db_custadvance', ['id' => $advance->id]);

        // Verify customer tot_advance reverted to 0
        $this->customer->refresh();
        $this->assertEquals(0.00, (float) $this->customer->tot_advance);

        // Verify account1 balance decremented back to 1000
        $this->account1->refresh();
        $this->assertEquals(1000.00, (float) $this->account1->balance);

        // Verify AcTransaction deleted
        $this->assertDatabaseMissing('ac_transactions', ['short_code' => $advance->payment_code]);
    }

    public function test_advance_delete_is_blocked_when_advance_has_been_consumed_by_pos()
    {
        $this->actingAs($this->user);

        // 1. Create advance of 500
        $this->post(route('advance.store'), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 500.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
        ]);

        $advance = DbCustAdvance::where('customer_id', $this->customer->id)->first();

        // Simulate consumption in POS: customer spends 400 of their 500 advance
        $this->customer->update(['tot_advance' => 100.00]);

        // 2. Attempt to delete the 500 advance
        $response = $this->delete(route('advance.delete', $advance->id));
        $response->assertSessionHas('error');

        // Confirm advance was NOT deleted
        $this->assertDatabaseHas('db_custadvance', ['id' => $advance->id]);

        // Confirm customer advance remained intact at 100
        $this->customer->refresh();
        $this->assertEquals(100.00, (float) $this->customer->tot_advance);

        // Confirm account balance was not modified
        $this->account1->refresh();
        $this->assertEquals(1500.00, (float) $this->account1->balance);
    }

    public function test_advance_update_reduction_is_blocked_when_advance_has_been_consumed()
    {
        $this->actingAs($this->user);

        // 1. Create advance of 500
        $this->post(route('advance.store'), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 500.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
        ]);

        $advance = DbCustAdvance::where('customer_id', $this->customer->id)->first();

        // Customer consumed 450 in sales, remaining available advance is 50
        $this->customer->update(['tot_advance' => 50.00]);

        // 2. Attempt to reduce advance from 500 to 200 (reduction of 300, but only 50 available)
        $response = $this->post(route('advance.update', $advance->id), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 200.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
        ]);

        $response->assertSessionHas('error');

        // Confirm advance amount was NOT updated
        $advance->refresh();
        $this->assertEquals(500.00, (float) $advance->amount);
    }

    public function test_advance_is_scoped_by_store_id()
    {
        $this->actingAs($this->user);

        // Advance in Store 1
        $advanceStore1 = DbCustAdvance::create([
            'store_id' => 1,
            'count_id' => 1,
            'payment_code' => 'AD-001',
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 250.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
            'status' => 1,
        ]);

        // Advance in Store 2
        $advanceStore2 = DbCustAdvance::create([
            'store_id' => 2,
            'count_id' => 2,
            'payment_code' => 'AD-002',
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 999.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
            'status' => 1,
        ]);

        // User is logged into Store 1: list should only see AD-001 and not AD-002
        $response = $this->get(route('advance.list'));
        $response->assertSee('AD-001');
        $response->assertDontSee('AD-002');

        // Trying to edit or delete Store 2's advance while in Store 1 should 404
        $this->get(route('advance.edit', $advanceStore2->id))->assertNotFound();
        $this->post(route('advance.update', $advanceStore2->id), [
            'payment_date' => '2026-09-03',
            'customer_id' => $this->customer->id,
            'amount' => 100.00,
            'payment_type' => 'Cash',
            'account_id' => $this->account1->id,
        ])->assertNotFound();
        $this->delete(route('advance.delete', $advanceStore2->id))->assertNotFound();
    }
}

