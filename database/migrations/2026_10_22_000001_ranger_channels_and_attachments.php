<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ranger: a ticket entry can be a My Account message, an email or a phone call (with direction and
 * length), and messages and emails can carry attachments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_ticket_messages', function (Blueprint $table) {
            $table->string('via', 10)->default('portal');     // portal | email | phone
            $table->string('direction', 3)->nullable();        // phone: in | out
            $table->unsignedInteger('duration')->nullable();   // phone: seconds
            $table->string('contact', 200)->nullable();        // the email address or phone number used
        });

        Schema::create('support_ticket_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('support_ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('support_ticket_message_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('from_customer')->default(false);
            $table->string('name', 200);
            $table->string('path', 300);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_ticket_attachments');
        Schema::table('support_ticket_messages', fn (Blueprint $table) => $table->dropColumn(['via', 'direction', 'duration', 'contact']));
    }
};
