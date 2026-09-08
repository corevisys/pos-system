<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('db_custadvance', function (Blueprint $table) {
            $table->unsignedBigInteger('account_id')->nullable()->after('payment_type');
            $table->index('account_id');
        });
    }

    public function down(): void
    {
        Schema::table('db_custadvance', function (Blueprint $table) {
            $table->dropIndex(['account_id']);
            $table->dropColumn('account_id');
        });
    }
};
