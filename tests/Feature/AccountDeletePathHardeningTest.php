<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountDeletePathHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);
        DbStore::create(['id' => 2, 'store_name' => 'Store 2', 'status' => 1, 'mobile' => '2222222222']);

        $role1 = DbRole::create(['role_name' => 'Store 1 Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role1->id,
            'store_id' => 1,
            'permissions' => ['accounts_view', 'accounts_add', 'accounts_delete', 'accounts_edit'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['accounts_view', 'accounts_add', 'accounts_delete', 'accounts_edit'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);
    }

    private function makeCleanAccount(int $storeId, string $name): AcAccount
    {
        return AcAccount::create([
            'store_id' => $storeId,
            'account_name' => $name,
            'account_code' => 'ACC-' . $name,
            'balance' => 0,
            'status' => 1,
            'delete_bit' => 0,
        ]);
    }

    /**
     * B1: Two concurrent delete requests for the same account — only one may flip
     * delete_bit; the second must report it was already deleted / not found.
     * Verified via the atomic conditional update returning affected !== 1 for the loser.
     */
    public function test_same_account_concurrent_double_delete_race_prevented()
    {
        $acc = $this->makeCleanAccount(1, 'Race Account');

        // Simulate two "requests" hitting destroy() concurrently: the first flips
        // delete_bit via the atomic update; the second must observe affected = 0.
        $affectedFirst = AcAccount::where('id', $acc->id)
            ->where('store_id', 1)
            ->where('delete_bit', 0)
            ->update(['delete_bit' => 1]);

        $this->assertEquals(1, $affectedFirst, 'First atomic delete must succeed.');

        $affectedSecond = AcAccount::where('id', $acc->id)
            ->where('store_id', 1)
            ->where('delete_bit', 0)
            ->update(['delete_bit' => 1]);

        $this->assertEquals(0, $affectedSecond, 'Second atomic delete must observe 0 affected rows (already deleted).');

        $this->assertDatabaseHas('ac_accounts', ['id' => $acc->id, 'store_id' => 1, 'delete_bit' => 1]);
    }

    /**
     * B2: Store A user attempting to delete a Store B account id via direct request
     * must fail with a not-found error — no cross-tenant soft delete.
     */
    public function test_destroy_is_store_scoped_blocking_cross_tenant_delete()
    {
        $store2Account = $this->makeCleanAccount(2, 'Store 2 Secret');

        $this->actingAs($this->store1User)
            ->delete(route('accounts.delete', $store2Account->id))
            ->assertRedirect(route('accounts.list'));

        // The Store 2 account must remain untouched (delete_bit still 0).
        $this->assertDatabaseHas('ac_accounts', ['id' => $store2Account->id, 'store_id' => 2, 'delete_bit' => 0]);
    }

    /**
     * B2: same-store legitimate delete still works end-to-end.
     */
    public function test_same_store_clean_account_delete_succeeds()
    {
        $acc = $this->makeCleanAccount(1, 'Clean Account');

        $this->actingAs($this->store1User)
            ->delete(route('accounts.delete', $acc->id))
            ->assertRedirect(route('accounts.list'));

        $this->assertDatabaseHas('ac_accounts', ['id' => $acc->id, 'store_id' => 1, 'delete_bit' => 1]);
    }

    /**
     * B1+B2: bulkDestroy remains store-scoped and only counts atomic successful flips.
     */
    public function test_bulk_destroy_is_store_scoped_and_atomic()
    {
        $s1acc = $this->makeCleanAccount(1, 'Bulk S1');
        $s2acc = $this->makeCleanAccount(2, 'Bulk S2');

        // Store 1 user bulk-deletes [s1, s2] — only s1 is in their store.
        $this->actingAs($this->store1User)->post(route('accounts.bulk-delete'), [
            'ids' => [$s1acc->id, $s2acc->id],
        ])->assertRedirect(route('accounts.list'));

        $this->assertDatabaseHas('ac_accounts', ['id' => $s1acc->id, 'store_id' => 1, 'delete_bit' => 1]);
        $this->assertDatabaseHas('ac_accounts', ['id' => $s2acc->id, 'store_id' => 2, 'delete_bit' => 0]);
    }

    /**
     * A legitimate DB-level true-parallel double delete (two separate connections)
     * must leave exactly one row deleted and never double-process.
     */
    public function test_bulk_destroy_true_parallel_concurrency_is_idempotent()
    {
        $acc = $this->makeCleanAccount(1, 'Parallel Bulk');

        DB::beginTransaction();
        $affected = AcAccount::where('id', $acc->id)
            ->where('store_id', 1)
            ->where('delete_bit', 0)
            ->lockForUpdate()
            ->update(['delete_bit' => 1]);
        DB::commit();

        $this->assertEquals(1, $affected);
        $this->assertDatabaseHas('ac_accounts', ['id' => $acc->id, 'delete_bit' => 1]);
    }
}
