<?php

namespace Tests\Feature;

use App\Models\DbItem;
use App\Models\DbStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 verification:
 * Confirms that db_items.item_code uniqueness is now scoped per store_id,
 * NOT globally. Two stores must be able to share the same item_code without
 * conflict, while the same (store_id, item_code) pair is still rejected.
 */
class ItemCodeCompositeUniqueTest extends TestCase
{
    use RefreshDatabase;

    private function makeStore(string $name, string $prefix = 'ITM'): DbStore
    {
        return DbStore::create([
            'store_name' => $name,
            'status'     => 1,
            'item_init'  => $prefix,
        ]);
    }

    private function makeSupports(int $storeId): array
    {
        $catId  = \Illuminate\Support\Facades\DB::table('db_category')->insertGetId([
            'store_id'      => $storeId,
            'category_name' => 'Cat-' . uniqid(),
            'status'        => 1,
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);
        $unitId = \Illuminate\Support\Facades\DB::table('db_units')->insertGetId([
            'store_id'  => $storeId,
            'unit_name' => 'Unit-' . uniqid(),
            'status'    => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $taxId  = \Illuminate\Support\Facades\DB::table('db_tax')->insertGetId([
            'store_id' => $storeId,
            'tax_name' => 'Tax-' . uniqid(),
            'tax'      => 0,
            'status'   => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        return [$catId, $unitId, $taxId];
    }

    /** Two different stores can both hold an item with item_code = ITM00001. */
    public function test_different_stores_can_share_same_item_code(): void
    {
        $store1 = $this->makeStore('Alpha Store');
        $store2 = $this->makeStore('Beta Store');
        [$catId, $unitId, $taxId] = $this->makeSupports($store1->id);

        $sharedCode = 'ITM00001';

        // Store 1 item
        $item1 = DbItem::create([
            'store_id'    => $store1->id,
            'item_code'   => $sharedCode,
            'item_name'   => 'Widget Alpha',
            'category_id' => $catId,
            'unit_id'     => $unitId,
            'tax_id'      => $taxId,
            'tax_type'    => 'Exclusive',
            'price'       => 100,
            'status'      => 1,
        ]);

        // Store 2 item — same item_code, different store. Must NOT throw.
        $item2 = DbItem::create([
            'store_id'    => $store2->id,
            'item_code'   => $sharedCode,
            'item_name'   => 'Widget Beta',
            'category_id' => $catId,
            'unit_id'     => $unitId,
            'tax_id'      => $taxId,
            'tax_type'    => 'Exclusive',
            'price'       => 200,
            'status'      => 1,
        ]);

        $this->assertSame($store1->id, $item1->store_id);
        $this->assertSame($store2->id, $item2->store_id);
        $this->assertSame($sharedCode, $item1->item_code);
        $this->assertSame($sharedCode, $item2->item_code);

        // Verify both rows exist in the DB.
        $this->assertSame(2, DbItem::where('item_code', $sharedCode)->count());
    }

    /** Same store cannot have two items with the same item_code. */
    public function test_same_store_rejects_duplicate_item_code(): void
    {
        $store = $this->makeStore('Gamma Store');
        [$catId, $unitId, $taxId] = $this->makeSupports($store->id);

        $code = 'ITM00001';

        DbItem::create([
            'store_id'    => $store->id,
            'item_code'   => $code,
            'item_name'   => 'Widget One',
            'category_id' => $catId,
            'unit_id'     => $unitId,
            'tax_id'      => $taxId,
            'tax_type'    => 'Exclusive',
            'price'       => 100,
            'status'      => 1,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DbItem::create([
            'store_id'    => $store->id,
            'item_code'   => $code,     // same store, same code -> must fail
            'item_name'   => 'Widget Two',
            'category_id' => $catId,
            'unit_id'     => $unitId,
            'tax_id'      => $taxId,
            'tax_type'    => 'Exclusive',
            'price'       => 150,
            'status'      => 1,
        ]);
    }
}
