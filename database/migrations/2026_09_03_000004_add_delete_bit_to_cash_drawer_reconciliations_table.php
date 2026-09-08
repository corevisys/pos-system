<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cash_drawer_reconciliations', function (Blueprint $table) {
            if (!Schema::hasColumn('cash_drawer_reconciliations', 'delete_bit')) {
                $table->tinyInteger('delete_bit')->default(0)->index()->after('notes');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_drawer_reconciliations', function (Blueprint $table) {
            if (Schema::hasColumn('cash_drawer_reconciliations', 'delete_bit')) {
                $table->dropColumn('delete_bit');
            }
        });
    }
};
