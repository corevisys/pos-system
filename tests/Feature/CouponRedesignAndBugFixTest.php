<?php

namespace Tests\Feature;

use App\Models\DbCoupon;
use App\Models\DbCustomer;
use App\Models\DbCustomerCoupon;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use App\SMS\Services\SmsTriggerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponRedesignAndBugFixTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $store;
    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = DbStore::firstOrCreate(['id' => 1], [
            'store_name' => 'Coupon Test Store',
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
            'permissions' => ['sales_view', 'sales_add', 'coupon_view', 'coupon_add'],
        ]);

        $this->user = User::factory()->create([
            'role_id' => 1,
            'role_name' => 'Super Admin',
            'store_id' => 1,
            'status' => 1,
        ]);

        $this->customer = DbCustomer::create([
            'customer_name' => 'Coupon VIP Customer',
            'customer_code' => 'CUST-001',
            'mobile' => '01811111111',
            'status' => 1,
            'store_id' => 1,
        ]);
    }

    public function test_master_coupon_rejects_percentage_greater_than_100()
    {
        $response = $this->actingAs($this->user)->post(route('coupons.store'), [
            'name' => 'Excessive Discount',
            'code' => 'SUPER250',
            'type' => 'Percentage',
            'value' => 250,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertSessionHasErrors('value');
        $this->assertDatabaseMissing('db_coupons', ['code' => 'SUPER250']);
    }

    public function test_customer_coupon_rejects_percentage_greater_than_100()
    {
        $response = $this->actingAs($this->user)->post(route('coupons.customer.store'), [
            'customer_id' => $this->customer->id,
            'name' => 'Excessive Customer Voucher',
            'code' => 'CUST-250PCT',
            'type' => 'Percentage',
            'value' => 150,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertSessionHasErrors('value');
        $this->assertDatabaseMissing('db_customer_coupons', ['code' => 'CUST-250PCT']);
    }

    public function test_valid_master_and_customer_coupons_are_stored_successfully()
    {
        // 1. Valid Master Percentage (<= 100)
        $respMaster = $this->actingAs($this->user)->post(route('coupons.store'), [
            'name' => 'Summer Sale 20%',
            'code' => 'SUMMER20',
            'type' => 'Percentage',
            'value' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
            'description' => '20% off campaign',
        ]);
        $respMaster->assertRedirect(route('coupons.master'));
        $this->assertDatabaseHas('db_coupons', ['code' => 'SUMMER20', 'value' => 20]);

        // 2. Valid Customer Fixed Voucher
        $respCust = $this->actingAs($this->user)->post(route('coupons.customer.store'), [
            'customer_id' => $this->customer->id,
            'name' => 'VIP Reward $50',
            'code' => 'CUST-VIP50',
            'type' => 'Fixed',
            'value' => 50,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);
        $respCust->assertRedirect(route('coupons.customer.list'));
        $this->assertDatabaseHas('db_customer_coupons', ['code' => 'CUST-VIP50', 'value' => 50]);
    }

    public function test_invalid_coupon_type_is_rejected()
    {
        $response = $this->actingAs($this->user)->post(route('coupons.store'), [
            'name' => 'Invalid Type',
            'code' => 'INVALIDTYPE',
            'type' => 'Dynamic',
            'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertSessionHasErrors('type');
    }

    public function test_cross_table_code_collision_is_blocked_on_create()
    {
        // Create master coupon
        DbCoupon::create([
            'name' => 'Master Promo',
            'code' => 'COLLIDE50',
            'type' => 'Fixed',
            'value' => 50,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Try creating customer coupon with same code -> must fail
        $response = $this->actingAs($this->user)->post(route('coupons.customer.store'), [
            'customer_id' => $this->customer->id,
            'name' => 'Customer Collision',
            'code' => 'COLLIDE50',
            'type' => 'Fixed',
            'value' => 50,
        ]);

        $response->assertSessionHasErrors('code');

        // Create customer coupon
        DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Customer Unique Voucher',
            'code' => 'CUST-ONLY1',
            'type' => 'Percentage',
            'value' => 15,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Try creating master coupon with same code -> must fail
        $response2 = $this->actingAs($this->user)->post(route('coupons.store'), [
            'name' => 'Master Collision',
            'code' => 'CUST-ONLY1',
            'type' => 'Percentage',
            'value' => 15,
            'expire_date' => now()->addMonth()->toDateString(),
        ]);

        $response2->assertSessionHasErrors('code');
    }

    public function test_cross_table_code_collision_is_blocked_on_update()
    {
        // 1. Create a customer coupon
        $customerCoupon = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Existing Customer Voucher',
            'code' => 'TAKEN-BY-CUST',
            'type' => 'Fixed',
            'value' => 50,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Create a master coupon
        $masterCoupon = DbCoupon::create([
            'name' => 'Master To Update',
            'code' => 'MASTER-ORIGINAL',
            'type' => 'Fixed',
            'value' => 25,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Attempt to update master coupon code to TAKEN-BY-CUST -> must fail
        $response = $this->actingAs($this->user)->put(route('coupons.update', $masterCoupon->id), [
            'name' => 'Master Updated Name',
            'code' => 'TAKEN-BY-CUST',
            'type' => 'Fixed',
            'value' => 25,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertDatabaseHas('db_coupons', [
            'id' => $masterCoupon->id,
            'code' => 'MASTER-ORIGINAL', // Code remains untouched
        ]);

        // 2. Create another master coupon
        $otherMaster = DbCoupon::create([
            'name' => 'Existing Master Voucher',
            'code' => 'TAKEN-BY-MASTER',
            'type' => 'Percentage',
            'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Attempt to update customer coupon code to TAKEN-BY-MASTER -> must fail
        $response2 = $this->actingAs($this->user)->put(route('coupons.customer.update', $customerCoupon->id), [
            'customer_id' => $this->customer->id,
            'name' => 'Customer Updated Name',
            'code' => 'TAKEN-BY-MASTER',
            'type' => 'Fixed',
            'value' => 50,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
        ]);

        $response2->assertSessionHasErrors('code');
        $this->assertDatabaseHas('db_customer_coupons', [
            'id' => $customerCoupon->id,
            'code' => 'TAKEN-BY-CUST', // Code remains untouched
        ]);
    }

    public function test_self_collision_is_permitted_on_update_when_code_is_unchanged()
    {
        // 1. Master Coupon: Update other attributes while leaving code unchanged
        $masterCoupon = DbCoupon::create([
            'name' => 'Original Master Campaign',
            'code' => 'KEEP-SAME-MASTER',
            'type' => 'Percentage',
            'value' => 10,
            'expire_date' => now()->addDays(10)->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $respMaster = $this->actingAs($this->user)->put(route('coupons.update', $masterCoupon->id), [
            'name' => 'Changed Master Name Only',
            'code' => 'KEEP-SAME-MASTER', // Same code resubmitted
            'type' => 'Percentage',
            'value' => 20,
            'expire_date' => now()->addDays(20)->toDateString(),
            'status' => 1,
            'description' => 'Unchanged code, updated discount',
        ]);

        $respMaster->assertSessionHasNoErrors();
        $respMaster->assertRedirect(route('coupons.master'));
        $this->assertDatabaseHas('db_coupons', [
            'id' => $masterCoupon->id,
            'name' => 'Changed Master Name Only',
            'code' => 'KEEP-SAME-MASTER',
            'value' => 20,
        ]);

        // 2. Customer Coupon: Update other attributes while leaving code unchanged
        $customerCoupon = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Original Customer Voucher',
            'code' => 'KEEP-SAME-CUST',
            'type' => 'Fixed',
            'value' => 15,
            'expire_date' => now()->addDays(5)->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $respCust = $this->actingAs($this->user)->put(route('coupons.customer.update', $customerCoupon->id), [
            'customer_id' => $this->customer->id,
            'name' => 'Changed Cust Name Only',
            'code' => 'KEEP-SAME-CUST', // Same code resubmitted
            'type' => 'Fixed',
            'value' => 35,
            'expire_date' => now()->addDays(15)->toDateString(),
            'status' => 1,
            'description' => 'Unchanged code, updated value',
        ]);

        $respCust->assertSessionHasNoErrors();
        $respCust->assertRedirect(route('coupons.customer.list'));
        $this->assertDatabaseHas('db_customer_coupons', [
            'id' => $customerCoupon->id,
            'name' => 'Changed Cust Name Only',
            'code' => 'KEEP-SAME-CUST',
            'value' => 35,
        ]);
    }

    public function test_same_table_code_collision_is_blocked_on_update()
    {
        // 1. Two master coupons
        $master1 = DbCoupon::create([
            'name' => 'First Master',
            'code' => 'MASTER-ONE',
            'type' => 'Fixed',
            'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $master2 = DbCoupon::create([
            'name' => 'Second Master',
            'code' => 'MASTER-TWO',
            'type' => 'Fixed',
            'value' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Try changing master2 code to master1's code
        $resp = $this->actingAs($this->user)->put(route('coupons.update', $master2->id), [
            'name' => 'Master 2 Renamed',
            'code' => 'MASTER-ONE',
            'type' => 'Fixed',
            'value' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
        ]);
        $resp->assertSessionHasErrors('code');

        // 2. Two customer coupons
        $cust1 = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'First Cust',
            'code' => 'CUST-ONE',
            'type' => 'Fixed',
            'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $cust2 = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Second Cust',
            'code' => 'CUST-TWO',
            'type' => 'Fixed',
            'value' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // Try changing cust2 code to cust1's code
        $resp2 = $this->actingAs($this->user)->put(route('coupons.customer.update', $cust2->id), [
            'customer_id' => $this->customer->id,
            'name' => 'Cust 2 Renamed',
            'code' => 'CUST-ONE',
            'type' => 'Fixed',
            'value' => 20,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
        ]);
        $resp2->assertSessionHasErrors('code');
    }

    public function test_edit_and_update_lifecycle_for_master_coupon()
    {
        $coupon = DbCoupon::create([
            'name' => 'Original Name',
            'code' => 'ORIGINAL10',
            'type' => 'Percentage',
            'value' => 10,
            'expire_date' => now()->addDays(10)->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // 1. Edit view loads
        $response = $this->actingAs($this->user)->get(route('coupons.edit', $coupon->id));
        $response->assertStatus(200);
        $response->assertSee('ORIGINAL10');

        // 2. Update with new code and value
        $updateResp = $this->actingAs($this->user)->put(route('coupons.update', $coupon->id), [
            'name' => 'Updated Name',
            'code' => 'NEWCODE15',
            'type' => 'Percentage',
            'value' => 15,
            'expire_date' => now()->addDays(20)->toDateString(),
            'status' => 1,
            'description' => 'Updated campaign',
        ]);

        $updateResp->assertRedirect(route('coupons.master'));
        $this->assertDatabaseHas('db_coupons', [
            'id' => $coupon->id,
            'name' => 'Updated Name',
            'code' => 'NEWCODE15',
            'value' => 15,
        ]);
    }

    public function test_edit_and_update_lifecycle_for_customer_coupon()
    {
        $customerCoupon = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Cust Original',
            'code' => 'CUST-ORIG',
            'type' => 'Fixed',
            'value' => 20,
            'expire_date' => now()->addDays(5)->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        // 1. Edit view loads
        $response = $this->actingAs($this->user)->get(route('coupons.customer.edit', $customerCoupon->id));
        $response->assertStatus(200);
        $response->assertSee('CUST-ORIG');

        // 2. Update with new code and value
        $updateResp = $this->actingAs($this->user)->put(route('coupons.customer.update', $customerCoupon->id), [
            'customer_id' => $this->customer->id,
            'name' => 'Cust Updated',
            'code' => 'CUST-NEW30',
            'type' => 'Fixed',
            'value' => 30,
            'expire_date' => now()->addDays(15)->toDateString(),
            'status' => 1,
        ]);

        $updateResp->assertRedirect(route('coupons.customer.list'));
        $this->assertDatabaseHas('db_customer_coupons', [
            'id' => $customerCoupon->id,
            'name' => 'Cust Updated',
            'code' => 'CUST-NEW30',
            'value' => 30,
        ]);
    }

    public function test_db_coupon_has_many_customer_coupons_and_with_count_works()
    {
        $master = DbCoupon::create([
            'name' => 'Parent Campaign',
            'code' => 'PARENT100',
            'type' => 'Fixed',
            'value' => 100,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        DbCustomerCoupon::create([
            'coupon_id' => $master->id,
            'customer_id' => $this->customer->id,
            'name' => 'Child Voucher 1',
            'code' => 'CUST-CHILD1',
            'type' => 'Fixed',
            'value' => 100,
            'status' => 1,
            'store_id' => 1,
        ]);

        DbCustomerCoupon::create([
            'coupon_id' => $master->id,
            'customer_id' => $this->customer->id,
            'name' => 'Child Voucher 2',
            'code' => 'CUST-CHILD2',
            'type' => 'Fixed',
            'value' => 100,
            'status' => 1,
            'store_id' => 1,
        ]);

        $queried = DbCoupon::withCount('customerCoupons')->find($master->id);
        $this->assertEquals(2, $queried->customer_coupons_count);
        $this->assertCount(2, $queried->customerCoupons);

        // List page renders linked count
        $resp = $this->actingAs($this->user)->get(route('coupons.master'));
        $resp->assertStatus(200);
        $resp->assertSee('PARENT100');
    }

    public function test_sms_trigger_service_resolves_coupon_code_property()
    {
        $coupon = DbCoupon::create([
            'name' => 'SMS Test Coupon',
            'code' => 'SMSCODE99',
            'type' => 'Percentage',
            'value' => 10,
            'expire_date' => '2026-12-31',
            'status' => 1,
            'store_id' => 1,
        ]);

        $service = app(SmsTriggerService::class);
        $reflected = new \ReflectionClass($service);
        $method = $reflected->getMethod('mapVariables');
        $method->setAccessible(true);

        $eventData = $method->invoke($service, 'CouponExpiry', $coupon);

        $this->assertIsArray($eventData);
        $this->assertEquals('SMSCODE99', $eventData['coupon_code']);
        $this->assertEquals('2026-12-31', $eventData['expiry_date']);
    }

    public function test_per_page_pagination_selection()
    {
        for ($i = 1; $i <= 15; $i++) {
            DbCoupon::create([
                'name' => "Coupon {$i}",
                'code' => "PERPAGE{$i}",
                'type' => 'Fixed',
                'value' => 10,
                'expire_date' => now()->addMonth()->toDateString(),
                'status' => 1,
                'store_id' => 1,
            ]);
        }

        // Test per_page = 25 loads all 15 on page 1
        $response = $this->actingAs($this->user)->get(route('coupons.master', ['per_page' => 25]));
        $response->assertStatus(200);
        $response->assertSee('PERPAGE1');
        $response->assertSee('PERPAGE15');
    }

    public function test_all_coupon_views_render_successfully()
    {
        $master = DbCoupon::create([
            'name' => 'Master View Test',
            'code' => 'VIEWMASTER',
            'type' => 'Fixed',
            'value' => 50,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $customerCoupon = DbCustomerCoupon::create([
            'customer_id' => $this->customer->id,
            'name' => 'Customer View Test',
            'code' => 'VIEWCUST',
            'type' => 'Percentage',
            'value' => 10,
            'expire_date' => now()->addMonth()->toDateString(),
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->actingAs($this->user)->get(route('coupons.master'))->assertStatus(200);
        $this->actingAs($this->user)->get(route('coupons.create'))->assertStatus(200);
        $this->actingAs($this->user)->get(route('coupons.edit', $master->id))->assertStatus(200);
        $this->actingAs($this->user)->get(route('coupons.customer.list'))->assertStatus(200);
        $this->actingAs($this->user)->get(route('coupons.customer.create'))->assertStatus(200);
        $this->actingAs($this->user)->get(route('coupons.customer.edit', $customerCoupon->id))->assertStatus(200);
    }
}
