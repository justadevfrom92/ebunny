@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Search Rates', 'sub' => 'Current energy charge plus TDSP delivery gives the average price customers see on the EFL.'])
    <div class="search-layout">
    <aside class="search-side">
    <div class="panel"><div class="panel-head"><h2>Search Rates</h2></div><div class="panel-body">
        <form method="get" class="form-row">
            <div class="field grow"><label for="plan">Plan</label><input id="plan" name="plan" value="{{ $f['plan'] ?? '' }}" placeholder="Search plan name or code"></div>
            <div class="field"><label for="market">Market</label><select id="market" name="market" data-autosubmit><option value="">-- All Markets --</option>
                @foreach ($markets as $m)<option value="{{ $m->id }}" @selected(($f['market'] ?? '') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
            <div class="field"><label for="kwh">Usage</label><select id="kwh" name="kwh" data-autosubmit>@foreach ([500, 1000, 2000] as $k)<option value="{{ $k }}" @selected($kwh === $k)>{{ number_format($k) }} kWh</option>@endforeach</select></div>
            <button class="btn">Search</button>
        </form>
    </div></div>
    </aside>
    <div class="search-main">

    <div class="panel"><div class="panel-head"><h2>Current Rates</h2><span class="muted">{{ count($rows) }} rates</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Rate Class</th><th class="num">Term</th><th>Display Name</th><th>Internal</th><th>Market</th><th class="num">Energy ¢</th><th class="num">TDSP ¢</th><th class="num">Avg ¢ @ {{ number_format($kwh) }}</th><th>Effective</th></tr></thead>
            <tbody>@forelse ($rows as $r)
                <tr><td>{{ $r['plan']->type }}</td><td class="num">{{ $r['plan']->term }}</td><td>{{ $r['plan']->name }}</td><td class="mono">{{ $r['plan']->internal }}</td><td>{{ $r['market']->name }}</td>
                    <td class="num">{{ number_format($r['rate']->energy, 3) }}</td><td class="num">{{ number_format($r['fee']->per_kwh, 4) }}</td><td class="num"><b>{{ number_format($r['avg'], 1) }}</b></td><td>{{ $r['rate']->effective_on->toDateString() }}</td></tr>
            @empty <tr><td colspan="9" class="empty">No rates match.</td></tr> @endforelse</tbody>
        </table></div>
    </div>
    </div>
    </div>
@endsection
