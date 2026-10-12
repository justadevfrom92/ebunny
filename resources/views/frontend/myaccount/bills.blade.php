@extends('frontend.layouts.main')
@section('title', 'View Bills')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="View Bills">
  <div class="co-card"><h3>Bills</h3><div style="overflow-x:auto"><table class="price-table">
    <thead><tr><th>Bill Date</th><th>Period</th><th>Usage</th><th>Amount</th><th>Due</th><th>Status</th></tr></thead>
    <tbody>@forelse ($bills as $b)
      <tr><td>{{ $b->billed_on->format('M j, Y') }}</td><td>{{ $b->period_start?->format('n/j') }}–{{ $b->period_end?->format('n/j') }}</td><td>{{ number_format($b->kwh) }} kWh</td>
        <td>${{ number_format($b->amount, 2) }}</td><td>{{ $b->due_on?->format('M j') }}</td><td>{{ $b->paid_on ? 'Paid '.$b->paid_on->format('n/j') : 'Open' }}</td></tr>
    @empty <tr><td colspan="6">No bills yet.</td></tr> @endforelse</tbody></table></div></div>
  <div class="co-card" style="margin-top:20px"><h3>Payments</h3><div style="overflow-x:auto"><table class="price-table">
    <thead><tr><th>Date</th><th>Amount</th><th>Method</th><th>Status</th><th>Confirmation</th></tr></thead>
    <tbody>@forelse ($payments as $p)
      <tr><td>{{ $p->paid_on?->format('M j, Y') }}</td><td>${{ number_format($p->amount, 2) }}</td><td>{{ $p->method }}</td><td>{{ $p->status }}</td><td>{{ $p->confirmation ?? $p->reference }}</td></tr>
    @empty <tr><td colspan="5">No payments yet.</td></tr> @endforelse</tbody></table></div></div>
</x-frontend.myaccount>
@endsection
