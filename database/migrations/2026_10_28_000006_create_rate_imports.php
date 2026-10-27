<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Astro → Update Rates → Upload: a rate file read in the background. Its changes wait here until someone
 * confirms them (then they become rates with an effective date) or discards them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rate_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->string('path');
            $table->string('status', 20)->default('queued');   // queued | processing | ready | failed | applied | discarded
            $table->unsignedInteger('rows')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->json('changes')->nullable();                 // [{plan_id, market_id, plan, market, old, new}]
            $table->json('problems')->nullable();                // [{line, message}]
            $table->text('error')->nullable();
            $table->date('effective_on')->nullable();
            $table->foreignId('applied_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rate_imports');
    }
};
