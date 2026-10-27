<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** What each API call sent and what came back, so its page can show both. Secrets are blanked before saving. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->string('method', 10)->nullable();
            $table->string('endpoint', 500)->nullable();
            $table->longText('request')->nullable();    // {headers, body}
            $table->longText('response')->nullable();   // {status, headers, body}
        });
    }

    public function down(): void
    {
        Schema::table('api_logs', function (Blueprint $table) {
            $table->dropColumn(['method', 'endpoint', 'request', 'response']);
        });
    }
};
