<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Each click on a link in an email or campaign, for Rodeo's click heat maps. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('link_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_log_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('email_template_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('link', 40);          // which link: logo, cta, body-1, footer-account…
            $table->string('url', 500)->nullable();
            $table->string('device', 20)->nullable();
            $table->timestamp('clicked_at')->index();
            $table->index(['email_template_id', 'link']);
            $table->index(['campaign_id', 'link']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_clicks');
    }
};
