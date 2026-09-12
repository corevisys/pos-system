<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Replace the implicit "super admin" convention with an explicit, naming-independent flag.
 *
 * PROBLEM: User::isSuperAdmin() previously returned true for `role_id === 1` OR
 * `role_name === 'Super Admin'`. AdminUserSeeder creates THREE "Super Admin" roles
 * (ids 1, 2, 3 — one per store), so every per-store admin bypassed EnsureUserHasStore
 * and every hasPermission() gate. `role_id === 1` is also not a stable identity: roles
 * created through the Roles UI auto-increment, so a future store's Super Admin role can
 * get any id.
 *
 * FIX: add db_roles.is_super_admin (boolean, default false) and backfill it from the
 * existing name convention ONCE. From then on the flag is authoritative.
 *
 * The backfill deliberately matches role_name so it captures ids 1, 2 and 3 in one pass,
 * rather than assuming sequential ids. It also captures id=1 explicitly so the previous
 * `role_id === 1` behaviour is preserved even if that role was renamed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('db_roles', 'is_super_admin')) {
            Schema::table('db_roles', function (Blueprint $table) {
                $table->boolean('is_super_admin')->default(false)->after('status');
            });
        }

        // One-time backfill from the legacy name convention (+ the legacy id=1 rule).
        DB::table('db_roles')
            ->where(function ($q) {
                $q->where('role_name', 'Super Admin')->orWhere('id', 1);
            })
            ->update(['is_super_admin' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('db_roles', 'is_super_admin')) {
            Schema::table('db_roles', function (Blueprint $table) {
                $table->dropColumn('is_super_admin');
            });
        }
    }
};
