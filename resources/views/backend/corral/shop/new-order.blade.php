@extends('backend.layouts.app')

@section('crumb', 'Order Products')
@section('crumb_trail')<a href="{{ route('corral.customers.index') }}">Customer Search</a> / <a href="{{ route('corral.customers.show', $c) }}">Account {{ $c->account }}</a>@endsection

{{-- Order Shop products for a customer on the phone, as they would on the website: pick items, then pay and ship like checkout. --}}
@section('content')
    @php $protection = \App\Services\Cart::PROTECTION; @endphp
    @include('backend.partials.page-head', ['title' => 'Order Products for '.$c->name, 'sub' => 'Account <a class="mono" href="'.route('corral.customers.show', $c).'">'.e($c->account).'</a> · the same products, prices and stars as the website Shop. Protection plans go on the electricity bill monthly.',
        'actions' => '<a class="btn ghost btn-back" href="'.route('corral.customers.show', $c).'#shop">Back to Account</a>'])

    <form method="post" action="{{ route('corral.customers.shop.place', $c) }}" data-shop-order style="display:flex;flex-direction:column;gap:20px">@csrf
        @if ($errors->any())<div class="flash bad">{{ $errors->first() }}</div>@endif

        @foreach ($products as $cat => $list)
            <div class="panel"><div class="panel-head"><h2>{{ $cats[$cat] ?? $cat }}</h2></div><div class="table-wrap"><table data-page="0">
                <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Stars</th><th>Stock</th><th class="num">Qty</th></tr></thead>
                <tbody>@foreach ($list as $p)
                    @php $monthly = isset($protection[$p->sku]); $q = (int) ($qty[$p->sku] ?? 0); @endphp
                    <tr @class(['row-warn' => $q > 0])><td><b>{{ $p->name }}</b>@if ($p->badge) <span class="pill info">{{ $p->badge }}</span>@endif<div class="help">{{ $p->blurb }}</div></td>
                        <td class="num nowrap">${{ number_format($monthly ? $p->price / 12 : $p->price, 2) }}{{ $monthly ? '/mo' : '' }}@if ($p->was && ! $monthly)<div class="help"><s>${{ number_format($p->was, 2) }}</s></div>@endif</td>
                        <td class="num">{{ number_format($p->stars) }}</td>
                        <td>@include('backend.partials.pill', ['text' => $p->stock ? 'In stock' : 'Out', 'tone' => $p->stock ? 'ok' : 'bad'])</td>
                        <td class="num" style="width:110px"><input type="number" name="qty[{{ $p->sku }}]" min="0" max="{{ $monthly ? 1 : 10 }}" value="{{ $q }}" aria-label="How many {{ $p->name }}" @disabled(! $p->stock)
                            data-price="{{ $monthly ? 0 : $p->price }}" data-monthly="{{ $monthly ? round($p->price / 12, 2) : 0 }}" data-stars="{{ $p->stars }}" style="width:80px;text-align:right"></td></tr>
                @endforeach</tbody>
            </table></div></div>
        @endforeach

        <div class="panel"><div class="panel-head"><h2>Checkout</h2></div>
            <div class="panel-body form-grid" style="grid-template-columns:1fr">
                <label>Order total</label><div class="order-total" data-order-total><b>$0.00</b> today · <b>$0.00</b>/mo on the bill · 0 stars</div>
                <label for="pay_with">Pay with</label>
                <select id="pay_with" name="pay_with" data-pay-with>
                    <option value="card" @selected(old('pay_with') === 'card') @disabled($methods->isEmpty())>A card on the account</option>
                    <option value="bill" @selected(old('pay_with', $methods->isEmpty() ? 'bill' : null) === 'bill')>Add to the next electricity bill</option>
                </select>
                <label for="method">Card</label><select id="method" name="method">@forelse ($methods as $m)<option value="{{ $m->id }}" @selected((int) old('method') === $m->id)>{{ $m->type }} •••• {{ $m->last4 }}{{ $m->nickname ? ' ('.$m->nickname.')' : '' }}{{ $m->autopay ? ' · AutoPay' : '' }}</option>@empty<option value="">No cards on the account</option>@endforelse</select>
                <label for="ship">Ship to</label>
                <select id="ship" name="ship" data-ship><option value="service" @selected(old('ship') !== 'other')>Service address: {{ trim($c->address.($c->unit ? ' #'.$c->unit : '')) }}, {{ $c->city }} {{ $c->zip }}</option><option value="other" @selected(old('ship') === 'other')>Another address</option></select>
                <label for="street" data-other>Street</label><input id="street" name="street" maxlength="120" value="{{ old('street') }}" data-other>
                <label for="city" data-other>City</label><input id="city" name="city" maxlength="60" value="{{ old('city') }}" data-other>
                <label for="zip" data-other>ZIP</label><input id="zip" name="zip" inputmode="numeric" maxlength="5" value="{{ old('zip') }}" data-other>
                <label for="agree">Order terms</label><label class="check"><input id="agree" type="checkbox" name="agree" value="1" required> I read the order terms to the customer and they agreed (returns within 30 days; protection plans can be canceled any time)</label>
            </div>
            <div class="panel-body"><button class="btn cyan">Place Order</button></div>
        </div>
    </form>
    <script>
    (function () {
        var form = document.querySelector('[data-shop-order]'), out = form.querySelector('[data-order-total]');
        function total() {
            var today = 0, monthly = 0, stars = 0;
            form.querySelectorAll('input[name^="qty["]').forEach(function (i) {
                var q = Math.max(0, +i.value || 0); i.closest('tr').classList.toggle('row-warn', q > 0);
                today += q * +i.dataset.price; monthly += q * +i.dataset.monthly; stars += q * +i.dataset.stars;
            });
            today = today * 1.0825;   // with sales tax, like the website
            out.innerHTML = '<b>$' + today.toFixed(2) + '</b> today (with tax) · <b>$' + monthly.toFixed(2) + '</b>/mo on the bill · ' + stars.toLocaleString() + ' stars';
        }
        function ship() { var other = form.querySelector('[data-ship]').value === 'other'; form.querySelectorAll('[data-other]').forEach(function (el) { el.hidden = !other; }); }
        form.addEventListener('input', total); form.querySelector('[data-ship]').addEventListener('change', ship);
        total(); ship();
    })();
    </script>
@endsection
