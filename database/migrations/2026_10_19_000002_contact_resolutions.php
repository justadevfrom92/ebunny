<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Resolution status (Resolved, Incomplete…) on calls, emails, texts, web chats and website messages. */
return new class extends Migration
{
    private array $tables = ['phonecalls', 'contact_logs', 'ai_conversations', 'contact_messages'];

    public function up(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->string('resolution', 30)->nullable()->index();
                $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $t) {
            Schema::table($t, function (Blueprint $table) {
                $table->dropConstrainedForeignId('resolved_by');
                $table->dropColumn(['resolution', 'resolved_at']);
            });
        }
    }
};
