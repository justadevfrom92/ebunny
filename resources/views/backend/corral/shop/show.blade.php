@extends('backend.layouts.app')

@section('crumb', $p->name)

{{-- One Shop product: what the website shows, order it for a customer, and its recent orders. --}}
@section('content')
    @php $monthly = isset(\App\Services\Cart::PROTECTION[$p->sku]); @endphp
    @include('backend.partials.page-head', ['title' => $p->name, 'sub' => 'Product #'.$p->id.' · <span class="mono">'.e($p->sku).'</span> · '.e($cats[$p->cat] ?? $p->cat),
        'actions' => '<a class="btn ghost btn-back" href="'.route('corral.shop.index').'">Back to Products</a><a class="btn cyan" href="'.route('corral.shop.edit', $p).'">Edit Product</a>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-4 stat-row">
            <div class="stat"><span>Price</span><strong>${{ number_format($p->price, 2) }}</strong><small>{{ $monthly ? '$'.number_format($p->price / 12, 2).'/mo on the electricity bill' : ($p->was ? 'was $'.number_format($p->was, 2) : 'charged today') }}</small></div>
            <div class="stat"><span>Rewards Stars</span><strong>{{ number_format($p->stars) }}</strong><small>each</small></div>
            <div @class(['stat', 'alert' => ! $p->stock])><span>Stock</span><strong style="font-size:1.25rem">{{ $p->stock ? 'In stock' : 'Out of stock' }}</strong></div>
            <div class="stat"><span>Sold</span><strong>{{ number_format($sold) }}</strong><small>all orders</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>On the Website</h2>@include('backend.partials.pill', ['text' => $p->active ? 'Shown in the Shop' : 'Hidden from the Shop', 'tone' => $p->active ? 'ok' : ''])</div><div class="panel-body"><dl class="kv">
            <dt>Name</dt><dd>{{ $p->name }}</dd>
            <dt>Description</dt><dd>{{ $p->blurb ?: '—' }}</dd>
            <dt>Badge</dt><dd>{{ $p->badge ?: '—' }}</dd>
            <dt>Rating</dt><dd>{{ number_format($p->rating, 1) }} ★ · {{ number_format($p->reviews) }} reviews</dd>
            <dt>Category</dt><dd>{{ $cats[$p->cat] ?? $p->cat }}</dd>
        </dl></div></div>

        <form method="get" action="" class="panel" data-order-for>
            <div class="panel-head"><h2>Order for a Customer</h2></div>
            <div class="panel-body">
                <div class="form-row"><input name="account" required inputmode="numeric" aria-label="Account number" placeholder="Account number"><button class="btn cyan">Start Order</button></div>
                <p class="help">Opens the order page for that account with this product in it, like the customer adding it to their cart on the website.</p>
            </div>
        </form>

        <div class="panel"><div class="panel-head"><h2>Recent Orders</h2><a class="btn sm ghost" href="{{ route('corral.shop.orders') }}">All Orders</a></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Order</th><th>Placed</th><th>Account</th><th class="num">Qty</th><th>Status</th></tr></thead>
            <tbody>@forelse ($orders as $o)
                @php $u = route('corral.shop.orders.show', $o); @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $o->id }}</b></a></td><td class="mono">{{ $o->number }}</td><td class="nowrap">{{ $o->created_at->format('n/j/Y g:i A') }}</td>
                    <td>@if ($o->customer)@include('backend.partials.account-link', ['c' => $o->customer])<div class="help">{{ $o->customer->name }}</div>@endif</td>
                    <td class="num">{{ collect($o->items)->where('sku', $p->sku)->sum('qty') }}</td><td>@include('backend.partials.pill', ['text' => $o->status, 'tone' => ['Delivered' => 'ok', 'Canceled' => 'bad', 'Shipped' => 'info'][$o->status] ?? 'warn'])</td></tr>
            @empty <tr><td colspan="6" class="empty">Not ordered yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
    <script>
    // Start Order: find the account's order page (account numbers are the account's address in Corral)
    document.querySelector('[data-order-for]').addEventListener('submit', function (e) {
        e.preventDefault();
        var acct = this.account.value.replace(/\D/g, '');
        if (acct) location.href = {!! json_encode(url('/admin/corral/customers')) !!} + '/' + acct + '/order-products?sku={{ urlencode($p->sku) }}';
    });
    </script>
@endsection
