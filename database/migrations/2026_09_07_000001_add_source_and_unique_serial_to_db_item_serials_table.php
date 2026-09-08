<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce per-item serial number uniqueness across every entry point that
     * creates DbItemSerial rows (Add Item, Edit Item, New/Edit Purchase,
     * Purchase Quick-Add Item, Stock Adjustment).
     *
     * Two changes:
     *  1. Add a `source` column recording which entry point created each row
     *     (traceability only — e.g. 'item_add', 'purchase', 'purchase_edit',
     *     'stock_adjustment'). Uniqueness is enforced independent of source.
     *  2. Add a UNIQUE constraint on (item_id, serial_number). A serial may
     *     legitimately exist for DIFFERENT items, but never twice for the SAME
     *     item — even if one of the rows is Sold/Returned (status is a property
     *     of the single per-item serial row; status transitions UPDATE the same
     *     row and never INSERT a duplicate, so they are unaffected).
     *
     * The database is freshly created for this rollout, so there is no legacy
     * data to dedupe before adding the constraint.
     */
    public function up(): void
    {
        Schema::table('db_item_serials', function (Blueprint $table) {
            // Traceability: which UI/entry point created this serial row.
            $table->string('source', 50)->nullable()->after('serial_number');
        });

        // Fresh DB — no duplicates exist. Add the composite unique index directly.
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->unique(['item_id', 'serial_number'], 'uq_db_item_serials_item_serial');
        });
    }

    public function down(): void
    {
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->dropUnique('uq_db_item_serials_item_serial');
            $table->dropColumn('source');
        });
    }
};
