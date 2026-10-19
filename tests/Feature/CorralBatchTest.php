<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\Corral\PanelLogController;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\CustomerFile;
use App\Models\Phonecall;
use App\Models\User;
use App\Models\WorkItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Corral: panel logs, New Contact and resolutions, account links, exception history, file pages, ticket timeline, search pages. */
class CorralBatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_balance_action_and_flag_logs(): void
    {
        $this->actingAs($this->admin());
        $c = Customer::has('notes', '>=', 12)->firstOrFail();
        $page = $this->get(route('corral.customers.show', $c))->assertOk();
        foreach (['balances', 'actions', 'flags'] as $panel) {
            $page->assertSee(route('corral.customers.panel-log', [$c, $panel]), false);
            $this->get(route('corral.customers.panel-log', [$c, $panel]))->assertOk();
        }
        $this->get(route('corral.customers.panel-log', [$c, 'nope']))->assertNotFound();
        // Actions panel shows the last 10; the log pages by 25
        $html = $page->getContent();
        $this->assertStringContainsString('Last 10', $html);
        $many = Customer::all()->first(fn ($x) => PanelLogController::actionNotes($x)->count() > 25);
        if ($many) {
            $this->get(route('corral.customers.panel-log', [$many, 'actions', 'page' => 2]))->assertOk()->assertSee('aria-current="page">2', false);
        }
    }

    public function test_new_contact_and_resolution(): void
    {
        $this->actingAs($this->admin());
        $c = Customer::whereNotNull('phone')->firstOrFail();
        $call = Phonecall::where('customer_id', $c->id)->firstOrFail();
        $this->get(route('corral.calls.transcript', $call))->assertOk()->assertSee('New Contact')->assertSee('tel:', false)
            ->assertSee(route('corral.customers.show', $c), false);
        $this->get(route('corral.contacts.create', ['account' => $c->account]))->assertOk()->assertSee($c->name);

        $this->post(route('corral.contacts.store'), ['account' => $c->account, 'channel' => 'call', 'direction' => 'in', 'issue' => 'Balance Inquiry',
            'resolution' => 'Incomplete', 'minutes' => 4, 'body' => 'Asked about the bill; will call back.'])->assertRedirect();
        $new = Phonecall::latest('id')->firstOrFail();
        $this->assertSame('Incomplete', $new->resolution);
        $this->post(route('corral.contacts.resolution', ['call', $new->id]), ['resolution' => 'Resolved'])->assertRedirect();
        $this->assertSame('Resolved', $new->fresh()->resolution);

        foreach (['email', 'sms', 'chat'] as $ch) {
            $this->post(route('corral.contacts.store'), ['account' => $c->account, 'channel' => $ch, 'direction' => 'out', 'resolution' => 'Follow-up Needed', 'body' => 'Logged '.$ch])->assertRedirect();
        }
        $this->assertSame('Follow-up Needed', ContactLog::where('body', 'Logged email')->value('resolution'));
        $this->post(route('corral.contacts.resolution', ['call', $new->id]), ['resolution' => 'Nope'])->assertSessionHasErrors('resolution');
        $this->get(route('corral.calls'))->assertSee('Resolution');
        $this->get(route('corral.customers.contact-log', $c))->assertOk()->assertSee('Follow-up Needed');
    }

    public function test_account_numbers_link_to_corral_from_other_apps(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('caboose.payments'))->assertOk()->assertSee('/admin/corral/customers/', false);
        $this->get(route('bounty.drawing'))->assertOk()->assertSee('/admin/corral/customers/', false);
    }

    public function test_exception_history(): void
    {
        $this->actingAs($this->admin());
        $fixed = WorkItem::whereNotNull('resolved_at')->firstOrFail();
        $this->get(route('corral.queues.index'))->assertOk()->assertSee('History')->assertSee('Fixed By');
        $this->get(route('corral.queues.show', $fixed->queue))->assertOk()->assertSee('id="history"', false);
        $open = WorkItem::open()->firstOrFail();
        $this->post(route('corral.queues.resolve', $open))->assertRedirect();
        $this->get(route('corral.queues.show', $open->queue))->assertSee($this->admin()->name);
    }

    public function test_file_pages_language_print_and_email(): void
    {
        Mail::fake();
        $this->actingAs($this->admin());
        $f = CustomerFile::where('kind', 'welcome')->firstOrFail();
        $this->get(route('corral.customers.show', $f->customer))->assertSee(route('corral.files.show', $f), false);
        $this->get(route('corral.files.show', [$f, 'lang' => 'en']))->assertOk()->assertSee('Welcome Letter')->assertSee('Email to Customer');
        $this->get(route('corral.files.show', [$f, 'lang' => 'es']))->assertOk()->assertSee('Carta de Bienvenida');
        $this->get(route('corral.files.print', [$f, 'lang' => 'es']))->assertOk()->assertSee('window.print', false);
        $this->post(route('corral.files.send', $f), ['lang' => 'es', 'email' => 'someone@example.com'])->assertRedirect();
        $this->assertSame(1, ContactLog::where('template', 'like', 'Customer File: '.$f->name.' (Spanish)')->count());
        $this->post(route('corral.files.send', $f), ['lang' => 'fr', 'email' => 'someone@example.com'])->assertSessionHasErrors('lang');
    }

    public function test_ticket_timeline_and_search_pages(): void
    {
        $this->actingAs($this->admin());
        $c = Customer::firstOrFail();
        $this->get(route('corral.customers.ticket', $c))->assertOk()->assertSee('data-view-pane="timeline"', false)->assertSee('By Item Type');
        $html = $this->get(route('corral.customers.index', ['limit' => 10]))->assertOk()->getContent();
        $this->assertStringContainsString('aria-current="page">1', $html);
        $this->get(route('corral.customers.index', ['limit' => 10, 'page' => 2]))->assertOk()->assertSee('Showing 11–20', false);
    }
}
