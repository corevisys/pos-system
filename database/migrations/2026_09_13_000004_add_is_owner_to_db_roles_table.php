<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 2.2 — the Owner role.
 *
 * The Owner sees across stores (all-store visibility + consolidated reporting) but
 * is NOT a bypass-everything super admin: it is subject to the normal permission
 * checks. It is therefore represented by a dedicated boolean (db_roles.is_owner)
 * rather than by the is_super_admin flag, so "sees across stores" and "bypasses
 * authorization" remain separate concepts.
 *
 * A boolean (not a fourth store-scoped role row per store) matches the binary
 * access model: a role is either a branch admin, an Owner, or the Developer.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('db_roles', 'is_owner')) {
            Schema::table('db_roles', function (Blueprint $table) {
                $table->boolean('is_owner')->default(false)->after('is_super_admin');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('db_roles', 'is_owner')) {
            Schema::table('db_roles', function (Blueprint $table) {
                $table->dropColumn('is_owner');
            });
        }
    }
};
