@extends('backend.layouts.app')

@section('crumb', 'Order '.$o->number)

{{-- One Shop order: what was bought, how it was paid, where it ships, and its status. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Order '.$o->number, 'sub' => 'Order #'.$o->id.' · placed '.$o->created_at->format('n/j/Y g:i A').' '.($o->placedBy ? 'by '.e($o->placedBy->name).' (by phone)' : 'by the customer in My Account'),
        'actions' => '<a class="btn ghost btn-back" href="'.route('corral.shop.orders').'">Back to Product Orders</a>'
            .($o->customer ? '<a class="btn ghost" href="'.route('corral.customers.shop', $o->customer).'">New Order for This Customer</a>' : '')])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-4 stat-row">
            <div class="stat"><span>Charged Today</span><strong>${{ number_format($o->total, 2) }}</strong><small>{{ $o->pay_with === 'bill' ? 'added to the next bill' : 'card '.($o->method?->last4 ? '•••• '.$o->method->last4 : '') }}</small></div>
            <div class="stat"><span>Monthly</span><strong>{{ $o->monthly > 0 ? '$'.number_format($o->monthly, 2) : '—' }}</strong><small>protection plans on the bill</small></div>
            <div class="stat"><span>Stars Earned</span><strong>{{ number_format($o->stars) }}</strong></div>
            <div class="stat"><span>Status</span><strong style="font-size:1.25rem">{{ $o->status }}</strong><small>payment {{ strtolower($o->payment_status) }}</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Items</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Product</th><th class="num">Qty</th><th class="num">Price</th><th class="num">Line</th><th class="num">Stars</th></tr></thead>
            <tbody>@foreach ($o->items as $i)
                <tr><td><b>{{ $i['name'] }}</b><div class="help mono">{{ $i['sku'] }}</div></td><td class="num">{{ $i['qty'] }}</td><td class="num">${{ number_format($i['price'], 2) }}</td>
                    <td class="num">${{ number_format($i['line'], 2) }}{{ ! empty($i['monthly']) ? '/mo' : '' }}</td><td class="num">{{ number_format($i['stars']) }}</td></tr>
            @endforeach</tbody>
            <tfoot><tr><td colspan="3" class="num">Subtotal</td><td class="num">${{ number_format($o->subtotal, 2) }}</td><td></td></tr><tr><td colspan="3" class="num">Tax</td><td class="num">${{ number_format($o->tax, 2) }}</td><td></td></tr>
                <tr><td colspan="3" class="num"><b>Total today</b></td><td class="num"><b>${{ number_format($o->total, 2) }}</b></td><td></td></tr></tfoot>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Customer & Shipping</h2></div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>@if ($o->customer)@include('backend.partials.account-link', ['c' => $o->customer]) {{ $o->customer->name }}@else — @endif</dd>
            <dt>Ship To</dt><dd>{{ $o->ship_to['name'] ?? '' }}, {{ $o->ship_to['street'] ?? '' }}, {{ $o->ship_to['city'] ?? '' }}, {{ $o->ship_to['state'] ?? 'TX' }} {{ $o->ship_to['zip'] ?? '' }}</dd>
            <dt>Paid With</dt><dd>{{ $o->pay_with === 'bill' ? 'Added to the next electricity bill' : 'Card '.($o->method ? $o->method->type.' •••• '.$o->method->last4 : '') }}</dd>
        </dl></div></div>

        <form method="post" action="{{ route('corral.shop.orders.update', $o) }}" class="panel">@csrf @method('patch')
            <div class="panel-head"><h2>Status</h2></div>
            <div class="panel-body"><div class="form-row"><select name="status" aria-label="Order status">@foreach (['Placed', 'Shipped', 'Delivered', 'Canceled'] as $s)<option @selected($o->status === $s)>{{ $s }}</option>@endforeach</select><button class="btn">Save</button></div></div>
        </form>
    </div>
@endsection
