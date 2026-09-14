<?php

use App\Models\AcAccount;
use App\Models\AcTransaction;
use App\Models\DbItem;
use App\Models\DbRole;
use App\Models\DbStore;
use App\Models\DbWarehouse;
use App\Models\DbWarehouseItem;
use App\Models\User;
use Database\Seeders\OwnerDeveloperSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Phase 3.2 / 3.4 — Owner-gated consolidated reporting.
 *
 * The Owner must see correct cross-store totals; a branch admin must be rejected
 * even when it crafts ?store_id= / ?all_stores= parameters.
 */

function consolidatedSeed(): array
{
    $a = DbStore::create(['id' => 1, 'store_code' => 'ST001', 'store_name' => 'Dhaka', 'status' => 1, 'mobile' => '01711000001']);
    $b = DbStore::create(['id' => 2, 'store_code' => 'ST002', 'store_name' => 'Chittagong', 'status' => 1, 'mobile' => '01711000002']);

    test()->seed([RolePermissionSeeder::class, OwnerDeveloperSeeder::class]);

    // Store 1: debit 100, credit 40 (net 60). Store 2: debit 25, credit 5 (net 20).
    $acc1 = AcAccount::create(['store_id' => 1, 'account_name' => 'Cash A', 'balance' => 0, 'status' => 1]);
    $acc2 = AcAccount::create(['store_id' => 2, 'account_name' => 'Cash B', 'balance' => 0, 'status' => 1]);
    AcTransaction::create(['store_id' => 1, 'transaction_type' => 'Journal', 'debit_account_id' => $acc1->id, 'debit_amt' => 100, 'credit_amt' => 0, 'transaction_date' => now()->toDateString()]);
    AcTransaction::create(['store_id' => 1, 'transaction_type' => 'Journal', 'credit_account_id' => $acc1->id, 'debit_amt' => 0, 'credit_amt' => 40, 'transaction_date' => now()->toDateString()]);
    AcTransaction::create(['store_id' => 2, 'transaction_type' => 'Journal', 'debit_account_id' => $acc2->id, 'debit_amt' => 25, 'credit_amt' => 0, 'transaction_date' => now()->toDateString()]);
    AcTransaction::create(['store_id' => 2, 'transaction_type' => 'Journal', 'credit_account_id' => $acc2->id, 'debit_amt' => 0, 'credit_amt' => 5, 'transaction_date' => now()->toDateString()]);

    // Stock: store 1 has 7 units, store 2 has 3 units.
    $wh1 = DbWarehouse::create(['store_id' => 1, 'warehouse_name' => 'W1', 'status' => 1]);
    $wh2 = DbWarehouse::create(['store_id' => 2, 'warehouse_name' => 'W2', 'status' => 1]);
    $item1 = DbItem::create(['store_id' => 1, 'item_name' => 'Item A', 'item_code' => 'ITM-A', 'stock' => 0, 'status' => 1]);
    $item2 = DbItem::create(['store_id' => 2, 'item_name' => 'Item B', 'item_code' => 'ITM-B', 'stock' => 0, 'status' => 1]);
    DbWarehouseItem::create(['store_id' => 1, 'warehouse_id' => $wh1->id, 'item_id' => $item1->id, 'available_qty' => 7]);
    DbWarehouseItem::create(['store_id' => 2, 'warehouse_id' => $wh2->id, 'item_id' => $item2->id, 'available_qty' => 3]);

    return compact('a', 'b');
}

function consolidatedOwner(): User
{
    $role = DbRole::where('role_name', 'Owner')->firstOrFail();
    return User::factory()->create(['role_id' => $role->id, 'role_name' => 'Owner', 'email_verified_at' => now()]);
}

function consolidatedBranchAdmin(int $storeId): User
{
    $role = DbRole::create(['store_id' => $storeId, 'role_name' => 'Branch ' . $storeId, 'status' => 1, 'is_super_admin' => false, 'is_owner' => false]);
    \App\Models\DbPermission::create(['role_id' => $role->id, 'store_id' => $storeId, 'permissions' => ['dashboard_view', 'reports_view']]);
    return User::factory()->create(['store_id' => $storeId, 'role_id' => $role->id, 'email_verified_at' => now()]);
}

// ── 1. Owner sees the correct consolidated ledger across both stores ─────────

test('owner sees consolidated ledger totals across all stores', function () {
    consolidatedSeed();
    $owner = consolidatedOwner();

    $response = $this->actingAs($owner)->get(route('consolidated.ledger'));
    $response->assertOk();

    // Total debit 125, credit 45, net 80 across the two stores.
    $response->assertViewHas('totals', function ($totals) {
        return abs($totals['total_debit'] - 125) < 0.01
            && abs($totals['total_credit'] - 45) < 0.01
            && abs($totals['net'] - 80) < 0.01
            && $totals['store_count'] === 2;
    });
});

// ── 2. Owner sees the correct consolidated stock ─────────────────────────────

test('owner sees consolidated stock quantities across all stores', function () {
    consolidatedSeed();
    $owner = consolidatedOwner();

    $response = $this->actingAs($owner)->get(route('consolidated.stock'));
    $response->assertOk();

    $response->assertViewHas('totals', function ($totals) {
        return abs($totals['total_qty'] - 10) < 0.01 && $totals['store_count'] === 2;
    });
});

// ── 3. Owner per-store filter narrows to one store ───────────────────────────

test('owner store filter narrows the consolidated ledger to one store', function () {
    consolidatedSeed();
    $owner = consolidatedOwner();

    $response = $this->actingAs($owner)->get(route('consolidated.ledger', ['store_id' => 2]));
    $response->assertOk();

    $response->assertViewHas('totals', function ($totals) {
        return abs($totals['net'] - 20) < 0.01 && $totals['store_count'] === 1;
    });
});

// ── 4. Branch admin is REJECTED even with a crafted ?store_id= ───────────────

test('branch admin cannot reach consolidated ledger even with a crafted store_id', function () {
    consolidatedSeed();
    $admin = consolidatedBranchAdmin(1);

    $this->actingAs($admin)->get(route('consolidated.ledger'))->assertForbidden();
    $this->actingAs($admin)->get(route('consolidated.ledger', ['store_id' => 2]))->assertForbidden();
    $this->actingAs($admin)->get(route('consolidated.ledger', ['all_stores' => 1]))->assertForbidden();
});

// ── 5. Branch admin is REJECTED from consolidated stock ──────────────────────

test('branch admin cannot reach consolidated stock', function () {
    consolidatedSeed();
    $admin = consolidatedBranchAdmin(1);

    $this->actingAs($admin)->get(route('consolidated.stock'))->assertForbidden();
    $this->actingAs($admin)->get(route('consolidated.stock', ['store_id' => 2]))->assertForbidden();
});

// ── 6. Branch admin cannot reach the Multi-Store dashboard ───────────────────

test('branch admin cannot reach the multi-store dashboard', function () {
    consolidatedSeed();
    $admin = consolidatedBranchAdmin(1);

    $this->actingAs($admin)->get(route('multi-store-dashboard'))->assertForbidden();
});

// ── 7. Owner CAN reach the Multi-Store dashboard ─────────────────────────────

test('owner can reach the multi-store dashboard', function () {
    consolidatedSeed();
    $owner = consolidatedOwner();

    $this->actingAs($owner)->get(route('multi-store-dashboard'))->assertOk();
});
