<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailCategory;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rodeo → Emails: overview, sent emails, test sends, categories and the suppression list. */
class RodeoEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function marketing(): User
    {
        return User::where('email', 'marketing@example.com')->firstOrFail();
    }

    public function test_every_emails_page_opens(): void
    {
        $this->actingAs($this->marketing());
        $email = ContactLog::where('channel', 'Email')->whereNotNull('dropped_at')->firstOrFail();

        foreach ([7, 30, 90, 365] as $days) {
            $this->get(route('rodeo.emails', ['days' => $days]))->assertOk()->assertSee('By Template')->assertSee('Renewal Offer');
        }
        $this->get(route('rodeo.emails.sent'))->assertOk()->assertSee('Search Emails')->assertSee('Fall Renewal Reminder');
        $this->get(route('rodeo.emails.sent', ['stage' => 'dropped']))->assertOk()->assertSee($email->customer->account);
        $this->get(route('rodeo.emails.sent', ['template' => 'Bill Ready', 'start' => today()->subYear()->toDateString(), 'user' => 'system', 'stage' => 'opened']))->assertOk();
        $this->get(route('rodeo.emails.sent.show', $email))->assertOk()->assertSee('on the suppression list')->assertSee('Timeline');
        $this->get(route('rodeo.emails.sent.show', ContactLog::where('channel', 'SMS')->firstOrFail()))->assertNotFound();
        $this->get('/admin/rodeo/emails/tests')->assertNotFound();   // Test Sends was removed from Emails
        $this->get(route('rodeo.emails.categories'))->assertOk()->assertSee('Billing')->assertSee('Disconnect Notice');
        $this->get(route('rodeo.emails.categories.create'))->assertOk();
        $this->get(route('rodeo.emails.suppressions'))->assertOk()->assertSee('old-address@example.net');
        $this->get(route('rodeo.emails.suppressions', ['reason' => 'complaint']))->assertOk()->assertDontSee('old-address@example.net');
        $this->get(route('rodeo.emails.suppressions.create', ['email' => 'a@example.com']))->assertOk()->assertSee('a@example.com');
        $this->get(route('rodeo.templates', ['category' => EmailCategory::where('name', 'Billing')->value('id')]))->assertOk()->assertSee('Bill Ready')->assertDontSee('Winback Offer');
        $this->get(route('rodeo.templates', ['category' => 'none']))->assertOk();
        $this->get(route('rodeo.dashboard'))->assertOk()->assertSee('Sent Emails')->assertSee('Suppression List');
    }

    public function test_categories_file_templates(): void
    {
        $this->actingAs($this->marketing());
        $ids = EmailTemplate::whereIn('name', ['Bill Ready', 'Winback Offer'])->pluck('id')->all();
        $this->post(route('rodeo.emails.categories.store'), ['name' => 'Seasonal', 'color' => '#123456', 'templates' => $ids])->assertRedirect(route('rodeo.emails.categories'));
        $cat = EmailCategory::where('name', 'Seasonal')->firstOrFail();
        $this->assertSame(2, $cat->templates()->count());

        // Unticking a template takes it out
        $this->put(route('rodeo.emails.categories.update', $cat), ['name' => 'Seasonal', 'color' => '#123456', 'templates' => [$ids[0]]])->assertRedirect();
        $this->assertSame([$ids[0]], $cat->templates()->pluck('id')->all());
        $this->assertNull(EmailTemplate::find($ids[1])->email_category_id);

        $this->delete(route('rodeo.emails.categories.destroy', $cat))->assertRedirect();
        $this->assertNull(EmailTemplate::find($ids[0])->email_category_id);
    }

    public function test_suppressed_addresses_get_no_email(): void
    {
        $this->actingAs($this->marketing());
        $c = Customer::whereNotNull('email')->where('marketing_opt_in', true)->whereNotIn('email', EmailSuppression::select('email'))->firstOrFail();

        $this->post(route('rodeo.emails.suppressions.store'), ['email' => strtoupper($c->email), 'reason' => 'manual'])->assertRedirect(route('rodeo.emails.suppressions'));
        $s = EmailSuppression::where('email', strtolower($c->email))->firstOrFail();
        $this->assertSame($c->id, $s->customer_id);
        $this->post(route('rodeo.emails.suppressions.store'), ['email' => $c->email, 'reason' => 'manual'])->assertSessionHasErrors('email');

        // A campaign skips it
        $campaign = Campaign::create(['name' => 'Test', 'channel' => 'Email', 'email_template_id' => EmailTemplate::first()->id, 'audience' => ['statuses' => [$c->status]]]);
        $this->post(route('rodeo.campaigns.send', $campaign))->assertRedirect();
        $this->assertFalse(ContactLog::where('campaign_id', $campaign->id)->where('customer_id', $c->id)->exists());

        // So does a test send to the account
        $tpl = EmailTemplate::first();
        $this->post(route('rodeo.templates.test.send', $tpl), ['mode' => 'accounts', 'accounts' => $c->account])->assertRedirect();
        $this->assertSame('suppressed', $tpl->testSends()->latest('id')->first()->status);

        // Removing it lets emails go again
        $this->delete(route('rodeo.emails.suppressions.destroy', $s))->assertRedirect();
        $this->assertFalse(EmailSuppression::has($c->email));
    }
}
