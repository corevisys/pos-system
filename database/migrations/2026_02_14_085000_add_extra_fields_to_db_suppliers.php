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
        Schema::table('db_suppliers', function (Blueprint $table) {
            $table->integer('delete_bit')->default(0)->after('status');
            $table->string('location_link')->nullable()->after('address');
            $table->string('attachment_1')->nullable()->after('location_link');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_suppliers', function (Blueprint $table) {
            $table->dropColumn(['delete_bit', 'location_link', 'attachment_1']);
            $table->dropSoftDeletes();
        });
    }
};
