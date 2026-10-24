<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedTinyInteger('discount_percent');
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->boolean('is_active')->default(true);
            $table->boolean('all_products')->default(true);
            $table->boolean('free_delivery')->default(false);
            $table->boolean('show_banner')->default(true);
            $table->boolean('show_countdown')->default(true);
            $table->string('banner_title', 120)->nullable();
            $table->string('banner_text', 300)->nullable();
            $table->string('banner_button', 40)->nullable();
            $table->string('banner_image')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        // Which categories a campaign covers (only used when all_products = false)
        Schema::create('campaign_category', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();

            $table->primary(['campaign_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_category');
        Schema::dropIfExists('campaigns');
    }
};