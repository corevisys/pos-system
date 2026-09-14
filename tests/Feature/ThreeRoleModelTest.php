<?php

use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\User;
use App\Services\StoreContext;
use Database\Seeders\OwnerDeveloperSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

/**
 * Phase 2 — the three-role model (Branch Admin / Owner / Developer).
 *
 * Exit criteria: three logins with three genuinely different capability sets, and no
 * login can silently escalate into another's scope.
 */

function threeRoleSeed(): void
{
    DbStore::create(['id' => 1, 'store_code' => 'ST001', 'store_name' => 'Dhaka', 'status' => 1, 'mobile' => '01711000001']);
    DbStore::create(['id' => 2, 'store_code' => 'ST002', 'store_name' => 'Chittagong', 'status' => 1, 'mobile' => '01711000002']);

    test()->seed([RolePermissionSeeder::class, OwnerDeveloperSeeder::class]);
}

function threeRoleBranchAdmin(int $storeId): User
{
    $role = DbRole::create([
        'store_id' => $storeId,
        'role_name' => 'Branch Admin ' . $storeId,
        'status' => 1,
        'is_super_admin' => false,
        'is_owner' => false,
    ]);

    \App\Models\DbPermission::create([
        'role_id' => $role->id,
        'store_id' => $storeId,
        'permissions' => ['dashboard_view', 'sales_view'],
    ]);

    return User::factory()->create([
        'store_id' => $storeId,
        'role_id' => $role->id,
        'email_verified_at' => now(),
    ]);
}

function threeRoleOwner(): User
{
    $role = DbRole::where('role_name', 'Owner')->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
        'role_name' => 'Owner',
        'email_verified_at' => now(),
    ]);
}

function threeRoleDeveloper(): User
{
    $role = DbRole::where('role_name', 'Developer')->firstOrFail();

    return User::factory()->create([
        'role_id' => $role->id,
        'role_name' => 'Developer',
        'email_verified_at' => now(),
    ]);
}

// ── 1. Branch admin: NOT a super admin, NOT an owner ─────────────────────────

test('branch admin is neither super admin nor owner', function () {
    threeRoleSeed();
    $admin = threeRoleBranchAdmin(1);

    expect($admin->isSuperAdmin())->toBeFalse()
        ->and($admin->isOwner())->toBeFalse()
        ->and($admin->canViewAllStores())->toBeFalse();
});

// ── 2. Branch admin cannot act as another store ──────────────────────────────

test('branch admin acting store is pinned to their own store even with a forged session', function () {
    threeRoleSeed();
    $admin = threeRoleBranchAdmin(1);

    $this->actingAs($admin)
        ->withSession([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => 2])
        ->get('/dashboard');

    expect(app(StoreContext::class)->storeId())->toBe(1);
});

// ── 3. Owner: cross-store visibility but NOT globally privileged ─────────────

test('owner has cross-store visibility but is not a super admin', function () {
    threeRoleSeed();
    $owner = threeRoleOwner();

    expect($owner->isOwner())->toBeTrue()
        ->and($owner->isSuperAdmin())->toBeFalse()
        ->and($owner->canViewAllStores())->toBeTrue();
});

// ── 4. Owner does NOT bypass permission checks (unlike the Developer) ────────

test('owner does not bypass permission checks; a missing slug is denied', function () {
    threeRoleSeed();
    $owner = threeRoleOwner();

    // Owner is seeded with reports_view but NOT users_delete — it must be denied.
    expect($owner->hasPermission('reports_view'))->toBeTrue()
        ->and($owner->hasPermission('users_delete'))->toBeFalse();
});

// ── 5. Owner can act as any store via the selector ───────────────────────────

test('owner may act as any store via the session selector', function () {
    threeRoleSeed();
    $owner = threeRoleOwner();

    $this->actingAs($owner)
        ->withSession([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => 2])
        ->get('/dashboard');

    expect(app(StoreContext::class)->storeId())->toBe(2);
});

// ── 6. Developer: unrestricted (the only global-privilege identity) ──────────

test('developer is the only globally privileged identity', function () {
    threeRoleSeed();
    $developer = threeRoleDeveloper();

    expect($developer->isSuperAdmin())->toBeTrue()
        ->and($developer->hasPermission('anything_at_all'))->toBeTrue();
});

// ── 7. Owner and Developer are distinct accounts/roles ───────────────────────

test('owner and developer are separate accounts with separate roles', function () {
    threeRoleSeed();

    $ownerRole = DbRole::where('role_name', 'Owner')->firstOrFail();
    $developerRole = DbRole::where('role_name', 'Developer')->firstOrFail();

    expect($ownerRole->id)->not->toBe($developerRole->id)
        ->and((bool) $ownerRole->is_super_admin)->toBeFalse()
        ->and((bool) $ownerRole->is_owner)->toBeTrue()
        ->and((bool) $developerRole->is_super_admin)->toBeTrue()
        ->and((bool) $developerRole->is_owner)->toBeFalse();

    expect(User::where('email', 'owner@corevisys.com')->exists())->toBeTrue()
        ->and(User::where('email', 'developer@corevisys.com')->exists())->toBeTrue()
        ->and(User::where('email', 'owner@corevisys.com')->value('role_id'))
        ->not->toBe(User::where('email', 'developer@corevisys.com')->value('role_id'));
});

// ── 8. EnsureUserHasStore lets the Owner/Developer through without a real store ─

test('owner is not blocked by EnsureUserHasStore for having a nominal store', function () {
    threeRoleSeed();
    $owner = threeRoleOwner();

    // Owner has a nominal store_id (DB NOT NULL) but is exempted from the fixed-store gate.
    $this->actingAs($owner)->get('/dashboard')->assertOk();
});

test('developer is not blocked by EnsureUserHasStore', function () {
    threeRoleSeed();
    $developer = threeRoleDeveloper();

    $this->actingAs($developer)->get('/dashboard')->assertOk();
});

// ── 9. Store selector endpoint: Owner allowed, branch admin rejected ─────────

test('owner can switch the acting store through the selector endpoint', function () {
    threeRoleSeed();
    $owner = threeRoleOwner();

    $this->actingAs($owner)
        ->post(route('store.context.update'), ['store_id' => 2])
        ->assertRedirect();

    expect(session(\App\Http\Middleware\SetCurrentStore::SESSION_KEY))->toBe(2);
});

test('branch admin is forbidden from the store selector endpoint', function () {
    threeRoleSeed();
    $admin = threeRoleBranchAdmin(1);

    $this->actingAs($admin)
        ->post(route('store.context.update'), ['store_id' => 2])
        ->assertForbidden();
});
