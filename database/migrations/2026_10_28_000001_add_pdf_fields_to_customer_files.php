<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_files', function (Blueprint $table) {
            $table->timestamp('generated_at')->nullable();      // last time the PDF was (re)generated
            $table->unsignedInteger('size')->nullable();        // bytes
            $table->unsignedSmallInteger('regenerated')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('customer_files', function (Blueprint $table) {
            $table->dropColumn(['generated_at', 'size', 'regenerated']);
        });
    }
};
