<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\EmailTemplate;
use App\Models\HistoryItem;
use App\Models\LinkClick;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Drawing entries in star history, top members, heat maps, cookies allowed, the Lando Activity button and Astro's BYOP links. */
class RewardsMarketingBatchTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    public function test_drawing_entries_show_in_star_history(): void
    {
        $c = Customer::findOrFail(HistoryItem::where('model', 'ItemDrawingEntry_model')->value('customer_id'));
        $this->assertTrue($c->starHistory()->contains('drawing', true));
        $this->get(route('bounty.members', ['account' => $c->account]))->assertOk()->assertSee('Monthly drawing entry');
        $this->get(route('corral.customers.stars', $c))->assertOk()->assertSee('Monthly drawing entry');
    }

    public function test_top_members_and_winner_links(): void
    {
        $top = Customer::orderByDesc('stars')->firstOrFail();
        $this->get(route('bounty.members'))->assertOk()->assertSee('Top Members')->assertSee($top->name);
        $this->get(route('bounty.drawing'))->assertOk()->assertSee('Past Winners')->assertSee('/admin/corral/customers/', false);
    }

    public function test_click_heat_maps(): void
    {
        $t = EmailTemplate::findOrFail(LinkClick::whereNotNull('email_template_id')->value('email_template_id'));
        $this->get(route('rodeo.templates.edit', $t))->assertSee(route('rodeo.templates.heatmap', $t), false);
        $this->get(route('rodeo.templates.heatmap', $t))->assertOk()->assertSee('Where People Click')->assertSee('data-link="footer-1"', false)->assertSee('data-link="header-1"', false);
        $sent = Campaign::where('status', 'sent')->firstOrFail();
        $this->get(route('rodeo.campaigns.heatmap', $sent))->assertOk()->assertSee('Clicks by Link');
        $sms = Campaign::where('channel', 'SMS')->firstOrFail();
        $this->get(route('rodeo.campaigns.heatmap', $sms))->assertOk();
    }

    public function test_cookies_allowed_lando_activity_and_astro_menu(): void
    {
        $this->get(route('lando.consents'))->assertOk()->assertSee('_ga')->assertSee('Cookies Allowed')->assertSee('site_session');
        $page = Page::firstOrFail();
        $this->get(route('lando.pages.edit', $page))->assertOk()->assertSee(route('lando.pages.activity', $page), false);
        $this->get(route('astro.plans.index'))->assertOk()->assertSee('>Build Your Own<', false)->assertSee('>BYOP Discounts<', false)->assertDontSee('Upload BYOP');
    }
}
