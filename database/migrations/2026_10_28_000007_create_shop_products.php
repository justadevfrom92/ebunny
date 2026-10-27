<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop products move from config/shop.php into the database so Corral → Products can add and edit them.
 * The table starts with the same products. Orders remember which employee placed them by phone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 20)->unique();
            $table->string('name');
            $table->string('cat', 30);
            $table->decimal('price', 10, 2);
            $table->decimal('was', 10, 2)->nullable();      // sale: the price before
            $table->decimal('rating', 2, 1)->default(0);
            $table->unsignedInteger('reviews')->default(0);
            $table->unsignedInteger('stars')->default(0);   // Rewards stars each one earns
            $table->boolean('stock')->default(true);
            $table->string('badge', 40)->nullable();
            $table->string('icon', 20)->default('plug');
            $table->string('blurb', 300)->nullable();
            $table->boolean('active')->default(true);       // shown in the Shop
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();
        });
        $now = now();
        foreach (config('shop.products', []) as $i => $p) {
            [$sku, $name, $cat, $price, $was, $rating, $reviews, $stars, $stock, $badge, $icon, $blurb] = $p;
            DB::table('shop_products')->insert(compact('sku', 'name', 'cat', 'price', 'was', 'rating', 'reviews', 'stars', 'stock', 'badge', 'icon', 'blurb')
                + ['active' => true, 'position' => $i, 'created_at' => $now, 'updated_at' => $now]);
        }
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();   // an employee, by phone
        });
    }

    public function down(): void
    {
        Schema::table('shop_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('placed_by');
        });
        Schema::dropIfExists('shop_products');
    }
};
