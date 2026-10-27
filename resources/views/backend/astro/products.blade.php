@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.products.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'BYOP Products', 'sub' => 'Add-ons offered in the website\'s Build Your Own Plan. Prices here are what the website shows.', 'actions' => '<a class="btn ghost" href="'.route('astro.products.create').'">New Product</a><button class="btn cyan">Save Products</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th>Product</th><th>Model</th><th>Step</th><th>Type</th><th class="num">Rate change ¢/kWh</th><th class="num">Monthly $</th><th>Show on Website</th></tr></thead>
            <tbody>@foreach ($products as $p)
                <tr><td><a href="{{ route('astro.products.edit', $p) }}"><b>{{ $p->name }}</b></a>@if ($p->description)<div class="help">{{ \Illuminate\Support\Str::limit($p->description, 60) }}</div>@endif</td><td class="mono">{{ $p->model }}</td><td>{{ $p->step }}</td><td>{{ $p->type }}</td>
                    <td class="num"><input type="number" step="0.01" name="p[{{ $p->id }}][rate_adj]" value="{{ number_format($p->rate_adj, 2, '.', '') }}" @class(['neg' => $p->rate_adj < 0]) data-track aria-label="{{ $p->name }} rate change"></td>
                    <td class="num"><input type="number" step="0.01" min="0" name="p[{{ $p->id }}][monthly]" value="{{ number_format($p->monthly, 2, '.', '') }}" data-track aria-label="{{ $p->name }} monthly price"></td>
                    <td><input type="hidden" name="p[{{ $p->id }}][active]" value="0">
                        <label class="check"><input type="checkbox" name="p[{{ $p->id }}][active]" value="1" @checked($p->active) data-track> <span data-label data-on="Shown" data-off="Hidden">{{ $p->active ? 'Shown' : 'Hidden' }}</span></label></td></tr>
            @endforeach</tbody>
        </table></div></div>
        <p class="help">A negative rate change is a discount. Discount limits are in Modifiers → <a href="{{ route('astro.modifiers.products') }}">Products</a>. Hidden products disappear from the website's Build Your Own Plan page.</p>
    </form>
@endsection
