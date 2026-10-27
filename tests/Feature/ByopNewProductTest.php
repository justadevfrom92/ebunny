<?php

namespace Tests\Feature;

use App\Models\ByopProduct;
use App\Models\User;
use App\Services\Catalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Astro → Build Your Own → New Product: a new add-on the website's Build Your Own Plan page shows. */
class ByopNewProductTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_new_product_is_added_and_sent_to_the_website(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('astro.products.index'))->assertOk()->assertSee(route('astro.products.create'), false);
        $this->get(route('astro.products.create'))->assertOk()->assertSee('Line under the name');
        $this->post(route('astro.products.store'), ['name' => 'Home Energy Audit', 'description' => 'A pro checks your home for leaks.', 'step' => 'Save More',
            'type' => 'Monthly', 'rate_adj' => '0', 'monthly' => '4.99', 'active' => '1'])->assertRedirect(route('astro.products.index'));
        $p = ByopProduct::where('key', 'homeenergyaudit')->firstOrFail();
        $this->assertSame('ItemProductHomeenergyaudit_model', $p->model);
        $this->assertEqualsWithDelta(4.99, $p->monthly, 0.001);
        // The website's catalog data includes it (the page adds the card on the Save More tab)
        $this->assertTrue(collect(app(Catalog::class)->siteData()['BYOP_PRODUCTS'] ?? [])->contains('key', 'homeenergyaudit'));
        $this->put(route('astro.products.save', $p), ['name' => 'Home Energy Audit', 'step' => 'Save More', 'type' => 'Monthly', 'rate_adj' => '0', 'monthly' => '5.99', 'active' => '0'])->assertRedirect();
        $this->assertFalse($p->fresh()->active);
    }
}
