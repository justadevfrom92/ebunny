<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Note;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Ranger: My Account tickets, replies and internal notes, managers taking over / joining in, the team, and approval %. */
class RangerTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_pages_and_approval_by_team(): void
    {
        $this->actingAs($this->user('csr@example.com'));
        // Agents' dashboard has no team approval; managers see it
        $this->get(route('ranger.dashboard'))->assertOk()->assertDontSee('Approval — In-Office')->assertDontSee('My Tickets');
        $this->actingAs($this->user('lead@example.com'))->get(route('ranger.dashboard'))->assertSee('Approval — In-Office')->assertSee('Approval — 3rd Party');
        $this->actingAs($this->user('csr@example.com'));
        foreach (['active', 'mine', 'unassigned', 'watching', 'done', 'all'] as $view) {
            $this->get(route('ranger.tickets', ['view' => $view]))->assertOk();
        }
        $this->get(route('ranger.team'))->assertOk()->assertSee('Devon Supervisor')->assertSee('3rd Party');
        $this->get(route('ranger.satisfaction'))->assertOk()->assertSee('By Team Member')->assertSee('Week by Week')->assertSee('How Approval Works')->assertSee('👎');
        // No search section: the tickets show straight away
        $this->get(route('ranger.tickets', ['view' => 'unassigned']))->assertSee('Unassigned Tickets')->assertDontSee('name="q"', false)->assertSee('RT-10001');
        // Each team member has their own page
        $agent = RangerAgent::where('level', 'agent')->firstOrFail();
        $this->get(route('ranger.team'))->assertSee(route('ranger.team.show', $agent));
        $this->get(route('ranger.team.show', $agent))->assertOk()->assertSee($agent->user->name)->assertSee('Approval by Week')->assertSee('Open Tickets');
        // Ranger is for support staff: pricing can't open it
        $this->actingAs($this->user('pricing@example.com'))->get(route('ranger.dashboard'))->assertRedirect();
    }

    public function test_customer_ticket_reply_note_solve_and_rating(): void
    {
        $c = Customer::whereNotNull('password')->firstOrFail();
        $this->actingAs($c, 'customer');
        $this->get(route('myaccount.support'))->assertOk()->assertSee('New Ticket');
        $this->post(route('myaccount.support.store'), ['topic' => 'billing', 'subject' => 'Test question about my bill', 'body' => 'Why is it so high?'])->assertRedirect();
        $t = SupportTicket::latest('id')->firstOrFail();
        $this->assertSame(['new', null, $c->id], [$t->status, $t->assigned_to, $t->customer_id]);

        // An agent answers: the reply shows in My Account, the internal note doesn't; answering first makes it theirs
        $csr = $this->user('csr@example.com');
        $this->actingAs($csr, 'web')->get(route('ranger.tickets', ['view' => 'unassigned']))->assertSee('Test question about my bill');
        // Notes come from the Notes section (category, action, priority), not the reply box
        $this->get(route('ranger.tickets.show', $t))->assertSee('Reply to the Customer')->assertDontSee('value="note"', false)->assertSee('Add Note');
        $this->post(route('ranger.tickets.notes', $t), ['category' => 'Billing', 'action' => 'Usage', 'body' => 'x', 'priority' => 'High'])->assertRedirect();
        $this->post(route('ranger.tickets.notes', $t), ['category' => 'Ticket', 'action' => 'Usage', 'body' => 'x', 'priority' => 'High'])->assertSessionHasErrors('action');
        Note::where('support_ticket_id', $t->id)->delete();
        $this->post(route('ranger.tickets.notes', $t), ['category' => 'Ticket', 'action' => 'Internal Note', 'body' => 'SECRET internal note', 'priority' => 'High'])->assertRedirect();
        // An internal note is an account note on the customer, linked to the ticket
        $note = Note::where('support_ticket_id', $t->id)->firstOrFail();
        $this->assertSame([$c->id, 'Ticket', 'High', $t->number()], [$note->customer_id, $note->category, $note->priority, $note->disposition]);
        // Notes are listed at the bottom, each linking to the Note page
        $this->get(route('ranger.tickets.show', $t))->assertSee('SECRET internal note')->assertSee('Ticket History')->assertSee('<h2>Notes</h2>', false)
            ->assertSee(route('corral.items.show', ['id' => $note->id, 'model' => 'ItemNote_model']));
        $this->get(route('corral.customers.show', $c))->assertSee('SECRET internal note');
        $this->post(route('ranger.tickets.reply', $t), ['body' => 'Your usage went up in the heat wave.', 'status' => 'solved'])->assertRedirect();
        $t->refresh();
        $this->assertSame([$csr->id, 'solved'], [$t->assigned_to, $t->status]);
        $this->assertNotNull($t->first_response_at);

        $this->actingAs($c, 'customer');
        $this->get(route('myaccount.support.show', $t->id))->assertOk()->assertSee('Your usage went up')->assertDontSee('SECRET')->assertSee('Did we help?');
        $this->post(route('myaccount.support.rate', $t->id), ['rating' => 'good', 'comment' => 'Thanks!'])->assertRedirect();
        $this->assertSame('good', $t->fresh()->rating);
        // Someone else's ticket is off limits
        $other = SupportTicket::where('customer_id', '!=', $c->id)->firstOrFail();
        $this->get(route('myaccount.support.show', $other->id))->assertNotFound();
        // Writing back reopens it
        $this->post(route('myaccount.support.reply', $t->id), ['body' => 'One more thing'])->assertRedirect();
        $this->assertSame('open', $t->fresh()->status);
    }

    public function test_managers_take_over_and_join_in(): void
    {
        $t = SupportTicket::active()->whereNotNull('assigned_to')->whereIn('assigned_to', RangerAgent::where('level', 'agent')->select('user_id'))->firstOrFail();
        $owner = $t->assignee;
        $agent = RangerAgent::with('user')->where('level', 'agent')->where('active', true)->where('user_id', '!=', $owner->id)->firstOrFail()->user;

        // Another agent can't answer someone else's ticket, join in or take it over
        $this->actingAs($agent);
        $this->get(route('ranger.tickets.show', $t))->assertOk()->assertDontSee('Take Over')->assertSee('A manager can join you in');
        $this->post(route('ranger.tickets.reply', $t), ['body' => 'x'])->assertForbidden();
        $this->post(route('ranger.tickets.take-over', $t))->assertForbidden();
        $this->post(route('ranger.tickets.join', $t))->assertForbidden();
        $this->post(route('ranger.tickets.ask-manager', $t))->assertForbidden();

        // The owner can't assign it to someone else but can ask for a manager
        $this->actingAs($owner);
        $this->get(route('ranger.tickets.show', $t))->assertSee('Need Help?')->assertSee('Ask for a Manager')->assertDontSee('Assign To');
        $this->post(route('ranger.tickets.ask-manager', $t), ['reason' => 'Wants a big refund'])->assertRedirect();
        $this->assertNotNull($t->fresh()->manager_requested_at);
        // The why is a Note (Manager Request), not a line in the conversation or a banner
        $this->assertSame('Manager Request', Note::where('support_ticket_id', $t->id)->latest('id')->value('action'));
        $this->assertSame(0, $t->messages()->where('body', 'like', '%asked for a manager%')->count());
        $this->get(route('ranger.tickets.show', $t))->assertDontSee('asked for a manager')->assertSee('No Longer Needed');
        $this->actingAs($this->user('lead@example.com'));
        $this->get(route('ranger.dashboard'))->assertSee('Wants a big refund');
        $this->get(route('ranger.tickets', ['view' => 'manager']))->assertSee($t->number());

        // A manager joins in (and can reply), then takes over; the agent stays on as a watcher
        $lead = $this->user('lead@example.com');
        $this->actingAs($lead);
        $this->get(route('ranger.tickets.show', $t))->assertSee('Take Over')->assertSee('Join In');
        $this->post(route('ranger.tickets.join', $t))->assertRedirect();
        $this->assertNull($t->fresh()->manager_requested_at);
        $this->assertTrue($t->watchers()->where('users.id', $lead->id)->exists());
        $this->post(route('ranger.tickets.reply', $t), ['body' => 'Manager here, helping out.'])->assertRedirect();
        $this->assertSame($owner->id, $t->fresh()->assigned_to);
        $this->post(route('ranger.tickets.take-over', $t))->assertRedirect();
        $this->assertSame($lead->id, $t->fresh()->assigned_to);
        $this->assertTrue($t->watchers()->where('users.id', $owner->id)->exists());
        $this->assertStringContainsString('took over', $t->messages()->reorder()->where('author', 'system')->latest('id')->value('body'));
    }

    public function test_emails_phone_calls_and_attachments(): void
    {
        Storage::fake('local');
        $c = Customer::whereNotNull('password')->firstOrFail();
        // The customer opens a ticket with a photo of their bill
        $this->actingAs($c, 'customer')->post(route('myaccount.support.store'), ['topic' => 'billing', 'subject' => 'Bill photo attached', 'body' => 'See attached.',
            'attachments' => [UploadedFile::fake()->image('my-bill.png')]])->assertRedirect();
        $t = SupportTicket::latest('id')->firstOrFail();
        $a = $t->attachments()->firstOrFail();
        $this->assertTrue($a->from_customer);
        $this->get(route('myaccount.support.file', [$t->id, $a->id]))->assertOk();

        // The agent sees it and opens its page, emails back (staff don't attach files) and logs a call
        $csr = $this->user('csr@example.com');
        $this->actingAs($csr, 'web');
        $this->get(route('ranger.tickets.show', $t))->assertSee('my-bill.png')->assertSee(route('ranger.tickets.attachment', [$t, $a]))->assertDontSee('name="attachments[]"', false);
        $this->get(route('ranger.tickets.attachment', [$t, $a]))->assertOk()->assertSee('my-bill.png')->assertSee('Download');
        $this->get(route('ranger.tickets.attachment.file', [$t, $a]))->assertOk();
        $this->post(route('ranger.tickets.reply', $t), ['via' => 'email', 'body' => 'Here is your payment plan.',
            'attachments' => [UploadedFile::fake()->create('plan.pdf', 20, 'application/pdf')]])->assertRedirect();
        $this->post(route('ranger.tickets.reply', $t), ['via' => 'phone', 'direction' => 'out', 'minutes' => 6, 'body' => 'CALLNOTE walked them through it'])->assertRedirect();
        $email = $t->messages()->reorder()->where('via', 'email')->firstOrFail();
        $phone = $t->messages()->reorder()->where('via', 'phone')->firstOrFail();
        $this->assertSame([$t->email, 0], [$email->contact, $email->attachments()->count()]);   // a file sent along is ignored
        $this->assertSame(['out', 360], [$phone->direction, $phone->duration]);
        $this->get(route('ranger.tickets.show', $t))->assertSee('Outbound call')->assertSee('6:00')->assertSee('Email to');

        // The customer sees the email and their own file; the call shows as a line without the call notes
        $this->actingAs($c, 'customer')->get(route('myaccount.support.show', $t->id))->assertSee('Here is your payment plan.')->assertSee('my-bill.png')->assertDontSee('plan.pdf')
            ->assertSee('We called you')->assertDontSee('CALLNOTE');
        // Someone else's ticket's file is off limits
        $other = SupportTicket::where('customer_id', '!=', $c->id)->firstOrFail();
        $this->get(route('myaccount.support.file', [$other->id, $a->id]))->assertNotFound();

        // Only managers and admins see Assign To
        $this->actingAs($this->user('lead@example.com'), 'web')->get(route('ranger.tickets.show', $t))->assertSee('Assign To');
    }

    public function test_third_party_rep_manager_and_company_admin(): void
    {
        $rep = $this->user('3p.csr@example.com');
        $mgr = $this->user('3p.manager@example.com');
        $admin = $this->user('3p.admin@example.com');
        $this->assertSame(['Brightline Contact Solutions', false, true, true], [$rep->company, RangerAgent::isManager($rep), RangerAgent::isManager($mgr), RangerAgent::isManager($admin)]);
        // All three are test sign-ins
        $this->get(route('admin.login'))->assertSee('Sam Contractrep')->assertSee('Reese Shiftlead')->assertSee('Jamie Accountlead');

        // The rep works their own tickets but can't assign
        $mine = SupportTicket::active()->where('assigned_to', $rep->id)->firstOrFail();
        $this->actingAs($rep, 'web')->get(route('ranger.tickets.show', $mine))->assertOk()->assertDontSee('Assign To')->assertSee('Ask for a Manager');

        // The company admin sees only Brightline's people, can add and change them, and assigns only among them
        $this->actingAs($admin, 'web');
        $this->get(route('ranger.team'))->assertOk()->assertSee('Brightline Contact Solutions — Ticket Team')->assertSee('Sam Contractrep')->assertDontSee('Morgan Placeholder');
        $office = RangerAgent::where('team', 'office')->firstOrFail();
        $this->get(route('ranger.team.show', $office))->assertForbidden();
        $this->patch(route('ranger.team.update', $office), ['active' => 0])->assertForbidden();
        $this->post(route('ranger.team.store'), ['who' => 'new', 'name' => 'Pat Newhire', 'email' => 'pat.n@brightline.example.net', 'password' => 'a-long-temp-password', 'team' => 'office', 'level' => 'agent'])->assertRedirect();
        $this->assertSame(['Brightline Contact Solutions', 'third_party'], [User::where('email', 'pat.n@brightline.example.net')->value('company'), RangerAgent::where('user_id', User::where('email', 'pat.n@brightline.example.net')->value('id'))->value('team')]);
        $this->post(route('ranger.team.store'), ['who' => 'existing', 'user_id' => $this->user('finance@example.com')->id, 'team' => 'third_party', 'level' => 'agent'])->assertForbidden();
        $this->get(route('ranger.tickets.show', $mine))->assertSee('Assign To')->assertSee('Alex Overflow')->assertDontSee('Morgan Placeholder');
        $this->patch(route('ranger.tickets.update', $mine), ['assigned_to' => $this->user('morgan.placeholder@staff.example.com')->id])->assertForbidden();
        $this->patch(route('ranger.tickets.update', $mine), ['assigned_to' => $this->user('alex.o@brightline.example.net')->id])->assertRedirect();
    }

    public function test_team_members_can_be_seen_and_added(): void
    {
        $csr = $this->user('csr@example.com');
        $this->actingAs($csr);
        $this->get(route('ranger.team.create'))->assertOk()->assertSee('Someone already in the admin');
        // A new 3rd-party person needs their company; agents can't add managers
        $base = ['who' => 'new', 'name' => 'Pat Helpdesk', 'email' => 'pat.h@brightline.example.net', 'password' => 'a-long-temp-password', 'team' => 'third_party', 'level' => 'agent'];
        $this->post(route('ranger.team.store'), $base)->assertSessionHasErrors('company');
        $r = $this->post(route('ranger.team.store'), array_merge($base, ['company' => 'Brightline Contact Solutions', 'level' => 'manager']));
        $r->assertForbidden();
        $this->post(route('ranger.team.store'), array_merge($base, ['company' => 'Brightline Contact Solutions']))->assertRedirect(route('ranger.team'));
        $pat = User::where('email', 'pat.h@brightline.example.net')->firstOrFail();
        $this->assertSame('Brightline Contact Solutions', $pat->company);
        $this->assertTrue($pat->hasPerm('ranger'));
        $this->actingAs($pat)->get(route('ranger.dashboard'))->assertOk();

        // Someone already in the admin, without Ranger, gets it
        $finance = $this->user('finance@example.com');
        $this->assertFalse($finance->hasPerm('ranger'));
        $this->actingAs($this->user('lead@example.com'))->post(route('ranger.team.store'), ['who' => 'existing', 'user_id' => $finance->id, 'team' => 'office', 'level' => 'manager'])->assertRedirect();
        $this->assertTrue($finance->fresh()->hasPerm('ranger'));
        $this->assertTrue(RangerAgent::isManager($finance->fresh()));
        $this->post(route('ranger.team.store'), ['who' => 'existing', 'user_id' => $finance->id, 'team' => 'office', 'level' => 'agent'])->assertSessionHasErrors('user_id');

        // Taking someone off tickets sends their open tickets back to the queue
        $agent = RangerAgent::where('user_id', $csr->id)->firstOrFail();
        $this->patch(route('ranger.team.update', $agent), ['active' => 0])->assertRedirect();
        $this->assertSame(0, SupportTicket::active()->where('assigned_to', $csr->id)->count());
    }
}
