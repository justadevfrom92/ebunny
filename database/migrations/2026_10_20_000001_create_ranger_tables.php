<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranger: support tickets customers open from My Account, their conversation, who works them
 * (in-office and 3rd-party team members), managers joining in, and the customer's rating.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranger_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('team', 20);                  // office | third_party
            $table->string('level', 20)->default('agent'); // agent | manager
            $table->boolean('active')->default(true);
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 150);
            $table->string('email', 200);
            $table->string('subject', 200);
            $table->string('topic', 40);
            $table->string('channel', 30)->default('My Account');
            $table->string('status', 20)->default('new')->index();     // new | open | waiting | solved | closed
            $table->string('priority', 20)->default('normal');         // low | normal | high | urgent
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('first_response_at')->nullable();
            $table->timestamp('solved_at')->nullable();
            $table->string('rating', 10)->nullable();                  // good | bad
            $table->string('rating_comment', 1000)->nullable();
            $table->timestamp('rated_at')->nullable();
            $table->timestamp('last_activity_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('support_ticket_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('author', 20);                // customer | agent | note (internal) | system
            $table->text('body');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('support_ticket_watchers', function (Blueprint $table) {
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('joined_at')->nullable();
            $table->primary(['support_ticket_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_watchers');
        Schema::dropIfExists('support_ticket_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('ranger_agents');
    }
};
