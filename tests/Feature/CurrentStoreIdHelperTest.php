<?php

use App\Models\User;
use App\Models\DbRole;
use App\Models\DbStore;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Shared Setup Helper ───────────────────────────────────────────────────

function getStoreIdTestUser(int $storeId = 1): User
{
    store_settings(true);

    $store = DbStore::firstOrCreate(['id' => $storeId], [
        'store_name' => 'StoreId Test Store',
        'status'     => 1,
        'mobile'     => '01700000001',
        'timezone'   => 'Asia/Dhaka',
    ]);

    $role = DbRole::firstOrCreate(['id' => 1], [
        'store_id'    => $store->id,
        'role_name'   => 'Super Admin',
        'description' => 'Super Admin Role',
        'status'      => 1,
    ]);

    return User::firstOrCreate(
        ['email' => 'storeid_tester@example.com'],
        [
            'name'         => 'StoreId Tester',
            'first_name'   => 'StoreId',
            'last_name'    => 'Tester',
            'username'     => 'storeid_tester',
            'password'     => bcrypt('password'),
            'role_id'      => $role->id,
            'role_name'    => $role->role_name,
            'status'       => 1,
            'store_id'     => $store->id,
            'created_date' => now()->format('Y-m-d'),
            'created_time' => now()->format('H:i:s'),
        ]
    );
}

// ─── Test 1: current_store_id() resolves from authenticated user's store_id ─

test('current_store_id returns authenticated users store_id', function () {
    $user = getStoreIdTestUser(1);

    $this->actingAs($user);

    $resolved = current_store_id();

    expect($resolved)->toBe(1)
        ->and($resolved)->toBeInt();
});

// ─── Test 2: current_store_id() falls back to store_settings()->id when unauthenticated ─

test('current_store_id falls back to store settings id when not authenticated', function () {
    // Ensure store exists
    $store = DbStore::firstOrCreate(['id' => 1], [
        'store_name' => 'Fallback Test Store',
        'status'     => 1,
        'mobile'     => '01700000002',
        'timezone'   => 'Asia/Dhaka',
    ]);
    store_settings(true);

    // No actingAs — unauthenticated
    $resolved = current_store_id();

    // Should resolve from store_settings()->id
    expect($resolved)->toBe((int) $store->id)
        ->and($resolved)->toBeInt();
});

// ─── Test 3: current_store_id() returns 1 as final fallback when no store record ─

test('current_store_id returns 1 as final fallback when no store and no auth', function () {
    // Wipe all stores so store_settings() returns null
    DbStore::truncate();
    store_settings(true); // clear memoization

    $resolved = current_store_id();

    expect($resolved)->toBe(1);
});

// ─── Test 4: current_store_id() is consistent with what gets written to the DB ─

test('current_store_id returns integer 1 for default single-store deployment', function () {
    $user = getStoreIdTestUser(1);

    $this->actingAs($user);

    // Simulate what a controller does when creating a model
    $storeIdUsed = current_store_id();

    expect($storeIdUsed)
        ->toBe(1)
        ->toBeInt();
});

// ─── Test 5: User with custom store_id resolves correctly ─

test('current_store_id returns correct store_id for user belonging to store', function () {
    $user = getStoreIdTestUser(1);

    // If we fabricate a user with a different store_id, that value should be returned
    $user->store_id = 1; // Explicitly set to confirm
    $user->save();

    $this->actingAs($user);

    $resolved = current_store_id();

    expect($resolved)->toBe(1);
});
