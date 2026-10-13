<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\SiteBlock;
use App\Models\SiteVisit;
use App\Models\User;
use App\Support\SiteTraffic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lando → Site: dashboard, health, visitors, blocked IPs and areas, and each page's activity. */
class LandoSiteTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_every_site_page_opens(): void
    {
        $this->actingAs($this->admin());
        $plans = Page::where('path', 'plans')->firstOrFail();

        $this->get('/admin/lando')->assertOk()->assertSee('Website Dashboard')->assertSee('On the Site Now')->assertSee('My Account Users')->assertSee('Page Views by Hour');
        $this->get(route('lando.health'))->assertOk()->assertSee('Database')->assertSee('Sitemap')->assertSee('About This Server');
        $this->get(route('lando.visitors'))->assertOk()->assertSee('Search Visitors')->assertSee('Block IP');
        $this->get(route('lando.visitors', ['who' => 'online']))->assertOk()->assertSee('On now');
        $this->get(route('lando.visitors', ['blocked' => '1', 'ip' => '192.0.2.*']))->assertOk()->assertSee('Turned away');
        $this->get(route('lando.blocked.ips'))->assertOk()->assertSee('203.0.113.66')->assertSee('Scraping');
        $this->get(route('lando.blocked.areas'))->assertOk()->assertSee('/wp-admin')->assertSee('Position filled');
        $this->get(route('lando.blocked.ips.create', ['value' => '198.51.100.9']))->assertOk()->assertSee('198.51.100.9');
        $this->get(route('lando.blocked.areas.edit', SiteBlock::where('value', 'wp-admin')->first()))->assertOk()->assertSee('page views');
        $this->get(route('lando.blocked.ips.edit', SiteBlock::where('value', 'wp-admin')->first()))->assertNotFound();
        $this->get(route('lando.pages.activity', $plans))->assertOk()->assertSee('Views by Day')->assertSee('History')->assertSee('Recent Visits');
        $this->get(route('lando.pages.index'))->assertOk()->assertSee('Views this week')->assertSee('Activity');
    }

    public function test_page_views_and_heartbeat(): void
    {
        $res = $this->getJson('/site/session?path=/plans.html&ref=https://www.google.com/search')->assertOk();
        $visit = SiteVisit::findOrFail($res->json('visit'));
        $this->assertSame('byop', SiteTraffic::normalize('/build-your-own-plan.html')[0]);
        $this->assertSame('/', $visit->path === '/' ? '/' : SiteTraffic::normalize('/index.html')[0]);
        $this->assertSame(Page::where('file', 'plans.html')->value('id'), $visit->page_id);
        $this->assertSame('https://www.google.com/search', $visit->referrer);
        $res->assertCookie(SiteTraffic::COOKIE);

        // The heartbeat only works for the browser that made the visit
        $this->travel(3)->minutes();
        $this->withCookie(SiteTraffic::COOKIE, 'someone-else-entirely-0000')->post('/site/ping?v='.$visit->id)->assertOk();
        $this->assertTrue($visit->fresh()->seen_at->lt(now()->subMinute()));
        $this->withCookie(SiteTraffic::COOKIE, $visit->visitor)->post('/site/ping?v='.$visit->id)->assertOk();
        $this->assertTrue($visit->fresh()->seen_at->gte(now()->subSeconds(5)));

        // No path: just the session, no page view
        $before = SiteVisit::count();
        $this->getJson('/site/session')->assertOk()->assertJsonPath('visit', null);
        $this->assertSame($before, SiteVisit::count());
    }

    public function test_blocked_ip_and_area_keep_visitors_out_but_never_the_admin(): void
    {
        $this->actingAs($this->admin());
        $this->post(route('lando.blocked.ips.store'), ['value' => '127.0.0.1', 'active' => 1])->assertSessionHasErrors('value');   // your own address
        $this->post(route('lando.blocked.ips.store'), ['value' => 'not an ip', 'active' => 1])->assertSessionHasErrors('value');
        $this->post(route('lando.blocked.areas.store'), ['value' => '/admin', 'active' => 1])->assertSessionHasErrors('value');
        $this->post(route('lando.blocked.ips.store'), ['value' => '10.9.8.*', 'reason' => 'Test', 'message' => 'Go away', 'active' => 1])->assertRedirect(route('lando.blocked.ips'));
        $this->post(route('lando.blocked.areas.store'), ['value' => '/careers/', 'active' => 1])->assertRedirect(route('lando.blocked.areas'));
        $this->assertTrue(SiteBlock::where('type', 'area')->where('value', 'careers')->exists());

        // Blocked IP: Laravel pages answer 403, static pages are told by /site/session; the admin still opens
        $from = fn () => $this->withServerVariables(['REMOTE_ADDR' => '10.9.8.7']);
        $from()->get('/about-us')->assertForbidden()->assertSee('Go away');
        $from()->getJson('/site/session?path=/index.html')->assertOk()->assertJsonPath('blocked.message', 'Go away')->assertJsonPath('visit', null);
        $from()->get('/admin/lando')->assertOk();
        $this->assertTrue(SiteVisit::where('ip', '10.9.8.7')->where('blocked', true)->exists());
        $this->withServerVariables(['REMOTE_ADDR' => '10.9.9.1'])->get('/about-us')->assertOk();

        // Blocked area: the path and everything under it, for everyone
        $this->get('/careers')->assertForbidden();
        $this->get('/careers/lead-developer')->assertForbidden();
        $this->get('/careers-are-fun')->assertNotFound();
        $area = SiteBlock::where('value', 'careers')->first();
        $this->assertSame(2, $area->fresh()->hits);
        $this->post(route('lando.blocked.areas.toggle', $area))->assertRedirect();
        $this->get('/careers')->assertOk();

        // Expired blocks stop on their own
        $ip = SiteBlock::where('value', '10.9.8.*')->first();
        $ip->update(['expires_at' => now()->subMinute()]);
        SiteTraffic::forget();
        $from()->get('/about-us')->assertOk();
        $this->delete(route('lando.blocked.ips.destroy', $ip))->assertRedirect();
        $this->assertModelMissing($ip);
    }

    public function test_page_list_shows_who_is_editing_and_viewing(): void
    {
        $this->actingAs($this->admin());
        $page = Page::where('path', 'about-us')->firstOrFail();
        $this->get(route('lando.pages.edit', $page))->assertOk();
        SiteVisit::create(['visitor' => str_repeat('a', 32), 'ip' => '198.51.100.7', 'path' => 'about-us', 'page_id' => $page->id, 'created_at' => now(), 'seen_at' => now()]);

        $this->get(route('lando.pages.index'))->assertOk()->assertSee('Editing: Admin Demo')->assertSee('1 on it');
        $this->get(route('lando.pages.activity', $page))->assertOk()->assertSee('Admin Demo')->assertSee('198.51.100.7');

        $page->update(['meta_description' => 'Who we are']);
        $this->get(route('lando.pages.activity', $page))->assertSee('meta description');
    }

    public function test_blocked_entry_page_has_unblock_and_remove_and_pages_have_history(): void
    {
        $this->actingAs($this->admin());
        $block = SiteBlock::where('type', 'ip')->where('active', true)->firstOrFail();
        $this->get(route('lando.blocked.ips.edit', $block))->assertOk()->assertSee('Unblock')->assertSee('Remove')
            ->assertSee(route('lando.blocked.ips.toggle', $block), false)->assertSee(route('lando.blocked.ips.destroy', $block), false);
        $this->post(route('lando.blocked.ips.toggle', $block))->assertRedirect();
        $this->assertFalse($block->fresh()->active);
        $this->get(route('lando.blocked.ips.edit', $block))->assertSee('Block Again');
        $this->delete(route('lando.blocked.ips.destroy', $block))->assertRedirect(route('lando.blocked.ips'));
        $this->assertNull($block->fresh());

        $page = Page::where('path', 'plans')->firstOrFail();
        $this->get(route('lando.pages.activity', $page))->assertOk()->assertSee('Page created: '.$page->title)->assertDontSee('No changes recorded.');
    }

    public function test_tooltip_code_is_not_inside_tables(): void
    {
        $this->actingAs(User::where('email', 'finance@example.com')->firstOrFail());
        $html = $this->get(route('walker.uploads.index'))->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, 'window.__chartTip = true'));
        $this->assertDoesNotMatchRegularExpression('#<td[^>]*>(?:(?!</td>).)*<style#s', $html);
    }
}
