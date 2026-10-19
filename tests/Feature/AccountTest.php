<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerFlag;
use App\Models\EmailTemplate;
use App\Models\Phonecall;
use App\Models\ReportRun;
use App\Models\RewardOffer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The Corral account page and everything it links to. */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /** A valid value for each field type an action form can have. */
    private function input(Customer $c, array $fields): array
    {
        $other = Customer::where('id', '!=', $c->id)->first();

        return collect($fields)->map(fn ($f, $name) => match ($f[1]) {
            'money' => 12.5,
            'number' => 3,
            'date' => today()->addDays(7)->toDateString(),
            'textarea' => 'Test message',
            'method' => $c->paymentMethods()->whereNull('removed_at')->value('id'),
            'payment' => $c->payments()->value('id'),
            'template' => EmailTemplate::value('name'),
            default => $name === 'account' ? $other->account : 'Test '.$name,
        })->all();
    }

    public function test_every_account_section_and_page_renders(): void
    {
        $c = Customer::has('paymentMethods')->has('payments')->firstOrFail();
        $this->actingAs($this->admin());

        $this->get('/admin/corral/customers/'.$c->account)->assertOk()
            ->assertSee('Flags')->assertSee('Balances')->assertSee('Contact Information')->assertSee('Products')
            ->assertSee('Billing Summary')->assertSee('Payment Methods')->assertSee('Plan Change Log')->assertSee('Warehouse');
        $this->get(route('corral.customers.stars', $c))->assertOk()->assertSee('Rangler Rewards');
        $this->get(route('corral.customers.contact-log', $c))->assertOk()->assertSee('Phone Calls');
        $this->get(route('corral.customers.api-log', $c))->assertOk();
        $this->get(route('corral.customers.ticket', $c))->assertOk();
        foreach (array_keys(config('history.logs')) as $log) {
            $this->get(route('corral.customers.log', [$c, $log]))->assertOk();
        }
    }

    public function test_every_account_action_opens_and_runs(): void
    {
        $this->actingAs($this->admin());
        $actions = config('corral.actions.status') + config('corral.actions.service');

        foreach ($actions as $key => $def) {
            // A fresh account for each so one action (cancel, delete…) can't affect the next
            $c = Customer::has('paymentMethods')->has('payments')->whereNotIn('status', ['Cancelled'])->inRandomOrder()->firstOrFail();
            $this->get(route('corral.customers.action', [$c, $key]))->assertOk()->assertSee($def[0]);
            $res = $this->post(route('corral.customers.action.run', [$c, $key]), $this->input($c, $def[1]));
            $this->assertTrue($res->isRedirect(), $key.': '.$res->exception?->getMessage());
            $res->assertSessionHasNoErrors();
        }

        $this->get('/admin/corral/customers/1/actions/not-an-action')->assertNotFound();
    }

    public function test_flags_products_stars_and_notes(): void
    {
        $c = Customer::firstOrFail();
        $this->actingAs($this->admin());

        $this->post(route('corral.customers.flags.add', $c), ['flag' => 'SOLAR CUSTOMER'])->assertRedirect();
        $this->get('/admin/corral/customers/'.$c->account)->assertSee('Handle with care!');
        $flag = CustomerFlag::where('customer_id', $c->id)->where('flag', 'SOLAR CUSTOMER')->firstOrFail();
        $this->delete(route('corral.flags.remove', $flag))->assertRedirect();
        $this->assertNotNull($flag->fresh()->removed_at);
        $this->post(route('corral.customers.flags.add', $c), ['flag' => 'made up'])->assertSessionHasErrors('flag');

        $this->post(route('corral.customers.products.add', $c), ['product' => 'Paperless Billing'])->assertRedirect();
        $this->assertTrue($c->fresh()->paperless);

        $c->update(['stars' => 0]);
        $c->starEntries()->delete();
        $this->post(route('corral.customers.stars.redeem', $c), ['offer' => RewardOffer::where('stars', 50)->value('id')])->assertSessionHasErrors('offer');
        $c->starEntries()->create(['reason' => 'Test', 'stars' => 60]);
        $c->syncStars();
        $this->post(route('corral.customers.stars.redeem', $c), ['offer' => RewardOffer::where('stars', 50)->value('id')])->assertRedirect();
        $this->assertSame(10, $c->fresh()->stars);

        $this->post(route('corral.customers.notes', $c), ['category' => 'Billing', 'action' => 'Balance Inquiry', 'priority' => 'Low', 'body' => 'Called about the bill'])->assertRedirect();
        $this->assertTrue($c->notes()->where('body', 'Called about the bill')->where('category', 'Billing')->exists());
    }

    public function test_only_corral_users_can_delete_orders(): void
    {
        // Deleting is open to everyone who can open Corral; someone without Corral can't reach it
        $pricing = User::where('email', 'pricing@example.com')->firstOrFail();
        $c = Customer::firstOrFail();
        $this->actingAs($pricing)->post(route('corral.customers.action.run', [$c, 'delete-order']))->assertRedirect();
        $this->assertNotNull($c->fresh());
    }

    public function test_corral_reports_sms_ercot_and_exception_menu(): void
    {
        $this->actingAs($this->admin());

        // Reports always run in the background and save a file in the chosen format
        foreach (['orders', 'notes', 'phonecalls'] as $report) {
            foreach (['xlsx', 'csv', 'txt', 'json'] as $format) {
                $this->get('/admin/corral/reports/'.$report.'?run=1&start=2020-01-01&format='.$format)->assertRedirect();
                $run = ReportRun::where('report', $report)->latest('id')->firstOrFail();
                $this->assertSame('done', $run->status);
                $this->assertStringEndsWith('.'.$format, $run->file);
            }
            $this->get('/admin/corral/reports/'.$report)->assertOk()->assertSee('Your Runs')->assertSee('aria-label="Rerun report"', false);
        }
        $this->get('/admin/corral/reports/notes?run=1&start=2020-01-01&priorities[]=System&format=json')->assertRedirect();
        $this->assertStringContainsString('Enrollment submitted', json_encode(ReportRun::where('report', 'notes')->latest('id')->first()->preview));

        $this->get('/admin/corral/ercot')->assertOk()->assertSee('814_16');
        $this->get('/admin/corral/ercot?type=814_16&status=linked')->assertOk();

        $c = Customer::whereHas('contactLogs', fn ($q) => $q->where('channel', 'SMS'))->firstOrFail();
        $this->get('/admin/corral/sms')->assertOk()->assertSee($c->name);
        $this->get('/admin/corral/sms?account='.$c->account)->assertOk()->assertSee('Your');
        $this->get('/admin/corral/sms/new?account='.$c->account)->assertOk()->assertSee('Send a Text')->assertSee($c->phone);
        $this->post('/admin/corral/sms', ['account' => $c->account, 'message' => 'Hello from the test'])->assertRedirect('/admin/corral/sms');
        $this->get('/admin/corral/sms')->assertSee('Hello from the test')->assertSee('Recent Messages');
        // Phone calls: recent calls with issue, duration, who answered and the transcript
        $call = Phonecall::whereNotNull('transcript')->firstOrFail();
        $this->get('/admin/corral/calls')->assertOk()->assertSee('Recent Calls')->assertSee('View transcript')->assertSee(e($call->disposition), false);
        $this->get('/admin/corral/calls/'.$call->id.'/transcript')->assertOk()->assertSee('Transcript')->assertSee('Agent');
        $this->get('/admin/corral/customers/'.$call->customer->account)->assertOk()->assertSee('Calls &amp; Texts', false)->assertSee(route('corral.calls.transcript', $call), false);
        $this->assertTrue($c->contactLogs()->where('channel', 'SMS')->where('body', 'Hello from the test')->where('status', 'not sent')->exists());

        // Every exception queue is its own menu item
        $page = $this->get('/admin/corral/queues/duplicate-ips')->assertOk();
        foreach (config('admin.queues') as $key => [$label]) {
            $page->assertSee(route('corral.queues.show', $key));
        }
        $page->assertSee('aria-current="page" >Duplicate IPs', false);
    }
}
