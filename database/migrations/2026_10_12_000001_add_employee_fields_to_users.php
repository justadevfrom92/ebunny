<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Admin users are employees: their details, and access added or taken away for one person on top of their role. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('employee_id', 20)->nullable()->unique()->after('id');
            $table->string('title')->nullable()->after('name');
            $table->string('department')->nullable()->after('title');
            $table->foreignId('manager_id')->nullable()->after('department')->constrained('users')->nullOnDelete();
            $table->string('phone_ext', 10)->nullable();
            $table->string('location')->nullable();
            $table->date('hired_on')->nullable();
            $table->json('extra_perms')->nullable();    // apps or rights this person has beyond their role
            $table->json('denied_perms')->nullable();   // apps or rights their role has that this person may not use
            $table->string('access_note')->nullable();  // why their access differs from the role
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['employee_id', 'title', 'department', 'phone_ext', 'location', 'hired_on', 'extra_perms', 'denied_perms', 'access_note']);
        });
    }
};
