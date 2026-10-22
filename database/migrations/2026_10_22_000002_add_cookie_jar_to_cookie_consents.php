<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What cookies the visitor's browser actually had (names and masked values), kept up to date as they browse. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cookie_consents', function (Blueprint $table) {
            $table->json('cookie_jar')->nullable();
            $table->timestamp('cookies_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cookie_consents', fn (Blueprint $table) => $table->dropColumn(['cookie_jar', 'cookies_seen_at']));
    }
};
