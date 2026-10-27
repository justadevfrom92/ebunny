@extends('backend.layouts.app')

{{-- Corral → Product Orders: every Shop order, from the website or placed by phone. Each row opens the order. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Product Orders', 'sub' => 'Shop orders customers placed in My Account, and orders placed for them by phone.'])

    <select class="pill-select" aria-label="Status" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('corral.shop.orders') }}">All orders</option>
        @foreach (['Placed', 'Shipped', 'Delivered', 'Canceled'] as $s)<option value="{{ route('corral.shop.orders', ['status' => $s]) }}" @selected($status === $s)>{{ $s }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Order</th><th>Placed</th><th>Account</th><th>Items</th><th class="num">Today</th><th class="num">Monthly</th><th>By</th><th>Status</th></tr></thead>
        <tbody>@forelse ($orders as $o)
            @php $u = route('corral.shop.orders.show', $o); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $o->id }}</b></a></td><td class="mono">{{ $o->number }}</td><td class="nowrap">{{ $o->created_at->format('n/j/Y g:i A') }}</td>
                <td>@if ($o->customer)@include('backend.partials.account-link', ['c' => $o->customer])<div class="help">{{ $o->customer->name }}</div>@endif</td>
                <td class="wrap">{{ \Illuminate\Support\Str::limit(collect($o->items)->map(fn ($i) => $i['qty'].'× '.$i['name'])->implode(', '), 80) }}</td>
                <td class="num">${{ number_format($o->total, 2) }}</td><td class="num">{{ $o->monthly > 0 ? '$'.number_format($o->monthly, 2) : '—' }}</td>
                <td>{{ $o->placedBy?->name ?? 'Customer' }}<div class="help">{{ $o->placedBy ? 'by phone' : 'My Account' }}</div></td>
                <td>@include('backend.partials.pill', ['text' => $o->status, 'tone' => ['Delivered' => 'ok', 'Canceled' => 'bad', 'Shipped' => 'info'][$o->status] ?? 'warn'])</td></tr>
        @empty <tr><td colspan="9" class="empty">No orders{{ $status ? ' '.strtolower($status) : '' }}.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $orders])</div>
@endsection
