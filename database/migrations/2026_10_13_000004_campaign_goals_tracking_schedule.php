<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campaigns, thought through: a goal (what success is), the product it promotes, tracking
 * (MSID and promo code), a send time, and a contact limit so customers aren't over-messaged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('goal')->default('announcement');   // renewal | product | winback | rewards | announcement
            $table->string('description', 500)->nullable();
            $table->string('product')->nullable();               // the product a "product" campaign promotes
            $table->string('msid')->nullable();
            $table->string('promo_code')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->unsignedSmallInteger('cap_days')->default(7);   // skip anyone who got a campaign in the last N days (0 = no limit)
            $table->unsignedInteger('skipped')->default(0);         // left out at send: suppressed or contacted recently
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', fn (Blueprint $t) => $t->dropColumn(['goal', 'description', 'product', 'msid', 'promo_code', 'scheduled_at', 'cap_days', 'skipped']));
    }
};
