<?php

namespace Tests\Feature;

use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\Note;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\SurveyEmails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The automatic 90-day check-in and farewell survey emails, and notes from any rep on any ticket. */
class SurveyEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_ninety_day_and_farewell_emails_go_out_once(): void
    {
        $new = Customer::whereNotNull('email')->where('status', 'Good - On Flow')->firstOrFail();
        $new->forceFill(['actual_start' => today()->subDays(92)->toDateString()])->saveQuietly();
        ContactLog::where('customer_id', $new->id)->where('template', 'Survey - 90 Day Check-In')->delete();
        $leaving = Customer::whereNotNull('email')->where('status', 'Good - On Flow')->where('id', '!=', $new->id)->firstOrFail();
        ContactLog::where('customer_id', $leaving->id)->where('template', 'Survey - Farewell')->delete();
        $leaving->update(['status' => 'Moved Out']);

        $this->assertTrue(SurveyEmails::due('90_day')->whereKey($new->id)->exists());
        $this->assertTrue(SurveyEmails::due('farewell')->whereKey($leaving->id)->exists());
        $this->artisan('et:survey-emails')->assertSuccessful();
        $log = ContactLog::where('customer_id', $new->id)->where('template', 'Survey - 90 Day Check-In')->firstOrFail();
        $this->assertStringContainsString('/survey/90-day-check-in', $log->body);
        $this->assertTrue(ContactLog::where('customer_id', $leaving->id)->where('template', 'Survey - Farewell')->exists());

        // Only once each
        $this->artisan('et:survey-emails')->assertSuccessful();
        $this->assertSame(1, ContactLog::where('customer_id', $new->id)->where('template', 'Survey - 90 Day Check-In')->count());

        // Rodeo → Survey Emails
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail(), 'web');
        $this->get(route('rodeo.survey-emails'))->assertOk()->assertSee('90-Day Check-In')->assertSee('Farewell Survey')->assertSee('Response Rate')->assertSee($new->account);
        $this->get('/survey/farewell')->assertOk()->assertSee('What is the main reason you left?');
    }

    public function test_any_rep_can_add_a_note_to_any_ticket(): void
    {
        $t = SupportTicket::active()->whereNotNull('assigned_to')->whereNotNull('customer_id')->firstOrFail();
        $rep = RangerAgent::with('user')->where('level', 'agent')->where('active', true)->where('user_id', '!=', $t->assigned_to)->firstOrFail()->user;
        $this->actingAs($rep, 'web');
        $this->get(route('ranger.tickets.show', $t))->assertSee('Add Note');
        $this->post(route('ranger.tickets.notes', $t), ['category' => 'Ticket', 'action' => 'Internal Note', 'priority' => 'Low', 'body' => 'Note from another rep'])->assertRedirect();
        $this->assertTrue(Note::where('support_ticket_id', $t->id)->where('user_id', $rep->id)->exists());
    }
}
