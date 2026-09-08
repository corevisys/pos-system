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
            // Basic Info
            $table->string('customer_id_card')->nullable()->after('customer_code');
            $table->string('father_name')->nullable()->after('customer_name');
            $table->string('mother_name')->nullable()->after('father_name');
            $table->date('dob')->nullable()->after('mother_name');
            $table->string('mobile_primary')->nullable()->after('mobile');
            $table->string('mobile_secondary')->nullable()->after('mobile_primary');
            $table->text('present_address')->nullable()->after('address');
            $table->text('permanent_address')->nullable()->after('present_address');
            $table->string('occupation')->nullable()->after('permanent_address');
            $table->decimal('monthly_income', 16, 2)->default(0)->after('occupation');
            $table->string('workplace_name')->nullable()->after('monthly_income');
            $table->text('workplace_address')->nullable()->after('workplace_name');

            // Documents
            $table->string('photo')->nullable();
            $table->string('nid_front')->nullable();
            $table->string('nid_back')->nullable();
            $table->string('job_id_card')->nullable();

            // Guardian
            $table->string('g_name')->nullable();
            $table->string('g_relationship')->nullable();
            $table->string('g_mobile')->nullable();
            $table->string('g_nid_front')->nullable();
            $table->string('g_nid_back')->nullable();
            $table->string('g_photo')->nullable();

            // Guarantor
            $table->string('gr_name')->nullable();
            $table->string('gr_father_name')->nullable();
            $table->text('gr_address')->nullable();
            $table->string('gr_mobile')->nullable();
            $table->string('gr_occupation')->nullable();
            $table->decimal('gr_monthly_income', 16, 2)->default(0);
            $table->string('gr_photo')->nullable();
            $table->string('gr_nid_front')->nullable();
            $table->string('gr_nid_back')->nullable();
            $table->string('gr_job_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_customers', function (Blueprint $table) {
            $table->dropColumn([
                'customer_id_card', 'father_name', 'mother_name', 'dob', 'mobile_primary', 'mobile_secondary',
                'present_address', 'permanent_address', 'occupation', 'monthly_income', 'workplace_name', 'workplace_address',
                'photo', 'nid_front', 'nid_back', 'job_id_card',
                'g_name', 'g_relationship', 'g_mobile', 'g_nid_front', 'g_nid_back', 'g_photo',
                'gr_name', 'gr_father_name', 'gr_address', 'gr_mobile', 'gr_occupation', 'gr_monthly_income', 'gr_photo', 'gr_nid_front', 'gr_nid_back', 'gr_job_id'
            ]);
        });
    }
};
