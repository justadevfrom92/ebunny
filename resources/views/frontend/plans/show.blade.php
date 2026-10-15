@extends('frontend.layouts.main')
@php
    $biz = $plan->type === 'Biz';
    $fixed = $plan->term > 1;
    $termLabel = $fixed ? $plan->term.'-month fixed rate' : 'Month-to-month, variable rate';
    $etf = $plan->etf && $plan->etf !== '-' ? $plan->etf : 'None';
    $mainPrice = $main ? $prices[$main][1000] : null;
    $mainMarket = $markets->firstWhere('id', $main);
    $signup = route('checkout').'?'.http_build_query(array_filter(['plan' => $plan->internal, 'zip' => $zip]));
    $included = collect($plan->perks ?: [])->whenEmpty(fn () => collect(array_filter([
        $fixed ? 'Your energy rate is locked for '.$plan->term.' months' : 'No contract: leave any time with no cancellation fee',
        $plan->green ? $plan->green.'% renewable energy' : null,
        'Earn Rangler Rewards stars for paying on time',
        'Manage everything in My Account, with AutoPay and paperless billing',
        $biz ? 'Business customer care line' : null,
    ])));
@endphp
@section('title', $plan->name)
@section('eyebrow', ($biz ? 'Business' : 'Home').' electricity plan')
@section('heading', $plan->name)
@section('show-title', '1')
@section('banner')<p class="lead">{{ $termLabel }}@if ($plan->green) · {{ $plan->green }}% renewable @endif</p>@endsection
@section('main')
<section class="section plan-page">
  <div class="wrap">
    <div class="pp-top">
      <div class="co-card pp-price">
        <span class="plan-term">{{ $termLabel }}</span>
        <div class="plan-rate"><strong>{{ $mainPrice !== null ? number_format($mainPrice, 1).'¢' : '—' }}</strong><span>per kWh</span></div>
        <p class="plan-rate-note">Average price at 1,000 kWh @if ($mainMarket) in the {{ $mainMarket->description ?: $mainMarket->short }} area @endif @if (! $here && $zip) (this zip isn't in an area we serve) @endif</p>
        <a class="btn btn-block" href="{{ $signup }}">Sign Up for {{ $plan->name }}</a>
        <form class="pp-zip" method="get" action="{{ route('plans.show', $plan->pageSlug()) }}">
          <label for="pp-zip">See the price for your zip</label>
          <div><input id="pp-zip" name="zip" inputmode="numeric" maxlength="5" pattern="\d{5}" value="{{ $zip }}" placeholder="Zip code"><button class="btn btn-outline">Go</button></div>
        </form>
      </div>
      <div class="co-card">
        <h2>Plan details</h2>
        <table class="price-table pd-specs"><tbody>
          <tr><th>Contract</th><td>{{ $fixed ? $plan->term.' months' : 'None (month-to-month)' }}</td></tr>
          <tr><th>Rate type</th><td>{{ $fixed ? 'Fixed: your energy rate stays the same for the whole contract' : 'Variable: the rate can change month to month' }}</td></tr>
          <tr><th>Monthly base charge</th><td>{{ $plan->mrc ? '$'.number_format($plan->mrc, 2) : 'None' }}</td></tr>
          <tr><th>Early termination fee</th><td>{{ $etf }}</td></tr>
          <tr><th>Renewable energy</th><td>{{ $plan->green }}%</td></tr>
          <tr><th>For</th><td>{{ $biz ? 'Businesses' : 'Homes' }}</td></tr>
          <tr><th>Plan code</th><td>{{ $plan->internal }}</td></tr>
        </tbody></table>
        <h3 style="margin-top:20px">What's included</h3>
        <ul class="checks">@foreach ($included as $x)<li>{{ $x }}</li>@endforeach</ul>
      </div>
    </div>

    <div class="co-card pp-areas">
      <h2>Price by utility area</h2>
      <p class="muted-p">Average price per kWh, including your utility's delivery charges and the base charge. Your utility depends on where you live.</p>
      <div class="ma-table"><table class="price-table">
        <thead><tr><th>Utility area</th><th class="num">500 kWh</th><th class="num">1,000 kWh</th><th class="num">2,000 kWh</th><th class="num">Energy charge</th><th class="num">Delivery</th></tr></thead>
        <tbody>@foreach ($markets as $m)
          <tr @class(['on' => $m->id === $here])><td><b>{{ $m->description ?: $m->short }}</b>@if ($m->id === $here)<br><small>Your area</small>@endif</td>
            @foreach ([500, 1000, 2000] as $k)<td class="num">{{ number_format($prices[$m->id][$k], 1) }}¢</td>@endforeach
            <td class="num">{{ number_format($rates[$plan->id.'-'.$m->id]->energy, 1) }}¢/kWh</td>
            <td class="num">{{ number_format($fees[$m->id]->per_kwh, 2) }}¢/kWh<br><small>+ ${{ number_format($fees[$m->id]->per_bill, 2) }}/mo</small></td></tr>
        @endforeach</tbody>
      </table></div>
    </div>

    <div class="pp-docs">
      <div class="co-card"><h2>Plan documents</h2>
        <ul class="pp-doclist"><li><a href="{{ url('historical-efls') }}">Electricity Facts Label (EFL)</a><small>Prices, fees and contract terms</small></li>
          <li><a href="{{ url('terms-of-use') }}">Terms of Service</a><small>Your agreement with us</small></li>
          <li><a href="{{ url('your-rights-as-a-customer') }}">Your Rights as a Customer</a><small>Protections under Texas rules</small></li></ul>
      </div>
      <div class="co-card"><h2>Common questions</h2>
        <details><summary>Is there a deposit?</summary><p>Maybe, depending on a quick credit check. AutoPay and paperless billing can lower or remove it.</p></details>
        <details><summary>What happens when the contract ends?</summary><p>{{ $fixed ? 'We send renewal offers before it ends. If you do nothing you move to a month-to-month rate; there is no fee to leave then.' : 'There is no contract: this plan continues month to month.' }}</p></details>
        <details><summary>Will my power go out when I switch?</summary><p>No. Your utility keeps delivering power; only the company you buy it from changes.</p></details>
        <details><summary>Can I leave early?</summary><p>{{ $etf === 'None' ? 'Yes, with no early termination fee.' : 'Yes. The early termination fee is '.$etf.'. There is no fee if you move.' }}</p></details>
      </div>
    </div>

    @if ($others->isNotEmpty())
      <h2 class="pd-related">Other {{ $biz ? 'business' : 'home' }} plans</h2>
      <div class="pp-others">@foreach ($others as $o)
        <a class="co-card pp-other" href="{{ route('plans.show', array_filter(['page' => $o->pageSlug(), 'zip' => $zip])) }}">
          <span class="plan-term">{{ $o->term > 1 ? $o->term.' months · fixed' : 'Month-to-month' }}</span><b>{{ $o->name }}</b>
          @if ($main && isset($rates[$o->id.'-'.$main], $fees[$main]))<span class="pp-o-price">{{ number_format(\App\Services\Catalog::averagePrice($rates[$o->id.'-'.$main]->energy, $fees[$main]->per_kwh, $fees[$main]->per_bill, $o->mrc, 1000), 1) }}¢ <small>at 1,000 kWh</small></span>@endif
          <span class="pp-o-more">See plan ›</span></a>
      @endforeach</div>
    @endif
  </div>
</section>
@endsection
