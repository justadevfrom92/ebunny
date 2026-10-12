@extends('backend.layouts.app')

@section('content')
    <form method="post" action="{{ route('astro.terms.update') }}" style="display:flex;flex-direction:column;gap:20px">
        @csrf @method('put')
        @include('backend.partials.page-head', ['title' => 'Term & Discounts', 'sub' => 'Values are in ¢/kWh. A positive value lowers the rate; a negative value raises it. 1.0 is a one-cent discount.', 'actions' => '<button class="btn cyan">Save Modifiers</button>'])
        <div class="panel"><div class="table-wrap"><table>
            <thead><tr><th class="num">Term</th>@foreach ($regions as $r)<th class="num">{{ $r }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($grid as $term => $row)
                <tr><td class="num"><b>{{ $term }}</b></td>
                    @foreach ($regions as $r)
                        @php $v = (float) ($row[$r] ?? 0); @endphp
                        <td class="num"><input type="number" step="0.01" name="d[{{ $term }}][{{ $r }}]" value="{{ number_format($v, 2, '.', '') }}" @class(['neg' => $v < 0]) data-track aria-label="Term {{ $term }} {{ $r }}"></td>
                    @endforeach</tr>
            @endforeach</tbody>
        </table></div></div>
    </form>
@endsection
