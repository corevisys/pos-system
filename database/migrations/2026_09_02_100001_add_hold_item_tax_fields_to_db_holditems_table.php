<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist per-line tax percent + is_serialized on db_holditems so a resumed
     * cart can restore tax (A3) and know which lines require serial re-selection
     * (A2). NOTE: db_holditems already has a tax_amt column from the original
     * create migration — only the genuinely new columns are added here.
     */
    public function up(): void
    {
        Schema::table('db_holditems', function (Blueprint $table) {
            $table->decimal('tax_percent', 16, 2)->default(0)->after('price_per_unit');
            $table->integer('is_serialized')->default(0)->after('tax_percent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_holditems', function (Blueprint $table) {
            $table->dropColumn(['tax_percent', 'is_serialized']);
        });
    }
};
