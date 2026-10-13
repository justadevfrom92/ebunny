<?php

namespace Tests\Feature;

use App\Mail\TemplateTest;
use App\Models\Campaign;
use App\Models\ContactLog;
use App\Models\Customer;
use App\Models\EmailSuppression;
use App\Models\EmailTemplate;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\RewardOffer;
use App\Models\RewardRule;
use App\Models\StarEntry;
use App\Models\Survey;
use App\Models\User;
use App\Support\AdminMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Caboose (finance), Bounty (rewards) and Rodeo (marketing). Surveys are in RodeoSurveysTest. */
class FinanceRewardsMarketingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(string $email = 'admin@example.com'): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_every_menu_page_opens_and_menus_stay_inside_their_app(): void
    {
        $this->actingAs($this->user());
        foreach (['caboose', 'bounty', 'rodeo'] as $app) {
            $this->get(route($app.'.dashboard'));   // sets the current app for the menu
            foreach (AdminMenu::sections($app) as $links) {
                foreach ($links as $link) {
                    $html = $this->get($link['url'])->assertOk()->getContent();
                    foreach (['corral', 'lando', 'astro', 'walker', 'sheriff'] as $other) {
                        $this->assertStringNotContainsString('/admin/'.$other.'/', $html, "$app {$link['label']} links into $other");
                    }
                }
            }
        }
        $this->get(route('rodeo.campaigns.create'))->assertOk();
        $this->get(route('rodeo.campaigns.edit', Campaign::first()))->assertOk();
        $this->get(route('rodeo.templates.create'))->assertOk();
        $this->get(route('rodeo.templates.edit', EmailTemplate::first()))->assertOk();
        $this->get(route('rodeo.surveys.create'))->assertOk();
        $this->get(route('rodeo.surveys.edit', Survey::first()))->assertOk();
        $this->get(route('rodeo.surveys.results', Survey::first()))->assertOk()->assertSee('Net Promoter');
    }

    public function test_apps_follow_role_permissions(): void
    {
        $this->actingAs($this->user('csr@example.com'));
        $this->get('/admin/caboose')->assertRedirect(route('admin.launcher', ['denied' => 'caboose']));
        $this->get('/admin/rodeo')->assertRedirect(route('admin.launcher', ['denied' => 'rodeo']));
        $this->get('/admin/bounty')->assertOk();

        $this->actingAs($this->user('finance@example.com'));
        $this->get('/admin/caboose')->assertOk();
        $this->get('/admin/bounty')->assertRedirect(route('admin.launcher', ['denied' => 'bounty']));

        $this->actingAs($this->user('marketing@example.com'));
        $this->get('/admin/rodeo')->assertOk();
        $this->get('/admin/caboose')->assertRedirect(route('admin.launcher', ['denied' => 'caboose']));
    }

    public function test_caboose_payments_ledger_refunds_and_journal(): void
    {
        $this->actingAs($this->user());
        $c = Customer::where('status', 'Good - On Flow')->firstOrFail();
        $balance = (float) $c->balance;

        $this->post(route('caboose.payments.record'), ['account' => $c->account, 'amount' => 25, 'method' => 'Check', 'reference' => '1042', 'kind' => 'Balance Payment'])->assertRedirect();
        $this->assertEqualsWithDelta($balance - 25, (float) $c->fresh()->balance, 0.001);
        $payment = Payment::where('customer_id', $c->id)->latest('id')->firstOrFail();
        $this->post(route('caboose.payments.reverse', $payment))->assertRedirect();
        $this->assertEqualsWithDelta($balance, (float) $c->fresh()->balance, 0.001);

        $entry = LedgerEntry::create(['customer_id' => $c->id, 'kind' => 'credit', 'amount' => 10, 'description' => 'Goodwill', 'status' => 'pending']);
        $this->post(route('caboose.ledger.decide', $entry), ['do' => 'apply'])->assertRedirect();
        $this->assertSame('applied', $entry->fresh()->status);
        $this->assertEqualsWithDelta($balance - 10, (float) $c->fresh()->balance, 0.001);

        // Refund a credit balance: request → approve → paid
        $c->update(['balance' => -40]);
        $this->post(route('caboose.refunds.request'), ['account' => $c->account, 'kind' => 'balance', 'amount' => 50, 'reason' => 'Move out', 'method' => 'Check'])->assertSessionHasErrors();
        $this->post(route('caboose.refunds.request'), ['account' => $c->account, 'kind' => 'balance', 'amount' => 40, 'reason' => 'Move out', 'method' => 'Check'])->assertRedirect();
        $refund = Refund::where('customer_id', $c->id)->latest('id')->firstOrFail();
        $this->post(route('caboose.refunds.decide', $refund), ['do' => 'paid'])->assertStatus(422);
        $this->post(route('caboose.refunds.decide', $refund), ['do' => 'approve'])->assertRedirect();
        $this->post(route('caboose.refunds.decide', $refund), ['do' => 'paid'])->assertRedirect();
        $this->assertSame('paid', $refund->fresh()->status);
        $this->assertEqualsWithDelta(0, (float) $c->fresh()->balance, 0.001);

        $this->get(route('caboose.journal', ['format' => 'csv', 'start' => today()->subYear()->toDateString()]))->assertOk()->assertHeader('content-type', 'text/csv; charset=utf-8');
        $this->get(route('caboose.journal', ['format' => 'xlsx']))->assertOk();
    }

    public function test_refund_decisions_need_the_refunds_right(): void
    {
        $refund = Refund::where('status', 'requested')->first() ?? Refund::first();
        $refund->update(['status' => 'requested']);
        $role = $this->user('finance@example.com')->role;
        $role->update(['perms' => array_values(array_diff($role->perms, ['refunds']))]);
        $this->actingAs($this->user('finance@example.com')->fresh());
        $this->post(route('caboose.refunds.decide', $refund), ['do' => 'approve'])->assertForbidden();
    }

    public function test_bounty_rules_adjustments_and_fulfilment(): void
    {
        $this->actingAs($this->user());
        $rule = RewardRule::firstOrFail();
        $this->put(route('bounty.rules.save'), ['r' => [$rule->id => ['label' => 'Renamed rule', 'stars' => 7, 'active' => 1]]])->assertRedirect();
        $this->assertSame('Renamed rule', $rule->fresh()->label);

        $c = Customer::where('status', 'Good - On Flow')->firstOrFail();
        $stars = (int) $c->stars;
        $this->post(route('bounty.adjust', $c), ['stars' => 15, 'reason' => 'Goodwill'])->assertRedirect();
        $this->assertSame($stars + 15, (int) $c->fresh()->stars);
        $this->post(route('bounty.adjust', $c), ['stars' => -($stars + 1000), 'reason' => 'Too many'])->assertSessionHasErrors('stars');

        $redemption = StarEntry::create(['customer_id' => $c->id, 'reason' => 'Redeemed: Test gift card', 'stars' => -1, 'reward_offer_id' => RewardOffer::value('id'), 'fulfillment' => 'pending']);
        $this->get(route('bounty.redemptions'))->assertOk()->assertSee('Test gift card');
        $this->post(route('bounty.fulfil', $redemption))->assertRedirect();
        $this->assertSame('sent', $redemption->fresh()->fulfillment);
    }

    public function test_rodeo_campaign_template_and_survey(): void
    {
        $this->actingAs($this->user('marketing@example.com'));
        $this->post(route('rodeo.templates.store'), ['name' => 'Survey invite', 'subject' => 'Tell us', 'body' => '<p>Hi {{first_name}}, <a href="{{survey:how-are-we-doing}}">take our survey</a></p>'])->assertRedirect();
        $template = EmailTemplate::where('name', 'Survey invite')->firstOrFail();

        $this->post(route('rodeo.campaigns.store'), ['name' => 'Fall survey', 'goal' => 'announcement', 'cap_days' => 0, 'channel' => 'Email', 'email_template_id' => $template->id, 'audience' => ['statuses' => ['Good - On Flow']]])->assertRedirect();
        $campaign = Campaign::where('name', 'Fall survey')->firstOrFail();
        $count = $campaign->audienceQuery()->count();
        $this->assertGreaterThan(0, $count);
        $this->post(route('rodeo.campaigns.send', $campaign))->assertRedirect()->assertSessionHas('status');
        // Addresses on the suppression list (Rodeo → Emails) are skipped
        $suppressed = $campaign->audienceQuery()->whereIn('email', EmailSuppression::select('email'))->count();
        $this->assertGreaterThan(0, $suppressed);
        $this->assertSame($count - $suppressed, ContactLog::where('campaign_id', $campaign->id)->count());

        $this->post(route('rodeo.channels.save'), ['msid' => '77001', 'name' => 'Radio spot', 'type' => 'Paid'])->assertRedirect();
        $this->post(route('rodeo.promos.save'), ['code' => 'rodeo10', 'description' => '$10 off', 'credit' => 10])->assertRedirect();
        $this->assertDatabaseHas('promo_codes', ['code' => 'RODEO10']);
    }

    public function test_email_template_send_test_page(): void
    {
        Mail::fake();
        $admin = $this->user();
        $this->actingAs($admin);
        $template = EmailTemplate::firstOrFail();
        $page = route('rodeo.templates.test', $template);
        $url = route('rodeo.templates.test.send', $template);
        $before = (int) $template->testSends()->max('id'); // the seed data has a sample test already

        // The template page and the list link to the Send Test page, which has every way to pick recipients
        $this->get(route('rodeo.templates.edit', $template))->assertOk()->assertSee($page, false)->assertDontSee('Recent Tests');
        $this->get(route('rodeo.templates'))->assertOk()->assertSee($page, false);
        $this->get($page)->assertOk()->assertSee('Send Test: '.$template->name)->assertSee('An admin team')->assertSee('Customer category')
            ->assertSee('Mail log only')->assertSee('Test History')->assertSee('Rodeo / <a', false);

        // Check Recipients and the live preview
        $sample = Customer::firstOrFail();
        $this->getJson(route('rodeo.templates.test.recipients', [$template, 'mode' => 'team', 'role_id' => $admin->role_id]))->assertOk()->assertJsonPath('count', User::where('role_id', $admin->role_id)->where('active', true)->count());
        $this->getJson(route('rodeo.templates.test.recipients', [$template, 'mode' => 'accounts', 'accounts' => '999']))->assertStatus(422)->assertJsonPath('error', 'No account 999');
        $this->getJson(route('rodeo.templates.test.preview', [$template, 'sample' => $sample->account, 'prefix' => '[QA]', 'note' => 'Logo check']))->assertOk()
            ->assertJsonPath('customer.account', $sample->account)->assertJson(fn ($j) => $j->where('subject', fn ($s) => str_starts_with($s, '[QA] '))->etc())
            ->assertSee('Logo check');

        // Just me, with a custom prefix and note
        $this->post($url, ['mode' => 'me', 'prefix' => '[QA]', 'note' => 'Logo check'])->assertRedirect($page)->assertSessionHas('test_result');
        Mail::assertSent(TemplateTest::class, fn ($m) => $m->hasTo($admin->email) && str_starts_with($m->subjectLine, '[QA] ') && str_contains($m->bodyHtml, 'Logo check'));

        // Typed addresses, filled in from a sample account
        $this->post($url, ['mode' => 'emails', 'emails' => "a@example.com, b@example.com\na@example.com", 'sample' => $sample->account])->assertRedirect();
        Mail::assertSentCount(3);
        Mail::assertSent(TemplateTest::class, fn ($m) => $m->hasTo('a@example.com') && str_starts_with($m->subjectLine, '[TEST] ') && str_contains($m->bodyHtml, e($sample->first_name)));
        $this->post($url, ['mode' => 'emails', 'emails' => 'not-an-email'])->assertSessionHasErrors('emails');

        // Account numbers: each customer gets their own fill-ins and a Contact Log entry (unless turned off)
        $c = Customer::whereNotNull('email')->latest('id')->firstOrFail();
        $this->post($url, ['mode' => 'accounts', 'accounts' => $c->account])->assertRedirect();
        $this->assertDatabaseHas('contact_logs', ['customer_id' => $c->id, 'template' => 'Test: '.$template->name]);
        $logs = ContactLog::where('customer_id', $c->id)->count();
        $this->post($url, ['mode' => 'accounts', 'accounts' => $c->account, 'log_contact' => '0'])->assertRedirect();
        $this->assertSame($logs, ContactLog::where('customer_id', $c->id)->count());
        $this->post($url, ['mode' => 'accounts', 'accounts' => '999'])->assertSessionHasErrors('accounts');

        // Bookmarks, an admin team, a customer category (multi-select statuses)
        $admin->bookmarks()->attach($c->id);
        $this->post($url, ['mode' => 'bookmarks'])->assertRedirect();
        $this->post($url, ['mode' => 'team', 'role_id' => $admin->role_id])->assertRedirect();
        $this->post($url, ['mode' => 'category', 'status' => ['Good - On Flow', 'Submitted'], 'limit' => 3])->assertRedirect();
        $this->post($url, ['mode' => 'category', 'limit' => 500])->assertSessionHasErrors('limit');

        $sends = $template->testSends()->where('id', '>', $before)->orderBy('id')->get();
        $this->assertSame(['me', 'emails', 'accounts', 'accounts', 'bookmarks', 'team', 'category'], $sends->pluck('mode')->all());
        $this->assertSame('logged', $sends->first()->status); // tests use the array mailer: kept, not delivered
        $this->assertSame('Logo check', $sends->first()->note);
        $this->assertSame(3, count($sends->last()->recipients));

        // Send Again repeats the same people and options
        $this->post(route('rodeo.templates.test.again', [$template, $sends->first()]))->assertRedirect($page);
        $again = $template->testSends()->latest('id')->first();
        $this->assertSame(['me', 'Logo check'], [$again->mode, $again->note]);
        $this->get($page)->assertSee('a@example.com')->assertSee('Send Again');
        $this->get($page.'?result=failed')->assertOk()->assertSee('No tests with that result');
    }
}
