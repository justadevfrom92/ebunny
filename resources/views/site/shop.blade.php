@extends('site.layouts.main')
@section('title', 'Shop')
@section('crumb', 'Shop')
@section('eyebrow', 'Energy-saving gear')
@section('heading', 'Shop')
@section('banner')<p class="lead" style="color:rgba(255,255,255,.85);max-width:620px">Smart thermostats, EV chargers, lighting and protection plans. Many earn Rangler Rewards stars.</p>@endsection
@section('main')
@php
    // Simple line icons for the product tiles
    $icons = [
        'thermo' => '<rect x="14" y="10" width="36" height="44" rx="10"/><circle cx="32" cy="32" r="9"/><path d="M32 23v-4"/>',
        'sensor' => '<rect x="20" y="16" width="24" height="32" rx="6"/><circle cx="32" cy="28" r="4"/><path d="M26 40h12"/>',
        'ev' => '<rect x="16" y="10" width="24" height="44" rx="4"/><path d="M28 20l-5 10h10l-5 10"/><path d="M40 26h6v16a4 4 0 0 1-8 0"/>',
        'cable' => '<path d="M14 40c10 0 10-16 20-16s10 16 20 16"/><circle cx="14" cy="40" r="4"/><circle cx="54" cy="40" r="4"/>',
        'tools' => '<path d="M20 44l16-16"/><path d="M36 28a8 8 0 1 0 8-12l-5 5-4-1-1-4 5-5"/><path d="M16 48l4-4"/>',
        'bulb' => '<path d="M24 38a12 12 0 1 1 16 0c-2 2-3 4-3 7H27c0-3-1-5-3-7z"/><path d="M27 50h10M29 55h6"/>',
        'sun' => '<circle cx="32" cy="32" r="9"/><path d="M32 12v6M32 46v6M12 32h6M46 32h6M18 18l4 4M42 42l4 4M18 46l4-4M42 22l4-4"/>',
        'plug' => '<path d="M24 12v10M40 12v10"/><rect x="18" y="22" width="28" height="14" rx="4"/><path d="M32 36v8a6 6 0 0 0 6 6h6"/>',
        'meter' => '<circle cx="32" cy="34" r="18"/><path d="M32 34l8-8"/><path d="M20 34h2M42 34h2M32 22v2"/>',
        'shield' => '<path d="M32 10l18 6v14c0 12-8 20-18 24-10-4-18-12-18-24V16z"/><path d="M24 32l6 6 10-12"/>',
        'battery' => '<rect x="12" y="20" width="36" height="24" rx="4"/><path d="M48 28h4v8h-4"/><path d="M30 24l-4 8h8l-4 8"/>',
        'house' => '<path d="M12 30l20-16 20 16"/><path d="M18 26v24h28V26"/><path d="M28 50V38h8v12"/>',
        'cap' => '<path d="M14 40c0-12 8-20 18-20s18 8 18 20z"/><path d="M14 40h40"/>',
        'cup' => '<path d="M22 16h20l-3 38H25z"/><path d="M20 16h24"/>',
        'bag' => '<rect x="14" y="24" width="36" height="28" rx="3"/><path d="M24 24v-4a8 8 0 0 1 16 0v4"/>',
    ];
    $active = collect([
        ...collect($f['cat'] ?? [])->map(fn ($c) => [$cats[$c], array_merge($f, ['cat' => array_values(array_diff($f['cat'], [$c]))])]),
        ...(isset($f['min']) || isset($f['max']) ? [['$'.($f['min'] ?? 0).'–$'.($f['max'] ?? $priceTop), array_diff_key($f, ['min' => 1, 'max' => 1])]] : []),
        ...(! empty($f['rating']) ? [[$f['rating'].'★ & up', array_diff_key($f, ['rating' => 1])]] : []),
        ...(! empty($f['stock']) ? [['In stock', array_diff_key($f, ['stock' => 1])]] : []),
        ...(! empty($f['sale']) ? [['On sale', array_diff_key($f, ['sale' => 1])]] : []),
        ...(! empty($f['stars']) ? [['Earns stars', array_diff_key($f, ['stars' => 1])]] : []),
        ...(! empty($f['q']) ? [['“'.$f['q'].'”', array_diff_key($f, ['q' => 1])]] : []),
    ]);
@endphp
<section class="section shop">
  <div class="wrap shop-grid">
    <aside class="shop-filters" aria-label="Filters">
      <details open class="shop-filter-box">
        <summary><span>Filters</span>@if ($active->count())<span class="shop-count">{{ $active->count() }}</span>@endif</summary>
        <form method="get" action="{{ route('shop') }}" id="shop-form">
          @if ($sort !== 'featured')<input type="hidden" name="sort" value="{{ $sort }}">@endif
          <div class="field"><label for="q">Search</label><input id="q" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Thermostat, charger…"></div>
          <fieldset class="field"><legend>Category</legend>
            @foreach ($cats as $k => $label)
              <label class="check"><input type="checkbox" name="cat[]" value="{{ $k }}" @checked(in_array($k, $f['cat'] ?? [], true))> <span>{{ $label }}</span> <small>{{ $counts[$k] ?? 0 }}</small></label>
            @endforeach
          </fieldset>
          <fieldset class="field"><legend>Price</legend>
            <div class="shop-price"><label class="sr-only" for="min">Min price</label><input id="min" name="min" type="number" min="0" step="1" inputmode="numeric" placeholder="$0" value="{{ $f['min'] ?? '' }}">
              <span>to</span><label class="sr-only" for="max">Max price</label><input id="max" name="max" type="number" min="0" step="1" inputmode="numeric" placeholder="${{ $priceTop }}" value="{{ $f['max'] ?? '' }}"></div>
          </fieldset>
          <fieldset class="field"><legend>Rating</legend>
            @foreach (['' => 'Any', '4' => '4★ & up', '4.5' => '4.5★ & up'] as $v => $l)
              <label class="radio"><input type="radio" name="rating" value="{{ $v }}" @checked(($f['rating'] ?? '') === (string) $v)> {{ $l }}</label>
            @endforeach
          </fieldset>
          <fieldset class="field"><legend>Show only</legend>
            <label class="check"><input type="checkbox" name="stock" value="1" @checked($f['stock'] ?? false)> In stock</label>
            <label class="check"><input type="checkbox" name="sale" value="1" @checked($f['sale'] ?? false)> On sale</label>
            <label class="check"><input type="checkbox" name="stars" value="1" @checked($f['stars'] ?? false)> Earns Rangler Rewards stars</label>
          </fieldset>
          <div class="shop-apply"><button class="btn">Apply</button>@if ($active->count())<a class="btn btn-outline" href="{{ route('shop', $sort !== 'featured' ? ['sort' => $sort] : []) }}">Clear all</a>@endif</div>
        </form>
      </details>
    </aside>

    <div class="shop-main">
      <div class="shop-bar">
        <p class="shop-results"><b>{{ $items->total() }}</b> {{ Str::plural('product', $items->total()) }}@if ($items->total() > $items->perPage()) · showing {{ $items->firstItem() }}–{{ $items->lastItem() }}@endif</p>
        <nav class="shop-sort" aria-label="Sort by">
          <span>Sort:</span>
          @foreach ($sorts as $k => $label)
            <a href="{{ route('shop', array_merge($f, ['sort' => $k === 'featured' ? null : $k])) }}" @class(['on' => $sort === $k]) @if ($sort === $k) aria-current="true" @endif>{{ $label }}</a>
          @endforeach
        </nav>
      </div>
      @if ($active->count())
        <div class="shop-chips" aria-label="Active filters">
          @foreach ($active as [$label, $without])<a class="shop-chip" href="{{ route('shop', array_filter($without, fn ($v) => $v !== null && $v !== [])) }}">{{ $label }} <span aria-label="Remove">×</span></a>@endforeach
        </div>
      @endif

      @if ($items->isEmpty())
        <div class="co-card" style="text-align:center"><h3>No products match</h3><p>Try fewer filters.</p><a class="btn" href="{{ route('shop') }}">Clear all</a></div>
      @else
        <div class="shop-cards">
          @foreach ($items as $p)
            <article class="shop-card">
              <div @class(['shop-img', 'shop-cat-'.$p['cat']])>
                <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $icons[$p['icon']] ?? $icons['plug'] !!}</svg>
                @if ($p['badge'])<span @class(['shop-badge', 'sale' => $p['was'] !== null])>{{ $p['badge'] }}</span>@endif
              </div>
              <div class="shop-body">
                <p class="shop-catname">{{ $cats[$p['cat']] }}</p>
                <h3>{{ $p['name'] }}</h3>
                <p class="shop-rating" aria-label="Rated {{ $p['rating'] }} out of 5 from {{ number_format($p['reviews']) }} reviews"><span class="stars" style="--r:{{ $p['rating'] }}" aria-hidden="true">★★★★★</span> {{ number_format($p['rating'], 1) }} <small>({{ number_format($p['reviews']) }})</small></p>
                <p class="shop-blurb">{{ $p['blurb'] }}</p>
                <p class="shop-price-row"><strong>{{ $p['price'] > 0 ? '$'.number_format($p['price'], 2) : 'Free' }}</strong>@if ($p['was'])<s>${{ number_format($p['was'], 2) }}</s>@endif</p>
                <p class="shop-meta">@if ($p['stars'])<span class="shop-earn">Earn {{ number_format($p['stars']) }} ★</span>@endif<span @class(['shop-stock', 'out' => ! $p['stock']])>{{ $p['stock'] ? 'In stock' : 'Out of stock' }}</span></p>
                <button type="button" class="btn btn-block shop-add" data-sku="{{ $p['sku'] }}" @disabled(! $p['stock'])>{{ $p['stock'] ? 'Add to Cart' : 'Sold Out' }}</button>
              </div>
            </article>
          @endforeach
        </div>
        @if ($items->hasPages())
          <nav class="shop-pages" aria-label="Pages">
            @if ($items->onFirstPage())<span class="disabled">‹ Previous</span>@else<a href="{{ $items->previousPageUrl() }}" rel="prev">‹ Previous</a>@endif
            @foreach (range(1, $items->lastPage()) as $n)<a href="{{ $items->url($n) }}" @class(['on' => $n === $items->currentPage()]) @if ($n === $items->currentPage()) aria-current="page" @endif>{{ $n }}</a>@endforeach
            @if ($items->hasMorePages())<a href="{{ $items->nextPageUrl() }}" rel="next">Next ›</a>@else<span class="disabled">Next ›</span>@endif
          </nav>
        @endif
      @endif
    </div>
  </div>
  <div class="shop-cart" id="shop-cart" hidden role="status"><span>Cart: <b id="shop-cart-n">0</b></span><button type="button" class="link-btn" id="shop-cart-clear">Clear</button><small>Sample shop: checkout isn't open yet.</small></div>
</section>
<script>
(function () {
  // Checkboxes and radios apply straight away; typed fields apply with Apply or Enter
  var form = document.getElementById('shop-form');
  form.addEventListener('change', function (e) { if (e.target.type === 'checkbox' || e.target.type === 'radio') form.requestSubmit ? form.requestSubmit() : form.submit(); });
  // Drop empty fields so the address stays short
  form.addEventListener('submit', function () { [].forEach.call(form.elements, function (el) { if (el.name && el.value === '' && el.type !== 'checkbox' && el.type !== 'radio') el.disabled = true; if (el.type === 'radio' && el.checked && el.value === '') el.disabled = true; }); });
  // On phones the filters start closed
  if (matchMedia('(max-width: 900px)').matches) document.querySelector('.shop-filter-box').open = false;
  // A small sample cart kept in this browser
  var key = 'et-shop-cart', cart = []; try { cart = JSON.parse(localStorage.getItem(key)) || []; } catch (e) {}
  var box = document.getElementById('shop-cart'), n = document.getElementById('shop-cart-n');
  function show() { n.textContent = cart.length; box.hidden = !cart.length; try { localStorage.setItem(key, JSON.stringify(cart)); } catch (e) {} }
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.shop-add'); if (!b) return;
    cart.push(b.dataset.sku); show(); b.textContent = 'Added ✓'; setTimeout(function () { b.textContent = 'Add to Cart'; }, 1200);
  });
  document.getElementById('shop-cart-clear').addEventListener('click', function () { cart = []; show(); });
  show();
})();
</script>
@endsection
