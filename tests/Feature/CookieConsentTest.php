<?php

namespace Tests\Feature;

use App\Models\CookieConsent;
use App\Models\Customer;
use App\Models\User;
use App\Support\SiteTraffic;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
