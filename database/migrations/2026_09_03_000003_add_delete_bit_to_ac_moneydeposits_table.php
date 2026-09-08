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
        if (Schema::hasTable('ac_moneydeposits')) {
            Schema::table('ac_moneydeposits', function (Blueprint $table) {
                if (!Schema::hasColumn('ac_moneydeposits', 'delete_bit')) {
                    $table->integer('delete_bit')->default(0)->after('status')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('ac_moneydeposits')) {
            Schema::table('ac_moneydeposits', function (Blueprint $table) {
                if (Schema::hasColumn('ac_moneydeposits', 'delete_bit')) {
                    $table->dropColumn('delete_bit');
                }
            });
        }
    }
};
