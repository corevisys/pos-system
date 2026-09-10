<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 (Warehouse rollout): introduce soft-delete on db_warehouse.
 *
 * destroy() previously performed an unconditional hard delete. db_warehouseitems
 * cascades on delete (silently destroying per-warehouse stock rows at the schema
 * level) and db_sales / db_item_serials / db_stocktransfer etc. have NO FK so they
 * were left with dangling warehouse_id values. Adding delete_bit lets destroy()
 * transition the row atomically and retain the audit trail, mirroring the
 * established Expenses / Deposits soft-delete convention.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_warehouse', function (Blueprint $table) {
            if (!Schema::hasColumn('db_warehouse', 'delete_bit')) {
                $table->integer('delete_bit')->default(0)->after('status');
                $table->index('delete_bit');
            }
        });
    }

    public function down(): void
    {
        Schema::table('db_warehouse', function (Blueprint $table) {
            if (Schema::hasColumn('db_warehouse', 'delete_bit')) {
                $table->dropIndex(['delete_bit']);
                $table->dropColumn('delete_bit');
            }
        });
    }
};
