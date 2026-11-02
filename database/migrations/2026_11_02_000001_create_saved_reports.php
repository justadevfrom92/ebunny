<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Walker → Saved Reports: a report with its settings, bookmarked to open or rerun in one click
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('report', 60);               // key in config/walker.php
            $table->json('params')->nullable();
            $table->string('format', 10)->default('xlsx');
            $table->boolean('summary')->default(false);
            $table->boolean('shared')->default(false);  // everyone with Walker sees it, not just you
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
        });
        Schema::table('report_runs', fn (Blueprint $table) => $table->foreignId('saved_report_id')->nullable()->constrained()->nullOnDelete());
    }

    public function down(): void
    {
        Schema::table('report_runs', fn (Blueprint $table) => $table->dropConstrainedForeignId('saved_report_id'));
        Schema::dropIfExists('saved_reports');
    }
};
