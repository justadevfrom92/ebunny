@extends('frontend.layouts.main')
@section('title', 'Your Cart')
@section('crumb')<a href="{{ route('shop') }}">Shop</a> / Cart @endsection
@section('heading', 'Your Cart')
@section('main')
<section class="section shop">
  <div class="wrap">
    @if (session('status'))<div class="site-alert ok" role="status">{{ session('status') }}</div>@endif
    @if ($cart['lines']->isEmpty())
      <div class="co-card" style="text-align:center"><h2>Your cart is empty</h2><p>Smart thermostats, EV chargers, protection plans and more.</p><a class="btn" href="{{ route('shop') }}">Go to the Shop</a></div>
    @else
      <div class="cart-grid">
        <div class="co-card">
          <div class="ma-table"><table class="price-table cart-table">
            <thead><tr><th>Item</th><th class="num">Price</th><th>Qty</th><th class="num">Total</th><th><span class="sr-only">Remove</span></th></tr></thead>
            <tbody>@foreach ($cart['lines'] as $l)
              <tr>
                <td><div class="cart-item"><span @class(['cart-thumb', 'shop-img', 'shop-cat-'.$l['cat']])>@include('frontend.shop._icon', ['icon' => $l['icon']])</span>
                  <span><a href="{{ route('shop.show', $l['slug']) }}"><b>{{ $l['name'] }}</b></a><br><small>{{ $l['sku'] }}@if ($l['stars']) · earns {{ number_format($l['stars_total']) }} ★@endif @unless ($l['stock'])· <b class="out">Out of stock</b>@endunless</small></span></div></td>
                <td class="num">@if ($l['monthly'])${{ number_format($l['line'], 2) }}/mo<br><small>on your bill</small>@else{{ $l['price'] > 0 ? '$'.number_format($l['price'], 2) : 'Free' }}@endif</td>
                <td>@if ($l['monthly'])1 @else
                  <form method="post" action="{{ route('shop.cart.update', $l['sku']) }}" class="cart-qty">@csrf @method('patch')
                    <label class="sr-only" for="q-{{ $l['sku'] }}">Quantity</label><input id="q-{{ $l['sku'] }}" name="qty" type="number" min="0" max="10" value="{{ $l['qty'] }}" inputmode="numeric" onchange="this.form.requestSubmit()">
                    <button class="link-btn">Update</button></form>@endif</td>
                <td class="num"><b>{{ $l['monthly'] ? '$'.number_format($l['line'], 2).'/mo' : ($l['line'] > 0 ? '$'.number_format($l['line'], 2) : 'Free') }}</b></td>
                <td><form method="post" action="{{ route('shop.cart.remove', $l['sku']) }}">@csrf @method('delete')<button class="link-btn" aria-label="Remove {{ $l['name'] }}">Remove</button></form></td>
              </tr>
            @endforeach</tbody>
          </table></div>
          <p style="margin-top:14px"><a href="{{ route('shop') }}">‹ Keep shopping</a></p>
        </div>
        @include('frontend.shop._totals', ['cart' => $cart, 'button' => true])
      </div>
    @endif
  </div>
</section>
@endsection
