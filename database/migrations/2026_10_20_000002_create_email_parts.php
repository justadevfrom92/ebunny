<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Email headers and footers (Rodeo → Headers & Footers); each template picks one of each, or the default. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_parts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);              // header | footer
            $table->string('name', 100);
            $table->text('html');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->unique(['kind', 'name']);
        });
        Schema::table('email_templates', function (Blueprint $table) {
            $table->foreignId('email_header_id')->nullable()->constrained('email_parts')->nullOnDelete();
            $table->foreignId('email_footer_id')->nullable()->constrained('email_parts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('email_header_id');
            $table->dropConstrainedForeignId('email_footer_id');
        });
        Schema::dropIfExists('email_parts');
    }
};
