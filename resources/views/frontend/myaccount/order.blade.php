@extends('frontend.layouts.main')
@section('title', 'Order '.$o->number)
@section('crumb')<a href="{{ route('myaccount.dashboard') }}">My Account</a> / <a href="{{ route('myaccount.orders') }}">Orders</a>@endsection
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" :title="'Order '.$o->number">
  <p class="msg-back"><a href="{{ route('myaccount.orders') }}">‹ All orders</a></p>
  <div class="msg-grid">
    <div class="msg-main">
      <section class="co-card"><h3>Items</h3>
        <div class="ma-table"><table class="price-table"><thead><tr><th>Item</th><th>Qty</th><th class="num">Total</th></tr></thead><tbody>@foreach ($o->items as $i)
          <tr><td><a href="{{ route('shop.show', strtolower($i['sku'])) }}">{{ $i['name'] }}</a><br><small>{{ $i['sku'] }}@if ($i['stars']) · {{ number_format($i['stars']) }} ★ earned @endif</small></td><td>{{ $i['qty'] }}</td>
            <td class="num">{{ $i['monthly'] ? '$'.number_format($i['line'], 2).'/mo' : ($i['line'] > 0 ? '$'.number_format($i['line'], 2) : 'Free') }}</td></tr>
        @endforeach</tbody></table></div>
      </section>
      <section class="co-card"><h3>What happens next</h3>
        <ol class="msg-steps"><li class="done">Order placed <small>{{ $o->created_at->format('M j, Y g:i A') }}</small></li>
          <li @class(['done' => in_array($o->status, ['Shipped', 'Delivered'])])>Shipped <small>usually within 2 business days</small></li>
          <li @class(['done' => $o->status === 'Delivered'])>Delivered <small>to {{ $o->ship_to['street'] ?? '' }}</small></li></ol>
        @if ($o->monthly > 0)<p class="cart-note">Your protection plans are on your account now (Products &amp; Protections) and show on your next bill.</p>@endif
      </section>
    </div>
    <aside class="msg-side">
      <section class="co-card"><h3>Summary</h3>
        <dl class="msg-dl"><dt>Order</dt><dd>{{ $o->number }}</dd><dt>Status</dt><dd>{{ $o->status }}</dd>
          <dt>Items</dt><dd>${{ number_format($o->subtotal, 2) }}</dd><dt>Tax</dt><dd>${{ number_format($o->tax, 2) }}</dd><dt>Shipping</dt><dd>Free</dd>
          <dt>Total</dt><dd><b>${{ number_format($o->total, 2) }}</b></dd>@if ($o->monthly > 0)<dt>On your bill</dt><dd>${{ number_format($o->monthly, 2) }}/mo</dd>@endif
          @if ($o->stars)<dt>Stars</dt><dd>{{ number_format($o->stars) }} ★</dd>@endif</dl>
      </section>
      <section class="co-card"><h3>Payment</h3><p>{{ $o->pay_with === 'bill' ? 'Added to your next electricity bill' : ($o->method?->label() ?? 'Card') }}<br><small>{{ $o->payment_status }}</small></p></section>
      <section class="co-card"><h3>Shipping to</h3><p>{{ $o->ship_to['name'] ?? '' }}<br>{{ $o->ship_to['street'] ?? '' }}<br>{{ $o->ship_to['city'] ?? '' }}, {{ $o->ship_to['state'] ?? 'TX' }} {{ $o->ship_to['zip'] ?? '' }}</p></section>
    </aside>
  </div>
</x-frontend.myaccount>
@endsection
