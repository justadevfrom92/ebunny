@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Crons', 'sub' => 'Scheduled background jobs. On the server, run <span class="mono">php artisan schedule:run</span> every minute from cron.'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Job</th><th>Command</th><th>Schedule</th><th>Last Run</th><th class="num">Duration</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach ($jobs as $j)
            @php $s = $j['last']?->status; @endphp
            <tr><td><b>{{ $j['name'] }}</b>@if ($j['last']?->message)<br><span class="help">{{ $j['last']->message }}</span>@endif</td>
                <td class="mono">{{ $j['command'] }}</td><td class="mono">{{ $j['schedule'] }}</td>
                <td>{{ $j['last']?->started_at->format('Y-m-d H:i') ?? 'Never' }}</td>
                <td class="num">{{ $j['last']?->duration_ms !== null && $j['last'] ? number_format($j['last']->duration_ms / 1000, 1).'s' : '—' }}</td>
                <td>@if ($s)@include('backend.partials.pill', ['text' => $s, 'tone' => ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad', 'Skipped' => 'warn', 'Running' => 'info'][$s] ?? ''])@endif</td>
                <td><form method="post" action="{{ route('sheriff.jobs.run') }}" class="inline">@csrf<input type="hidden" name="command" value="{{ $j['command'] }}"><button class="btn sm ghost">Run Now</button></form></td></tr>
        @endforeach</tbody>
    </table></div></div>
    <div class="panel"><div class="panel-head"><h2>Recent Runs</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Started</th><th>Command</th><th>Status</th><th>Message</th></tr></thead>
        <tbody>@foreach ($history as $r)
            <tr><td>{{ $r->started_at->format('Y-m-d H:i:s') }}</td><td class="mono">{{ $r->command }}</td><td>{{ $r->status }}</td><td class="wrap">{{ $r->message }}</td></tr>
        @endforeach</tbody>
    </table></div></div>
@endsection
