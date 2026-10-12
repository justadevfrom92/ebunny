<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Payments a customer set up in My Account to go through at a date and time they picked. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_method_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamp('pay_at')->index();
            $table->string('status')->default('scheduled');   // scheduled | paid | canceled | failed
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_payments');
    }
};
