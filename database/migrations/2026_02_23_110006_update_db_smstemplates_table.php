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
        Schema::table('db_smstemplates', function (Blueprint $table) {
            $table->string('category')->nullable()->after('template_name'); // Promotional, Transactional, EMI, etc.
            $table->json('variables_used')->nullable()->after('content');
            $table->text('default_footer')->nullable()->after('variables_used');
            $table->string('language', 10)->default('en')->after('default_footer'); // en, bn
            $table->string('message_type')->default('transactional')->after('language'); // promo, transactional
            $table->softDeletes()->after('updated_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('db_smstemplates', function (Blueprint $table) {
            $table->dropColumn(['category', 'variables_used', 'default_footer', 'language', 'message_type', 'deleted_at']);
        });
    }
};
