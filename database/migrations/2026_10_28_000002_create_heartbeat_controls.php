<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Heartbeat controls: which parts are taken down (paused) on purpose, and a log of every control used on a
 * check (restart, reboot, take down, bring up, clear, retry…), who used it and what came back.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heartbeat_controls', function (Blueprint $table) {
            $table->string('key', 40)->primary();       // a Health::CHECKS key
            $table->string('state', 10)->default('up'); // up | down
            $table->string('reason', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('changed_at')->nullable();
        });
        Schema::create('heartbeat_actions', function (Blueprint $table) {
            $table->id();
            $table->string('check_key', 40)->index();
            $table->string('action', 40);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('ok')->default(true);
            $table->text('output')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heartbeat_actions');
        Schema::dropIfExists('heartbeat_controls');
    }
};
