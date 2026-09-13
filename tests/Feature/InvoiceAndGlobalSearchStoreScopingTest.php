<?php

namespace Tests\Feature;

use App\Models\DbCurrency;
use App\Models\DbCustomer;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbSale;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Two unrelated store-scoping gaps:
 *
 *   PHASE 1 — SaleInvoiceController::show() no longer bypasses the store scope
 *             (was DbSale::allStores()->findOrFail($id)) — a Store-B user
 *             requesting a Store-A invoice by id now gets a 404.
 *
 *   PHASE 2 — GlobalSearchController's user search no longer leaks other stores'
 *             users to a non-super-admin (scoped to the acting store; a confirmed
 *             super-admin still searches network-wide).
 */
class InvoiceAndGlobalSearchStoreScopingTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $storeA;
    protected DbStore $storeB;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Taka', 'currency_code' => 'BDT', 'symbol' => '৳', 'status' => 1,
        ]);
        $language = DbLanguage::firstOrCreate(['id' => 1], [
            'language' => 'English', 'code' => 'en', 'status' => 1,
        ]);

        $this->storeA = DbStore::create([
            'id' => 1, 'store_code' => 'INV-A', 'store_name' => 'Alpha Store',
            'mobile' => '01711110001', 'status' => 1,
            'currency_id' => $currency->id, 'language_id' => $language->id,
            'decimals' => 2, 'qty_decimals' => 2,
        ]);
        $this->storeB = DbStore::create([
            'id' => 2, 'store_code' => 'INV-B', 'store_name' => 'Beta Store',
            'mobile' => '01711110002', 'status' => 1,
            'currency_id' => $currency->id, 'language_id' => $language->id,
            'decimals' => 2, 'qty_decimals' => 2,
        ]);
    }

    private function actor(int $storeId, array $permissions = []): User
    {
        $role = DbRole::create([
            'store_id' => $storeId, 'role_name' => 'Role ' . $storeId . ' ' . uniqid(), 'status' => 1,
        ]);
        DbPermission::create([
            'role_id' => $role->id, 'store_id' => $storeId, 'permissions' => $permissions,
        ]);

        return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id]);
    }

    private function makeSale(int $storeId, string $code): DbSale
    {
        $warehouse = DbWarehouse::create([
            'store_id' => $storeId, 'warehouse_name' => 'WH ' . $code, 'status' => 1,
        ]);
        $customer = DbCustomer::create([
            'store_id' => $storeId, 'customer_name' => 'Cust ' . $code,
            'customer_code' => 'C-' . $code, 'mobile' => '0179999000' . $storeId, 'status' => 1,
        ]);

        return DbSale::create([
            'store_id' => $storeId,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'sales_code' => $code,
            'sales_date' => now()->toDateString(),
            'subtotal' => 100.00,
            'grand_total' => 100.00,
            'paid_amount' => 100.00,
            'payment_status' => 'Paid',
            'status' => 1,
        ]);
    }

    /* ══════════════════ PHASE 1 — invoice store scoping ══════════════════ */

    public function test_phase1_store_b_user_cannot_view_store_a_invoice_by_id(): void
    {
        $saleA = $this->makeSale(1, 'ALPHA-INV-1');
        $userB = $this->actor(2, ['sales_view']);

        // Cross-store id → clean 404 (the StoreScoped trait hides Store-A rows).
        $this->actingAs($userB)
            ->get(route('sales.invoice', ['id' => $saleA->id]))
            ->assertNotFound();
    }

    public function test_phase1_store_b_user_can_still_view_own_invoice(): void
    {
        $saleB = $this->makeSale(2, 'BETA-INV-1');
        $userB = $this->actor(2, ['sales_view']);

        $this->actingAs($userB)
            ->get(route('sales.invoice', ['id' => $saleB->id]))
            ->assertOk()
            ->assertSee('BETA-INV-1');
    }

    public function test_phase1_store_a_user_can_still_view_own_invoice(): void
    {
        $saleA = $this->makeSale(1, 'ALPHA-INV-2');
        $userA = $this->actor(1, ['sales_view']);

        $this->actingAs($userA)
            ->get(route('sales.invoice', ['id' => $saleA->id]))
            ->assertOk()
            ->assertSee('ALPHA-INV-2');
    }

    /* ══════════════════ PHASE 2 — global search user scoping ══════════════════ */

    public function test_phase2_non_superadmin_users_view_search_is_store_scoped(): void
    {
        // users_view on the acting (store 1) role — the exposure path.
        $user1 = $this->actor(1, ['users_view']);

        // A store-2 user with a distinctive name.
        User::factory()->create([
            'store_id' => 2, 'first_name' => 'CrossStore', 'last_name' => 'Secret',
            'username' => 'crossstore_secret',
        ]);

        // A store-1 (same-store) user with a distinctive name.
        User::factory()->create([
            'store_id' => 1, 'first_name' => 'SameStore', 'last_name' => 'Visible',
            'username' => 'samestore_visible',
        ]);

        // The cross-store user must NOT appear.
        $res = $this->actingAs($user1)->getJson(route('global.search', ['q' => 'CrossStore']));
        $res->assertOk();
        $titles = collect($res->json('categories.users.items') ?? [])->pluck('title')->all();
        $this->assertNotContains('CrossStore Secret', $titles, 'A store-2 user must not leak into store-1 search.');

        // Control: the SAME-store user IS found.
        $res2 = $this->actingAs($user1)->getJson(route('global.search', ['q' => 'SameStore']));
        $res2->assertOk();
        $titles2 = collect($res2->json('categories.users.items') ?? [])->pluck('title')->all();
        $this->assertContains('SameStore Visible', $titles2, 'The acting store\'s own user must still be found.');
    }

    public function test_phase2_superadmin_still_searches_users_across_stores(): void
    {
        // A genuine super-admin (is_super_admin = true).
        $role = DbRole::create([
            'store_id' => 1, 'role_name' => 'Super Admin ' . uniqid(),
            'status' => 1, 'is_super_admin' => true,
        ]);
        DbPermission::create(['role_id' => $role->id, 'store_id' => 1, 'permissions' => []]);
        $admin = User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);

        User::factory()->create([
            'store_id' => 2, 'first_name' => 'CrossStore', 'last_name' => 'Secret',
            'username' => 'crossstore_secret',
        ]);

        $res = $this->actingAs($admin)->getJson(route('global.search', ['q' => 'CrossStore']));
        $res->assertOk();
        $titles = collect($res->json('categories.users.items') ?? [])->pluck('title')->all();
        $this->assertContains('CrossStore Secret', $titles, 'A super-admin must still find users across stores.');
    }
}
