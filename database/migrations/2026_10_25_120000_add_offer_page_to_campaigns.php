<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('slug', 120)->nullable()->unique()->after('name');
            $table->boolean('offer_in_menu')->default(false)->after('free_delivery');
            $table->string('offer_title', 60)->nullable()->after('offer_in_menu');
            $table->string('banner_link', 255)->nullable()->after('banner_button');
        });

        // Old campaigns get a link name too
        $used = [];

        foreach (DB::table('campaigns')->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::limit(Str::slug((string) $row->name) ?: 'offer', 80, '');
            $slug = $base;
            $n = 2;

            while (isset($used[$slug])) {
                $slug = $base . '-' . $n++;
            }

            $used[$slug] = true;
            DB::table('campaigns')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'offer_in_menu', 'offer_title', 'banner_link']);
        });
    }
};