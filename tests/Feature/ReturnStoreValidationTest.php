<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\DbSale;
use App\Models\DbItemSerial;
use App\Models\DbSaleItem;
use App\Models\DbSalesItemReturn;
use App\Models\DbSalesReturn;
use App\Models\AcAccount;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

function getReturnStoreValidationUser(): User {
    store_settings(true);

    $currency = \App\Models\DbCurrency::firstOrCreate(['id' => 1], [
        'currency_name' => 'BDT', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1,
    ]);

    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Return Store Validation Store',
        'status' => 1, 'mobile' => '01700000000',
        'currency_id' => $currency->id, 'decimals' => 2,
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1, 'role_id' => $role->id, 'role_name' => 'Super Admin',
    ]);
}

function makeSerializedReturnSale(User $user): array {
    $warehouse = DbWarehouse::create(['warehouse_name' => 'Ser WH', 'status' => 1]);
    $customer = DbCustomer::create([
        'customer_name' => 'Ser Customer', 'customer_code' => 'CUST-SER-01', 'mobile' => '01795000001', 'status' => 1,
    ]);
    $category = DbCategory::create(['category_name' => 'Goods', 'status' => 1]);
    $item = DbItem::create([
        'item_name' => 'Serialized Item', 'item_code' => 'ITM-SER-01', 'category_id' => $category->id,
        'purchase_price' => 100, 'sales_price' => 500, 'stock' => 10, 'status' => 1, 'is_serialized' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1, 'warehouse_id' => $warehouse->id, 'customer_id' => $customer->id,
        'sales_code' => 'SA-SER-01', 'sales_date' => Carbon::today()->format('Y-m-d'),
        'subtotal' => 500, 'grand_total' => 500, 'paid_amount' => 500,
        'payment_status' => 'Paid', 'status' => 1, 'return_bit' => 0,
    ]);

    DbSaleItem::create([
        'store_id' => 1, 'sales_id' => $sale->id, 'item_id' => $item->id,
        'sales_qty' => 1, 'price_per_unit' => 500, 'total_cost' => 500,
    ]);

    // One sold serial
    DbItemSerial::create([
        'store_id' => 1, 'warehouse_id' => $warehouse->id, 'item_id' => $item->id,
        'serial_number' => 'SN-10001', 'status' => 1, 'sale_id' => $sale->id,
    ]);

    $account = AcAccount::create([
        'store_id' => 1, 'account_name' => 'Ser Acct', 'account_code' => 'ACC-SER-01',
        'balance' => 5000.00, 'status' => 1, 'delete_bit' => 0,
    ]);

    return ['warehouse' => $warehouse, 'sale' => $sale, 'item' => $item, 'account' => $account, 'customer' => $customer];
}

test('1. Serialized item return with mismatched serial count is rejected with a clear 422 message', function () {
    $user = getReturnStoreValidationUser();
    $fx = makeSerializedReturnSale($user);

    $response = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $fx['sale']->id,
        'return_date' => Carbon::today()->format('Y-m-d'),
        'paid_amount' => 0,
        'items' => [
            [
                'item_id' => $fx['item']->id,
                'return_qty' => 1,
                'price_per_unit' => 500,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 500,
                'serials' => [], // mismatch: 1 qty but 0 serials
            ],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJson(['success' => false]);
    expect($response->json('message'))->toContain('serial');

    // No return record created
    expect(DbSalesReturn::where('sales_id', $fx['sale']->id)->count())->toBe(0);
});

test('2. Serialized item return with correct serial count succeeds', function () {
    $user = getReturnStoreValidationUser();
    $fx = makeSerializedReturnSale($user);

    $response = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $fx['sale']->id,
        'return_date' => Carbon::today()->format('Y-m-d'),
        'paid_amount' => 0,
        'items' => [
            [
                'item_id' => $fx['item']->id,
                'return_qty' => 1,
                'price_per_unit' => 500,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 500,
                'serials' => ['SN-10001'],
            ],
        ],
    ]);

    $response->assertOk();
    $response->assertJson(['success' => true]);
    expect(DbSalesReturn::where('sales_id', $fx['sale']->id)->count())->toBe(1);
});

test('3. Return store failure returns a generic message and logs the real error', function () {
    $user = getReturnStoreValidationUser();
    $fx = makeSerializedReturnSale($user);

    Log::shouldReceive('error')
        ->once()
        ->withArgs(fn ($message, $context) => str_contains($message, 'Sales return recording failed'));

    // Force failure inside the transaction: omit total_cost (the real client always
    // sends it via calculateItemTotal). This makes DbSalesItemReturn::create throw
    // an "Undefined array key" error → DB::rollBack → generic catch path.
    $response = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $fx['sale']->id,
        'return_date' => Carbon::today()->format('Y-m-d'),
        'paid_amount' => 0,
        'items' => [
            [
                'item_id' => $fx['item']->id,
                'return_qty' => 1,
                'price_per_unit' => 500,
                'tax_amt' => 0,
                'discount_amt' => 0,
                // NOTE: no total_cost → in-transaction failure
                'serials' => ['SN-10001'],
            ],
        ],
    ]);

    $response->assertStatus(500);
    $response->assertJson(['success' => false]);
    // Generic message, no exception text
    expect($response->json('message'))->toBe('This return could not be processed. Please try again.');
    expect($response->json('message'))->not->toContain('total_cost');

    // Rolled back cleanly — no partial return records survive
    expect(DbSalesReturn::where('sales_id', $fx['sale']->id)->count())->toBe(0);
    expect(DbSalesItemReturn::where('sales_id', $fx['sale']->id)->count())->toBe(0);
});
