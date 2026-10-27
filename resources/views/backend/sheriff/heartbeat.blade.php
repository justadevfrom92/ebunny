@extends('backend.layouts.app')

@section('crumb', 'Heartbeat')

{{-- The heartbeat: everything the admin runs on its own, checked every minute, with the last 24 hours. Refreshes itself. --}}
@section('content')
    @php
        $label = ['ok' => 'Healthy', 'warn' => 'Warnings', 'bad' => 'Problems', 'down' => 'Taken Down'];
        $tone = ['ok' => 'ok', 'warn' => 'warn', 'bad' => 'bad', 'down' => 'info'];
        $bad = collect($checks)->where(0, 'bad')->count();
        $warn = collect($checks)->where(0, 'warn')->count();
        $down = collect($checks)->where(0, 'down')->count();
    @endphp
    @include('backend.partials.page-head', ['title' => 'Heartbeat', 'sub' => 'Checked every minute by the scheduler (php artisan et:heartbeat). '.($last ? 'Last check '.$last->checked_at->diffForHumans().' in '.$last->duration_ms.' ms.' : 'No check has run yet.').' This page refreshes every 30 seconds.',
        'actions' => '<form method="post" action="'.route('sheriff.heartbeat.check').'" class="inline">'.csrf_field().'<button class="btn cyan">Check Now</button></form>'])

    <div id="hb-live" style="display:flex;flex-direction:column;gap:20px">
        <div class="hb-banner hb-{{ $status }}">
            <span class="hb-heart" aria-hidden="true">❤</span>
            <div><b>{{ $label[$status] }}</b><span>{{ $status === 'ok' ? 'Everything is running.' : ($bad ? $bad.' '.Str::plural('problem', $bad) : '').($bad && $warn ? ' and ' : '').($warn ? $warn.' '.Str::plural('warning', $warn) : '').($down ? (($bad || $warn) ? ', ' : '').$down.' taken down' : '').' below.' }}</span></div>
            @if ($uptime !== null)<div class="hb-uptime"><strong>{{ rtrim(rtrim(number_format($uptime, 1), '0'), '.') }}%</strong><small>healthy, last 24 hours</small></div>@endif
        </div>

        <div class="grid-4 stat-row">
            <div class="stat"><span>Scheduler</span><strong style="font-size:1.25rem">{{ $scheduler ? $scheduler->diffForHumans() : 'Never' }}</strong><small>last beat</small></div>
            <div class="stat"><span>Queue Worker</span><strong style="font-size:1.25rem">{{ $queue ? $queue->diffForHumans() : 'Never' }}</strong><small>last answered a ping</small></div>
            <div @class(['stat', 'alert' => $waitingCount > 50])><span>Jobs Waiting</span><strong>{{ number_format($waitingCount) }}</strong><small>in the queue now</small></div>
            <div @class(['stat', 'alert' => $failedCount > 0])><span>Failed Jobs</span><strong>{{ number_format($failedCount) }}</strong><small>last 24 hours</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Last 24 Hours</h2><span class="muted">{{ number_format($beats24) }} checks · each block is 15 minutes, the worst result in it</span></div>
            <div class="panel-body">
                @include('backend.sheriff._hb-strip', ['blocks' => $blocks, 'mode' => 'all'])
            </div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Checks</h2><a class="btn sm ghost" href="{{ route('sheriff.heartbeat.checks') }}">All Checks</a></div><div class="table-wrap"><table>
            <thead><tr><th>Status</th><th>Check</th><th>Now</th><th>What to Do</th></tr></thead>
            <tbody>@foreach (collect(\App\Support\Health::CHECKS)->sortBy(fn ($c, $k) => ['bad' => 0, 'warn' => 1, 'down' => 2, 'ok' => 3][$checks[$k][0]]) as $k => [$name, $group, $what, $route])
                @php [$st, $detail, $fix] = $checks[$k]; $url = route('sheriff.heartbeat.show', $k); @endphp
                <tr @class(['click', 'row-warn' => $st !== 'ok']) data-href="{{ $url }}">
                    <td>@include('backend.partials.pill', ['text' => $label[$st], 'tone' => $tone[$st]])</td>
                    <td><a href="{{ $url }}"><b>{{ $name }}</b></a><div class="help">{{ $group }} · {{ $what }}</div></td>
                    <td class="wrap">{{ $detail }}</td><td class="wrap help">{{ $fix ?? '—' }}</td></tr>
            @endforeach</tbody>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Crons</h2><a class="btn sm ghost" href="{{ route('sheriff.jobs.index') }}">All Crons</a></div><div class="table-wrap"><table>
            <thead><tr><th>Cron</th><th>Runs</th><th>Last Run</th><th>Result</th></tr></thead>
            <tbody>@foreach ($crons as $c)
                <tr class="click" data-href="{{ route('sheriff.jobs.index') }}"><td><a href="{{ route('sheriff.jobs.index') }}"><b>{{ $c['name'] }}</b></a><div class="help mono">{{ $c['command'] }}</div></td><td class="mono">{{ $c['schedule'] }}</td>
                    <td class="nowrap">{{ $c['last']?->started_at?->diffForHumans() ?? 'Never' }}</td>
                    <td>@if ($c['last'])@include('backend.partials.pill', ['text' => $c['last']->status, 'tone' => ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad', 'Running' => 'info'][$c['last']->status] ?? ''])<div class="help">{{ \Illuminate\Support\Str::limit($c['last']->message, 80) }}</div>@else<span class="muted">—</span>@endif</td></tr>
            @endforeach</tbody>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Waiting in the Queue</h2><span class="muted">{{ number_format($waitingCount) }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Job</th><th>Queue</th><th class="num">Tries</th><th>Waiting Since</th></tr></thead>
            <tbody>@forelse ($waiting as $j)
                @php $p = json_decode($j->payload, true); @endphp
                <tr><td><b>{{ class_basename($p['displayName'] ?? 'Job') }}</b></td><td class="mono">{{ $j->queue }}</td><td class="num">{{ $j->attempts }}</td><td class="nowrap">{{ \Illuminate\Support\Carbon::createFromTimestamp($j->available_at)->diffForHumans() }}</td></tr>
            @empty <tr><td colspan="4" class="empty">Nothing waiting: the worker is keeping up.</td></tr> @endforelse</tbody>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Failed Jobs</h2><span class="muted">latest 10</span></div><div class="table-wrap"><table>
            <thead><tr><th>Failed</th><th>Job</th><th>Error</th></tr></thead>
            <tbody>@forelse ($failed as $j)
                @php $p = json_decode($j->payload, true); @endphp
                <tr><td class="nowrap">{{ \Illuminate\Support\Carbon::parse($j->failed_at)->format('n/j/Y g:i A') }}</td><td><b>{{ class_basename($p['displayName'] ?? 'Job') }}</b><div class="help mono">{{ $j->queue }}</div></td>
                    <td class="wrap help">{{ \Illuminate\Support\Str::limit(strtok((string) $j->exception, "\n"), 200) }}</td></tr>
            @empty <tr><td colspan="3" class="empty">No failed jobs.</td></tr> @endforelse</tbody>
        </table></div></div>

        <div class="panel"><div class="panel-head"><h2>Latest Actions</h2><a class="btn sm ghost" href="{{ route('sheriff.timeline') }}">Timeline</a></div><div class="table-wrap"><table>
            <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
            <tbody>@forelse ($actions as $h)
                <tr class="click" data-href="{{ route('sheriff.history.show', $h) }}"><td class="nowrap"><a href="{{ route('sheriff.history.show', $h) }}">{{ $h->created_at->format('n/j g:i A') }}</a></td><td>{{ $h->actor() }}</td><td class="wrap">{{ $h->summary }}</td></tr>
            @empty <tr><td colspan="3" class="empty">No actions recorded.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    <script>
    // Keep the page current: reload its live part every 30 seconds
    setInterval(function () {
        fetch(location.href, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : null; }).then(function (html) {
            if (!html) return; var fresh = new DOMParser().parseFromString(html, 'text/html').getElementById('hb-live'), now = document.getElementById('hb-live');
            if (fresh && now) { now.replaceWith(fresh); document.dispatchEvent(new Event('hb:refresh')); }   // the picked block stays open
        }).catch(function () {});
    }, 30000);
    </script>
@endsection
