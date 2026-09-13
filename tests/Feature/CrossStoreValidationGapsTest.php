<?php

namespace Tests\Feature;

use App\Http\Controllers\PosController;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\DbStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Verification for the two real gaps found in the multi-store discovery pass:
 *
 * 1. db_items.sku / db_items.custom_barcode had NO unique constraint at all.
 *    They now have per-store composite uniques — same value across stores is
 *    allowed, the same value twice within one store is rejected.
 *
 * 2. Several cross-entity operations trusted a submitted FK without confirming
 *    it belonged to the acting store (IDOR-class). Each referenced record is now
 *    validated/queried with an explicit store_id check.
 */
class CrossStoreValidationGapsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111', 'item_init' => 'ITM']);
        DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'mobile' => '2222222222', 'item_init' => 'ITM']);
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function makeUser(int $storeId)
    {
        $roleId = DB::table('db_roles')->insertGetId([
            'store_id' => $storeId,
            'role_name' => 'Role-' . $storeId . '-' . uniqid(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('db_permissions')->insert([
            'role_id' => $roleId,
            'store_id' => $storeId,
            // customerCouponAdd is required by the now-gated CustomerCouponController::store().
            'permissions' => json_encode(['sales_add', 'sales_view', 'purchase_add', 'cust_adv_payments_add', 'discountCouponAdd', 'customerCouponAdd']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = DB::table('users')->insertGetId([
            'store_id' => $storeId,
            'name' => 'User ' . $storeId,
            'email' => 'u' . $storeId . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password'),
            'role_id' => $roleId,
            'role_name' => 'Role-' . $storeId,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return \App\Models\User::find($userId);
    }

    private function makeWarehouse(int $storeId): int
    {
        return DB::table('db_warehouse')->insertGetId([
            'store_id' => $storeId,
            'warehouse_name' => 'WH-' . $storeId . '-' . uniqid(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeCustomer(int $storeId): int
    {
        return DB::table('db_customers')->insertGetId([
            'store_id' => $storeId,
            'customer_name' => 'Cust ' . $storeId,
            'customer_code' => 'CU' . $storeId . uniqid(),
            'status' => 1,
            'tot_advance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeSupplier(int $storeId): int
    {
        return DB::table('db_suppliers')->insertGetId([
            'store_id' => $storeId,
            'supplier_name' => 'Sup ' . $storeId,
            'supplier_code' => 'SUP' . $storeId . uniqid(),
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeAccount(int $storeId): int
    {
        return DB::table('ac_accounts')->insertGetId([
            'store_id' => $storeId,
            'account_name' => 'Acc ' . $storeId,
            'account_code' => 'AC' . $storeId . uniqid(),
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeItem(int $storeId): int
    {
        return DB::table('db_items')->insertGetId([
            'store_id' => $storeId,
            'item_name' => 'Item ' . $storeId,
            'item_code' => 'ITM' . $storeId . uniqid(),
            'sales_price' => 100,
            'purchase_price' => 50,
            'price' => 100,
            'status' => 1,
            'is_serialized' => 0,
            'service_bit' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /* ═══════════════ STEP 1 — sku / custom_barcode uniqueness ═══════════════ */

    public function test_same_sku_allowed_across_two_stores(): void
    {
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => 'SHARED-SKU', 'item_name' => 'a', 'status' => 1]);
        DB::table('db_items')->insert(['store_id' => 2, 'sku' => 'SHARED-SKU', 'item_name' => 'b', 'status' => 1]);

        $this->assertSame(2, DB::table('db_items')->where('sku', 'SHARED-SKU')->count());
    }

    public function test_same_sku_twice_in_one_store_is_rejected(): void
    {
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => 'DUP-SKU', 'item_name' => 'a', 'status' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => 'DUP-SKU', 'item_name' => 'b', 'status' => 1]);
    }

    public function test_same_barcode_allowed_across_two_stores(): void
    {
        DB::table('db_items')->insert(['store_id' => 1, 'custom_barcode' => 'BC-SHARED', 'item_name' => 'a', 'status' => 1]);
        DB::table('db_items')->insert(['store_id' => 2, 'custom_barcode' => 'BC-SHARED', 'item_name' => 'b', 'status' => 1]);

        $this->assertSame(2, DB::table('db_items')->where('custom_barcode', 'BC-SHARED')->count());
    }

    public function test_same_barcode_twice_in_one_store_is_rejected(): void
    {
        DB::table('db_items')->insert(['store_id' => 1, 'custom_barcode' => 'BC-DUP', 'item_name' => 'a', 'status' => 1]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('db_items')->insert(['store_id' => 1, 'custom_barcode' => 'BC-DUP', 'item_name' => 'b', 'status' => 1]);
    }

    public function test_blank_sku_and_barcode_rows_are_exempt(): void
    {
        // Multiple NULLs must be allowed (blank rows exempt from the unique).
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => null, 'custom_barcode' => null, 'item_name' => 'a', 'status' => 1]);
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => null, 'custom_barcode' => null, 'item_name' => 'b', 'status' => 1]);

        $this->assertSame(2, DB::table('db_items')->whereNull('sku')->where('store_id', 1)->count());
    }

    public function test_sku_and_barcode_validation_is_store_scoped(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        DB::table('db_items')->insert(['store_id' => 2, 'sku' => 'OTHER-STORE-SKU', 'item_name' => 'x', 'status' => 1]);

        // A store-2 sku must NOT block a store-1 item.
        $passes = !Validator::make(['sku' => 'OTHER-STORE-SKU'], [
            'sku' => [Rule::unique('db_items', 'sku')->where('store_id', current_store_id())],
        ])->fails();
        $this->assertTrue($passes, 'Store-2 sku must not conflict for a store-1 user');

        // The same value within store 1 must fail.
        DB::table('db_items')->insert(['store_id' => 1, 'sku' => 'SAME-STORE-SKU', 'item_name' => 'y', 'status' => 1]);
        $fails = Validator::make(['sku' => 'SAME-STORE-SKU'], [
            'sku' => [Rule::unique('db_items', 'sku')->where('store_id', current_store_id())],
        ])->fails();
        $this->assertTrue($fails, 'Same-store sku must be rejected');
    }

    /* ═════════ STEP 2.6/2.7 — Purchase supplier + warehouse scoping ═════════ */

    private function purchaseRules(array $payload): array
    {
        $req = StorePurchaseRequest::create('/purchase/store', 'POST', $payload);
        $req->setContainer(app());
        $req->setRedirector(app()->make('redirect'));
        return $req->rules();
    }

    public function test_purchase_rejects_cross_store_supplier(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $payload = [
            'warehouse_id' => $this->makeWarehouse(1),
            'supplier_id' => $this->makeSupplier(2), // other store
            'purchase_date' => now()->format('Y-m-d'),
            'cart' => [['item_id' => $this->makeItem(1), 'qty' => 1, 'price' => 10]],
        ];

        $v = Validator::make($payload, $this->purchaseRules($payload));
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('supplier_id', $v->errors()->toArray());
    }

    public function test_purchase_accepts_same_store_supplier(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $payload = [
            'warehouse_id' => $this->makeWarehouse(1),
            'supplier_id' => $this->makeSupplier(1),
            'purchase_date' => now()->format('Y-m-d'),
            'cart' => [['item_id' => $this->makeItem(1), 'qty' => 1, 'price' => 10]],
        ];

        $v = Validator::make($payload, $this->purchaseRules($payload));
        $this->assertFalse($v->errors()->has('supplier_id'));
    }

    public function test_purchase_rejects_cross_store_warehouse(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $payload = [
            'warehouse_id' => $this->makeWarehouse(2), // other store
            'supplier_id' => $this->makeSupplier(1),
            'purchase_date' => now()->format('Y-m-d'),
            'cart' => [['item_id' => $this->makeItem(1), 'qty' => 1, 'price' => 10]],
        ];

        $v = Validator::make($payload, $this->purchaseRules($payload));
        $this->assertTrue($v->fails());
        $this->assertArrayHasKey('warehouse_id', $v->errors()->toArray());
    }

    public function test_purchase_accepts_same_store_warehouse(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $payload = [
            'warehouse_id' => $this->makeWarehouse(1),
            'supplier_id' => $this->makeSupplier(1),
            'purchase_date' => now()->format('Y-m-d'),
            'cart' => [['item_id' => $this->makeItem(1), 'qty' => 1, 'price' => 10]],
        ];

        $v = Validator::make($payload, $this->purchaseRules($payload));
        $this->assertFalse($v->errors()->has('warehouse_id'));
    }

    /* ═════════════════════ 2.3 — AdvanceController ═════════════════════ */

    public function test_advance_rejects_cross_store_customer(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('advance.store'), [
            'payment_date' => now()->format('Y-m-d'),
            'customer_id' => $this->makeCustomer(2),
            'amount' => 50,
            'payment_type' => 'Cash',
            'account_id' => $this->makeAccount(1),
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertSame(0, DB::table('db_custadvance')->count());
    }

    public function test_advance_accepts_same_store_customer(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $customerId = $this->makeCustomer(1);
        $response = $this->post(route('advance.store'), [
            'payment_date' => now()->format('Y-m-d'),
            'customer_id' => $customerId,
            'amount' => 50,
            'payment_type' => 'Cash',
            'account_id' => $this->makeAccount(1),
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('db_custadvance')->count());
        $this->assertSame(50.0, (float) DB::table('db_customers')->where('id', $customerId)->value('tot_advance'));
    }

    /* ═════════════════════ 2.4 — CustomerCouponController ═════════════════ */

    public function test_customer_coupon_rejects_cross_store_customer(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('coupons.customer.store'), [
            'customer_id' => $this->makeCustomer(2),
            'name' => 'Coupon X',
            'code' => 'CCTEST-' . uniqid(),
            'type' => 'Fixed',
            'value' => 10,
        ]);

        $response->assertSessionHasErrors('customer_id');
        $this->assertSame(0, DB::table('db_customer_coupons')->count());
    }

    public function test_customer_coupon_accepts_same_store_customer(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('coupons.customer.store'), [
            'customer_id' => $this->makeCustomer(1),
            'name' => 'Coupon Y',
            'code' => 'CCTEST-' . uniqid(),
            'type' => 'Fixed',
            'value' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('db_customer_coupons')->count());
    }

    /* ════════════════ 2.2 — POS hold warehouse scoping ════════════════ */

    public function test_pos_hold_rejects_cross_store_warehouse(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('sales.pos.hold'), [
            'warehouse_id' => $this->makeWarehouse(2),
            'cart' => [['id' => $this->makeItem(1), 'qty' => 1]],
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('does not belong to your store', $response->json('message'));
        $this->assertSame(0, DB::table('db_hold')->count());
    }

    public function test_pos_hold_accepts_same_store_warehouse(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('sales.pos.hold'), [
            'warehouse_id' => $this->makeWarehouse(1),
            'cart' => [['id' => $this->makeItem(1), 'qty' => 1, 'price' => 100]],
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $response->assertOk();
        $this->assertTrue((bool) $response->json('success'));
        $this->assertSame(1, DB::table('db_hold')->count());
    }

    /* ════════════════ 2.5 — POS sale warehouse gate ════════════════ */

    public function test_pos_sale_rejects_cross_store_warehouse(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('sales.pos.store'), [
            'warehouse_id' => $this->makeWarehouse(2),
            'cart' => [['id' => $this->makeItem(1), 'qty' => 1, 'price' => 100]],
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('does not belong to your store', $response->json('message'));
    }

    public function test_pos_sale_same_store_warehouse_passes_store_gate(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $response = $this->post(route('sales.pos.store'), [
            'warehouse_id' => $this->makeWarehouse(1),
            'cart' => [['id' => $this->makeItem(1), 'qty' => 1, 'price' => 100]],
            'subtotal' => 100,
            'grand_total' => 100,
        ]);

        // Same-store warehouse must NOT be rejected by the warehouse store-gate.
        $this->assertStringNotContainsString('does not belong to your store', (string) $response->json('message'));
    }

    /* ════════════════ 2.1 — PosController::resolveCoupon ════════════════ */

    private function makeCustomerCoupon(int $storeId, int $customerId, string $code, float $value): int
    {
        return DB::table('db_customer_coupons')->insertGetId([
            'store_id' => $storeId,
            'customer_id' => $customerId,
            'code' => $code,
            'name' => 'Coupon ' . $code,
            'value' => $value,
            'type' => 'Fixed',
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function invokeResolveCoupon(array $requestData, float $subtotal): array
    {
        $controller = app(PosController::class);
        $request = Request::create('/sales/pos/store', 'POST', $requestData);
        $method = new ReflectionMethod(PosController::class, 'resolveCoupon');
        $method->setAccessible(true);

        return $method->invoke($controller, $request, $subtotal);
    }

    public function test_resolve_coupon_ignores_cross_store_coupon(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $cust2 = $this->makeCustomer(2);
        $coupon2 = $this->makeCustomerCoupon(2, $cust2, 'XSTORE-' . uniqid(), 25.0);

        // Store-1 user referencing a store-2 customer coupon must NOT get the discount.
        [$couponId, $couponAmt] = $this->invokeResolveCoupon([
            'customer_coupon_id' => $coupon2,
            'customer_id' => $cust2,
        ], 100.0);

        $this->assertSame(0.0, (float) $couponAmt);
        $this->assertNull($couponId);
    }

    public function test_resolve_coupon_applies_same_store_coupon(): void
    {
        $user1 = $this->makeUser(1);
        $this->actingAs($user1);

        $cust1 = $this->makeCustomer(1);
        $coupon1 = $this->makeCustomerCoupon(1, $cust1, 'SSTORE-' . uniqid(), 25.0);

        [$couponId, $couponAmt] = $this->invokeResolveCoupon([
            'customer_coupon_id' => $coupon1,
            'customer_id' => $cust1,
        ], 100.0);

        $this->assertSame(25.0, (float) $couponAmt);
    }
}
