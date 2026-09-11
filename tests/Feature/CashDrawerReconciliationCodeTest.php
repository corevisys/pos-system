<?php

namespace Tests\Feature;

use App\Models\AcAccount;
use App\Models\CashDrawerReconciliation;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\User;
use App\Services\CodeGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashDrawerReconciliationCodeTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(string $name): DbStore
    {
        return DbStore::create([
            'store_name' => $name,
            'status'     => 1,
        ]);
    }

    private function makeAccount(int $storeId): AcAccount
    {
        return AcAccount::create([
            'store_id'     => $storeId,
            'account_name' => 'Cash Account ' . uniqid(),
            'account_code' => 'ACC-' . uniqid(),
            'status'       => 1,
            'delete_bit'   => 0,
        ]);
    }

    public function test_reconciliation_code_generated_sequentially(): void
    {
        $store = $this->makeStore('Reconciliation Store');
        $code1 = CodeGeneratorService::generate('reconciliation', null, $store->id);
        $this->assertEquals('REC-00001', $code1);

        $acc = $this->makeAccount($store->id);
        $user = User::factory()->create(['store_id' => $store->id]);

        CashDrawerReconciliation::create([
            'store_id'            => $store->id,
            'reconciliation_code' => $code1,
            'account_id'          => $acc->id,
            'user_id'             => $user->id,
            'reconciliation_date' => now()->toDateString(),
            'status'              => 'Open',
        ]);

        $code2 = CodeGeneratorService::generate('reconciliation', null, $store->id);
        $this->assertEquals('REC-00002', $code2);
    }

    public function test_different_stores_can_share_same_reconciliation_code(): void
    {
        $store1 = $this->makeStore('Store A');
        $store2 = $this->makeStore('Store B');

        $acc1 = $this->makeAccount($store1->id);
        $acc2 = $this->makeAccount($store2->id);

        $user1 = User::factory()->create(['store_id' => $store1->id]);
        $user2 = User::factory()->create(['store_id' => $store2->id]);

        $sharedCode = 'REC-00001';

        $rec1 = CashDrawerReconciliation::create([
            'store_id'            => $store1->id,
            'reconciliation_code' => $sharedCode,
            'account_id'          => $acc1->id,
            'user_id'             => $user1->id,
            'reconciliation_date' => now()->toDateString(),
            'status'              => 'Open',
        ]);

        // Must succeed without throwing uniqueness violation
        $rec2 = CashDrawerReconciliation::create([
            'store_id'            => $store2->id,
            'reconciliation_code' => $sharedCode,
            'account_id'          => $acc2->id,
            'user_id'             => $user2->id,
            'reconciliation_date' => now()->toDateString(),
            'status'              => 'Open',
        ]);

        $this->assertSame($store1->id, $rec1->store_id);
        $this->assertSame($store2->id, $rec2->store_id);
        $this->assertSame(2, CashDrawerReconciliation::allStores()->where('reconciliation_code', $sharedCode)->count());
    }

    public function test_same_store_rejects_duplicate_reconciliation_code(): void
    {
        $store = $this->makeStore('Store Single');
        $acc = $this->makeAccount($store->id);
        $user = User::factory()->create(['store_id' => $store->id]);

        $code = 'REC-00001';

        CashDrawerReconciliation::create([
            'store_id'            => $store->id,
            'reconciliation_code' => $code,
            'account_id'          => $acc->id,
            'user_id'             => $user->id,
            'reconciliation_date' => now()->toDateString(),
            'status'              => 'Open',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        CashDrawerReconciliation::create([
            'store_id'            => $store->id,
            'reconciliation_code' => $code, // Duplicate in same store
            'account_id'          => $acc->id,
            'user_id'             => $user->id,
            'reconciliation_date' => now()->toDateString(),
            'status'              => 'Open',
        ]);
    }
}
