<?php

use App\Models\DbStore;
use App\Models\DbRole;
use App\Models\User;
use App\Services\StoreContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 1 — explicit acting-store request context (App\Services\StoreContext +
 * App\Http\Middleware\SetCurrentStore + current_store_id()).
 *
 * The middleware resolves the acting store from the session (falling back to the
 * user's own store) and binds it into StoreContext. A disallowed session value —
 * e.g. a branch admin trying to act as another store — must be IGNORED, never
 * silently honoured.
 */

function setCurrentStoreMakeStore(int $id, string $name): DbStore
{
    return DbStore::create([
        'id' => $id, 'store_code' => 'ST' . $id, 'store_name' => $name,
        'status' => 1, 'mobile' => '01711000' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
    ]);
}

function setCurrentStoreMakeBranchAdmin(int $storeId): User
{
    $role = DbRole::create([
        'store_id' => $storeId,
        'role_name' => 'Branch Admin ' . $storeId,
        'status' => 1,
        'is_super_admin' => false,
    ]);

    return User::factory()->create([
        'store_id' => $storeId,
        'role_id' => $role->id,
        'email_verified_at' => now(),
    ]);
}

// ── 1. Branch admin acting as their OWN store (via session) is honoured ──────

test('branch admin can act as their own store via the session selector', function () {
    setCurrentStoreMakeStore(2, 'Store Two');
    $user = setCurrentStoreMakeBranchAdmin(2);

    $this->actingAs($user)
        ->withSession([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => 2])
        ->get('/dashboard');

    expect(app(StoreContext::class)->storeId())->toBe(2)
        ->and(current_store_id())->toBe(2);
});

// ── 2. Branch admin CANNOT act as another store (silently ignored) ───────────

test('branch admin cannot set another store id; it is ignored and falls back to their own', function () {
    setCurrentStoreMakeStore(2, 'Store Two');
    setCurrentStoreMakeStore(3, 'Store Three');
    $user = setCurrentStoreMakeBranchAdmin(2);

    $this->actingAs($user)
        ->withSession([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => 3])
        ->get('/dashboard');

    // The forged session value must NOT be honoured — the acting store stays 2.
    expect(app(StoreContext::class)->storeId())->toBe(2)
        ->and(current_store_id())->toBe(2);
});

// ── 3. No session value → the user's own store is the default ───────────────

test('without a session value the acting store is the authenticated user store', function () {
    setCurrentStoreMakeStore(2, 'Store Two');
    $user = setCurrentStoreMakeBranchAdmin(2);

    $this->actingAs($user)->get('/dashboard');

    expect(app(StoreContext::class)->storeId())->toBe(2);
});

// ── 4. A super admin (developer) may act as any store ───────────────────────

test('super admin may act as any store via the session selector', function () {
    setCurrentStoreMakeStore(1, 'Store One');
    setCurrentStoreMakeStore(3, 'Store Three');

    $role = DbRole::create([
        'store_id' => 1, 'role_name' => 'Super Admin', 'status' => 1, 'is_super_admin' => true,
    ]);
    $super = User::factory()->create([
        'store_id' => 1, 'role_id' => $role->id, 'email_verified_at' => now(),
    ]);

    $this->actingAs($super)
        ->withSession([\App\Http\Middleware\SetCurrentStore::SESSION_KEY => 3])
        ->get('/dashboard');

    expect(app(StoreContext::class)->storeId())->toBe(3);
});

// ── 5. Unauthenticated (guest) request leaves the context unresolved ─────────

test('unauthenticated request leaves the store context unresolved without throwing', function () {
    setCurrentStoreMakeStore(1, 'Store One');

    $this->get('/')->assertOk();

    expect(app(StoreContext::class)->isResolved())->toBeFalse()
        ->and(app(StoreContext::class)->storeId())->toBeNull();
});

// ── 6. CLI/console context: current_store_id() still resolves (no middleware) ─

test('current_store_id resolves outside a web request (console/queue context)', function () {
    setCurrentStoreMakeStore(1, 'Store One');

    // No HTTP request / no middleware ran: the helper must not throw and must
    // fall back to the default store.
    expect(current_store_id())->toBe(1);
});

// ── 7. The context is a true singleton within the request lifecycle ─────────

test('StoreContext is a shared singleton', function () {
    $a = app(StoreContext::class);
    $b = app(StoreContext::class);

    expect($a)->toBe($b);
});
