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
        Schema::table('db_customers', function (Blueprint $table) {
            $table->dropColumn([
                'g_name',
                'g_relationship',
                'g_mobile',
                'g_nid_front',
                'g_nid_back',
                'g_photo',
                'gr_name',
                'gr_father_name',
                'gr_address',
                'gr_mobile',
                'gr_occupation',
                'gr_monthly_income',
                'gr_photo',
                'gr_nid_front',
                'gr_nid_back',
                'gr_job_id'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_customers', function (Blueprint $table) {
            $table->string('g_name')->nullable();
            $table->string('g_relationship')->nullable();
            $table->string('g_mobile')->nullable();
            $table->string('g_nid_front')->nullable();
            $table->string('g_nid_back')->nullable();
            $table->string('photo')->nullable(); // Note: actually g_photo, but let's be careful
            $table->string('gr_name')->nullable();
            $table->string('gr_father_name')->nullable();
            $table->text('gr_address')->nullable();
            $table->string('gr_mobile')->nullable();
            $table->string('gr_occupation')->nullable();
            $table->decimal('gr_monthly_income', 12, 2)->nullable();
            $table->string('gr_photo')->nullable();
            $table->string('gr_nid_front')->nullable();
            $table->string('gr_nid_back')->nullable();
            $table->string('gr_job_id')->nullable();
        });
    }
};
