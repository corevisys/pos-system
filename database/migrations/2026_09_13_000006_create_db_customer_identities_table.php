<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 5.1 — shared-identity-only customer sharing.
 *
 * A person's IDENTITY is recognised across all branches (so the same phone is never
 * re-entered as a new person), while each store keeps its OWN store-scoped
 * db_customers row (dues, loyalty, sales history stay per store and do NOT carry
 * over).
 *
 * db_customer_identities is deliberately NOT StoreScoped: it is the one place
 * identity data crosses a store boundary, and is only ever read through
 * CustomerIdentityResolver (a sanctioned cross-store read, like
 * CodeGeneratorService's per-store generation and the Owner reports).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('db_customer_identities')) {
            Schema::create('db_customer_identities', function (Blueprint $table) {
                $table->id();
                // Natural key for "same person". Unique GLOBALLY (not per store).
                $table->string('phone')->unique();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('nid')->nullable();
                $table->timestamps();
            });
        }

        // Link each store-scoped customer row to the shared identity.
        //
        // NOTE: nullable (not NOT NULL). No production data exists, but the test
        // suite and seeders create db_customers rows directly; a NOT NULL column
        // would break every such fixture. Real application creation paths populate
        // it via CustomerIdentityResolver, so identity sharing still holds. A
        // DB-level FK constraint is intentionally omitted because SQLite cannot add
        // a foreign key to an EXISTING table via ALTER; the column is indexed and
        // integrity is maintained by the resolver (identities are never deleted).
        if (!Schema::hasColumn('db_customers', 'customer_identity_id')) {
            Schema::table('db_customers', function (Blueprint $table) {
                $table->unsignedBigInteger('customer_identity_id')->nullable()->after('customer_code');
                $table->index('customer_identity_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('db_customers', 'customer_identity_id')) {
            Schema::table('db_customers', function (Blueprint $table) {
                $table->dropIndex(['customer_identity_id']);
                $table->dropColumn('customer_identity_id');
            });
        }

        Schema::dropIfExists('db_customer_identities');
    }
};
