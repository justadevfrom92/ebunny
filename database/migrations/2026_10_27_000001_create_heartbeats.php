<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The heartbeat: "pulses" from the parts that run on their own (the scheduler, the queue worker) and one row per
 * health check (every minute), with each check's result, so Sheriff → Heartbeat can show now and the last 24 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeat_pulses', function (Blueprint $table) {
            $table->string('key', 40)->primary();      // scheduler | queue
            $table->timestamp('beat_at');
            $table->json('meta')->nullable();
        });
        Schema::create('heartbeats', function (Blueprint $table) {
            $table->id();
            $table->timestamp('checked_at')->index();
            $table->string('status', 10);                // ok | warn | bad
            $table->json('results');                     // [key => [state, detail]]
            $table->unsignedInteger('duration_ms')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeats');
        Schema::dropIfExists('heartbeat_pulses');
    }
};
