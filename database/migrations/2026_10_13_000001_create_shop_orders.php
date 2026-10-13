<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Orders placed from the Shop cart at My Account → Checkout. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();                     // SO-100245
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->json('items');                                   // [{sku, name, qty, price, monthly, stars}]
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2);
            $table->decimal('shipping', 10, 2)->default(0);
            $table->decimal('total', 10, 2);                        // due today
            $table->decimal('monthly', 10, 2)->default(0);          // protection plans added to the electricity bill
            $table->unsignedInteger('stars')->default(0);
            $table->string('pay_with');                              // card | bill
            $table->foreignId('payment_method_id')->nullable()->constrained()->nullOnDelete();
            $table->json('ship_to')->nullable();
            $table->string('status')->default('Placed');             // Placed | Shipped | Delivered | Canceled
            $table->string('payment_status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
