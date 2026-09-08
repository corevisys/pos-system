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
            $table->unsignedBigInteger('opened_by')->nullable()->after('user_id');
            $table->timestamp('opened_at')->nullable()->after('opened_by');
            $table->unsignedBigInteger('closed_by')->nullable()->after('opened_at');
            $table->timestamp('closed_at')->nullable()->after('closed_by');

            $table->foreign('opened_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('closed_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cash_drawer_reconciliations', function (Blueprint $table) {
            $table->dropForeign(['opened_by']);
            $table->dropForeign(['closed_by']);
            $table->dropColumn(['opened_by', 'opened_at', 'closed_by', 'closed_at']);
        });
    }
};
