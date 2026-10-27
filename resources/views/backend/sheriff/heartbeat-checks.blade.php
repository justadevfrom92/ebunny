@extends('backend.layouts.app')

@section('crumb', 'Heartbeat Checks')

{{-- Every heartbeat check right now. Each row opens the check's own page with its history and controls. --}}
@section('content')
    @php $labels = \App\Support\Health::LABELS; @endphp
    @include('backend.partials.page-head', ['title' => 'Heartbeat Checks', 'sub' => 'Everything the heartbeat checks every minute. Open a check to see its last 24 hours and to restart, reboot, take it down or bring it back up.',
        'actions' => '<form method="post" action="'.route('sheriff.heartbeat.check').'" class="inline">'.csrf_field().'<button class="btn cyan">Check Now</button></form>'])

    @foreach (collect(\App\Support\Health::CHECKS)->groupBy(fn ($c) => $c[1], true) as $group => $items)
        <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>{{ $group }}</h2><span class="muted">{{ $items->count() }} checks</span></div><div class="table-wrap"><table>
            <thead><tr><th>Status</th><th>Check</th><th>Now</th><th>Last Control Used</th></tr></thead>
            <tbody>@foreach ($items as $k => [$name, , $what])
                @php [$st, $detail] = $checks[$k]; $url = route('sheriff.heartbeat.show', $k); $a = $lastAction[$k] ?? null; @endphp
                <tr @class(['click', 'row-warn' => in_array($st, ['warn', 'bad'], true)]) data-href="{{ $url }}">
                    <td>@include('backend.partials.pill', ['text' => $labels[$st][0], 'tone' => $labels[$st][1]])</td>
                    <td><a href="{{ $url }}"><b>{{ $name }}</b></a><div class="help">{{ $what }}</div></td>
                    <td class="wrap">{{ $detail }}</td>
                    <td class="wrap">@if ($a){{ $a->label() }}<div class="help">{{ $a->user?->name ?? 'System' }} · {{ $a->created_at->diffForHumans() }}</div>@else<span class="muted">—</span>@endif</td></tr>
            @endforeach</tbody>
        </table></div></div>
    @endforeach
@endsection
