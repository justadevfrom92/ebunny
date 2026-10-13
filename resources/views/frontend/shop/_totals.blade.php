{{-- Order summary box for the cart and checkout pages --}}
<aside class="co-card cart-sum">
  <h2>Order summary</h2>
  <dl>
    <dt>Items ({{ $cart['count'] }})</dt><dd>${{ number_format($cart['subtotal'], 2) }}</dd>
    <dt>Shipping</dt><dd>Free</dd>
    <dt>Sales tax ({{ rtrim(rtrim(number_format(\App\Services\Cart::TAX * 100, 2), '0'), '.') }}%)</dt><dd>${{ number_format($cart['tax'], 2) }}</dd>
    <dt class="cart-total">Due today</dt><dd class="cart-total">${{ number_format($cart['total'], 2) }}</dd>
    @if ($cart['monthly'] > 0)<dt>Added to your electricity bill</dt><dd>${{ number_format($cart['monthly'], 2) }}/mo</dd>@endif
    @if ($cart['stars'] > 0)<dt>Rewards stars you'll earn</dt><dd class="shop-earn">{{ number_format($cart['stars']) }} ★</dd>@endif
  </dl>
  @if (! empty($button))
    <a class="btn btn-block" href="{{ route('myaccount.checkout') }}">Checkout</a>
    <p class="cart-note">{{ auth('customer')->check() ? 'You will review and pay in My Account.' : 'Checkout is in My Account: sign in or create an account first.' }}</p>
  @endif
</aside>
