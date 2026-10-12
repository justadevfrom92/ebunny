@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.modifiers.products.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'Products', 'sub' => 'How far each Build Your Own Plan add-on can be discounted, in ¢/kWh. Leave blank for no limit.',
            'actions' => '<button class="btn cyan">Save</button>'])
        @error('p')<div class="alert bad">{{ $message }}</div>@enderror
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Product</th><th class="num">Current Rate Change</th><th class="num">Min Discount</th><th class="num">Max Discount</th></tr></thead>
            <tbody>@foreach ($products as $p)
                <tr><td><b>{{ $p->name }}</b></td><td class="num">{{ number_format($p->rate_adj, 2) }}</td>
                    @foreach (['min_discount' => 'min discount', 'max_discount' => 'max discount'] as $f => $l)
                        <td class="num"><input type="number" step="0.01" name="p[{{ $p->id }}][{{ $f }}]" value="{{ old("p.$p->id.$f", $p->$f !== null ? number_format($p->$f, 2, '.', '') : '') }}" data-track aria-label="{{ $p->name }} {{ $l }}" placeholder="—"></td>
                    @endforeach</tr>
            @endforeach</tbody>
        </table></div></div>
        <p class="help">Discounts are negative: Min Discount is the smallest cut allowed, Max Discount the largest (e.g. 0 and -0.50). To change many at once use Upload → <a href="{{ route('astro.byop.upload') }}">BYOP Discounts</a>.</p>
    </form>
@endsection
