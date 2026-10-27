<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PDF templates (Rodeo → PDFs): letters and notices made into PDFs for an account, like email templates
 * are for emails. Each PDF made keeps a copy of the wording it was made from.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pdf_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('category')->nullable();
            $table->string('title');                     // big title on the first page
            $table->longText('body');                    // simple markup, see App\Support\PdfTemplateRenderer
            $table->text('footer')->nullable();          // small print on every page
            $table->boolean('show_address')->default(true);
            $table->string('status')->default('Active'); // Active | Draft
            $table->timestamps();
        });
        Schema::create('pdf_generations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pdf_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->unsignedSmallInteger('pages')->default(1);
            $table->unsignedInteger('size')->default(0);
            $table->string('title');
            $table->longText('body');
            $table->text('footer')->nullable();
            $table->boolean('show_address')->default(true);
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pdf_generations');
        Schema::dropIfExists('pdf_templates');
    }
};
