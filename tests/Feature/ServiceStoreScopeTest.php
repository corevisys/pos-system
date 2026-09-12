<?php

namespace Tests\Feature;

use App\Models\DbCategory;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbTax;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Verification for ServiceController::store().
 *
 * The defect: store() wrote a literal store_id = 1 regardless of the acting
 * user's store (a direct data-corruption bug in multi-store deployments). The
 * fix writes current_store_id(), defaults status to 1 (so new services are
 * immediately sellable in POS), and guards the category/tax lookups by the
 * same store (so a crafted cross-store category/tax id cannot be attached).
 */
class ServiceStoreScopeTest extends TestCase
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
            'permissions' => ['services_view', 'services_add', 'services_edit', 'sales_add', 'sales_view'],
        ]);
        $this->store1User = User::factory()->create(['store_id' => 1, 'role_id' => $role1->id]);

        $role2 = DbRole::create(['role_name' => 'Store 2 Admin', 'status' => 1, 'store_id' => 2]);
        DbPermission::create([
            'role_id' => $role2->id,
            'store_id' => 2,
            'permissions' => ['services_view', 'services_add', 'services_edit', 'sales_add', 'sales_view'],
        ]);
        $this->store2User = User::factory()->create(['store_id' => 2, 'role_id' => $role2->id]);

        $this->cat1 = DbCategory::create(['category_name' => 'Store 1 Cat', 'status' => 1, 'store_id' => 1]);
        $this->cat2 = DbCategory::create(['category_name' => 'Store 2 Cat', 'status' => 1, 'store_id' => 2]);
        $this->tax1 = DbTax::create(['tax_name' => 'Store 1 Tax', 'tax' => 5.00, 'status' => 1, 'store_id' => 1]);
        $this->tax2 = DbTax::create(['tax_name' => 'Store 2 Tax', 'tax' => 10.00, 'status' => 1, 'store_id' => 2]);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'item_name' => 'Consultation Fee',
            'category_id' => $this->cat2->id,
            'item_code' => 'SVC-' . uniqid(),
            'hsn' => '9983',
            'seller_points' => 0,
            'description' => 'Test service',
            'discount_type' => 'Percentage(%)',
            'discount' => 0,
            'price' => 100.00,
            'tax_id' => $this->tax2->id,
            'tax_type' => 'Inclusive',
            'sales_price' => 150.00,
        ], $overrides);
    }

    /** A Store-2 user creating a service results in store_id = 2 (not 1). */
    public function test_store_writes_current_store_id_for_store_2_user()
    {
        $this->actingAs($this->store2User)
            ->post(route('items.service.store'), $this->validPayload())
            ->assertRedirect(route('items.service.list'));

        $this->assertDatabaseHas('db_items', [
            'item_name' => 'Consultation Fee',
            'store_id' => 2,
            'service_bit' => 1,
        ]);
    }

    /** New services default to status = 1 so they are immediately sellable in POS. */
    public function test_store_defaults_new_service_to_active()
    {
        $this->actingAs($this->store1User)
            ->post(route('items.service.store'), $this->validPayload(['category_id' => $this->cat1->id, 'tax_id' => $this->tax1->id]));

        $this->assertDatabaseHas('db_items', [
            'item_name' => 'Consultation Fee',
            'store_id' => 1,
            'status' => 1,
        ]);
    }

    /** A crafted cross-store category id is rejected (not written). */
    public function test_store_rejects_cross_store_category_id()
    {
        $this->actingAs($this->store2User)
            ->post(route('items.service.store'), $this->validPayload(['category_id' => $this->cat1->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('db_items', ['item_name' => 'Consultation Fee']);
    }

    /** A crafted cross-store tax id is rejected (not written). */
    public function test_store_rejects_cross_store_tax_id()
    {
        $this->actingAs($this->store2User)
            ->post(route('items.service.store'), $this->validPayload(['tax_id' => $this->tax1->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('db_items', ['item_name' => 'Consultation Fee']);
    }

    /** Item 13: an explicit duplicate item_code shows a friendly message, not raw SQL. */
    public function test_store_duplicate_item_code_shows_friendly_message()
    {
        $payload = $this->validPayload(['item_code' => 'DUP-CODE-001']);
        $this->actingAs($this->store2User)
            ->post(route('items.service.store'), $payload)
            ->assertRedirect(route('items.service.list'));

        $this->actingAs($this->store2User)
            ->post(route('items.service.store'), $payload)
            ->assertSessionHas('error', 'This item code is already in use — please try again or enter a different code.');
    }

    /** Item 9/D: uploading an image on Add Service persists the file and DB field (mirrors Items). */
    public function test_store_persists_item_image_upload()
    {
        $image = \Illuminate\Http\UploadedFile::fake()->image('service.png', 20, 20);

        $this->actingAs($this->store1User)
            ->post(route('items.service.store'), $this->validPayload([
                'category_id' => $this->cat1->id,
                'tax_id' => $this->tax1->id,
                'item_code' => 'IMG-SVC-1',
                'item_image' => $image,
            ]))
            ->assertRedirect(route('items.service.list'));

        $service = \App\Models\DbItem::where('item_code', 'IMG-SVC-1')->first();
        $this->assertNotNull($service);
        $this->assertNotEmpty($service->item_image, 'item_image DB field must be set.');
        $this->assertStringStartsWith('uploads/items/', $service->item_image);
        $this->assertFileExists(public_path($service->item_image), 'Uploaded file must exist on disk.');
    }

    /** Item 9/D: updating a service replaces the existing image (delete-old + write-new), mirroring Items. */
    public function test_update_replaces_item_image()
    {
        $image1 = \Illuminate\Http\UploadedFile::fake()->image('first.png', 20, 20);
        $this->actingAs($this->store1User)
            ->post(route('items.service.store'), $this->validPayload([
                'category_id' => $this->cat1->id,
                'tax_id' => $this->tax1->id,
                'item_code' => 'IMG-SVC-2',
                'item_image' => $image1,
            ]));

        $service = \App\Models\DbItem::where('item_code', 'IMG-SVC-2')->first();
        $oldPath = public_path($service->item_image);
        $this->assertFileExists($oldPath);

        $image2 = \Illuminate\Http\UploadedFile::fake()->image('second.png', 20, 20);
        $this->actingAs($this->store1User)
            ->post(route('items.service.update', $service->id), [
                'item_name' => 'IMG-SVC-2',
                'item_code' => 'IMG-SVC-2',
                'category_id' => $this->cat1->id,
                'price' => 100.00,
                'tax_id' => $this->tax1->id,
                'tax_type' => 'Inclusive',
                'sales_price' => 150.00,
                'item_image' => $image2,
            ])
            ->assertRedirect(route('items.service.list'));

        $service->refresh();
        $newPath = public_path($service->item_image);
        $this->assertNotEquals(basename($oldPath), basename($service->item_image), 'Image must be replaced.');
        $this->assertFileExists($newPath);
        $this->assertFileDoesNotExist($oldPath, 'Old image file must be removed on replace.');
    }
}
