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
        Schema::table('db_stocktransfer', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('to_store_id');
            $table->index('reference_no');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_stocktransfer', function (Blueprint $table) {
            $table->dropIndex(['reference_no']);
            $table->dropColumn('reference_no');
        });
    }
};
