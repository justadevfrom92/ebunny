@extends('frontend.layouts.main')
@section('title', 'Dashboard')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Dashboard">
  <div class="ma-cards">
    <div class="ma-stat"><span>Current Balance</span><strong>${{ number_format($c->balance, 2) }}</strong><small>{{ $c->due_date ? 'Due '.$c->due_date->format('M j, Y') : 'Nothing due' }}</small>
      <a class="btn btn-block" href="{{ route('myaccount.pay') }}">Pay Bill</a></div>
    <div class="ma-stat"><span>Plan</span><strong class="sm">{{ $c->plan?->name ?? '—' }}</strong>
      <small>@if ($term?->contract_end)Contract ends {{ $term->contract_end->format('M j, Y') }}@else{{ $c->status }}@endif</small>
      <a class="btn btn-outline btn-block" href="{{ route('myaccount.plan') }}">Plan & Renewal</a></div>
    <div class="ma-stat"><span>Rewards</span><strong>{{ number_format($c->stars) }} ★</strong><small>stars to spend</small>
      <a class="btn btn-outline btn-block" href="{{ route('myaccount.rewards') }}">Redeem</a></div>
  </div>
  @php $properties = auth('customer')->user()->properties(); @endphp
  @if ($properties->count() > 1)
    <div class="co-card" style="margin-bottom:20px"><h3>Your {{ auth('customer')->user()->type === 'Residential' ? 'Accounts' : 'Properties' }}</h3>
      <div class="ma-table"><table class="price-table">
        <thead><tr><th>Property</th><th>Account</th><th>Plan</th><th class="num">Balance</th><th>Due</th><th></th></tr></thead>
        <tbody>@foreach ($properties as $p)
          <tr @class(['on' => $p->is($c)])><td>{{ $p->propertyLabel() }}</td><td>{{ $p->account }}</td><td>{{ $p->plan?->name ?? '—' }}</td><td class="num">${{ number_format($p->balance, 2) }}</td><td>{{ $p->due_date?->format('M j') ?? '—' }}</td>
            <td class="ma-actions">@unless ($p->is($c))<form method="post" action="{{ route('myaccount.property', $p->account) }}">@csrf<button class="link">View</button></form>@endunless
              <form method="post" action="{{ route('myaccount.property', $p->account) }}">@csrf<input type="hidden" name="to" value="pay"><button class="link">Pay</button></form></td></tr>
        @endforeach</tbody>
        <tfoot><tr><td colspan="3"><b>Total</b></td><td class="num"><b>${{ number_format($properties->sum('balance'), 2) }}</b></td><td colspan="2"></td></tr></tfoot>
      </table></div>
    </div>
  @endif
  <div class="ma-row">
    <div class="co-card"><h3>Last Bill</h3>
      @if ($lastBill)<p>{{ $lastBill->billed_on->format('M j, Y') }} · {{ number_format($lastBill->kwh) }} kWh · <b>${{ number_format($lastBill->amount, 2) }}</b></p><a href="{{ route('myaccount.bills') }}">View all bills</a>
      @else<p>Your first bill will show here.</p>@endif
    </div>
    <div class="co-card"><h3>Services</h3>
      <ul class="ma-services">
        @foreach (['AutoPay' => 'autopay', 'Paperless Billing' => 'paperless-billing', 'Peak Perks' => 'peak-perks'] as $label => $slug)
          <li><a href="{{ route('myaccount.products') }}#{{ $slug }}">{{ $label }}</a> <span @class(['pill-on' => $c->hasProduct($label), 'pill-off' => ! $c->hasProduct($label)])>{{ $c->hasProduct($label) ? 'On' : 'Off' }}</span></li>
        @endforeach
      </ul>
      <a href="{{ route('myaccount.products') }}">All products</a>
    </div>
  </div>
  @if ($usage->count())
    <div class="co-card" style="margin-top:20px"><h3>Usage (kWh)</h3>
      @php $max = max(1, $usage->max('kwh')); @endphp
      <x-bar-chart :bars="$usage->map(fn ($b) => ['label' => $b->billed_on->format('M'), 'value' => $b->kwh, 'tip' => $b->billed_on->format('F Y')])" unit="kWh" />
      <a href="{{ route('myaccount.insights') }}">Energy insights</a>
    </div>
  @endif
</x-frontend.myaccount>
@endsection
