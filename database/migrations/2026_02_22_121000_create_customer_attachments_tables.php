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
            $table->string('customer_type')->default('regular')->after('customer_name');
        });

        Schema::create('customer_guardians', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('customer_id')->constrained('db_customers')->cascadeOnDelete();
            $blueprint->string('name')->nullable();
            $blueprint->string('relationship')->nullable();
            $blueprint->string('mobile')->nullable();
            $blueprint->string('nid_front')->nullable();
            $blueprint->string('nid_back')->nullable();
            $blueprint->string('photo')->nullable();
            $blueprint->timestamps();
        });

        Schema::create('customer_guarantors', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('customer_id')->constrained('db_customers')->cascadeOnDelete();
            $blueprint->string('name')->nullable();
            $blueprint->string('father_name')->nullable();
            $blueprint->text('address')->nullable();
            $blueprint->string('mobile')->nullable();
            $blueprint->string('occupation')->nullable();
            $blueprint->decimal('monthly_income', 12, 2)->nullable();
            $blueprint->string('photo')->nullable();
            $blueprint->string('nid_front')->nullable();
            $blueprint->string('nid_back')->nullable();
            $blueprint->string('job_id')->nullable();
            $blueprint->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_guarantors');
        Schema::dropIfExists('customer_guardians');
        Schema::table('db_customers', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });
    }
};
