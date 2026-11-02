<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Rodeo → PDFs: headers and footers PDF templates pick (like email Headers & Footers), and test PDFs sent by email
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_parts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 10);                      // header | footer
            $table->string('name', 100);
            $table->string('title', 120)->nullable();        // header: the big text in the band (fill-ins work)
            $table->string('subtitle', 160)->nullable();     // header: the line under it (blank = the PDF's title)
            $table->string('color', 10)->default('navy');    // header band: navy | cyan | white
            $table->text('body')->nullable();                // footer: small print on every page
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
        Schema::table('pdf_templates', function (Blueprint $table) {
            $table->foreignId('pdf_header_id')->nullable()->constrained('pdf_parts')->nullOnDelete();
            $table->foreignId('pdf_footer_id')->nullable()->constrained('pdf_parts')->nullOnDelete();
        });
        Schema::table('pdf_generations', fn (Blueprint $table) => $table->json('header')->nullable());   // the header as it was when made
        Schema::create('pdf_test_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();   // whose details filled it in
            $table->string('recipients', 600);
            $table->string('status', 10);                    // sent | failed
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_test_sends');
        Schema::table('pdf_generations', fn (Blueprint $table) => $table->dropColumn('header'));
        Schema::table('pdf_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pdf_header_id');
            $table->dropConstrainedForeignId('pdf_footer_id');
        });
        Schema::dropIfExists('pdf_parts');
    }
};
