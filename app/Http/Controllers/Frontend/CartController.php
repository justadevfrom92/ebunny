<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Services\Cart;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The Shop cart (/shop/cart): add, change quantities, remove, and go to checkout in My Account. */
class CartController extends Controller
{
    public function show(Cart $cart): View
    {
        return view('frontend.shop.cart', ['cart' => $cart->summary()]);
    }

    public function add(Request $request, Cart $cart): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['sku' => ['required', Rule::in(ShopController::products()->pluck('sku')->all())], 'qty' => ['nullable', 'integer', 'min:1', 'max:10']]);
        $p = ShopController::products()->firstWhere('sku', $data['sku']);
        abort_unless($p['stock'], 422, 'That item is out of stock.');
        $cart->add($data['sku'], (int) ($data['qty'] ?? 1));

        return $request->expectsJson()
            ? response()->json(['count' => $cart->count(), 'message' => $p['name'].' added to your cart.'])
            : redirect()->route('shop.cart')->with('status', $p['name'].' added to your cart.');
    }

    public function update(Request $request, Cart $cart, string $sku): RedirectResponse
    {
        $cart->set($sku, (int) $request->validate(['qty' => ['required', 'integer', 'min:0', 'max:10']])['qty']);

        return redirect()->route('shop.cart')->with('status', 'Cart updated.');
    }

    public function remove(Cart $cart, string $sku): RedirectResponse
    {
        $cart->set($sku, 0);

        return redirect()->route('shop.cart')->with('status', 'Removed from your cart.');
    }
}
