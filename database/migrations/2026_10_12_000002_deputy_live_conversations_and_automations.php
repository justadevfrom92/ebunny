<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deputy: conversations are live (texts and the My Account chat): who is answering now (the bot,
 * waiting for a person, or a person) and when the last message came in. Automation agents run on
 * a schedule instead of talking to customers; each run is logged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->string('status')->default('closed')->index();   // bot | waiting | agent | closed
            $table->timestamp('last_message_at')->nullable();
        });
        Schema::table('ai_agents', function (Blueprint $table) {
            $table->string('schedule')->nullable();                 // cron expression, automation agents only
        });
        Schema::create('ai_automation_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ai_model_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status');                               // ok | failed
            $table->unsignedInteger('checked')->default(0);         // items looked at
            $table->unsignedInteger('fixed')->default(0);           // fixed on its own
            $table->unsignedInteger('flagged')->default(0);         // left for a person
            $table->text('summary')->nullable();
            $table->unsignedInteger('duration_sec')->default(0);
            $table->unsignedInteger('input_tokens')->default(0);
            $table->unsignedInteger('output_tokens')->default(0);
            $table->timestamp('started_at')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_automation_runs');
        Schema::table('ai_agents', fn (Blueprint $t) => $t->dropColumn('schedule'));
        Schema::table('ai_conversations', fn (Blueprint $t) => $t->dropColumn(['status', 'last_message_at']));
    }
};
