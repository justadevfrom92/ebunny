<?php

namespace App\Http\Controllers\Frontend\MyAccount;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ShopOrder;
use App\Services\Cart;
use App\Services\ShopCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * My Account → Cart & Checkout and Orders. Checkout ships to the service address (or another
 * address), pays with a saved card or adds the amount to the next electricity bill, adds any
 * protection plans to the account, and credits the Rewards stars the items earn.
 */
class CheckoutController extends Controller
{
    private function c(Request $request): Customer
    {
        $login = $request->user('customer');
        $id = $request->session()->get('ma_property');

        return $id && $id !== $login->id ? ($login->properties()->firstWhere('id', $id) ?? $login) : $login;
    }

    private function methods(Request $request, Customer $c)
    {
        return PaymentMethod::whereIn('customer_id', array_unique([$c->id, $request->user('customer')->id]))->whereNull('removed_at')->orderByDesc('autopay')->get();
    }

    public function form(Request $request, Cart $cart): View|RedirectResponse
    {
        $summary = $cart->summary();
        if ($summary['lines']->isEmpty()) {
            return redirect()->route('shop.cart')->with('status', 'Your cart is empty. Add something from the Shop first.');
        }
        $c = $this->c($request);

        return view('frontend.myaccount.checkout', ['c' => $c, 'cart' => $summary, 'methods' => $this->methods($request, $c)]);
    }

    public function place(Request $request, Cart $cart): RedirectResponse
    {
        $summary = $cart->summary();
        abort_if($summary['lines']->isEmpty(), 422, 'Your cart is empty.');
        if ($summary['unavailable']->isNotEmpty()) {
            return back()->withErrors(['cart' => $summary['unavailable']->pluck('name')->implode(', ').' went out of stock. Remove it from your cart to continue.']);
        }
        $c = $this->c($request);
        $data = $request->validate([
            'pay_with' => ['required', Rule::in(['card', 'bill'])],
            'method' => ['nullable', 'required_if:pay_with,card', Rule::in($this->methods($request, $c)->pluck('id')->all())],
            'ship' => ['required', Rule::in(['service', 'other'])],
            'street' => ['nullable', 'required_if:ship,other', 'string', 'max:120'],
            'city' => ['nullable', 'required_if:ship,other', 'string', 'max:60'],
            'zip' => ['nullable', 'required_if:ship,other', 'digits:5'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => 'Please agree to the order terms.', 'method.required_if' => 'Pick the card to pay with.']);

        $order = ShopCheckout::place($c, $summary, ['pay_with' => $data['pay_with'], 'method' => $data['method'] ?? null, 'ship_to' => ShopCheckout::shipTo($c, $data)]);
        $cart->clear();

        return redirect()->route('myaccount.order', $order)->with('status', 'Thanks! Order '.$order->number.' is placed.');
    }

    public function orders(Request $request): View
    {
        $c = $this->c($request);

        return view('frontend.myaccount.orders', ['c' => $c, 'orders' => ShopOrder::where('customer_id', $c->id)->latest('id')->get()]);
    }

    public function order(Request $request, ShopOrder $order): View
    {
        $c = $this->c($request);
        abort_unless($order->customer_id === $c->id, 404);

        return view('frontend.myaccount.order', ['c' => $c, 'o' => $order->load('method')]);
    }
}
