@extends('frontend.layouts.main')
@section('title', 'Checkout')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Checkout">
  @error('cart')<div class="site-alert error" role="alert">{{ $message }}</div>@enderror
  <form method="post" action="{{ route('myaccount.checkout.place') }}" class="cart-grid pay-form" novalidate>@csrf
    <div class="co-grid-col">
      <section class="co-card">
        <h3>1. Your items <a class="cart-edit" href="{{ route('shop.cart') }}">Edit cart</a></h3>
        <div class="ma-table"><table class="price-table"><tbody>@foreach ($cart['lines'] as $l)
          <tr><td>{{ $l['name'] }}<br><small>{{ $l['monthly'] ? 'Protection plan · added to your bill' : 'Qty '.$l['qty'] }}</small></td>
            <td class="num">{{ $l['monthly'] ? '$'.number_format($l['line'], 2).'/mo' : ($l['line'] > 0 ? '$'.number_format($l['line'], 2) : 'Free') }}</td></tr>
        @endforeach</tbody></table></div>
      </section>

      <section class="co-card">
        <h3>2. Ship to</h3>
        <label class="radio"><input type="radio" name="ship" value="service" @checked(old('ship', 'service') === 'service')> <span>{{ $c->name }}, {{ trim($c->address.($c->unit ? ' #'.$c->unit : '')) }}, {{ $c->city }}, TX {{ $c->zip }} <small>(your service address)</small></span></label>
        <label class="radio"><input type="radio" name="ship" value="other" @checked(old('ship') === 'other')> Another address</label>
        <div class="ship-other" @if (old('ship') !== 'other') hidden @endif>
          <div class="field"><label for="street">Street</label><input id="street" name="street" value="{{ old('street') }}" autocomplete="address-line1">@error('street')<small class="err">{{ $message }}</small>@enderror</div>
          <div class="form-2"><div class="field"><label for="city">City</label><input id="city" name="city" value="{{ old('city') }}" autocomplete="address-level2">@error('city')<small class="err">{{ $message }}</small>@enderror</div>
            <div class="field"><label for="zip">Zip</label><input id="zip" name="zip" value="{{ old('zip') }}" inputmode="numeric" maxlength="5" autocomplete="postal-code">@error('zip')<small class="err">{{ $message }}</small>@enderror</div></div>
        </div>
      </section>

      <section class="co-card">
        <h3>3. Pay with</h3>
        <div class="ma-table"><table class="price-table"><tbody>
          @foreach ($methods as $m)
            <tr><td style="width:36px"><input type="radio" id="pm{{ $m->id }}" name="pay" value="card:{{ $m->id }}" @checked(old('pay', 'card:'.$methods->first()?->id) === 'card:'.$m->id)></td><td><label for="pm{{ $m->id }}">{{ $m->label() }}</label></td><td>{{ $m->autopay ? 'Default' : '' }}</td></tr>
          @endforeach
          <tr><td><input type="radio" id="pm-bill" name="pay" value="bill" @checked(old('pay', $methods->isEmpty() ? 'bill' : '') === 'bill')></td><td colspan="2"><label for="pm-bill">Add ${{ number_format($cart['total'], 2) }} to my next electricity bill</label></td></tr>
        </tbody></table></div>
        @error('method')<small class="err">{{ $message }}</small>@enderror
        <input type="hidden" name="pay_with" value="{{ old('pay_with', $methods->isEmpty() ? 'bill' : 'card') }}"><input type="hidden" name="method" value="{{ old('method', $methods->first()?->id) }}">
        @if ($cart['monthly'] > 0)<p class="cart-note">Protection plans (${{ number_format($cart['monthly'], 2) }}/mo) are added to your electricity bill each month.</p>@endif
      </section>
    </div>

    <div>
      @include('frontend.shop._totals', ['cart' => $cart])
      <div class="co-card cart-place">
        <label class="check"><input type="checkbox" name="agree" value="1" @checked(old('agree'))> I agree to the order terms: items ship in 2–5 business days and can be returned within 30 days; protection plans can be canceled any time.</label>
        @error('agree')<small class="err">{{ $message }}</small>@enderror
        <button class="btn btn-block">Place Order · ${{ number_format($cart['total'], 2) }}</button>
        <p class="cart-note">Sample shop: cards are not charged (Stripe is not set up), so card orders show payment Pending.</p>
      </div>
    </div>
  </form>
</x-frontend.myaccount>
<script>
(function () {
  var f = document.querySelector('.pay-form');
  function sync() {
    var other = f.querySelector('input[name=ship]:checked').value === 'other'; f.querySelector('.ship-other').hidden = !other;
    var p = (f.querySelector('input[name=pay]:checked') || {}).value || 'bill';
    f.pay_with.value = p === 'bill' ? 'bill' : 'card'; f.method.value = p === 'bill' ? '' : p.split(':')[1];
  }
  f.addEventListener('change', sync); sync();
})();
</script>
@endsection
