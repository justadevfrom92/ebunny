@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'ESIID Lookup', 'sub' => 'Search premises on file by street address or ESIID. A live ERCOT premise lookup needs an ERCOT data integration.'])

    <div class="panel"><div class="panel-body">
        <form method="get" class="form-row">
            <div class="field grow"><label for="q">Street Address or ESIID</label><input id="q" name="q" value="{{ $q }}" required placeholder="e.g. 1047 Example Ln or 1008901…"></div>
            <div class="field"><label for="zip">Zip</label><input id="zip" name="zip" value="{{ $zip }}" maxlength="5" inputmode="numeric" style="width:100px"></div>
            <button class="btn">Look Up</button>
        </form>
    </div></div>

    <div class="panel"><div class="panel-head"><h2>Results</h2></div>
        @if ($results === null)
            <div class="empty">Enter an address or ESIID to search.</div>
        @else
            <div class="table-wrap"><table>
                <thead><tr><th>ESIID</th><th>Address</th><th>TDSP</th><th>Premise</th><th>Current Customer</th><th></th></tr></thead>
                <tbody>@forelse ($results as $c)
                    <tr><td class="mono">{{ $c->esiid ?? '—' }}</td><td>{{ $c->address }}, {{ $c->city }} {{ $c->zip }}</td><td>{{ $c->market?->name }}</td>
                        <td>{{ $c->type === 'Residential' ? 'Residential' : 'Small Non-Residential' }}</td>
                        <td><a href="{{ route('corral.customers.show', $c) }}">{{ $c->name }}</a></td>
                        <td>@if ($c->esiid)<a class="btn sm" href="{{ route('corral.orders.create', ['esiid' => $c->esiid]) }}">Start Order</a>@endif</td></tr>
                @empty <tr><td colspan="6" class="empty">No premises found. Check the spelling or try just the house number and street name.</td></tr> @endforelse</tbody>
            </table></div>
        @endif
    </div>
@endsection
