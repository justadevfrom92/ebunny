<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Rodeo → Campaigns: goal, audience chips with a live count, tracking, scheduling, duplicate and results. */
class CampaignsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_list_builder_and_results(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('rodeo.campaigns'))->assertOk()->assertSee('Scheduled')->assertSee('Drafts')->assertSee('Converted')->assertSee('Double Stars Weekend');
        $this->get(route('rodeo.campaigns.create'))->assertOk()->assertSee('Goal')->assertSee('Audience')->assertSee('Tracking')->assertDontSee('name="audience[market_id]"', false);

        $sent = Campaign::where('name', 'Fall Renewal Reminder')->firstOrFail();
        $this->assertGreaterThan(0, $sent->results()['converted']);
        $html = $this->get(route('rodeo.campaigns.edit', $sent))->assertOk()->assertSee('Recipients')->assertSee('Converted')->getContent();
        $this->assertDoesNotMatchRegularExpression('#/admin/corral/(?!customers/\d+(/ticket|/logs/\w+)?")#', $html);   // only account links go to Corral
    }

    public function test_count_save_schedule_duplicate_and_job(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $all = $this->getJson(route('rodeo.campaigns.count', ['channel' => 'SMS', 'cap_days' => 0]))->assertOk()->json();
        $some = $this->getJson(route('rodeo.campaigns.count', ['channel' => 'SMS', 'cap_days' => 0, 'audience' => ['autopay' => 'no']]))->assertOk()->json();
        $this->assertGreaterThan(0, $some['count']);
        $this->assertLessThan($all['count'], $some['count']);
        $this->assertArrayHasKey('suppressed', $all['excluded']);

        $this->post(route('rodeo.campaigns.store'), ['name' => 'Product test', 'goal' => 'product', 'channel' => 'SMS', 'cap_days' => 7])->assertSessionHasErrors('product');
        $this->post(route('rodeo.campaigns.store'), ['name' => 'Text test', 'goal' => 'announcement', 'channel' => 'SMS', 'cap_days' => 0,
            'message' => 'Hi {{first_name}}, use {{promo_code}}', 'promo_code' => PromoCode::value('code'), 'audience' => ['autopay' => 'no']])->assertRedirect();
        $c = Campaign::where('name', 'Text test')->firstOrFail();
        $this->assertSame($some['count'], $c->audienceQuery()->count());
        $this->assertStringNotContainsString('{{', $c->textFor($c->audienceQuery()->first()));

        $this->post(route('rodeo.campaigns.schedule', $c), ['date' => now()->addDay()->toDateString(), 'time' => '09:00'])->assertRedirect();
        $this->assertSame('scheduled', $c->fresh()->status);
        $this->post(route('rodeo.campaigns.unschedule', $c))->assertRedirect();
        $this->assertSame('draft', $c->fresh()->status);

        $this->post(route('rodeo.campaigns.duplicate', $c))->assertRedirect();
        $this->assertSame(2, Campaign::where('name', 'like', 'Text test%')->count());

        $c->update(['status' => 'scheduled', 'scheduled_at' => now()->subMinute()]);
        $this->artisan('et:campaigns')->assertSuccessful();
        $this->assertSame('sent', $c->fresh()->status);
        $this->assertGreaterThan(0, $c->results()['sent']);
    }
}
