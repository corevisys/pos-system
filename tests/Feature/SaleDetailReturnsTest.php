<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbCustomer;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\DbItem;
use App\Models\DbCategory;
use App\Models\AcAccount;
use App\Models\DbSale;
use App\Models\DbSaleItem;
use App\Models\DbSalePayment;
use App\Models\DbSalesReturn;
use App\Models\DbSalesItemReturn;
use App\Models\DbSalesPaymentReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;

function getSaleDetailTestUser(): User {
    $currency = \App\Models\DbCurrency::firstOrCreate(['currency_code' => 'BDT'], [
        'currency_name' => 'Bangladeshi Taka',
        'symbol' => '৳',
        'status' => 1,
    ]);

    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Corevisys Test Store',
        'status' => 1,
        'mobile' => '01700000000',
        'currency_id' => $currency->id,
        'decimals' => 2,
        'qty_decimals' => 2,
        'currency_placement' => 'before',
    ]);

    store_settings(true);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id' => 1,
        'role_name' => 'Super Admin',
        'status' => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id' => 1,
        'permissions' => ['sales_add', 'sales_view', 'pos', 'accounts_view', 'sales_return_view'],
    ]);

    return User::factory()->create([
        'store_id' => 1,
        'role_id' => $role->id,
        'role_name' => 'Super Admin',
    ]);
}

test('1. Sale with zero returns renders without returns section and Balance Due equals grand_total minus paid_amount', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Rahim Uddin',
        'mobile' => '01811111111',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Dhaka Hub',
        'status' => 1,
    ]);

    $category = DbCategory::create([
        'store_id' => 1,
        'category_name' => 'Electronics',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Smart Watch Pro',
        'item_code' => 'ITM-WATCH-01',
        'category_id' => $category->id,
        'purchase_price' => 500,
        'sales_price' => 1000,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00099',
        'sales_date' => '2026-08-25',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'paid_amount' => 600,
        'payment_status' => 'Partial',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 1,
        'price_per_unit' => 1000,
        'total_cost' => 1000,
    ]);

    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();

    // Returns section should NOT be in HTML
    $response->assertDontSee('Sales Returns History');
    $response->assertDontSee('Returned Items');

    // Balance Due should be exactly 1000 - 600 = 400
    $response->assertSee('Balance Due');
    assert_compact_amount($response, 400);
});

test('2. Scenario 1: Fully paid sale with cash refund displays correct return info and zero Balance Due', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Karim Ali',
        'mobile' => '01822222222',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Chittagong Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Wireless Keyboard',
        'item_code' => 'ITM-KB-01',
        'purchase_price' => 300,
        'sales_price' => 500,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00101',
        'sales_date' => '2026-08-25',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'paid_amount' => 1000,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 2,
        'price_per_unit' => 500,
        'total_cost' => 1000,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Petty Cash',
        'account_code' => 'ACC-001',
        'balance' => 5000,
        'status' => 1,
    ]);

    // Customer returns 1 item ($500) and gets $500 cash refund
    $return = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-2026082501',
        'return_date' => '2026-08-25',
        'return_status' => 'Completed',
        'subtotal' => 500,
        'grand_total' => 500,
        'paid_amount' => 500,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'item_id' => $item->id,
        'return_qty' => 1,
        'price_per_unit' => 500,
        'total_cost' => 500,
    ]);

    DbSalesPaymentReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'payment_date' => '2026-08-25',
        'payment_type' => 'Cash',
        'payment' => 500,
        'account_id' => $account->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();

    // Assert returns section is present
    $response->assertSee('Sales Returns History');
    $response->assertSee('SR-2026082501');
    $response->assertSee('Wireless Keyboard');
    $response->assertSee('ITM-KB-01');
    $response->assertSee(format_quantity(1));
    assert_compact_amount($response, 500);

    // Returned Items row in summary
    $response->assertSee('Returned Items');

    // Balance Due: (1000 - 500) - (1000 - 500) = 0
    assert_compact_amount($response, 0);
});

test('3. Scenario 2: Partially paid sale with due offset and no cash refund displays correct reduced Balance Due', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Jamal Hossain',
        'mobile' => '01833333333',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Sylhet Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Gaming Mouse',
        'item_code' => 'ITM-MOUSE-01',
        'purchase_price' => 500,
        'sales_price' => 1000,
        'stock' => 10,
        'status' => 1,
    ]);

    // Sale: Grand Total 1000, Paid 600, Initial Due 400
    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00102',
        'sales_date' => '2026-08-25',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'paid_amount' => 600,
        'payment_status' => 'Partial',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 1,
        'price_per_unit' => 1000,
        'total_cost' => 1000,
    ]);

    // Return: $300 returned goods, $0 cash refund (due offset)
    $return = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-2026082502',
        'return_date' => '2026-08-25',
        'return_status' => 'Completed',
        'subtotal' => 300,
        'grand_total' => 300,
        'paid_amount' => 0,
        'payment_status' => 'Unpaid',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'item_id' => $item->id,
        'return_qty' => 0.3,
        'price_per_unit' => 1000,
        'total_cost' => 300,
    ]);

    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();

    // Balance Due: (1000 - 300) - (600 - 0) = 700 - 600 = 100
    assert_compact_amount($response, 100);
    $response->assertSee('No cash refund issued (Due offset).');
});

test('4. Scenario 3: Partially paid sale with partial offset and partial refund displays correct zero Balance Due', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Tanvir Ahmed',
        'mobile' => '01844444444',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Rajshahi Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'USB-C Cable',
        'item_code' => 'ITM-CABLE-01',
        'purchase_price' => 100,
        'sales_price' => 200,
        'stock' => 20,
        'status' => 1,
    ]);

    // Sale: Grand Total 1000, Paid 600, Initial Due 400
    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00103',
        'sales_date' => '2026-08-25',
        'subtotal' => 1000,
        'grand_total' => 1000,
        'paid_amount' => 600,
        'payment_status' => 'Partial',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 5,
        'price_per_unit' => 200,
        'total_cost' => 1000,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Main Cash Drawer',
        'account_code' => 'ACC-002',
        'balance' => 5000,
        'status' => 1,
    ]);

    // Return: $500 returned goods, $100 cash refund ($400 due offset + $100 cash refund)
    $return = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-2026082503',
        'return_date' => '2026-08-25',
        'return_status' => 'Completed',
        'subtotal' => 500,
        'grand_total' => 500,
        'paid_amount' => 100,
        'payment_status' => 'Partial',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'item_id' => $item->id,
        'return_qty' => 2.5,
        'price_per_unit' => 200,
        'total_cost' => 500,
    ]);

    DbSalesPaymentReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'payment_date' => '2026-08-25',
        'payment_type' => 'Cash',
        'payment' => 100,
        'account_id' => $account->id,
        'customer_id' => $customer->id,
        'created_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();

    // Balance Due: (1000 - 500) - (600 - 100) = 500 - 500 = 0
    assert_compact_amount($response, 0);
    $response->assertSee('Refund (Cash - Main Cash Drawer):');
    assert_compact_amount($response, 100);
});

test('5. Scenario 4: Return edge case where returned value exceeds net amount floors at zero without negative due', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Edge Case Customer',
        'mobile' => '01855555555',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Barisal Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Adapter',
        'item_code' => 'ITM-ADP-01',
        'purchase_price' => 100,
        'sales_price' => 200,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00104',
        'sales_date' => '2026-08-25',
        'subtotal' => 200,
        'grand_total' => 200,
        'paid_amount' => 200,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 1,
        'price_per_unit' => 200,
        'total_cost' => 200,
    ]);

    // Return: full 200 returned, 200 refunded
    $return = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-2026082504',
        'return_date' => '2026-08-25',
        'return_status' => 'Completed',
        'subtotal' => 200,
        'grand_total' => 200,
        'paid_amount' => 200,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $return->id,
        'item_id' => $item->id,
        'return_qty' => 1,
        'price_per_unit' => 200,
        'total_cost' => 200,
    ]);

    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();

    // Balance Due: max(0, (200 - 200) - (200 - 200)) = 0
    assert_compact_amount($response, 0);
});

test('6. Multiple returns for single sale all display on sales/show and cross-links work', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Multi Return Customer',
        'mobile' => '01866666666',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Rangpur Hub',
        'status' => 1,
    ]);

    $item1 = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Monitor 24 inch',
        'item_code' => 'ITM-MON-01',
        'purchase_price' => 8000,
        'sales_price' => 12000,
        'stock' => 5,
        'status' => 1,
    ]);

    $item2 = DbItem::create([
        'store_id' => 1,
        'item_name' => 'HDMI Cable',
        'item_code' => 'ITM-HDMI-01',
        'purchase_price' => 200,
        'sales_price' => 500,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00105',
        'sales_date' => '2026-08-25',
        'subtotal' => 12500,
        'grand_total' => 12500,
        'paid_amount' => 12500,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item1->id,
        'sales_qty' => 1,
        'price_per_unit' => 12000,
        'total_cost' => 12000,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item2->id,
        'sales_qty' => 1,
        'price_per_unit' => 500,
        'total_cost' => 500,
    ]);

    // Return 1: HDMI cable ($500)
    $ret1 = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-20260825-RET1',
        'return_date' => '2026-08-25',
        'return_status' => 'Completed',
        'subtotal' => 500,
        'grand_total' => 500,
        'paid_amount' => 500,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $ret1->id,
        'item_id' => $item2->id,
        'return_qty' => 1,
        'price_per_unit' => 500,
        'total_cost' => 500,
    ]);

    // Return 2: Monitor ($12000)
    $ret2 = DbSalesReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'return_code' => 'SR-20260825-RET2',
        'return_date' => '2026-08-26',
        'return_status' => 'Completed',
        'subtotal' => 12000,
        'grand_total' => 12000,
        'paid_amount' => 12000,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSalesItemReturn::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'return_id' => $ret2->id,
        'item_id' => $item1->id,
        'return_qty' => 1,
        'price_per_unit' => 12000,
        'total_cost' => 12000,
    ]);

    // Check sales.show page
    $response = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $response->assertOk();
    $response->assertSee('2 Returns');
    $response->assertSee('SR-20260825-RET1');
    $response->assertSee('SR-20260825-RET2');
    $response->assertSee('HDMI Cable');
    $response->assertSee('Monitor 24 inch');

    // Check returns_list cross-link to sales.show
    $listResponse = $this->actingAs($user)->get(route('sales.returns'));
    $listResponse->assertOk();
    $listResponse->assertSee(route('sales.show', $sale->id));

    // Check return_show cross-link to sales.show
    $showRetResponse = $this->actingAs($user)->get(route('sales.return.show', $ret1->id));
    $showRetResponse->assertOk();
    $showRetResponse->assertSee(route('sales.show', $sale->id));
});

test('7. Partial return flow: qty 10 item, return #1 qty 4 keeps Create Return enabled with max 6, return #2 qty 6 disables Create Return and blocks further returns', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Partial Return Customer',
        'mobile' => '01877777777',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Khulna Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Mechanical Keyboard',
        'item_code' => 'ITM-MKB-01',
        'purchase_price' => 1000,
        'sales_price' => 2000,
        'stock' => 50,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00106',
        'sales_date' => '2026-08-25',
        'subtotal' => 20000,
        'grand_total' => 20000,
        'paid_amount' => 20000,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 10,
        'price_per_unit' => 2000,
        'total_cost' => 20000,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Return Cash Drawer',
        'account_code' => 'ACC-RET-01',
        'balance' => 50000,
        'status' => 1,
    ]);

    // Step A: Initial state (0 returns)
    $showResp0 = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $showResp0->assertOk();
    $showResp0->assertSee('Create Return');

    // Step B: Process Return #1 for 4 units
    $postResp1 = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => '2026-08-25',
        'paid_amount' => 8000,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'items' => [
            [
                'item_id' => $item->id,
                'return_qty' => 4,
                'price_per_unit' => 2000,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 8000,
            ]
        ]
    ]);
    $postResp1->assertOk()->assertJson(['success' => true]);

    // Refresh sale and check show page: "Create Return" MUST STILL BE VISIBLE!
    $sale->refresh();
    $showResp1 = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $showResp1->assertOk();
    $showResp1->assertSee('Create Return');

    // Check create-return form: must allow remaining 6 units
    $createResp1 = $this->actingAs($user)->get(route('sales.return.create', $sale->id));
    $createResp1->assertOk();
    $createResp1->assertSee('"sold_qty":10', false);
    $createResp1->assertSee('"already_returned":4', false);
    $createResp1->assertSee('"remaining_qty":6', false);

    // Step C: Process Return #2 for the remaining 6 units (fully returning the sale)
    $postResp2 = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => '2026-08-26',
        'paid_amount' => 12000,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'items' => [
            [
                'item_id' => $item->id,
                'return_qty' => 6,
                'price_per_unit' => 2000,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 12000,
            ]
        ]
    ]);
    $postResp2->assertOk()->assertJson(['success' => true]);

    // Refresh sale and check show page: "Create Return" MUST NOW BE HIDDEN!
    $sale->refresh();
    $showResp2 = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $showResp2->assertOk();
    $showResp2->assertDontSee('Create Return');

    // Direct visit to create return must redirect with error
    $createResp2 = $this->actingAs($user)->get(route('sales.return.create', $sale->id));
    $createResp2->assertRedirect(route('sales.show', $sale->id));
    $createResp2->assertSessionHas('error', 'All items on this invoice have already been returned in full.');
});

test('8. Server-side over-return validation rejects quantities exceeding remaining returnable qty with 422 and rolls back', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Over-Return Test Customer',
        'mobile' => '01888888888',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Comilla Hub',
        'status' => 1,
    ]);

    $item = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Power Bank 20000mAh',
        'item_code' => 'ITM-PB-01',
        'purchase_price' => 1000,
        'sales_price' => 1500,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00107',
        'sales_date' => '2026-08-25',
        'subtotal' => 4500,
        'grand_total' => 4500,
        'paid_amount' => 4500,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item->id,
        'sales_qty' => 3,
        'price_per_unit' => 1500,
        'total_cost' => 4500,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Cash Account',
        'account_code' => 'ACC-CASH-99',
        'balance' => 10000,
        'status' => 1,
    ]);

    $initialReturnCount = DbSalesReturn::count();
    $initialItemReturnCount = DbSalesItemReturn::count();
    $initialStock = $item->stock;

    // Attempt to return 5 units when only 3 were sold
    $overReturnResp = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => '2026-08-25',
        'paid_amount' => 4500,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'items' => [
            [
                'item_id' => $item->id,
                'return_qty' => 5, // Exceeds sold_qty of 3!
                'price_per_unit' => 1500,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 7500,
            ]
        ]
    ]);

    $overReturnResp->assertStatus(422);
    $overReturnResp->assertJson([
        'status' => 'error',
        'success' => false,
    ]);
    $overReturnResp->assertJsonFragment([
        'message' => 'Return quantity (' . format_quantity(5) . ') exceeds remaining returnable quantity (' . format_quantity(3) . ') for Power Bank 20000mAh.'
    ]);

    // Assert complete rollback: no return records created, no stock changes
    expect(DbSalesReturn::count())->toBe($initialReturnCount);
    expect(DbSalesItemReturn::count())->toBe($initialItemReturnCount);
    // Cast both sides to float: MySQL returns decimal columns as numeric strings
    // ("10.00") while SQLite returned native numbers (10).
    expect((float) $item->fresh()->stock)->toBe((float) $initialStock);
});

test('9. Multi-item sale with one item fully returned allows partial return for remaining items only', function () {
    $user = getSaleDetailTestUser();

    $customer = DbCustomer::create([
        'store_id' => 1,
        'customer_name' => 'Multi Item Customer',
        'mobile' => '01899999999',
        'status' => 1,
    ]);

    $warehouse = DbWarehouse::create([
        'store_id' => 1,
        'warehouse_name' => 'Mymensingh Hub',
        'status' => 1,
    ]);

    $item1 = DbItem::create([
        'store_id' => 1,
        'item_name' => 'USB Hub',
        'item_code' => 'ITM-HUB-01',
        'purchase_price' => 500,
        'sales_price' => 800,
        'stock' => 20,
        'status' => 1,
    ]);

    $item2 = DbItem::create([
        'store_id' => 1,
        'item_name' => 'Webcam HD',
        'item_code' => 'ITM-CAM-01',
        'purchase_price' => 2000,
        'sales_price' => 3000,
        'stock' => 10,
        'status' => 1,
    ]);

    $sale = DbSale::create([
        'store_id' => 1,
        'warehouse_id' => $warehouse->id,
        'customer_id' => $customer->id,
        'sales_code' => 'SA-00108',
        'sales_date' => '2026-08-25',
        'subtotal' => 6800,
        'grand_total' => 6800,
        'paid_amount' => 6800,
        'payment_status' => 'Paid',
        'created_by' => $user->id,
    ]);

    // Item 1: 1 unit sold ($800)
    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item1->id,
        'sales_qty' => 1,
        'price_per_unit' => 800,
        'total_cost' => 800,
    ]);

    // Item 2: 2 units sold ($6000)
    DbSaleItem::create([
        'store_id' => 1,
        'sales_id' => $sale->id,
        'item_id' => $item2->id,
        'sales_qty' => 2,
        'price_per_unit' => 3000,
        'total_cost' => 6000,
    ]);

    $account = AcAccount::create([
        'store_id' => 1,
        'account_name' => 'Refund Drawer',
        'account_code' => 'ACC-REF-99',
        'balance' => 20000,
        'status' => 1,
    ]);

    // Return Item 1 completely (1 of 1 unit)
    $ret1Resp = $this->actingAs($user)->postJson(route('sales.return.store'), [
        'sales_id' => $sale->id,
        'return_date' => '2026-08-25',
        'paid_amount' => 800,
        'payment_type' => 'Cash',
        'account_id' => $account->id,
        'items' => [
            [
                'item_id' => $item1->id,
                'return_qty' => 1,
                'price_per_unit' => 800,
                'tax_amt' => 0,
                'discount_amt' => 0,
                'total_cost' => 800,
            ]
        ]
    ]);
    $ret1Resp->assertOk();

    // Create Return button MUST still be visible because Item 2 has 2 units returnable
    $sale->refresh();
    $showResp = $this->actingAs($user)->get(route('sales.show', $sale->id));
    $showResp->assertOk();
    $showResp->assertSee('Create Return');

    // Create return page should show Item 1 with remaining_qty 0 and Item 2 with remaining_qty 2
    $createResp = $this->actingAs($user)->get(route('sales.return.create', $sale->id));
    $createResp->assertOk();
    $createResp->assertSee('"item_name":"USB Hub"', false);
    $createResp->assertSee('"already_returned":1,"remaining_qty":0', false);
    $createResp->assertSee('"item_name":"Webcam HD"', false);
    $createResp->assertSee('"already_returned":0,"remaining_qty":2', false);
});

