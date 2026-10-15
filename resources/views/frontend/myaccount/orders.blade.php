@extends('frontend.layouts.main')
@section('title', 'Orders')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="Orders">
  <div class="co-card"><div class="ma-table"><table class="price-table">
    <thead><tr><th>Order</th><th>Placed</th><th>Items</th><th class="num">Total</th><th>Status</th><th><span class="sr-only">Open</span></th></tr></thead>
    <tbody>@forelse ($orders as $o)
      <tr><td><a href="{{ route('myaccount.order', $o) }}"><b>{{ $o->number }}</b></a></td><td>{{ $o->created_at->format('M j, Y') }}</td>
        <td>{{ collect($o->items)->pluck('name')->implode(', ') }}</td><td class="num">${{ number_format($o->total, 2) }}@if ($o->monthly > 0)<br><small>+ ${{ number_format($o->monthly, 2) }}/mo</small>@endif</td>
        <td>{{ $o->status }}</td><td class="num"><a href="{{ route('myaccount.order', $o) }}">Open ›</a></td></tr>
    @empty <tr><td colspan="6">No orders yet. <a href="{{ route('shop') }}">Visit the Shop</a>.</td></tr> @endforelse</tbody>
  </table></div></div>
</x-frontend.myaccount>
@endsection
