<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cookie choices from the website's cookie banner: every choice is kept (the latest one counts),
 * for signed-in My Account customers, signed-in employees and anonymous visitors alike.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookie_consents', function (Blueprint $table) {
            $table->id();
            $table->string('visitor', 40)->index();          // the browser's visitor id (et_vid cookie)
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('choice');                         // all | necessary | custom
            $table->boolean('analytics')->default(false);
            $table->boolean('marketing')->default(false);
            $table->string('policy_version', 20);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 250)->nullable();
            $table->string('page', 250)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookie_consents');
    }
};
