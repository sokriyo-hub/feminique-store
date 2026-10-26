<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('return_reason', 30)->nullable()->after('status');
            $table->string('return_note', 500)->nullable()->after('return_reason');
            $table->timestamp('returned_at')->nullable()->after('return_note');
        });

        // Orders that were already Returned: the return date = the last change
        DB::table('orders')->where('status', 'returned')->update(['returned_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['return_reason', 'return_note', 'returned_at']);
        });
    }
};