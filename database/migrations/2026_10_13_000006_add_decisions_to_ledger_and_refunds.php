<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Caboose: who applied or rejected a credit/debit and when, and a note with each finance decision. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 250)->nullable();
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->string('decision_note', 250)->nullable();
            $table->string('check_number', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ledger_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('decided_by');
            $table->dropColumn(['decided_at', 'decision_note']);
        });
        Schema::table('refunds', function (Blueprint $table) {
            $table->dropColumn(['decision_note', 'check_number']);
        });
    }
};
