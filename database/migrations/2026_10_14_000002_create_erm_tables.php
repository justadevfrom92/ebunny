<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bunkhouse (employee relationship management): reviews, one-on-one check-ins,
 * training, kudos, time off, HR cases and onboarding/offboarding checklists for
 * the people in Sheriff → Users (employees and 3rd-party company users).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erm_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 30);            // Annual, Mid-year, 90-day, Contractor check-in
            $table->string('period', 30);          // 2026 H2
            $table->string('status', 20)->default('scheduled');   // scheduled, self, manager, complete
            $table->date('due_on');
            $table->unsignedTinyInteger('rating')->nullable();     // 1-5
            $table->text('strengths')->nullable();
            $table->text('growth')->nullable();
            $table->text('goals')->nullable();
            $table->text('self_review')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('erm_checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('held_on');
            $table->unsignedTinyInteger('mood')->nullable();       // 1-5: how they're feeling about work
            $table->text('topics')->nullable();
            $table->text('action_items')->nullable();
            $table->timestamps();
        });

        Schema::create('erm_courses', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 40);        // Compliance, Customer Care, Systems, Product, Safety, Leadership
            $table->json('required_for')->nullable();             // departments, "all" or "external"; empty = optional
            $table->decimal('hours', 5, 2)->default(1);
            $table->unsignedSmallInteger('renew_months')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('erm_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('erm_courses')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('assigned_on');
            $table->date('due_on');
            $table->date('completed_on')->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->timestamps();
            $table->unique(['course_id', 'user_id', 'assigned_on']);
        });

        Schema::create('erm_kudos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('to_id')->constrained('users')->cascadeOnDelete();
            $table->string('value', 40);           // the company value it shows
            $table->string('message', 500);
            $table->timestamps();
        });

        Schema::create('erm_time_off', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);            // PTO, Sick, Jury Duty, Bereavement, Unpaid, Volunteer
            $table->date('starts_on');
            $table->date('ends_on');
            $table->decimal('hours', 6, 2);
            $table->string('status', 20)->default('requested');   // requested, approved, denied
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('note', 250)->nullable();
            $table->timestamps();
        });

        Schema::create('erm_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // who it's about
            $table->foreignId('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 40);            // Concern, Coaching, Accommodation, Policy Question, Conflict, Performance Plan, Pay or Benefits
            $table->string('subject', 200);
            $table->string('priority', 10)->default('Medium');
            $table->string('status', 20)->default('open');        // open, in_progress, closed
            $table->text('resolution')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('erm_case_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('erm_cases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('erm_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();          // the person joining or leaving
            $table->string('kind', 12);            // onboarding, offboarding
            $table->string('task', 200);
            $table->string('team', 30);            // HR, IT, Manager, Finance, Facilities
            $table->unsignedSmallInteger('position')->default(0);
            $table->date('due_on')->nullable();
            $table->timestamp('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['erm_checklist_items', 'erm_case_updates', 'erm_cases', 'erm_time_off', 'erm_kudos', 'erm_enrollments', 'erm_courses', 'erm_checkins', 'erm_reviews'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
