<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Every admin sign-in (My Profile → Signed In On): where, which browser, when it started and how it ended. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_sign_ins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('impersonator_id')->nullable()->constrained('users')->nullOnDelete();   // someone using Log in as
            $table->string('session_key', 64)->nullable()->index();   // sha256 of the session id while it's signed in
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('how', 30)->default('password');          // password, test, log_in_as, back_from_log_in_as
            $table->timestamp('signed_in_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('ended_reason', 120)->nullable();           // Signed out, Signed out from another device, Blocked, Reported…
            $table->timestamps();
            $table->index(['user_id', 'signed_in_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sign_ins');
    }
};
