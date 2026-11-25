<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\RangerAgent;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Account app: My Work, My Bookmarks, My Team, Availability, Start Page and Email Signature. */
class AccountMoreTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $who): User
    {
        return User::where('email', $who.'@example.com')->firstOrFail();
    }

    public function test_pages_open_from_the_account_menu(): void
    {
        $this->actingAs($this->user('csr'));
        foreach (['work' => 'My Open Tickets', 'bookmarks' => 'My Bookmarks', 'team' => 'My Manager', 'availability' => 'Availability'] as $p => $see) {
            $this->get('/admin/my/'.$p)->assertOk()->assertSee($see)->assertSee('My Work')->assertSee('My Team');
        }
        $this->get('/admin/my/settings')->assertOk()->assertSee('Start Page')->assertSee('Email Signature');
    }

    public function test_my_work_lists_your_open_tickets_and_leads_see_approvals(): void
    {
        $csr = $this->user('csr');
        $t = SupportTicket::active()->first();
        $t->update(['assigned_to' => $csr->id]);
        $this->actingAs($csr)->get('/admin/my/work')->assertSee($t->number())->assertSee('My Lead Approval Requests')->assertDontSee('Waiting for Your Approval');
        $this->actingAs($this->user('lead'))->get('/admin/my/work')->assertSee('Waiting for Your Approval');
    }

    public function test_bookmarks_list_and_remove(): void
    {
        $admin = $this->user('admin');
        $c = $admin->bookmarks()->first();
        $this->actingAs($admin)->get('/admin/my/bookmarks')->assertSee($c->account);
        $this->delete(route('admin.account.bookmarks.remove', $c))->assertRedirect(route('admin.account.bookmarks'));
        $this->assertFalse($admin->bookmarks()->whereKey($c->id)->exists());
    }

    public function test_out_of_office_shows_and_blocks_ticket_assignment(): void
    {
        $csr = $this->user('csr');
        $this->actingAs($csr)->put('/admin/my/availability', ['availability' => 'out'])->assertSessionHasErrors('out_until');
        $this->put('/admin/my/availability', ['availability' => 'out', 'out_until' => today()->addDays(3)->toDateString(), 'out_note' => 'Vacation'])->assertRedirect();
        $csr->refresh();
        $this->assertTrue($csr->isOut());
        $this->assertStringStartsWith('Out of Office until', $csr->availabilityLabel());
        $this->get('/admin/my/profile')->assertSee('Out of Office until');

        if (RangerAgent::where('user_id', $csr->id)->where('active', true)->exists()) {
            $t = SupportTicket::active()->where(fn ($q) => $q->whereNull('assigned_to')->orWhere('assigned_to', '!=', $csr->id))->first();
            $this->actingAs($this->user('admin'))->patch(route('ranger.tickets.update', $t), ['assigned_to' => $csr->id])->assertSessionHasErrors('assigned_to');
            $this->assertNotSame($csr->id, $t->fresh()->assigned_to);
        }
        $this->actingAs($csr)->put('/admin/my/availability', ['availability' => 'available'])->assertRedirect();
        $this->assertFalse($csr->fresh()->isOut());
    }

    public function test_start_page_is_where_you_land_after_signing_in(): void
    {
        $csr = $this->user('csr');
        $this->actingAs($csr)->put('/admin/my/start-page', ['start_page' => 'ranger'])->assertRedirect();
        $this->put('/admin/my/start-page', ['start_page' => 'sheriff'])->assertSessionHasErrors('start_page');   // not one of their apps
        auth()->logout();
        $this->post('/admin/login', ['email' => $csr->email, 'password' => 'password'])->assertRedirect(route('ranger.dashboard'));
    }

    public function test_signature_goes_on_emails_to_customers(): void
    {
        $csr = $this->user('csr');
        $this->actingAs($csr)->put('/admin/my/signature', ['signature' => "Casey\nCustomer Care"])->assertRedirect();
        $c = Customer::whereDoesntHave('flags', fn ($q) => $q->where('flag', 'VIP - Friends & Family'))->first();
        $this->post(route('corral.customers.action.run', [$c, 'send-email']), ['template' => EmailTemplate::value('name'), 'message' => 'Hello'])->assertRedirect();
        $this->assertStringContainsString("Hello\n\nCasey\nCustomer Care", (string) $c->contactLogs()->latest('id')->first()->body);
    }
}
