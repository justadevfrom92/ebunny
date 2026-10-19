<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reports always run in the background and save a file: the format chosen (xlsx, csv, txt, json),
 * the file's size, whether it's a summary, and when the person who ran it saw the "ready" notice.
 * report_columns: the export columns for a report — model keys (e.g. customer.name) and the
 * column titles they get, in order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_runs', function (Blueprint $table) {
            $table->string('format', 8)->default('xlsx');
            $table->boolean('summary')->default(false);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamp('seen_at')->nullable();
        });
        Schema::create('report_columns', function (Blueprint $table) {
            $table->id();
            $table->string('report', 60)->unique();
            $table->json('columns');               // [{key: "customer.name", title: "Name"}, …] in running order
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_columns');
        Schema::table('report_runs', function (Blueprint $table) {
            $table->dropColumn(['format', 'summary', 'file_size', 'seen_at']);
        });
    }
};
