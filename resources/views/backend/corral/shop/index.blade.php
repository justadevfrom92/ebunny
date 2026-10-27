@extends('backend.layouts.app')

{{-- Corral → Products: the website Shop's products. Each row opens the product. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Products', 'sub' => 'Everything in the website Shop. Open a product to edit it or order it for a customer.',
        'actions' => '<a class="btn ghost" href="'.route('corral.shop.orders').'">Product Orders</a><a class="btn cyan" href="'.route('corral.shop.create').'">New Product</a>'])

    <select class="pill-select" aria-label="Category" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('corral.shop.index') }}">All categories · {{ $counts->sum() }}</option>
        @foreach ($cats as $k => $label)<option value="{{ route('corral.shop.index', ['cat' => $k]) }}" @selected($cat === $k)>{{ $label }} · {{ $counts[$k] ?? 0 }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stars</th><th>Stock</th><th>Shop</th><th class="num">Sold</th></tr></thead>
        <tbody>@forelse ($products as $p)
            @php $u = route('corral.shop.show', $p); $monthly = isset(\App\Services\Cart::PROTECTION[$p->sku]); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $p->id }}</b></a></td>
                <td><b>{{ $p->name }}</b><div class="help mono">{{ $p->sku }}</div></td><td>{{ $cats[$p->cat] ?? $p->cat }}</td>
                <td class="num nowrap">${{ number_format($p->price, 2) }}@if ($monthly)<div class="help">${{ number_format($p->price / 12, 2) }}/mo on the bill</div>@elseif ($p->was)<div class="help"><s>${{ number_format($p->was, 2) }}</s></div>@endif</td>
                <td class="num">{{ number_format($p->stars) }}</td>
                <td>@include('backend.partials.pill', ['text' => $p->stock ? 'In stock' : 'Out of stock', 'tone' => $p->stock ? 'ok' : 'bad'])</td>
                <td>@include('backend.partials.pill', ['text' => $p->active ? 'Shown' : 'Hidden', 'tone' => $p->active ? 'ok' : ''])</td>
                <td class="num">{{ number_format($sold[$p->sku] ?? 0) }}</td></tr>
        @empty <tr><td colspan="8" class="empty">No products{{ $cat ? ' in this category' : '' }}.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $products])</div>
@endsection
