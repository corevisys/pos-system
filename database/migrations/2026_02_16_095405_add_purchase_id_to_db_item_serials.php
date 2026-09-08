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
            $table->unsignedBigInteger('purchase_id')->nullable()->after('store_id');
            $table->foreign('purchase_id')->references('id')->on('db_purchase')->onDelete('cascade');
            $table->index(['item_id', 'purchase_id']); // Index for faster lookups in invoice
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_item_serials', function (Blueprint $table) {
            $table->dropForeign(['purchase_id']);
            $table->dropColumn('purchase_id');
        });
    }
};
