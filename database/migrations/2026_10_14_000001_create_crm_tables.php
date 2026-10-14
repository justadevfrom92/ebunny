<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Corral → CRM: brokers and partners, leads moving through the sales pipeline, and the calls, emails, meetings and follow-ups on them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_partners', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('kind', 40);                 // Broker, Referral Partner, Property Manager, Home Builder, Realtor
            $table->string('contact_name', 120)->nullable();
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->decimal('commission_rate', 6, 4)->default(0);   // per kWh for brokers, flat % otherwise
            $table->string('commission_basis', 10)->default('kwh'); // kwh | flat
            $table->string('status', 20)->default('Active');        // Active, Paused, Ended
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('agreement_ends_on')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);                // contact person
            $table->string('company', 150)->nullable();
            $table->string('title', 100)->nullable();
            $table->string('kind', 30);                 // Residential, Small Business, Commercial, Multi-site
            $table->string('email', 200)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('city', 80)->nullable();
            $table->foreignId('market_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30);               // Website, Referral, Broker, Event, Outbound, Partner, Renewal
            $table->foreignId('partner_id')->nullable()->constrained('crm_partners')->nullOnDelete();
            $table->string('stage', 20)->default('new');
            $table->unsignedInteger('annual_kwh')->default(0);
            $table->decimal('est_value', 12, 2)->default(0);       // yearly revenue if won
            $table->unsignedSmallInteger('sites')->default(1);
            $table->string('current_provider', 80)->nullable();
            $table->date('contract_ends_on')->nullable();          // their contract with the current provider
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('next_follow_up_on')->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('lost_reason', 200)->nullable();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['stage', 'owner_id']);
        });

        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained('crm_partners')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);                 // call, email, meeting, note, task, stage
            $table->string('subject', 200);
            $table->text('body')->nullable();
            $table->date('due_on')->nullable();         // follow-ups and tasks
            $table->timestamp('done_at')->nullable();
            $table->timestamps();
            $table->index(['type', 'due_on', 'done_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
        Schema::dropIfExists('crm_leads');
        Schema::dropIfExists('crm_partners');
    }
};
