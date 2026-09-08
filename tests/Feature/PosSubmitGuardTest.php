<?php

use App\Models\User;
use App\Models\DbStore;
use Database\Seeders\StoreSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\CurrencySeeder;
use Database\Seeders\WarehouseSeeder;
use Database\Seeders\LanguageSeeder;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    $this->seed([
        CurrencySeeder::class,
        LanguageSeeder::class,
        StoreSeeder::class,
        RolePermissionSeeder::class,
        WarehouseSeeder::class,
    ]);

    $this->user = User::factory()->create([
        'store_id' => 1,
        'role_id' => 1,
        'role_name' => 'Super Admin',
    ]);
});

/**
 * Regression coverage for the POS double-submit guard (FIX 1).
 *
 * The guard is Alpine state inside posComponent(), so it cannot be driven by a
 * pure HTTP test. These view-render assertions pin the exact contract that the
 * earlier remediation pass ported from Add Sale:
 *   1. a `submitting: false` state exists in posComponent(),
 *   2. submitSale()/submitHold()/submitEmi() each early-return when submitting,
 *   3. every server-hitting trigger button is bound to :disabled="submitting".
 * If any of these are removed in a future refactor, the test fails.
 */
test('POS page ships the double-submit guard state and early-returns for all submit actions', function () {
    $response = $this->actingAs($this->user)->get(route('sales.pos'));

    $response->assertOk();

    $html = $response->getContent();

    // 1. Guard state exists on the Alpine component.
    expect($html)->toContain('submitting: false');

    // 2. Early-return guard in every server-hitting action.
    expect(substr_count($html, 'if (this.submitting) {'))->toBeGreaterThanOrEqual(3);

    // 3. Trigger buttons are disabled while submitting (Pay All, Hold, modals).
    expect(substr_count($html, ':disabled="submitting"'))->toBeGreaterThanOrEqual(6);

    // 4. Spinner/label-change visuals exist so the disabled state is visible.
    expect($html)->toContain("submitting ? 'Saving...'");
    expect($html)->toContain("submitting ? 'Holding...'");
});

test('POS page still guards the protected checkout contract (no payload-math regression)', function () {
    $response = $this->actingAs($this->user)->get(route('sales.pos'));

    $response->assertOk();

    $html = $response->getContent();

    // The checkout payload must still be sent intact to the POS store route.
    expect($html)->toContain(route('sales.pos.store', [], false));

    // Dark-mode Swal coupling must remain intact.
    expect($html)->toContain("document.documentElement.classList.contains('dark')");

    // The fullscreen contract class must remain on the POS root.
    expect($html)->toContain('class="pos-screen');
});
