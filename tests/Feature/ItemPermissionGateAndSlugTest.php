<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbCurrency;
use App\Models\DbItem;
use App\Models\DbLanguage;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\DbUnit;
use App\Models\DbWarehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ItemController permission hardening.
 *
 * PHASE 1 — items_view/items_add/items_edit/items_delete gates on the core CRUD
 * methods (index/create/store/edit/update/destroy), which previously had NO
 * permission check at all.
 *
 * PHASE 2 — the seeded outlier slug `print_labels` renamed to `items_print_labels`
 * so the Print Labels feature is actually reachable (seeders + a data migration
 * that rewrites already-deployed role permissions in place).
 */
class ItemPermissionGateAndSlugTest extends TestCase
{
    use RefreshDatabase;

    protected DbStore $store;
    protected DbWarehouse $warehouse;
    protected int $categoryId;
    protected int $unitId;
    protected int $taxId;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = DbCurrency::firstOrCreate(['id' => 1], [
            'currency_name' => 'Bangladeshi Taka', 'currency_code' => 'BDT',
            'symbol' => '৳', 'status' => 1,
        ]);
        $language = DbLanguage::firstOrCreate(['id' => 1], [
            'language' => 'English', 'code' => 'en', 'status' => 1,
        ]);

        $this->store = DbStore::create([
            'store_code' => 'ITEMPERM-ST',
            'store_name' => 'Item Permission Store',
            'mobile'     => '01799992222',
            'status'     => 1,
            'currency_id' => $currency->id,
            'language_id' => $language->id,
            'decimals'   => 2,
            'qty_decimals' => 2,
        ]);
        store_settings(true);

        $this->warehouse = DbWarehouse::create([
            'store_id' => $this->store->id,
            'warehouse_name' => 'Permission Warehouse',
            'status' => 1,
        ]);

        $this->categoryId = DbCategory::create(['category_name' => 'Perm Goods', 'status' => 1, 'store_id' => $this->store->id])->id;
        $this->unitId = DbUnit::create(['unit_name' => 'Pcs', 'status' => 1, 'store_id' => $this->store->id])->id;
        $this->taxId = DbTax::create(['tax_name' => 'VAT 5%', 'tax' => 5, 'status' => 1, 'store_id' => $this->store->id])->id;
    }

    /** A NON-super-admin actor whose role is granted exactly $permissions. */
    private function actor(array $permissions): User
    {
        $role = DbRole::create([
            'store_id'  => $this->store->id,
            'role_name' => 'Item Perm Role ' . uniqid(),
            'status'    => 1,
        ]);
        DbPermission::create([
            'role_id'     => $role->id,
            'store_id'    => $this->store->id,
            'permissions' => $permissions,
        ]);

        return User::factory()->create([
            'store_id' => $this->store->id,
            'role_id'  => $role->id,
        ]);
    }

    private function makeItem(string $name = 'Perm Item'): DbItem
    {
        return DbItem::create([
            'store_id'   => $this->store->id,
            'item_name'  => $name,
            'item_code'  => 'PERM-' . strtoupper(uniqid()),
            'sales_price' => 100,
            'status'     => 1,
            'child_bit'  => 0,
        ]);
    }

    private function storePayload(string $name): array
    {
        return [
            'item_name'     => $name,
            'item_group'    => 'Single',
            'category_id'   => $this->categoryId,
            'unit_id'       => $this->unitId,
            'tax_id'        => $this->taxId,
            'tax_type'      => 'Inclusive',
            'discount_type' => 'Percentage',
            'discount'      => 0,
            'warehouse_id'  => $this->warehouse->id,
            'price'         => 100,
            'purchase_price' => 100,
            'sales_price'   => 150,
            'opening_stock' => 0,
        ];
    }

    /* ══════════════════════ PHASE 1: items_view ══════════════════════ */

    public function test_items_view_denied_without_the_slug(): void
    {
        // Has add/edit/delete but NOT view.
        $user = $this->actor(['items_add', 'items_edit', 'items_delete']);

        $this->actingAs($user)->get(route('items.list'))->assertStatus(403);
    }

    public function test_items_view_control_with_the_slug(): void
    {
        $user = $this->actor(['items_view']);

        $this->actingAs($user)->get(route('items.list'))->assertOk();
    }

    /* ══════════════════════ PHASE 1: items_add ══════════════════════ */

    public function test_items_add_denied_without_the_slug_and_no_row_created(): void
    {
        $user = $this->actor(['items_view', 'items_edit', 'items_delete']);
        $before = DbItem::withoutGlobalScope('store_id')->count();

        $this->actingAs($user)
            ->post(route('items.store'), $this->storePayload('Should Not Be Created'))
            ->assertStatus(403);

        $this->assertSame($before, DbItem::withoutGlobalScope('store_id')->count(), 'No item row may be created without items_add.');
        $this->assertSame(0, DbItem::withoutGlobalScope('store_id')->where('item_name', 'Should Not Be Created')->count());

        // create() form is also gated by items_add.
        $this->actingAs($user)->get(route('items.add'))->assertStatus(403);
    }

    public function test_items_add_control_with_the_slug(): void
    {
        $user = $this->actor(['items_view', 'items_add']);

        $this->actingAs($user)->get(route('items.add'))->assertOk();

        $this->actingAs($user)
            ->post(route('items.store'), $this->storePayload('Created With Permission'))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, DbItem::withoutGlobalScope('store_id')->where('item_name', 'Created With Permission')->count());
    }

    /* ══════════════════════ PHASE 1: items_edit ══════════════════════ */

    public function test_items_edit_denied_without_the_slug_and_row_unchanged(): void
    {
        $user = $this->actor(['items_view', 'items_add', 'items_delete']);
        $item = $this->makeItem('Original Name');

        // edit() form
        $this->actingAs($user)->get(route('items.edit', $item->id))->assertStatus(403);

        // update() action
        $this->actingAs($user)
            ->post(route('items.update', $item->id), $this->storePayload('Hacked Name'))
            ->assertStatus(403);

        $this->assertSame('Original Name', $item->fresh()->item_name, 'Item must be unchanged without items_edit.');
    }

    public function test_items_edit_control_with_the_slug(): void
    {
        $user = $this->actor(['items_view', 'items_edit']);
        $item = $this->makeItem('Editable Item');

        $this->actingAs($user)->get(route('items.edit', $item->id))->assertOk();

        $this->actingAs($user)
            ->post(route('items.update', $item->id), $this->storePayload('Renamed With Permission'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed With Permission', $item->fresh()->item_name);
    }

    /* ══════════════════════ PHASE 1: items_delete ══════════════════════ */

    public function test_items_delete_denied_without_the_slug_and_row_survives(): void
    {
        $user = $this->actor(['items_view', 'items_add', 'items_edit']);
        $item = $this->makeItem('Undeletable Item');

        $this->actingAs($user)
            ->deleteJson(route('items.delete', $item->id))
            ->assertStatus(403);

        $this->assertNotNull(DbItem::withoutGlobalScope('store_id')->find($item->id), 'Item must survive a delete without items_delete.');
    }

    public function test_items_delete_control_with_the_slug(): void
    {
        $user = $this->actor(['items_view', 'items_delete']);
        $item = $this->makeItem('Deletable Item');

        $this->actingAs($user)
            ->deleteJson(route('items.delete', $item->id))
            ->assertOk();

        $this->assertNull(DbItem::withoutGlobalScope('store_id')->find($item->id), 'Item must be deleted with items_delete.');
    }

    /* ══════════════════════ PHASE 2: slug rename (seeders) ══════════════════════ */

    public function test_phase2_permission_seeder_grants_items_print_labels_not_print_labels(): void
    {
        $this->seed(\Database\Seeders\PermissionSeeder::class);

        $role = DbRole::where('role_name', 'Super Admin')->firstOrFail();
        $permissions = DbPermission::where('role_id', $role->id)->firstOrFail()->permissions;

        $this->assertContains('items_print_labels', $permissions, 'PermissionSeeder must grant the code-facing slug.');
        $this->assertNotContains('print_labels', $permissions, 'The orphan print_labels slug must be gone.');

        // Neighbouring slugs must be untouched by the rename.
        $this->assertContains('items_view', $permissions);
        $this->assertContains('items_category_view', $permissions);
        $this->assertContains('dashboard_view', $permissions);
    }

    public function test_phase2_role_permission_seeder_grants_items_print_labels_not_print_labels(): void
    {
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $this->assertTrue(
            DbPermission::where('permissions', 'like', '%items_print_labels%')->exists(),
            'RolePermissionSeeder must grant items_print_labels.'
        );
        $this->assertFalse(
            DbPermission::where('permissions', 'like', '%"print_labels"%')->exists(),
            'No permission row may still carry the orphan print_labels slug.'
        );
    }

    /* ══════════════════════ PHASE 2: data migration remap ══════════════════════ */

    public function test_phase2_migration_rewrites_deployed_print_labels_in_place(): void
    {
        // Simulate an already-deployed role that was granted the OLD slug.
        $role = DbRole::create([
            'store_id'  => $this->store->id,
            'role_name' => 'Legacy Print Labels Role ' . uniqid(),
            'status'    => 1,
        ]);
        $perm = DbPermission::create([
            'role_id'     => $role->id,
            'store_id'    => $this->store->id,
            'permissions' => ['print_labels', 'items_view', 'sales_view'],
        ]);

        // Run the REAL migration's up() (idempotent; RefreshDatabase already ran it
        // against empty data, so this applies it to the seeded legacy row).
        $migration = require base_path('database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php');
        $migration->up();

        $updated = DbPermission::find($perm->id)->permissions;

        $this->assertContains('items_print_labels', $updated, 'The old slug must be remapped in place.');
        $this->assertNotContains('print_labels', $updated, 'The old slug must no longer be present.');
        // Assignment preserved: the row is the SAME row (no delete+recreate), and
        // its other slugs are intact.
        $this->assertSame($perm->id, DbPermission::find($perm->id)->id);
        $this->assertContains('items_view', $updated);
        $this->assertContains('sales_view', $updated);
    }

    public function test_phase2_migration_remaps_item_printed_labels_to_the_code_slug_end_to_end(): void
    {
        // A role carrying the OLD slug, remapped by the migration...
        $role = DbRole::create([
            'store_id'  => $this->store->id,
            'role_name' => 'Print Labels E2E Role ' . uniqid(),
            'status'    => 1,
        ]);
        DbPermission::create([
            'role_id'     => $role->id,
            'store_id'    => $this->store->id,
            'permissions' => ['print_labels'],
        ]);

        $migration = require base_path('database/migrations/2026_09_13_000002_rename_print_labels_slug_to_items_print_labels.php');
        $migration->up();

        $user = User::factory()->create(['store_id' => $this->store->id, 'role_id' => $role->id]);

        // ...now actually reaches the feature, which the orphan slug never did.
        $this->actingAs($user)->get(route('items.labels'))->assertOk();
    }

    public function test_phase2_role_without_items_print_labels_still_gets_403(): void
    {
        $user = $this->actor(['items_view']);

        $this->actingAs($user)->get(route('items.labels'))->assertStatus(403);
    }
}
