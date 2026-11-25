<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Your own settings in the Account app: availability (out of office), the page you start on, your email signature. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('availability', 20)->default('available');   // available | busy | out
            $table->date('out_until')->nullable();                      // back on (out of office)
            $table->string('out_note', 250)->nullable();                // shown to coworkers while you're out
            $table->string('start_page', 40)->nullable();               // an app key, "account" or null (the Admin home)
            $table->text('signature')->nullable();                      // added to emails you send customers
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['availability', 'out_until', 'out_note', 'start_page', 'signature']);
        });
    }
};
