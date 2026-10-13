<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Page components can fill in their content block's {{variables}} (heading, text, city…). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_components', fn (Blueprint $t) => $t->json('vars')->nullable());
    }

    public function down(): void
    {
        Schema::table('page_components', fn (Blueprint $t) => $t->dropColumn('vars'));
    }
};
