<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Corral → Products: the Shop's products (add, edit) and ordering for a customer by phone like the website. */
class CorralShopTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_products_can_be_added_and_show_in_the_website_shop(): void
    {
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail());
        $this->get(route('corral.shop.index'))->assertOk()->assertSee('ecobee Smart Thermostat Premium');
        $this->post(route('corral.shop.store'), ['sku' => 'pl-999', 'name' => 'Sample Smart Strip', 'cat' => 'plugs', 'price' => '39.99', 'stars' => '50', 'icon' => 'plug', 'stock' => '1', 'active' => '1'])->assertRedirect();
        $p = ShopProduct::where('sku', 'PL-999')->firstOrFail();
        $this->get(route('corral.shop.show', $p))->assertOk()->assertSee('Order for a Customer');
        auth()->logout();
        $this->get('/shop/pl-999')->assertOk()->assertSee('Sample Smart Strip');
        $this->actingAs(User::where('email', 'admin@example.com')->firstOrFail())->put(route('corral.shop.update', $p), ['sku' => 'PL-999', 'name' => 'Sample Smart Strip', 'cat' => 'plugs', 'price' => '39.99', 'stars' => '50', 'icon' => 'plug', 'stock' => '1', 'active' => '0']);
        auth()->logout();
        $this->get('/shop/pl-999')->assertNotFound();
    }

    public function test_order_products_for_a_customer_by_phone(): void
    {
        $rep = User::where('email', 'admin@example.com')->firstOrFail();
        $this->actingAs($rep);
        $c = Customer::orderBy('id')->firstOrFail();
        $stars = $c->stars;
        $this->get(route('corral.customers.show', $c))->assertOk()->assertSee(route('corral.customers.shop', $c), false);
        $this->get(route('corral.customers.shop', [$c, 'sku' => 'TH-100']))->assertOk()->assertSee('Place Order')->assertSee('value="1"', false);
        $this->post(route('corral.customers.shop.place', $c), ['qty' => ['TH-100' => 0], 'pay_with' => 'bill', 'ship' => 'service', 'agree' => '1'])->assertSessionHasErrors('qty');
        $this->post(route('corral.customers.shop.place', $c), ['qty' => ['TH-100' => 1, 'PR-510' => 3], 'pay_with' => 'bill', 'ship' => 'service'])->assertSessionHasErrors('agree');
        $this->post(route('corral.customers.shop.place', $c), ['qty' => ['TH-100' => 1, 'PR-510' => 3], 'pay_with' => 'bill', 'ship' => 'service', 'agree' => '1'])->assertRedirect();
        $o = ShopOrder::latest('id')->firstOrFail();
        $this->assertSame($rep->id, $o->placed_by);
        $this->assertSame(1, collect($o->items)->firstWhere('sku', 'PR-510')['qty']);   // protection plans: one each
        $this->assertGreaterThan($stars, $c->fresh()->stars);
        $this->assertTrue($c->fresh()->hasProduct('Air Conditioning Protection Program'));
        $this->get(route('corral.shop.orders'))->assertOk()->assertSee($o->number);
        $this->get(route('corral.shop.orders.show', $o))->assertOk()->assertSee('by phone');
        $this->patch(route('corral.shop.orders.update', $o), ['status' => 'Shipped'])->assertRedirect();
        $this->assertSame('Shipped', $o->fresh()->status);
    }
}
