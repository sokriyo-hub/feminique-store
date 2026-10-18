<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('show_in_navbar')->default(false)->after('image');
        });

        // Existing categories: the first 5 start in the navbar
        $ids = DB::table('categories')->orderBy('sort_order')->orderBy('id')->limit(5)->pluck('id');
        DB::table('categories')->whereIn('id', $ids)->update(['show_in_navbar' => true]);
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('show_in_navbar');
        });
    }
};