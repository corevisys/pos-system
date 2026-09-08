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
            $table->unsignedBigInteger('stocktransfer_id')->nullable()->after('adjustment_id');
            $table->index('stocktransfer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->dropIndex(['stocktransfer_id']);
            $table->dropColumn('stocktransfer_id');
        });
    }
};
