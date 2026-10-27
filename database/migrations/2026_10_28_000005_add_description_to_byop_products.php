<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The line under a Build Your Own add-on's name on the website, so products added in Astro show a card. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('byop_products', function (Blueprint $table) {
            $table->string('description', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('byop_products', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
