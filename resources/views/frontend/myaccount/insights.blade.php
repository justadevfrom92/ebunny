@extends('frontend.layouts.main')
@section('title', 'Energy Insights')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Energy Insights">
  <div class="ma-cards">
    <div class="ma-stat"><span>Average Use</span><strong>{{ number_format($avg ?? 0) }}</strong><small>kWh per month</small></div>
    <div class="ma-stat"><span>Highest Month</span><strong>{{ number_format($bills->max('kwh') ?? 0) }}</strong><small>kWh</small></div>
    <div class="ma-stat"><span>Last Month</span><strong>{{ number_format($bills->last()?->kwh ?? 0) }}</strong><small>kWh</small></div>
  </div>
  <div class="co-card" style="margin-top:20px"><h3>Monthly Usage</h3>
    <x-bar-chart :bars="$bills->map(fn ($b) => ['label' => $b->billed_on->format('M y'), 'value' => $b->kwh, 'tip' => $b->billed_on->format('F Y')])" unit="kWh" />
    <table class="price-table" style="margin-top:20px"><thead><tr><th>Bill</th><th>kWh</th><th>Amount</th><th>¢/kWh</th></tr></thead><tbody>
      @foreach ($bills->reverse() as $b)<tr><td>{{ $b->billed_on->format('M Y') }}</td><td>{{ number_format($b->kwh) }}</td><td>${{ number_format($b->amount, 2) }}</td><td>{{ $b->kwh ? number_format($b->amount / $b->kwh * 100, 1) : '—' }}</td></tr>@endforeach
    </tbody></table>
  </div>
</x-frontend.myaccount>
@endsection
