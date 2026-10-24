<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Specific products of a campaign (used together with categories when "All products" is off)
        Schema::create('campaign_product', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();

            $table->primary(['campaign_id', 'product_id']);
        });

        // A campaign banner can now sit in several places: banner_slot (one) becomes banner_slots (list)
        Schema::table('campaigns', function (Blueprint $table) {
            $table->json('banner_slots')->nullable()->after('show_countdown');
        });

        foreach (DB::table('campaigns')->get(['id', 'banner_slot']) as $row) {
            DB::table('campaigns')->where('id', $row->id)->update([
                'banner_slots' => json_encode([$row->banner_slot ?: 'home_after_categories']),
            ]);
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('banner_slot');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('banner_slot', 40)->default('home_after_categories')->after('show_countdown');
        });

        foreach (DB::table('campaigns')->get(['id', 'banner_slots']) as $row) {
            $slots = json_decode((string) $row->banner_slots, true);

            DB::table('campaigns')->where('id', $row->id)->update([
                'banner_slot' => is_array($slots) && $slots ? (string) $slots[0] : 'home_after_categories',
            ]);
        }

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('banner_slots');
        });

        Schema::dropIfExists('campaign_product');
    }
};