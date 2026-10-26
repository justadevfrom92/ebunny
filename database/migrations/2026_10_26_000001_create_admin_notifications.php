<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Notifications for admin users (the number on their avatar), and which kinds each person wants. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('title', 200);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['user_id', 'read_at']);
        });
        Schema::table('users', fn (Blueprint $table) => $table->json('notification_prefs')->nullable());
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notifications');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('notification_prefs'));
    }
};
