<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('media');
            $table->string('poster')->nullable();
            $table->string('mobile_media')->nullable();
            $table->string('title', 120)->nullable();
            $table->string('text', 300)->nullable();
            $table->string('button_text', 40)->nullable();
            $table->string('link_url', 500)->nullable();
            $table->string('text_color', 10)->default('light');
            $table->string('text_align', 10)->default('left');
            $table->json('placements');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });

        // Where a campaign banner is shown (existing campaigns get the default place)
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('banner_slot', 40)->default('home_after_categories')->after('show_countdown');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('banner_slot');
        });

        Schema::dropIfExists('ads');
    }
};