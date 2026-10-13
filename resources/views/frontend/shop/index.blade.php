@extends('frontend.layouts.main')
@section('title', 'Shop')
@section('crumb', 'Shop')
@section('eyebrow', 'Energy-saving gear')
@section('heading', 'Shop')
@section('banner')<p class="lead" style="color:rgba(255,255,255,.85);max-width:620px">Smart thermostats, EV chargers, lighting and protection plans. Many earn Rangler Rewards stars.</p>@endsection
@section('main')
@php
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
            @include('frontend.shop._card', ['p' => $p])
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
  @include('frontend.shop._cart')
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
})();
</script>
@endsection
