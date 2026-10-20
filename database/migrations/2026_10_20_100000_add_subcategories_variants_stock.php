<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Subcategory: a category can have a parent
        Schema::table('categories', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('slug')
                ->constrained('categories')->restrictOnDelete();
            $table->index(['parent_id', 'sort_order']);
        });

        // Product: sku, variants flag, stock
        Schema::table('products', function (Blueprint $table) {
            $table->string('sku', 64)->nullable()->unique()->after('slug');
            $table->boolean('has_variants')->default(false)->after('discount_price');
            $table->unsignedInteger('stock')->default(0)->after('has_variants');
        });

        // Existing products start with stock 10 (admin will fix later)
        DB::table('products')->update(['stock' => 10]);

        // Options (Size, Color ...) of a product
        Schema::create('product_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('name', 50);
            $table->json('choices');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'name']);
        });

        // Variants (one combination = one row)
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku', 64)->nullable()->unique();
            $table->json('combination');
            $table->string('combination_key');
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('discount_price', 10, 2)->nullable();
            $table->unsignedInteger('stock')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'combination_key']);
        });

        // An image can be tied to one option value (e.g. Color = Gold)
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('option_name', 50)->nullable()->after('path');
            $table->string('option_value', 100)->nullable()->after('option_name');
        });

        // Order item keeps a snapshot of the variant
        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')
                ->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label')->nullable()->after('product_name');
            $table->string('sku', 64)->nullable()->after('variant_label');
        });

        // Was stock already taken for this order? (old orders = false)
        Schema::table('orders', function (Blueprint $table) {
            $table->boolean('stock_deducted')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('stock_deducted');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn(['variant_label', 'sku']);
        });

        Schema::table('product_images', function (Blueprint $table) {
            $table->dropColumn(['option_name', 'option_value']);
        });

        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_options');

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['sku']);
            $table->dropColumn(['sku', 'has_variants', 'stock']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['parent_id', 'sort_order']);
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};