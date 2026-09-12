<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add a PER-STORE composite unique on db_roles (store_id, role_name).
 *
 * WHY: `roles` are per-store rows (db_roles.store_id), but role_name had NO
 * database-level uniqueness at all — so concurrent requests could both pass the
 * application's pre-INSERT check and create duplicate roles within a store.
 * Uniqueness belongs per store: two branches may legitimately each define a
 * "Manager" role, but one store must not have two.
 *
 * The live database was verified before adding this constraint:
 *   - no unique index existed on role_name (only PRIMARY, index(store_id), index(status));
 *   - ZERO same-store (store_id, role_name) duplicates existed;
 *   - cross-store same-name rows DID exist and are expected ("Super Admin" x 3 stores).
 *
 * SAFETY: aborts loudly if any same-store duplicate is present, matching the
 * precedent in 2026_09_08_000001 and 2026_09_11_000003/000006, rather than
 * silently corrupting or deduplicating data.
 */
return new class extends Migration
{
    private string $constraint = 'uq_db_roles_store_role_name';

    public function up(): void
    {
        // 1. Pre-existing per-store duplicate detection — abort loudly.
        $duplicates = DB::table('db_roles')
            ->select('store_id', 'role_name', DB::raw('COUNT(*) as cnt'))
            ->whereNotNull('role_name')
            ->where('role_name', '!=', '')
            ->groupBy('store_id', 'role_name')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $details = $duplicates
                ->map(fn ($r) => "store_id={$r->store_id} role_name='{$r->role_name}' count={$r->cnt}")
                ->implode('; ');

            throw new \RuntimeException(
                "make_role_name_unique_per_store aborted: duplicate (store_id, role_name) rows found in db_roles. " .
                "Resolve them before re-running: {$details}"
            );
        }

        // 2. Add the composite per-store unique constraint.
        Schema::table('db_roles', function (Blueprint $table) {
            $table->unique(['store_id', 'role_name'], $this->constraint);
        });
    }

    public function down(): void
    {
        Schema::table('db_roles', function (Blueprint $table) {
            $table->dropUnique($this->constraint);
        });
    }
};
