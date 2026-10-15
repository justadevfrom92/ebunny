@extends('frontend.layouts.main')
@section('title', 'Current Plan & Renew')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="Current Plan & Renew">
  <div class="co-card">
    <h3>{{ $c->plan?->name ?? 'No plan' }}</h3>
    <dl class="kv-site">
      <dt>Term</dt><dd>{{ $c->plan ? ($c->plan->term === 1 ? 'Month-to-month' : $c->plan->term.' months') : '—' }}</dd>
      <dt>Contract</dt><dd>{{ $term?->contract_start?->format('M j, Y') ?? '—' }} – {{ $term?->contract_end?->format('M j, Y') ?? '—' }}</dd>
      <dt>Early termination fee</dt><dd>{{ $c->plan?->etf ?? '—' }}</dd>
    </dl>
    @if ($pending)<p class="site-alert ok">Renewal scheduled: {{ $pending->plan?->name }} starting {{ $pending->contract_start?->format('M j, Y') }}.</p>@endif
  </div>
  <div class="co-card" style="margin-top:20px"><h3>Renew or change your plan</h3>
    <form method="post" action="{{ route('myaccount.renew') }}">@csrf
      <div class="plan-pick">
        @forelse ($offers as $r)
          <label class="plan-option"><input type="radio" name="plan_id" value="{{ $r['plan']->id }}" required>
            <span><b>{{ $r['plan']->name }}</b><br><small>{{ $r['plan']->term }}-month fixed rate · ETF {{ $r['plan']->etf }}</small></span>
            <strong>{{ number_format($r['price'], 1) }}¢<small>/kWh</small></strong></label>
        @empty <p>No renewal offers right now. Call {{ config('brand.phone') }}.</p> @endforelse
      </div>
      @if ($offers->count())
        <label class="check" style="margin-top:16px"><input type="checkbox" name="agree" value="1" required> I agree to the new plan's Electricity Facts Label and Terms of Service</label>
        <button class="btn" style="margin-top:12px">Renew</button>
      @endif
    </form>
    <p class="muted-text">Prices are averages at 1,000 kWh a month, including delivery charges. Your new plan starts when your current contract ends.</p>
  </div>
</x-frontend.myaccount>
@endsection
