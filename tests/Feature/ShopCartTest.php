<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ShopOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Shop cart and checkout: add to cart, change quantities, check out in My Account, orders. */
class ShopCartTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_cart_checkout_and_orders(): void
    {
        $this->get('/shop/cart')->assertOk()->assertSee('Your cart is empty');
        $this->postJson('/shop/cart', ['sku' => 'EV-200', 'qty' => 2])->assertOk()->assertJsonPath('count', 2);
        $this->postJson('/shop/cart', ['sku' => 'PR-510'])->assertJsonPath('count', 3);
        $this->postJson('/shop/cart', ['sku' => 'PR-510'])->assertJsonPath('count', 3);          // protection plans: one each
        $this->postJson('/shop/cart', ['sku' => 'TH-130'])->assertStatus(422);                   // out of stock
        $this->postJson('/shop/cart', ['sku' => 'NOPE'])->assertUnprocessable();
        $this->getJson('/site/session')->assertJsonPath('cart', 3);

        // 2 × $649 + 8.25% tax, plus $179.88/12 a month on the bill
        $this->get('/shop/cart')->assertOk()->assertSee('Level 2 Home EV Charger 48A')->assertSee('$1,298.00')->assertSee('$107.09')->assertSee('$1,405.09')->assertSee('$14.99/mo');
        $this->patch('/shop/cart/EV-200', ['qty' => 1])->assertRedirect('/shop/cart');
        $this->get('/shop/cart')->assertSee('$702.54');

        // Checkout is in My Account
        $this->get('/myaccount/checkout')->assertRedirect('/myaccount/login');
        $c = Customer::where('username', 'demo.customer')->firstOrFail();
        $this->post(route('myaccount.login.test', $c));
        $this->get('/myaccount/checkout')->assertOk()->assertSee('Place Order')->assertSee('Add $702.54 to my next electricity bill')->assertSee('Cart &amp; Checkout (2)', false);
        $this->post('/myaccount/checkout', ['pay_with' => 'bill', 'ship' => 'other'])->assertSessionHasErrors(['street', 'city', 'zip', 'agree']);
        $stars = $c->stars;
        $this->post('/myaccount/checkout', ['pay_with' => 'bill', 'ship' => 'service', 'agree' => 1])->assertRedirect();
        $o = ShopOrder::where('customer_id', $c->id)->firstOrFail();
        $this->assertSame(['702.54', '14.99', 'bill', 'On next bill'], [$o->total, $o->monthly, $o->pay_with, $o->payment_status]);
        $this->assertTrue($c->hasProduct('Air Conditioning Protection Program'));
        $this->assertSame($stars + 1200 + 200, $c->fresh()->stars);
        $this->get('/shop/cart')->assertSee('Your cart is empty');
        $this->get('/myaccount/orders')->assertOk()->assertSee($o->number);
        $this->get('/myaccount/orders/'.$o->number)->assertOk()->assertSee('What happens next')->assertSee('Added to your next electricity bill');

        // Card payment with someone else's card is refused; another customer's order is hidden
        $this->postJson('/shop/cart', ['sku' => 'PL-400']);
        $other = PaymentMethod::where('customer_id', '!=', $c->id)->firstOrFail();
        $this->post('/myaccount/checkout', ['pay_with' => 'card', 'method' => $other->id, 'ship' => 'service', 'agree' => 1])->assertSessionHasErrors('method');
        $theirs = ShopOrder::create(['number' => 'SO-999999', 'customer_id' => Customer::where('id', '!=', $c->id)->value('id'), 'items' => [], 'subtotal' => 1, 'tax' => 0, 'total' => 1, 'pay_with' => 'bill']);
        $this->get('/myaccount/orders/'.$theirs->number)->assertNotFound();
    }

    public function test_account_menu_is_never_a_scroll_box(): void
    {
        $css = file_get_contents(public_path('frontend/css/style.css'));
        preg_match_all('/\.ma-nav\s*\{[^}]*\}/', $css, $m);
        foreach ($m[0] as $rule) {
            $this->assertStringNotContainsString('max-height: calc', $rule);
            $this->assertStringNotContainsString('overflow-y: auto', $rule);
            $this->assertStringNotContainsString('sticky', $rule);
        }
    }
}
