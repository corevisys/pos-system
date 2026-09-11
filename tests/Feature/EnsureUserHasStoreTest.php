<?php

namespace Tests\Feature;

use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsureUserHasStoreTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $activeStore;
    protected DbStore $inactiveStore;
    protected DbRole $superAdminRole;
    protected DbRole $cashierRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activeStore = DbStore::create([
            'id' => 1,
            'store_code' => 'ST0001',
            'store_name' => 'Active Central Store',
            'mobile' => '01700000001',
            'email' => 'active@example.com',
            'city' => 'Dhaka',
            'status' => 1,
        ]);

        $this->inactiveStore = DbStore::create([
            'id' => 2,
            'store_code' => 'ST0002',
            'store_name' => 'Deactivated Branch Store',
            'mobile' => '01700000002',
            'email' => 'inactive@example.com',
            'city' => 'Chittagong',
            'status' => 0,
        ]);

        $this->superAdminRole = DbRole::create([
            'id' => 1,
            'role_name' => 'Super Admin',
            'status' => 1,
            'store_id' => 1,
        ]);

        $this->cashierRole = DbRole::create([
            'id' => 2,
            'role_name' => 'Cashier',
            'status' => 1,
            'store_id' => 1,
        ]);
    }

    /**
     * Test 1: Super Admin bypasses EnsureUserHasStore middleware even if
     * pointing to an inactive store or non-existent store.
     */
    public function test_super_admin_bypasses_store_guard(): void
    {
        $superAdmin = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'role_name' => 'Super Admin',
            'store_id' => $this->inactiveStore->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($superAdmin)->get(route('dashboard'));
        $response->assertStatus(200);
    }

    /**
     * Test 2: Regular user with an active store can access protected routes.
     */
    public function test_regular_user_with_active_store_can_access_protected_routes(): void
    {
        $cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'role_name' => 'Cashier',
            'store_id' => $this->activeStore->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($cashier)->get(route('dashboard'));
        $response->assertStatus(200);
    }

    /**
     * Test 3: Regular user whose store_id is NOT NULL but points to an
     * INACTIVE store (status = 0) is blocked with 403.
     */
    public function test_regular_user_with_deactivated_store_is_blocked_with_403(): void
    {
        $cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'role_name' => 'Cashier',
            'store_id' => $this->inactiveStore->id, // store_id is NOT NULL, but store.status = 0
            'status' => 1,
        ]);

        $response = $this->actingAs($cashier)->get(route('dashboard'));
        $response->assertStatus(403);
        $response->assertSee('The assigned store has been deactivated');
    }

    /**
     * Test 4: Regular user whose store is deactivated AFTER user creation
     * gets blocked on subsequent requests even though store_id never became null.
     */
    public function test_store_deactivated_after_user_creation_blocks_access(): void
    {
        $cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'role_name' => 'Cashier',
            'store_id' => $this->activeStore->id,
            'status' => 1,
        ]);

        // Initially active: 200 OK
        $response1 = $this->actingAs($cashier)->get(route('dashboard'));
        $response1->assertStatus(200);

        // Store is now deactivated
        $this->activeStore->update(['status' => 0]);

        // Subsequent request is blocked with 403
        $response2 = $this->actingAs($cashier)->get(route('dashboard'));
        $response2->assertStatus(403);
        $response2->assertSee('The assigned store has been deactivated');
    }

    /**
     * Test 5: Regular user whose store_id points to a non-existent / deleted
     * store row gets blocked with 403.
     */
    public function test_regular_user_pointing_to_non_existent_store_is_blocked_with_403(): void
    {
        $cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'role_name' => 'Cashier',
            'store_id' => 99999, // store_id is NOT NULL, but no db_store row exists
            'status' => 1,
        ]);

        $response = $this->actingAs($cashier)->get(route('dashboard'));
        $response->assertStatus(403);
        $response->assertSee('The assigned store does not exist');
    }

    /**
     * Test 6: Unauthenticated guest hitting protected routes is redirected to login,
     * not blocked by ensure.store with 403.
     */
    public function test_unauthenticated_request_redirects_to_login_not_403(): void
    {
        $response = $this->get(route('dashboard'));
        $response->assertRedirect(route('login'));
    }

    /**
     * Test 7: Profile routes outside ensure.store remain accessible to users
     * so they can manage their profile or log out.
     */
    public function test_profile_routes_remain_accessible_for_deactivated_store_user(): void
    {
        $cashier = User::factory()->create([
            'role_id' => $this->cashierRole->id,
            'role_name' => 'Cashier',
            'store_id' => $this->inactiveStore->id,
            'status' => 1,
        ]);

        $response = $this->actingAs($cashier)->get(route('profile.edit'));
        $response->assertStatus(200);
    }
}
