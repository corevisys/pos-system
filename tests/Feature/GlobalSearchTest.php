<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbPermission;
use App\Models\DbStore;
use App\Models\DbItem;
use App\Models\DbCustomer;
use App\Models\DbSupplier;
use App\Models\DbSale;
use App\Models\DbPurchase;
use App\Models\DbQuotation;
use App\Models\DbSalesReturn;
use App\Models\DbPurchaseReturn;
use App\Models\DbStockTransfer;
use App\Models\DbStockAdjustment;
use App\Models\DbExpense;

// ─── Helpers ───────────────────────────────────────────────────────────────

function gsCreateStore(): void
{
    DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Test Store',
        'status'     => 1,
    ]);
}

function gsSuperAdmin(): User
{
    gsCreateStore();

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id'  => 1,
        'role_name' => 'Super Admin',
        'status'    => 1,
    ]);

    DbPermission::firstOrCreate(['role_id' => $role->id], [
        'store_id'    => 1,
        'permissions' => [],
    ]);

    return User::factory()->create([
        'store_id'  => 1,
        'role_id'   => $role->id,
        'role_name' => 'Super Admin',
        'status'    => 1,
    ]);
}

/**
 * Create a limited-permission user with exactly the given slugs.
 */
function gsLimitedUser(array $permissions): User
{
    gsCreateStore();

    // Ensure Role 1 is always Super Admin so the limited user does not get role_id = 1
    DbRole::firstOrCreate(['id' => 1], [
        'store_id'  => 1,
        'role_name' => 'Super Admin',
        'status'    => 1,
    ]);

    $role = DbRole::create([
        'store_id'  => 1,
        'role_name' => 'Limited-' . uniqid(),
        'status'    => 1,
    ]);

    DbPermission::create([
        'store_id'    => 1,
        'role_id'     => $role->id,
        'permissions' => $permissions,
    ]);

    return User::factory()->create([
        'store_id'  => 1,
        'role_id'   => $role->id,
        'role_name' => $role->role_name,
        'status'    => 1,
    ]);
}

// ─── Authentication Guard ───────────────────────────────────────────────────

test('global search redirects guests to login', function () {
    $this->get(route('global.search', ['q' => 'test']))
        ->assertRedirect('/login');
});

// ─── Minimum Query Length ───────────────────────────────────────────────────

test('global search returns empty results for query shorter than 2 characters', function () {
    $user = gsSuperAdmin();

    $this->actingAs($user)->getJson(route('global.search', ['q' => 'a']))
        ->assertOk()
        ->assertJson([
            'status'      => 'success',
            'total_count' => 0,
            'categories'  => [],
        ]);
});

test('global search returns empty results for blank query', function () {
    $user = gsSuperAdmin();

    $this->actingAs($user)->getJson(route('global.search', ['q' => '']))
        ->assertOk()
        ->assertJson(['total_count' => 0, 'categories' => []]);
});

// ─── Items ──────────────────────────────────────────────────────────────────

test('global search returns matching items for super admin', function () {
    $user = gsSuperAdmin();

    DbItem::create([
        'item_name' => 'Wireless Keyboard UniqueXYZ',
        'item_code' => 'ITM-WK-001',
        'status'    => 1,
        'service_bit' => 0,
        'sales_price' => 100,
        'purchase_price' => 60,
        'stock' => 10,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'UniqueXYZ']));
    $res->assertOk()->assertJsonPath('categories.items.label', 'Items');
    $this->assertCount(1, $res->json('categories.items.items'));
    expect($res->json('categories.items.items.0.title'))->toContain('Wireless Keyboard UniqueXYZ');
    expect($res->json('categories.items.items.0.badge'))->toBe('ITEM');
});

test('global search item result includes formatted sales_price in meta field', function () {
    $user = gsSuperAdmin();

    DbItem::create([
        'item_name'      => 'PriceCheck Monitor QZ99',
        'item_code'      => 'ITM-PCM-099',
        'status'         => 1,
        'service_bit'    => 0,
        'sales_price'    => 1500.50,
        'purchase_price' => 900,
        'stock'          => 3,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'PriceCheck Monitor QZ99']));
    $res->assertOk();

    $meta = $res->json('categories.items.items.0.meta');

    // format_currency uses the active store currency (৳)
    expect($meta)->toContain('1,500.50');
});

test('global search service result includes formatted sales_price in meta field', function () {
    $user = gsSuperAdmin();

    DbItem::create([
        'item_name'      => 'ServicePriceTest Installation ZZ01',
        'item_code'      => 'SVC-ZZ-001',
        'status'         => 1,
        'service_bit'    => 1,
        'sales_price'    => 250.00,
        'purchase_price' => 0,
        'stock'          => 0,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'ServicePriceTest Installation ZZ01']));
    $res->assertOk();

    $meta = $res->json('categories.services.items.0.meta');

    expect($meta)->toContain('250.00');
});

test('global search hides items from user without items_view permission', function () {
    $user = gsLimitedUser(['customers_view']);

    DbItem::create([
        'item_name' => 'HiddenItem NoPermission',
        'item_code' => 'ITM-NP-002',
        'status'    => 1,
        'service_bit' => 0,
        'sales_price' => 100,
        'purchase_price' => 60,
        'stock' => 5,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'HiddenItem']));
    $res->assertOk();
    $this->assertArrayNotHasKey('items', $res->json('categories'));
});

// ─── Customers ──────────────────────────────────────────────────────────────

test('global search returns matching customers', function () {
    $user = gsLimitedUser(['customers_view']);

    DbCustomer::create([
        'customer_name' => 'AlphaTestCustomer',
        'customer_code' => 'CUST-ALPHA-001',
        'mobile'        => '01811000001',
        'status'        => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'AlphaTest']));
    $res->assertOk()->assertJsonPath('categories.customers.label', 'Customers');
    expect($res->json('categories.customers.items.0.title'))->toBe('AlphaTestCustomer');
    expect($res->json('categories.customers.items.0.badge'))->toBe('CUSTOMER');
});

test('global search hides customers from user without customers_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbCustomer::create([
        'customer_name' => 'HiddenCustomerNoPerms',
        'customer_code' => 'CUST-HP-002',
        'status'        => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'HiddenCustomer']));
    $res->assertOk();
    $this->assertArrayNotHasKey('customers', $res->json('categories'));
});

// ─── Suppliers ──────────────────────────────────────────────────────────────

test('global search returns matching suppliers', function () {
    $user = gsLimitedUser(['suppliers_view']);

    DbSupplier::create([
        'supplier_name' => 'BetaTestSupplier',
        'supplier_code' => 'SUP-BETA-001',
        'mobile'        => '01922000002',
        'status'        => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'BetaTest']));
    $res->assertOk()->assertJsonPath('categories.suppliers.label', 'Suppliers');
    expect($res->json('categories.suppliers.items.0.badge'))->toBe('SUPPLIER');
});

test('global search hides suppliers without suppliers_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbSupplier::create([
        'supplier_name' => 'HiddenSupplierNoPerms',
        'supplier_code' => 'SUP-HP-002',
        'status'        => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'HiddenSupplier']));
    $this->assertArrayNotHasKey('suppliers', $res->json('categories'));
});

// ─── Sales ──────────────────────────────────────────────────────────────────

test('global search returns matching sales invoices', function () {
    $user = gsLimitedUser(['sales_include_pos_view']);

    DbSale::create([
        'store_id'       => 1,
        'sales_code'     => 'SA-GSTEST-001',
        'sales_date'     => now()->format('Y-m-d'),
        'grand_total'    => 100,
        'subtotal'       => 100,
        'paid_amount'    => 100,
        'payment_status' => 'Paid',
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'GSTEST']));
    $res->assertOk()->assertJsonPath('categories.sales.label', 'Sales & Invoices');
    expect($res->json('categories.sales.items.0.title'))->toBe('SA-GSTEST-001');
    expect($res->json('categories.sales.items.0.badge'))->toBe('SALE');
});

test('global search hides sales without sales_view or sales_include_pos_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbSale::create([
        'store_id'       => 1,
        'sales_code'     => 'SA-NOPERM-099',
        'sales_date'     => now()->format('Y-m-d'),
        'grand_total'    => 50,
        'subtotal'       => 50,
        'paid_amount'    => 50,
        'payment_status' => 'Paid',
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'NOPERM']));
    $this->assertArrayNotHasKey('sales', $res->json('categories'));
});

// ─── Purchases ──────────────────────────────────────────────────────────────

test('global search returns matching purchases', function () {
    $user = gsLimitedUser(['purchase_view']);

    DbPurchase::create([
        'store_id'       => 1,
        'purchase_code'  => 'PU-GSTEST-001',
        'purchase_date'  => now()->format('Y-m-d'),
        'grand_total'    => 200,
        'subtotal'       => 200,
        'paid_amount'    => 200,
        'payment_status' => 'Paid',
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'PU-GSTEST']));
    $res->assertOk()->assertJsonPath('categories.purchases.label', 'Purchases');
    expect($res->json('categories.purchases.items.0.badge'))->toBe('PURCHASE');
});

test('global search hides purchases without purchase_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbPurchase::create([
        'store_id'       => 1,
        'purchase_code'  => 'PU-NOPERM-099',
        'purchase_date'  => now()->format('Y-m-d'),
        'grand_total'    => 50,
        'subtotal'       => 50,
        'paid_amount'    => 50,
        'payment_status' => 'Paid',
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'PU-NOPERM']));
    $this->assertArrayNotHasKey('purchases', $res->json('categories'));
});

// ─── Stock Transfers ────────────────────────────────────────────────────────

test('global search returns matching stock transfers', function () {
    $user = gsLimitedUser(['stock_transfer_view']);

    DbStockTransfer::create([
        'store_id'     => 1,
        'reference_no' => 'TRF-GSTEST-001',
        'transfer_date' => now()->format('Y-m-d'),
        'status'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'TRF-GSTEST']));
    $res->assertOk()->assertJsonPath('categories.stock_transfers.label', 'Stock Transfers');
    expect($res->json('categories.stock_transfers.items.0.badge'))->toBe('TRANSFER');
});

test('global search hides stock transfers without stock_transfer_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbStockTransfer::create([
        'store_id'     => 1,
        'reference_no' => 'TRF-NOPERM-002',
        'transfer_date' => now()->format('Y-m-d'),
        'status'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'TRF-NOPERM']));
    $this->assertArrayNotHasKey('stock_transfers', $res->json('categories'));
});

// ─── Stock Adjustments ──────────────────────────────────────────────────────

test('global search returns matching stock adjustments', function () {
    $user = gsLimitedUser(['stock_adjustment_view']);

    DbStockAdjustment::create([
        'store_id'        => 1,
        'reference_no'    => 'ADJ-GSTEST-001',
        'adjustment_date' => now()->format('Y-m-d'),
        'status'          => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'ADJ-GSTEST']));
    $res->assertOk()->assertJsonPath('categories.stock_adjustments.label', 'Stock Adjustments');
    expect($res->json('categories.stock_adjustments.items.0.badge'))->toBe('ADJUST');
});

test('global search hides stock adjustments without stock_adjustment_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbStockAdjustment::create([
        'store_id'        => 1,
        'reference_no'    => 'ADJ-NOPERM-002',
        'adjustment_date' => now()->format('Y-m-d'),
        'status'          => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'ADJ-NOPERM']));
    $this->assertArrayNotHasKey('stock_adjustments', $res->json('categories'));
});

// ─── Expenses ───────────────────────────────────────────────────────────────

test('global search returns matching expenses', function () {
    $user = gsLimitedUser(['expense_view']);

    DbExpense::create([
        'store_id'     => 1,
        'expense_code' => 'EXP-GSTEST-001',
        'expense_for'  => 'Office Supplies UniqueExp',
        'expense_date' => now()->format('Y-m-d'),
        'expense_amt'  => 50,
        'status'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'UniqueExp']));
    $res->assertOk()->assertJsonPath('categories.expenses.label', 'Expenses');
    expect($res->json('categories.expenses.items.0.badge'))->toBe('EXPENSE');
});

test('global search hides expenses without expense_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbExpense::create([
        'store_id'     => 1,
        'expense_code' => 'EXP-NOPERM-002',
        'expense_for'  => 'NoPermExpense',
        'expense_date' => now()->format('Y-m-d'),
        'expense_amt'  => 30,
        'status'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'NoPermExpense']));
    $this->assertArrayNotHasKey('expenses', $res->json('categories'));
});

// ─── Purchase Returns ───────────────────────────────────────────────────────

test('global search returns matching purchase returns', function () {
    $user = gsLimitedUser(['purchase_return_view']);

    DbPurchaseReturn::create([
        'return_code'    => 'PR-GSTEST-001',
        'return_date'    => now()->format('Y-m-d'),
        'grand_total'    => 80,
        'subtotal'       => 80,
        'paid_amount'    => 80,
        'payment_status' => 'Paid',
        'store_id'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'PR-GSTEST']));
    $res->assertOk()->assertJsonPath('categories.purchase_returns.label', 'Purchase Returns');
    expect($res->json('categories.purchase_returns.items.0.badge'))->toBe('PR');
});

test('global search hides purchase returns without purchase_return_view permission', function () {
    $user = gsLimitedUser(['items_view']);

    DbPurchaseReturn::create([
        'return_code'    => 'PR-NOPERM-002',
        'return_date'    => now()->format('Y-m-d'),
        'grand_total'    => 30,
        'subtotal'       => 30,
        'paid_amount'    => 30,
        'payment_status' => 'Paid',
        'store_id'       => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'PR-NOPERM']));
    $this->assertArrayNotHasKey('purchase_returns', $res->json('categories'));
});

// ─── Users (strictest gate) ─────────────────────────────────────────────────

test('global search returns users for super admin', function () {
    $admin = gsSuperAdmin();

    User::factory()->create([
        'store_id'   => 1,
        'first_name' => 'SearchableUser',
        'last_name'  => 'GsTest',
        'username'   => 'gs_searchable_user',
        'status'     => 1,
    ]);

    $res = $this->actingAs($admin)->getJson(route('global.search', ['q' => 'SearchableUser']));
    $res->assertOk()->assertJsonPath('categories.users.label', 'Users');
    expect($res->json('categories.users.items.0.badge'))->toBe('USER');
});

test('global search hides users from user with no users_view permission even if isSuperAdmin false', function () {
    $user = gsLimitedUser(['items_view', 'customers_view', 'sales_view']);

    // The user to be searched
    User::factory()->create([
        'store_id'   => 1,
        'username'   => 'secretstaff_gstest',
        'first_name' => 'SecretStaff',
        'status'     => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'SecretStaff']));
    $res->assertOk();
    $this->assertArrayNotHasKey('users', $res->json('categories'));
});

test('global search shows users to user with explicit users_view permission', function () {
    $user = gsLimitedUser(['users_view']);

    User::factory()->create([
        'store_id'   => 1,
        'username'   => 'visible_user_gstest',
        'first_name' => 'VisibleUser',
        'status'     => 1,
    ]);

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'VisibleUser']));
    $res->assertOk();
    $this->assertArrayHasKey('users', $res->json('categories'));
});

// ─── Result count limit ─────────────────────────────────────────────────────

test('global search returns at most 5 results per category', function () {
    $user = gsSuperAdmin();

    foreach (range(1, 8) as $i) {
        DbItem::create([
            'item_name'      => "LimitTestItem {$i}",
            'item_code'      => "LMT-{$i}",
            'status'         => 1,
            'service_bit'    => 0,
            'sales_price'    => 10,
            'purchase_price' => 5,
            'stock'          => 1,
        ]);
    }

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'LimitTest']));
    $res->assertOk();
    $this->assertLessThanOrEqual(5, count($res->json('categories.items.items') ?? []));
});

// ─── Pages & Navigation Search ──────────────────────────────────────────────

test('global search returns matching navigation pages for pos', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'pos']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');
    
    $pages = collect($res->json('categories.pages.items'));
    expect($pages->pluck('title')->all())->toContain('POS (Point of Sale)');
});

test('global search returns matching navigation pages for add sale', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'add sale']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $pages = collect($res->json('categories.pages.items'));
    expect($pages->pluck('title')->all())->toContain('Add Sale');
});

test('global search returns matching navigation pages for tax', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'tax']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('Tax List');
});

test('global search returns matching navigation pages for backup', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'backup']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('Database Backup');
});

test('global search returns matching navigation pages for warehouse', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'warehouse']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('Warehouse List');
});

test('global search returns matching navigation pages for sales summary report', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'sales summary']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('Sales Summary');
});

test('global search returns matching navigation pages for sms', function () {
    $user = gsSuperAdmin();

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'sms']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');

    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('SMS History');

    $resSend = $this->actingAs($user)->getJson(route('global.search', ['q' => 'send sms']));
    $sendTitles = collect($resSend->json('categories.pages.items'))->pluck('title')->all();
    expect($sendTitles)->toContain('Send SMS');
});

test('global search shows dashboard and profile pages to any authenticated user', function () {
    $user = gsLimitedUser([]); // zero special permissions

    $res = $this->actingAs($user)->getJson(route('global.search', ['q' => 'dashboard']));
    $res->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');
    $titles = collect($res->json('categories.pages.items'))->pluck('title')->all();
    expect($titles)->toContain('Dashboard');

    $resProf = $this->actingAs($user)->getJson(route('global.search', ['q' => 'profile']));
    $resProf->assertOk()->assertJsonPath('categories.pages.label', 'Pages & Actions');
    $profTitles = collect($resProf->json('categories.pages.items'))->pluck('title')->all();
    expect($profTitles)->toContain('Profile');
});

test('global search page results strictly enforce permission gating', function () {
    // User without tax_view or database_backup
    $cashier = gsLimitedUser(['sales_include_pos_add', 'items_view']);

    $resTax = $this->actingAs($cashier)->getJson(route('global.search', ['q' => 'tax']));
    $taxTitles = collect($resTax->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($taxTitles)->not->toContain('Tax List');

    $resBackup = $this->actingAs($cashier)->getJson(route('global.search', ['q' => 'backup']));
    $backupTitles = collect($resBackup->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($backupTitles)->not->toContain('Database Backup');

    // User with tax_view
    $taxAdmin = gsLimitedUser(['tax_view']);
    $resTaxAdmin = $this->actingAs($taxAdmin)->getJson(route('global.search', ['q' => 'tax']));
    $taxAdminTitles = collect($resTaxAdmin->json('categories.pages.items'))->pluck('title')->all();
    expect($taxAdminTitles)->toContain('Tax List');
});


test('global search accounts pages enforce Vocabulary A/B slug gating (absent without, present with)', function () {
    // User WITHOUT any Accounts permission slugs
    $noAccounts = gsLimitedUser(['items_view']);

    $resDep = $this->actingAs($noAccounts)->getJson(route('global.search', ['q' => 'deposit']));
    $depTitles = collect($resDep->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($depTitles)->not->toContain('Deposit List');

    $resTrf = $this->actingAs($noAccounts)->getJson(route('global.search', ['q' => 'transfer']));
    $trfTitles = collect($resTrf->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($trfTitles)->not->toContain('Money Transfer List');

    $resCash = $this->actingAs($noAccounts)->getJson(route('global.search', ['q' => 'cash']));
    $cashTitles = collect($resCash->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($cashTitles)->not->toContain('Cash Transactions');

    // User WITH money_deposit_view / money_transfer_view / cash_transactions (Vocabulary A/B)
    $withAccounts = gsLimitedUser(['money_deposit_view', 'money_transfer_view', 'cash_transactions']);

    $resDep2 = $this->actingAs($withAccounts)->getJson(route('global.search', ['q' => 'deposit']));
    $dep2Titles = collect($resDep2->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($dep2Titles)->toContain('Deposit List');

    $resTrf2 = $this->actingAs($withAccounts)->getJson(route('global.search', ['q' => 'transfer']));
    $trf2Titles = collect($resTrf2->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($trf2Titles)->toContain('Money Transfer List');

    $resCash2 = $this->actingAs($withAccounts)->getJson(route('global.search', ['q' => 'cash']));
    $cash2Titles = collect($resCash2->json('categories.pages.items') ?? [])->pluck('title')->all();
    expect($cash2Titles)->toContain('Cash Transactions');
});
