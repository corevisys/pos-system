<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 2.1 — Clear the branch-admin bypass.
 *
 * The one-time backfill in 2026_09_12_000002 granted is_super_admin = true to every
 * role named "Super Admin" (plus id = 1). AdminUserSeeder and RolePermissionSeeder
 * seed exactly those per-store "Super Admin" roles (ids 1-3), so every branch admin
 * was globally privileged — bypassing EnsureUserHasStore and every permission gate,
 * and able to read another branch's data.
 *
 * Access is binary and branch admins are bound to their own store, so this clears the
 * global-privilege flag from those branch-admin roles. The flag is retained ONLY for
 * the dedicated Developer/system account (seeded separately, role name "Developer"),
 * which is the sole unrestricted cross-store identity.
 *
 * The Developer role is matched by name ("Developer"), so this migration never touches
 * it even if it already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Only the per-store branch-admin roles are named exactly "Super Admin".
        DB::table('db_roles')
            ->where('role_name', 'Super Admin')
            ->update(['is_super_admin' => false]);
    }

    public function down(): void
    {
        // Restore the (over-broad) legacy convention so the migration is reversible.
        DB::table('db_roles')
            ->where(function ($q) {
                $q->where('role_name', 'Super Admin')->orWhere('id', 1);
            })
            ->update(['is_super_admin' => true]);
    }
};
