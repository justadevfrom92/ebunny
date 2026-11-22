<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A change on a Friends & Family account waiting for a Customer Service Lead (Corral → Lead Approvals). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('action', 60);                       // an account action key, or ledger-delete
            $table->json('input')->nullable();                  // what was filled in on the action's form
            $table->foreignId('ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note', 500)->nullable();            // why, from the person asking
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending');   // pending | approved | denied
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->string('result', 500)->nullable();          // what happened when it was approved
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_approvals');
    }
};
