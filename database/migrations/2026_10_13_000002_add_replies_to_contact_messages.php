<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Web Messages: the account the message matches, and the reply an agent sent. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reply')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->foreignId('replied_by')->nullable()->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', fn (Blueprint $t) => $t->dropConstrainedForeignId('customer_id') && $t->dropConstrainedForeignId('replied_by') && $t->dropColumn(['reply', 'replied_at']));
    }
};
