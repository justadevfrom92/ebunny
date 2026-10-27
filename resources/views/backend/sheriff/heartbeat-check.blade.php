@extends('backend.layouts.app')

@section('crumb', $check[0])

{{-- One heartbeat check: now, the last 24 hours, each time it changed, what it's looking at, its controls and what's been done to it. --}}
@section('content')
    @php
        [$name, $group, $what] = $check;
        [$st, $detail, $fix] = $now;
        $labels = \App\Support\Health::LABELS;
        $isDown = ($control['state'] ?? 'up') === 'down';
    @endphp
    @include('backend.partials.page-head', ['title' => $name, 'sub' => e($group).' · '.e($what),
        'actions' => '<a class="btn ghost btn-back" href="'.route('sheriff.heartbeat.checks').'">Back to Checks</a>'])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="hb-banner hb-{{ $st }}">
            <span class="hb-heart" aria-hidden="true">❤</span>
            <div><b>{{ $labels[$st][0] }}</b><span>{{ $detail }}</span>@if ($fix)<span class="help" style="display:block;margin-top:4px">{{ $fix }}</span>@endif</div>
            @if ($healthy !== null)<div class="hb-uptime"><strong>{{ rtrim(rtrim(number_format($healthy, 1), '0'), '.') }}%</strong><small>no problems, last 24 hours</small></div>@endif
        </div>

        <div class="panel" id="controls"><div class="panel-head"><h2>Controls</h2>@if ($downable)<span class="muted">{{ $isDown ? 'Taken down' : 'Running' }}</span>@endif</div>
            <div class="panel-body hb-controls">
                @foreach ($actions as $act => [$label, $about, $tone])
                    @continue($act === 'down' && $isDown)
                    @continue($act === 'up' && ! $isDown)
                    <form method="post" action="{{ route('sheriff.heartbeat.control', [$key, $act]) }}" @class(['hb-control', 'danger' => $tone === 'danger'])
                        @if ($tone === 'danger') data-confirm="{{ $label }}: {{ $name }}?|{{ $about }}|{{ $label }}" @endif>@csrf
                        <div><b>{{ $label }}</b><p class="help">{{ $about }}</p>
                            @if ($act === 'down')<label class="sr-only" for="hb-reason">Reason</label><input id="hb-reason" name="reason" maxlength="500" placeholder="Reason (optional), e.g. deploy in progress">@endif</div>
                        <button @class(['btn', 'sm', 'danger' => $tone === 'danger', 'cyan' => $act === 'up', 'ghost' => $tone !== 'danger' && $act !== 'up'])>{{ $label }}</button>
                    </form>
                @endforeach
            </div>
        </div>

        <div class="grid-4 stat-row">
            <div @class(['stat', 'alert' => $st === 'bad'])><span>Now</span><strong style="font-size:1.25rem">{{ $labels[$st][0] }}</strong><small>{{ \Illuminate\Support\Str::limit($detail, 40) }}</small></div>
            <div class="stat"><span>Checks</span><strong>{{ number_format($beats24) }}</strong><small>last 24 hours</small></div>
            <div class="stat"><span>Changes</span><strong>{{ number_format($changes->total()) }}</strong><small>last 24 hours</small></div>
            <div class="stat"><span>Controls Used</span><strong>{{ number_format($log->total()) }}</strong><small>all time</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Last 24 Hours</h2><span class="muted">each block is 15 minutes, the worst result in it</span></div>
            <div class="panel-body">
                <div class="hb-strip">@foreach ($blocks as $b)<i class="hb-{{ $b['status'] }}" tabindex="0" data-tip="{{ $b['from']->format('g:i A') }}–{{ $b['from']->copy()->addMinutes(15)->format('g:i A') }}: {{ $b['n'] ? $labels[$b['status']][0] : 'no check ran' }}"></i>@endforeach</div>
                <div class="hb-axis"><span>24 hours ago</span><span>12 hours ago</span><span>now</span></div>
                <div class="hour-legend"><span><i class="hb-dot hb-ok"></i> Healthy</span><span><i class="hb-dot hb-warn"></i> Warning</span><span><i class="hb-dot hb-bad"></i> Problem</span><span><i class="hb-dot hb-down"></i> Taken down</span><span><i class="hb-dot hb-none"></i> No check ran</span></div>
            </div>
        </div>

        @php [$dTitle, $dCols, $dRows] = $details; @endphp
        <div class="panel"><div class="panel-head"><h2>{{ $dTitle }}</h2><span class="muted">what this check is looking at</span></div><div class="table-wrap"><table>
            <thead><tr>@foreach ($dCols as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
            <tbody>@forelse ($dRows as $r)<tr>@foreach ($r as $i => $v)<td class="{{ $i === 0 ? '' : 'wrap' }}">@if ($i === 0)<b>{{ $v }}</b>@else{{ $v }}@endif</td>@endforeach</tr>
            @empty <tr><td colspan="{{ max(1, count($dCols)) }}" class="empty">Nothing here right now.</td></tr> @endforelse</tbody>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Changes</h2><span class="muted">each time the result changed, last 24 hours</span></div><div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Changed To</th><th>From</th><th>Detail</th></tr></thead>
            <tbody>@forelse ($changes as $c)
                <tr @class(['row-warn' => in_array($c['to'], ['warn', 'bad'], true)])><td class="nowrap">{{ $c['at']->format('n/j g:i A') }}</td>
                    <td>@include('backend.partials.pill', ['text' => $labels[$c['to']][0] ?? $c['to'], 'tone' => $labels[$c['to']][1] ?? ''])</td>
                    <td>{{ $c['from'] ? ($labels[$c['from']][0] ?? $c['from']) : 'First check' }}</td><td class="wrap">{{ $c['detail'] }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No checks in the last 24 hours.</td></tr> @endforelse</tbody>
        </table></div>@include('backend.partials.pager', ['p' => $changes])</div>

        <div class="panel"><div class="panel-head"><h2>Control Log</h2><span class="muted">controls used on this check</span></div><div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Control</th><th>Who</th><th>Result</th></tr></thead>
            <tbody>@forelse ($log as $a)
                <tr><td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td><td><b>{{ $a->label() }}</b></td><td>{{ $a->user?->name ?? 'System' }}</td>
                    <td class="wrap">@include('backend.partials.pill', ['text' => $a->ok ? 'Done' : 'Failed', 'tone' => $a->ok ? 'ok' : 'bad'])<div class="help mono" style="white-space:pre-wrap">{{ \Illuminate\Support\Str::limit($a->output, 300) }}</div></td></tr>
            @empty <tr><td colspan="4" class="empty">No controls used yet.</td></tr> @endforelse</tbody>
        </table></div>@include('backend.partials.pager', ['p' => $log])</div>
    </div>
@endsection
