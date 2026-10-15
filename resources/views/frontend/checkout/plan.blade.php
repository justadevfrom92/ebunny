@extends('frontend.layouts.main')
@section('title', 'Choose a Plan')
@section('heading', 'Choose your plan')
@section('show-title', '1')
@section('banner')<p>Prices for {{ $market->description }}, including delivery charges, at 1,000 kWh a month.</p>@endsection
@section('main')
<x-frontend.checkout :d="$d" :step="$step" :intro="$intro">
  <form method="post" action="{{ route('checkout.plan.store') }}">@csrf
    <h2>Plans at {{ $d['address'] }}</h2>
    <div class="plan-pick">
      @forelse ($plans as $r)
        <label class="plan-option">
          <input type="radio" name="plan_id" value="{{ $r['plan']->id }}" required @checked(old('plan_id', $selected) == $r['plan']->id)>
          <span><b>{{ $r['plan']->name }}</b><br><small>{{ $r['plan']->term === 1 ? 'Month-to-month' : $r['plan']->term.'-month fixed rate' }}@if ($r['plan']->green) · {{ $r['plan']->green }}% renewable @endif · ETF {{ $r['plan']->etf }}</small></span>
          <strong>{{ number_format($r['price'], 1) }}¢<small>/kWh</small></strong>
        </label>
      @empty
        <p>No plans are available at this address right now. Please call us at {{ config('brand.phone') }}.</p>
      @endforelse
    </div>
    <div class="co-actions"><a class="btn btn-outline" href="{{ route('checkout') }}">Back</a>@if ($plans->count())<button class="btn">Continue</button>@endif</div>
  </form>
</x-frontend.checkout>
@endsection
