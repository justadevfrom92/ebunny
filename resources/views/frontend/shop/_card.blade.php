{{-- One product tile on the Shop grid; the whole card opens the product's page --}}
<article class="shop-card">
  <div @class(['shop-img', 'shop-cat-'.$p['cat']])>
    @include('frontend.shop._icon', ['icon' => $p['icon']])
    @if ($p['badge'])<span @class(['shop-badge', 'sale' => $p['was'] !== null])>{{ $p['badge'] }}</span>@endif
  </div>
  <div class="shop-body">
    <p class="shop-catname">{{ config('shop.categories.'.$p['cat']) }}</p>
    <h3><a class="shop-link" href="{{ route('shop.show', $p['slug']) }}">{{ $p['name'] }}</a></h3>
    <p class="shop-rating" aria-label="Rated {{ $p['rating'] }} out of 5 from {{ number_format($p['reviews']) }} reviews"><span class="stars" style="--r:{{ $p['rating'] }}" aria-hidden="true">★★★★★</span> {{ number_format($p['rating'], 1) }} <small>({{ number_format($p['reviews']) }})</small></p>
    <p class="shop-blurb">{{ $p['blurb'] }}</p>
    <p class="shop-price-row"><strong>{{ $p['price'] > 0 ? '$'.number_format($p['price'], 2) : 'Free' }}</strong>@if ($p['was'])<s>${{ number_format($p['was'], 2) }}</s>@endif</p>
    <p class="shop-meta">@if ($p['stars'])<span class="shop-earn">Earn {{ number_format($p['stars']) }} ★</span>@endif<span @class(['shop-stock', 'out' => ! $p['stock']])>{{ $p['stock'] ? 'In stock' : 'Out of stock' }}</span></p>
    <button type="button" class="btn btn-block shop-add" data-sku="{{ $p['sku'] }}" @disabled(! $p['stock'])>{{ $p['stock'] ? 'Add to Cart' : 'Sold Out' }}</button>
  </div>
</article>
