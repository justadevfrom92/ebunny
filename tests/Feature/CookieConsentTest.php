<?php

namespace Tests\Feature;

use App\Models\CookieConsent;
use App\Models\Customer;
use App\Models\User;
use App\Support\SiteTraffic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** The website cookie banner: choices are stored for anonymous visitors, customers and employees, and shown in Lando. */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_anonymous_visitor_choice_is_stored_and_remembered(): void
    {
        $this->getJson('/site/session?path=/index.html')->assertJsonPath('consent', null);
        $res = $this->postJson('/site/consent', ['choice' => 'necessary', 'page' => '/index.html'])->assertOk()->assertJsonPath('consent.analytics', false);
        $visitor = CookieConsent::latest('id')->firstOrFail()->visitor;
        $res->assertCookie(SiteTraffic::COOKIE);
        $this->assertNull(CookieConsent::latest('id')->first()->customer_id);

        $this->withCredentials()->withCookie(SiteTraffic::COOKIE, $visitor)->getJson('/site/session?path=/plans.html')->assertJsonPath('consent.choice', 'necessary');
        $this->withCredentials()->withCookie(SiteTraffic::COOKIE, 'someoneelse0000000000000000000')->getJson('/site/session')->assertJsonPath('consent', null);
        $this->postJson('/site/consent', ['choice' => 'nope'])->assertUnprocessable();
    }

    public function test_signed_in_customer_choice_follows_them_and_lando_lists_it(): void
    {
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $this->postJson('/site/consent', ['choice' => 'custom', 'analytics' => true, 'marketing' => false])->assertJsonPath('consent.analytics', true);
        $k = CookieConsent::latest('id')->firstOrFail();
        $this->assertSame([$c->id, 'custom', true, false], [$k->customer_id, $k->choice, $k->analytics, $k->marketing]);
        // Another browser, same customer: already answered
        $this->withCredentials()->withCookie(SiteTraffic::COOKIE, 'otherbrowser00000000000000000000')->getJson('/site/session')->assertJsonPath('consent.choice', 'custom');

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($admin, 'web')->get(route('lando.consents'))->assertOk()->assertSee('Cookie Consents')->assertSee($c->account)->assertSee('Chose');
        $this->get(route('lando.consents', ['who' => 'anonymous']))->assertOk()->assertDontSee($c->account);
    }

    public function test_each_choice_has_a_page_with_its_cookie_cache(): void
    {
        // The browser's cookies are kept with the choice; sign-in tokens are cut short
        $this->withHeader('Cookie', 'site_session=abcdefghijklmnopqrstuvwxyz; _ga=GA1.1.123.456; stray_cookie=1')
            ->postJson('/site/consent', ['choice' => 'necessary', 'page' => '/plans.html'])->assertOk();
        $k = CookieConsent::latest('id')->firstOrFail();
        $this->assertSame('abcdefgh…', $k->cookie_jar['site_session']);
        $this->assertSame(['_ga'], $k->unexpected());

        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail(), 'web');
        $this->get(route('lando.consents'))->assertSee(route('lando.consents.show', $k))->assertSee('data-auto-search', false)->assertDontSee('>Search</button>', false);
        $this->get(route('lando.consents.show', $k))->assertOk()->assertSee('Cookie Cache')->assertSee('GA1.1.123.456')->assertSee('present but not allowed')
            ->assertSee('stray_cookie')->assertSee('Not on our list')->assertDontSee('abcdefghijklmnop');
        // Search finds it by IP
        $this->get(route('lando.consents', ['q' => $k->ip]))->assertSee(route('lando.consents.show', $k));
    }

    public function test_demo_export_writes_only_the_chosen_pages(): void
    {
        $out = storage_path('framework/testing/demo-'.uniqid());
        config(['demo.pages' => ['/admin/ranger', '/admin/ranger/tickets']]);
        $this->artisan('demo:export', ['--out' => $out])->assertSuccessful();
        $dash = file_get_contents($out.'/admin/ranger/index.html');
        $this->assertStringContainsString('href="../../admin/ranger/tickets/index.html"', $dash);   // a chosen page: linked
        $this->assertStringContainsString('data-demo-off', $dash);                                     // everything else: no link
        $this->assertStringNotContainsString('href="http://', preg_replace('#href="https?://(fonts|cdn)[^"]*"#', '', $dash));
        $this->assertStringNotContainsString('Energy Texas', $dash);                                    // placeholder brand
        $this->assertFileExists($out.'/backend/admin.css');
        $this->assertFileExists($out.'/shared/config/brand.js');
        $this->assertFileExists($out.'/index.html');
        File::deleteDirectory($out);
    }
}
