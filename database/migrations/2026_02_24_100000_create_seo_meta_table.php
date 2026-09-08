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
        Schema::create('seo_meta', function (Blueprint $table) {
            $table->id();
            $table->string('page_key')->unique()->comment('Identifier like home, privacy, terms, login, register');
            $table->string('title');
            $table->string('meta_description', 255);
            $table->text('meta_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->string('twitter_card')->default('summary_large_image');
            $table->string('canonical_url')->nullable();
            $table->string('schema_type')->default('SoftwareApplication');
            $table->json('schema_json')->nullable();
            $table->boolean('is_indexable')->default(true);
            $table->string('changefreq', 20)->default('weekly');
            $table->decimal('priority', 2, 1)->default(0.8);
            $table->timestamps();

            $table->index('is_indexable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seo_meta');
    }
};
