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
            $table->decimal('system_opening_balance', 16, 2)->default(0)->after('period_end');
            $table->decimal('opening_variance', 16, 2)->default(0)->after('opening_balance');
            $table->boolean('is_initial')->default(false)->after('opening_variance');
            $table->text('opening_notes')->nullable()->after('is_initial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_drawer_reconciliations', function (Blueprint $table) {
            $table->dropColumn(['system_opening_balance', 'opening_variance', 'is_initial', 'opening_notes']);
        });
    }
};
