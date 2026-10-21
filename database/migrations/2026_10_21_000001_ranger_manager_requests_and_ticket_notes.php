<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ranger: an agent asking for a manager on a ticket, and internal notes saved as account Notes linked to the ticket. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->timestamp('manager_requested_at')->nullable()->index();
            $table->foreignId('manager_requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('manager_request_note', 500)->nullable();
        });
        Schema::table('notes', function (Blueprint $table) {
            $table->foreignId('support_ticket_id')->nullable()->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('notes', fn (Blueprint $table) => $table->dropConstrainedForeignId('support_ticket_id'));
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_requested_by');
            $table->dropColumn(['manager_requested_at', 'manager_request_note']);
        });
    }
};
