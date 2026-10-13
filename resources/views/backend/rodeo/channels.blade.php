@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'MSIDs & Promo Codes', 'sub' => 'Website links carry ?msid= and ?promo= (e.g. /checkout?msid=3001&promo=FALL25); orders keep them for reporting.'])
    <div class="grid-2" style="align-items:start">
        <div class="panel"><div class="panel-head"><h2>MSIDs (Sales Channels)</h2><a class="btn sm cyan" href="{{ route('rodeo.channels.create') }}">New MSID</a></div>
            <div class="table-wrap"><table><thead><tr><th>MSID</th><th>Channel</th><th>Type</th><th class="num">Orders</th><th>Last Order</th></tr></thead>
            <tbody>@foreach ($channels as $ch)<tr @class(['click', 'muted' => ! $ch->active]) data-href="{{ route('rodeo.channels.edit', $ch) }}"><td class="mono"><a href="{{ route('rodeo.channels.edit', $ch) }}">{{ $ch->msid }}</a></td><td>{{ $ch->name }}</td><td>{{ $ch->type }}</td><td class="num">{{ $orders[$ch->msid]->n ?? 0 }}</td><td>{{ isset($orders[$ch->msid]) ? \Illuminate\Support\Carbon::parse($orders[$ch->msid]->last_at)->format('n/j/Y') : '—' }}</td></tr>@endforeach</tbody></table></div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Promo Codes</h2><a class="btn sm cyan" href="{{ route('rodeo.promos.create') }}">New Promo Code</a></div>
            <div class="table-wrap"><table><thead><tr><th>Code</th><th>Description</th><th class="num">Credit</th><th>Dates</th><th class="num">Uses</th><th>Status</th></tr></thead>
            <tbody>@foreach ($promos as $p)<tr class="click" data-href="{{ route('rodeo.promos.edit', $p) }}"><td class="mono"><a href="{{ route('rodeo.promos.edit', $p) }}"><b>{{ $p->code }}</b></a></td><td>{{ $p->description }}</td><td class="num">${{ number_format($p->credit, 2) }}</td>
                <td class="help">{{ $p->starts_on?->format('n/j/y') ?? 'any' }} – {{ $p->ends_on?->format('n/j/y') ?? 'open' }}</td><td class="num">{{ $promoUses[$p->code] ?? 0 }}</td>
                <td>@include('backend.partials.pill', ['text' => $p->isLive() ? 'Live' : 'Off', 'tone' => $p->isLive() ? 'ok' : ''])</td></tr>@endforeach</tbody></table></div>
        </div>
    </div>
@endsection
