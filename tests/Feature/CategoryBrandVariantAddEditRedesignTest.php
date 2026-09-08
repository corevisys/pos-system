<?php

namespace Tests\Feature;

use App\Models\DbBrand;
use App\Models\DbCategory;
use App\Models\DbPermission;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbVariant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Add/Edit redesign (18c) parity verification.
 *
 * Confirms the 6 redesigned pages still render: the double-submit guard
 * (root-scoped x-data + :disabled submit), the design-system classes
 * (card/input-base/btn-primary/btn-secondary), the hidden _method spoof on
 * Edit forms, and status=0 pre-selected on the 3 Edit pages. Also verifies
 * inline validation errors render under the Name field after a failed submit.
 */
class CategoryBrandVariantAddEditRedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DbStore::create(['id' => 1, 'store_name' => 'Store 1', 'status' => 1, 'mobile' => '1111111111']);

        $role = DbRole::create(['role_name' => 'Admin', 'status' => 1, 'store_id' => 1]);
        DbPermission::create([
            'role_id' => $role->id,
            'store_id' => 1,
            'permissions' => [
                'items_category_view', 'items_category_add', 'items_category_edit',
                'brand_view', 'brand_add', 'brand_edit',
                'variant_view', 'variant_add', 'variant_edit',
            ],
        ]);
        $this->user = User::factory()->create(['store_id' => 1, 'role_id' => $role->id]);

        // Inactive rows to verify status=0 is pre-selected on Edit.
        $this->cat = DbCategory::create(['category_name' => 'Inactive Cat', 'status' => 0, 'store_id' => 1]);
        $this->brand = DbBrand::create(['brand_name' => 'Inactive Brand', 'status' => 0, 'store_id' => 1]);
        $this->variant = DbVariant::create(['variant_name' => 'Inactive Variant', 'status' => 0, 'store_id' => 1]);
    }

    private function assertGuardMarkup(string $html): void
    {
        // Root-scoped x-data carrying isSubmitting (so the header Save button's
        // :disabled binds to the same scope as the form's @submit).
        $this->assertStringContainsString('x-data="{ isSubmitting: false }"', $html);
        // The submit button is disabled while submitting.
        $this->assertStringContainsString(':disabled="isSubmitting"', $html);
        // The form still sets isSubmitting on submit (double-submit guard).
        $this->assertStringContainsString('@submit="isSubmitting = true"', $html);
    }

    private function assertDesignSystem(string $html): void
    {
        $this->assertStringContainsString('class="card p-4 md:p-6"', $html);
        $this->assertStringContainsString('class="input-base"', $html);
        $this->assertStringContainsString('btn-primary', $html);
        $this->assertStringContainsString('btn-secondary', $html);
    }

    private function assertPutSpoof(string $html): void
    {
        // @method('PUT') compiles to a hidden _method input.
        $this->assertStringContainsString('name="_method"', $html);
        $this->assertMatchesRegularExpression('/name="_method"[^>]*value="PUT"/', $html);
    }

    /* ───────────────────────── ADD PAGES ───────────────────────── */

    public function test_add_category_renders_guard_card_and_design_system(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.categories.add'))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
    }

    public function test_add_brand_renders_guard_card_and_design_system(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.brands.add'))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
    }

    public function test_add_variant_renders_guard_card_and_design_system(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.variants.add'))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
    }

    /* ───────────────────────── EDIT PAGES ───────────────────────── */

    public function test_edit_category_preselects_inactive_status_and_keeps_guard(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.categories.edit', $this->cat->id))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
        $this->assertPutSpoof($html);
        // status=0 row must render Inactive pre-selected.
        $this->assertMatchesRegularExpression('/<option value="0"[^>]*selected[^>]*>Inactive<\/option>/', $html);
    }

    public function test_edit_brand_preselects_inactive_status_and_keeps_guard(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.brands.edit', $this->brand->id))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
        $this->assertPutSpoof($html);
        $this->assertMatchesRegularExpression('/<option value="0"[^>]*selected[^>]*>Inactive<\/option>/', $html);
    }

    public function test_edit_variant_preselects_inactive_status_and_keeps_guard(): void
    {
        $html = $this->actingAs($this->user)->get(route('items.variants.edit', $this->variant->id))->assertOk()->getContent();

        $this->assertGuardMarkup($html);
        $this->assertDesignSystem($html);
        $this->assertPutSpoof($html);
        $this->assertMatchesRegularExpression('/<option value="0"[^>]*selected[^>]*>Inactive<\/option>/', $html);
    }

    /* ───────────────────────── INLINE ERROR DISPLAY ───────────────────────── */

    public function test_inline_error_renders_under_category_name_on_add(): void
    {
        // Empty name → validation error. The redirect-back carries session errors,
        // so the next GET of the add page renders the inline @error under the field.
        $this->actingAs($this->user)
            ->from(route('items.categories.add'))
            ->post(route('items.categories.store'), ['category_name' => ''])
            ->assertSessionHasErrors('category_name');

        $html = $this->actingAs($this->user)->get(route('items.categories.add'))->assertOk()->getContent();

        // Inline error paragraph (the @error slot) next to the Name field.
        $this->assertStringContainsString('text-danger mt-1 ml-0.5', $html);
        $this->assertStringContainsString('The category name field is required.', $html);
    }

    public function test_inline_error_renders_under_brand_name_on_add(): void
    {
        $this->actingAs($this->user)
            ->from(route('items.brands.add'))
            ->post(route('items.brands.store'), ['brand_name' => ''])
            ->assertSessionHasErrors('brand_name');

        $html = $this->actingAs($this->user)->get(route('items.brands.add'))->assertOk()->getContent();

        $this->assertStringContainsString('text-danger mt-1 ml-0.5', $html);
        $this->assertStringContainsString('The brand name field is required.', $html);
    }

    public function test_inline_error_renders_under_variant_name_on_add(): void
    {
        $this->actingAs($this->user)
            ->from(route('items.variants.add'))
            ->post(route('items.variants.store'), ['variant_name' => ''])
            ->assertSessionHasErrors('variant_name');

        $html = $this->actingAs($this->user)->get(route('items.variants.add'))->assertOk()->getContent();

        $this->assertStringContainsString('text-danger mt-1 ml-0.5', $html);
        $this->assertStringContainsString('The variant name field is required.', $html);
    }

    public function test_duplicate_name_error_renders_inline_after_uniqueness_rejection(): void
    {
        // Create "Electronics" first.
        $this->actingAs($this->user)->post(route('items.categories.store'), ['category_name' => 'Electronics'])->assertRedirect();

        // Second identical name within the same store → validation error.
        $this->actingAs($this->user)
            ->from(route('items.categories.add'))
            ->post(route('items.categories.store'), ['category_name' => 'Electronics'])
            ->assertSessionHasErrors('category_name');

        $html = $this->actingAs($this->user)->get(route('items.categories.add'))->assertOk()->getContent();

        // The per-store uniqueness message renders inline under the Name field.
        $this->assertStringContainsString('text-danger mt-1 ml-0.5', $html);
        $this->assertStringContainsString('already been taken', $html);
    }
}
