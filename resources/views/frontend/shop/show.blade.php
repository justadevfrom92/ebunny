@extends('frontend.layouts.main')
@section('title', $p['name'])
@section('crumb')<a href="{{ route('shop') }}">Shop</a> / <a href="{{ route('shop', ['cat' => [$p['cat']]]) }}">{{ $cats[$p['cat']] }}</a> / {{ $p['name'] }}@endsection
@section('eyebrow', $cats[$p['cat']])
@section('heading', $p['name'])
@section('main')
<section class="section shop">
  <div class="wrap">
    <div class="pd-grid">
      <div @class(['pd-img', 'shop-img', 'shop-cat-'.$p['cat']])>
        @include('frontend.shop._icon', ['icon' => $p['icon']])
        @if ($p['badge'])<span @class(['shop-badge', 'sale' => $p['was'] !== null])>{{ $p['badge'] }}</span>@endif
      </div>
      <div class="pd-buy">
        <p class="shop-rating"><span class="stars" style="--r:{{ $p['rating'] }}" aria-hidden="true">★★★★★</span> {{ number_format($p['rating'], 1) }} <a href="#reviews">{{ number_format($p['reviews']) }} reviews</a></p>
        <p class="pd-blurb">{{ $p['blurb'] }}</p>
        <p class="shop-price-row pd-price"><strong>{{ $p['price'] > 0 ? '$'.number_format($p['price'], 2) : 'Free' }}</strong>
          @if ($p['was'])<s>${{ number_format($p['was'], 2) }}</s><span class="pd-save">Save ${{ number_format($p['was'] - $p['price'], 2) }}</span>@endif</p>
        @if ($p['cat'] === 'protection')<p class="pd-note">That's ${{ number_format($p['price'] / 12, 2) }} a month on your electricity bill.</p>@endif
        <p class="shop-meta">@if ($p['stars'])<span class="shop-earn">Earn {{ number_format($p['stars']) }} Power Rewards ★</span>@endif<span @class(['shop-stock', 'out' => ! $p['stock']])>{{ $p['stock'] ? 'In stock' : 'Out of stock' }}</span></p>
        <div class="pd-add">
          @if ($p['cat'] !== 'protection' && $p['price'] > 0)
            <div class="field pd-qty"><label for="qty">Qty</label><input id="qty" type="number" min="1" max="10" value="1" inputmode="numeric"></div>
          @endif
          <button type="button" class="btn shop-add" data-sku="{{ $p['sku'] }}" data-qty="qty" @disabled(! $p['stock'])>{{ $p['stock'] ? ($p['cat'] === 'protection' ? 'Add to My Account' : 'Add to Cart') : 'Sold Out' }}</button>
        </div>
        <ul class="pd-perks"><li>{{ $details['delivery'] }}</li><li>{{ $details['warranty'] }}</li><li>Questions? Call <a href="tel:{{ preg_replace('/[^\d+]/', '', config('brand.phone')) }}">{{ config('brand.phone') }}</a></li></ul>
      </div>
    </div>

    <div class="pd-info">
      <section class="co-card"><h2>What you get</h2>
        <ul class="pd-features">@foreach ($details['features'] as $f)<li>{{ $f }}</li>@endforeach</ul>
      </section>
      <section class="co-card"><h2>Details</h2>
        <table class="price-table pd-specs"><tbody>
          <tr><th>Item number</th><td>{{ $p['sku'] }}</td></tr>
          <tr><th>Category</th><td>{{ $cats[$p['cat']] }}</td></tr>
          <tr><th>Price</th><td>{{ $p['price'] > 0 ? '$'.number_format($p['price'], 2) : 'Free' }}@if ($p['was']) (was ${{ number_format($p['was'], 2) }})@endif</td></tr>
          <tr><th>Rewards</th><td>{{ $p['stars'] ? number_format($p['stars']).' stars' : '—' }}</td></tr>
          <tr><th>Warranty</th><td>{{ $details['warranty'] }}</td></tr>
          <tr><th>Delivery</th><td>{{ $details['delivery'] }}</td></tr>
          <tr><th>Availability</th><td>{{ $p['stock'] ? 'In stock' : 'Out of stock' }}</td></tr>
        </tbody></table>
      </section>
      <section class="co-card" id="reviews"><h2>Ratings</h2>
        <p class="pd-avg"><strong>{{ number_format($p['rating'], 1) }}</strong> out of 5 <span class="stars" style="--r:{{ $p['rating'] }}" aria-hidden="true">★★★★★</span><br><small>{{ number_format($p['reviews']) }} reviews (sample data)</small></p>
        @php $most = max(1, $breakdown->max()); @endphp
        <ul class="pd-bars">@foreach ($breakdown as $stars => $n)
          <li><span>{{ $stars }} ★</span><span class="pd-track" aria-hidden="true"><span style="width:{{ round($n / $most * 100) }}%"></span></span><span>{{ number_format($n) }}</span></li>
        @endforeach</ul>
      </section>
    </div>

    @if ($related->isNotEmpty())
      <h2 class="pd-related">You might also like</h2>
      <div class="shop-cards">@foreach ($related as $r)@include('frontend.shop._card', ['p' => $r])@endforeach</div>
    @endif
  </div>
  @include('frontend.shop._cart')
</section>
@endsection
