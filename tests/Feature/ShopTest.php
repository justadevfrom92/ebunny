<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sample Shop page: filters, sorts and paging work on the server (no JavaScript needed). */
class ShopTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_filters_sorts_and_pages(): void
    {
        $this->get('/shop')->assertOk()->assertSee('26</b> products', false)->assertSee('showing 1–12')->assertSee('Price: low to high');
        $this->get('/shop?page=3')->assertOk()->assertSee('showing 25–26');

        // Category + sort by price
        $html = $this->get('/shop?cat[]=thermostats&sort=price_asc')->assertOk()->assertSee('5</b> products', false)->getContent();
        preg_match_all('/<strong>\$([\d,.]+)<\/strong>/', $html, $m);
        $prices = array_map(fn ($p) => (float) str_replace(',', '', $p), $m[1]);
        $sorted = $prices;
        sort($sorted);
        $this->assertSame($sorted, $prices);
        $this->assertCount(5, $prices);

        // More filters, with chips to remove each one
        $this->get('/shop?sale=1&stock=1&rating=4.5')->assertOk()->assertSee('On sale')->assertSee('In stock')->assertSee('4.5★ &amp; up', false)->assertDontSee('Honeywell T9');
        $this->get('/shop?min=500&max=700')->assertOk()->assertSee('Level 2 Home EV Charger 48A')->assertDontSee('Canvas Tote');
        $this->get('/shop?q=charger')->assertOk()->assertSee('EV Charger Protection Program')->assertDontSee('Smart Color Bulbs');
        $this->get('/shop?q=zzzz')->assertOk()->assertSee('No products match');
        $this->get('/shop?cat[]=nope')->assertRedirect();
        $this->get('/shop?sort=random')->assertRedirect();
    }
}
