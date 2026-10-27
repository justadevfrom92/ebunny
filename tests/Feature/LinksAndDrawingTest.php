<?php

namespace Tests\Feature;

use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\HistoryItem;
use App\Models\Phonecall;
use App\Models\ReferenceRow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Every email, text and call links to its own page; the monthly drawing works by month with its own Draw a Winner page. */
class LinksAndDrawingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_contact_log_rows_open_their_email_text_or_call(): void
    {
        $this->actingAs($this->admin());
        $text = ContactLog::where('channel', 'SMS')->whereNotNull('customer_id')->firstOrFail();
        $c = $text->customer;
        $email = $c->contactLogs()->where('channel', 'Email')->firstOrFail();
        $call = Phonecall::where('customer_id', $c->id)->first() ?? Phonecall::firstOrFail();

        $page = $this->get(route('corral.customers.contact-log', $c))->assertOk();
        $page->assertSee(route('corral.sms.show', $text), false)->assertSee($email->corralUrl(), false);
        $this->get($email->corralUrl())->assertOk()->assertSee($email->template);
        $this->get(route('corral.sms.show', $text))->assertOk()->assertSee('Conversation')->assertSee(e($text->body), false);
        $this->get(route('corral.sms.show', $email))->assertNotFound();
        $this->get(route('corral.calls.transcript', $call))->assertOk()->assertSee('Phone Call');
        $this->get(route('corral.customers.show', $c))->assertSee(route('corral.sms.show', $text), false);
        $this->get(route('corral.sms'))->assertSee('/admin/corral/sms/', false);

        // Log pages: each entry also opens the record it is about
        $h = HistoryItem::where('model', 'ItemSms_model')->where('customer_id', $c->id)->where('action', 'created')->first();
        if ($h) {
            $this->assertSame(route('corral.sms.show', $h->record_id), $h->recordUrl());
            $this->get(route('corral.history.show', $h))->assertOk()->assertSee('Open the text');
        }
        $this->get(route('corral.customers.log', [$c, 'emails']))->assertOk();
    }

    public function test_monthly_drawing_by_month_and_draw_page(): void
    {
        $this->actingAs($this->admin());
        $this->get(route('bounty.drawing'))->assertOk()->assertSee('Entries for '.now()->format('F Y'))->assertSee('Draw a Winner');
        $last = now()->startOfMonth()->subMonth();
        $this->get(route('bounty.drawing', ['month' => $last->format('Y-m')]))->assertOk()->assertSee('Entries for '.$last->format('F Y'));
        $this->get(route('bounty.drawing.draw'))->assertOk()->assertSee(now()->format('F Y').' Drawing')->assertSee('Each Entry');

        $this->post(route('bounty.draw'), ['prize' => '$50 Bill Credit'])->assertRedirect(route('bounty.drawing'));
        $row = ReferenceRow::where('table_key', 'monthly-drawing')->get()->first(fn ($r) => ($r->cells[0] ?? null) === now()->format('Y-m'));
        $this->assertNotNull($row);
        $this->assertTrue(Customer::where('account', $row->cells[2])->exists());
        $this->get(route('bounty.drawing.draw'))->assertOk()->assertSee('already has a winner');
    }

    public function test_drawing_winners_have_their_own_pages(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $w = ReferenceRow::where('table_key', 'monthly-drawing')->get()->filter(fn ($r) => filled($r->cells[2] ?? null))->sortByDesc(fn ($r) => ($r->cells[3] ?? '').str_pad((string) $r->id, 8, '0', STR_PAD_LEFT))->first();   // newest, so on page 1
        $this->get(route('bounty.winners'))->assertOk()->assertSee('#'.$w->id)->assertDontSee('<input name="cells', false);
        $this->get(route('bounty.winners.show', $w))->assertOk()->assertSee($w->cells[1])->assertSee('Date Won');
        $this->get(route('bounty.drawing'))->assertOk()->assertSee('<select class="pill-select" aria-label="Month"', false)->assertSee(route('bounty.winners.show', $w), false);
        $this->get(route('rodeo.dashboard'))->assertOk()->assertSee('Latest Survey Responses')->assertSee('Score (NPS)');
        $this->delete(route('bounty.winners.destroy', $w))->assertRedirect(route('bounty.winners'));
        $this->assertNull($w->fresh());
    }
}
