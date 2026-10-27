@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Crons', 'sub' => 'Scheduled background jobs. On the server, run <span class="mono">php artisan schedule:run</span> every minute from cron.'])
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Job</th><th>Command</th><th>Schedule</th><th>Last Run</th><th class="num">Duration</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach ($jobs as $j)
            @php $s = $j['last']?->status; @endphp
            <tr><td><b>{{ $j['name'] }}</b>@if ($j['last']?->message)<br><span class="help">{{ $j['last']->message }}</span>@endif</td>
                <td class="mono">{{ $j['command'] }}</td><td class="mono">{{ $j['schedule'] }}</td>
                <td class="nowrap">@if ($j['last'])<a href="{{ route('sheriff.jobs.show', $j['last']) }}">#{{ $j['last']->id }}</a> · {{ $j['last']->started_at->format('n/j/Y g:i A') }}@else Never @endif</td>
                <td class="num">{{ $j['last']?->duration_ms !== null && $j['last'] ? number_format($j['last']->duration_ms / 1000, 1).'s' : '—' }}</td>
                <td>@if ($s)@include('backend.partials.pill', ['text' => $s, 'tone' => ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad', 'Skipped' => 'warn', 'Running' => 'info'][$s] ?? ''])@endif</td>
                <td><form method="post" action="{{ route('sheriff.jobs.run') }}" class="inline">@csrf<input type="hidden" name="command" value="{{ $j['command'] }}"><button class="btn sm ghost">Run Now</button></form></td></tr>
        @endforeach</tbody>
    </table></div></div>
    <div class="panel" style="margin-top:20px"><div class="panel-head"><h2>Recent Runs</h2><span class="muted">every cron run, newest first</span></div><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Started</th><th>Job</th><th>Status</th><th class="num">Duration</th><th>Message</th></tr></thead>
        <tbody>@forelse ($history as $r)
            @php $url = route('sheriff.jobs.show', $r); @endphp
            <tr class="click" data-href="{{ $url }}"><td class="nowrap"><a href="{{ $url }}"><b>#{{ $r->id }}</b></a></td><td class="nowrap">{{ $r->started_at->format('n/j/Y g:i A') }}</td>
                <td><b>{{ $r->jobName() }}</b><div class="help mono">{{ $r->command }}</div></td>
                <td>@include('backend.partials.pill', ['text' => $r->status, 'tone' => $r->tone()])</td>
                <td class="num">{{ $r->duration_ms !== null ? number_format($r->duration_ms / 1000, 1).'s' : '—' }}</td>
                <td class="wrap">{{ \Illuminate\Support\Str::limit((string) $r->message, 120) }}</td></tr>
        @empty <tr><td colspan="6" class="empty">No runs yet.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $history])</div>
@endsection
