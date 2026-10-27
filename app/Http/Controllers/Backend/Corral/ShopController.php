<?php

namespace App\Http\Controllers\Backend\Corral;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\ShopOrder;
use App\Models\ShopProduct;
use App\Services\Cart;
use App\Services\ShopCheckout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Corral → Products: the website Shop's products (add and edit them), every shop order, and ordering for a
 * customer on the phone the same way they would on the website (cart, payment, shipping, stars).
 */
class ShopController extends Controller
{
    public function index(Request $request): View
    {
        $cats = config('shop.categories');
        $cat = array_key_exists((string) $request->query('cat'), $cats) ? $request->query('cat') : null;
        $sold = collect(ShopOrder::pluck('items')->flatten(1))->groupBy('sku')->map(fn ($l) => $l->sum('qty'));

        return view('backend.corral.shop.index', ['cats' => $cats, 'cat' => $cat, 'sold' => $sold,
            'products' => ShopProduct::when($cat, fn ($q) => $q->where('cat', $cat))->orderBy('position')->orderBy('id')->paginate(10)->withQueryString(),
            'counts' => ShopProduct::selectRaw('cat, count(*) as n')->groupBy('cat')->pluck('n', 'cat')]);
    }

    public function show(ShopProduct $product): View
    {
        $orders = ShopOrder::with('customer')->latest('id')->get()->filter(fn ($o) => collect($o->items)->contains('sku', $product->sku))->take(10);

        return view('backend.corral.shop.show', ['p' => $product, 'cats' => config('shop.categories'), 'orders' => $orders,
            'sold' => collect(ShopOrder::pluck('items')->flatten(1))->where('sku', $product->sku)->sum('qty')]);
    }

    public function form(?ShopProduct $product = null): View
    {
        return view('backend.corral.shop.form', ['p' => $product ?? new ShopProduct(['active' => true, 'stock' => true, 'icon' => 'plug', 'cat' => array_key_first(config('shop.categories'))]),
            'cats' => config('shop.categories')]);
    }

    public function save(Request $request, ?ShopProduct $product = null): RedirectResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'alpha_dash', 'max:20', Rule::unique('shop_products', 'sku')->ignore($product)],
            'name' => ['required', 'string', 'max:120'],
            'cat' => ['required', Rule::in(array_keys(config('shop.categories')))],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'was' => ['nullable', 'numeric', 'gt:price', 'max:100000'],
            'stars' => ['required', 'integer', 'min:0', 'max:100000'],
            'badge' => ['nullable', 'string', 'max:40'],
            'icon' => ['required', Rule::in(ShopProduct::ICONS)],
            'blurb' => ['nullable', 'string', 'max:300'],
        ], ['was.gt' => 'The "was" price has to be higher than the price (it shows as a sale).']);
        $data['sku'] = strtoupper($data['sku']);
        $data['stock'] = $request->boolean('stock');
        $data['active'] = $request->boolean('active');
        if (! $product) {
            $data['position'] = (int) ShopProduct::max('position') + 1;
        }
        $product = $product ? tap($product)->update($data) : ShopProduct::create($data);

        return redirect()->route('corral.shop.show', $product)->with('status', $product->name.' saved.'.($product->active ? ' It\'s in the website Shop.' : ' It\'s hidden from the website Shop.'));
    }

    public function orders(Request $request): View
    {
        $status = in_array($request->query('status'), ['Placed', 'Shipped', 'Delivered', 'Canceled'], true) ? $request->query('status') : null;

        return view('backend.corral.shop.orders', ['status' => $status,
            'orders' => ShopOrder::with(['customer', 'placedBy'])->when($status, fn ($q) => $q->where('status', $status))->latest('id')->paginate(10)->withQueryString()]);
    }

    public function order(ShopOrder $order): View
    {
        return view('backend.corral.shop.order', ['o' => $order->load(['customer', 'method', 'placedBy'])]);
    }

    public function updateOrder(Request $request, ShopOrder $order): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['Placed', 'Shipped', 'Delivered', 'Canceled'])]]);
        $order->update($data);
        $order->customer?->notes()->create(['user_id' => $request->user()->id, 'author' => $request->user()->name, 'category' => 'Shop', 'action' => 'Shop Order', 'priority' => 'Low',
            'body' => 'Shop order '.$order->number.' marked '.$data['status'].'.']);

        return back()->with('status', 'Order '.$order->number.' is '.$data['status'].'.');
    }

    /** Order for a customer on the phone: pick products like the website Shop, then check out as they would. */
    public function orderForm(Request $request, Customer $customer): View
    {
        $items = collect((array) $request->old('qty', $request->query('sku') ? [strtoupper((string) $request->query('sku')) => 1] : []))->map(fn ($q) => (int) $q)->filter()->all();

        return view('backend.corral.shop.new-order', ['c' => $customer, 'cats' => config('shop.categories'), 'qty' => $items,
            'products' => ShopProduct::where('active', true)->orderBy('position')->get()->groupBy('cat'),
            'methods' => PaymentMethod::where('customer_id', $customer->id)->whereNull('removed_at')->orderByDesc('autopay')->get()]);
    }

    public function placeOrder(Request $request, Customer $customer): RedirectResponse
    {
        $methods = PaymentMethod::where('customer_id', $customer->id)->whereNull('removed_at')->pluck('id')->all();
        $data = $request->validate([
            'qty' => ['required', 'array'], 'qty.*' => ['nullable', 'integer', 'min:0', 'max:10'],
            'pay_with' => ['required', Rule::in(['card', 'bill'])],
            'method' => ['nullable', 'required_if:pay_with,card', Rule::in($methods)],
            'ship' => ['required', Rule::in(['service', 'other'])],
            'street' => ['nullable', 'required_if:ship,other', 'string', 'max:120'],
            'city' => ['nullable', 'required_if:ship,other', 'string', 'max:60'],
            'zip' => ['nullable', 'required_if:ship,other', 'digits:5'],
            'agree' => ['accepted'],
        ], ['agree.accepted' => 'Read the order terms to the customer and check that they agreed.', 'method.required_if' => 'Pick the card to pay with.']);
        $items = collect($data['qty'])->map(fn ($q) => (int) $q)->filter()->mapWithKeys(fn ($q, $sku) => [strtoupper($sku) => isset(Cart::PROTECTION[strtoupper($sku)]) ? 1 : min(10, $q)])->all();
        $summary = Cart::summarize($items);
        if ($summary['lines']->isEmpty()) {
            return back()->withInput()->withErrors(['qty' => 'Pick at least one product.']);
        }
        if ($summary['unavailable']->isNotEmpty()) {
            return back()->withInput()->withErrors(['qty' => $summary['unavailable']->pluck('name')->implode(', ').' is out of stock.']);
        }
        $order = ShopCheckout::place($customer, $summary, ['pay_with' => $data['pay_with'], 'method' => $data['method'] ?? null, 'ship_to' => ShopCheckout::shipTo($customer, $data)], $request->user());

        return redirect()->route('corral.shop.orders.show', $order)->with('status', 'Order '.$order->number.' placed for '.$customer->name.'.');
    }
}
