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
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->unsignedBigInteger('adjustment_id')->nullable()->after('sale_id');
            $table->index('adjustment_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->dropIndex(['adjustment_id']);
            $table->dropColumn('adjustment_id');
        });
    }
};
