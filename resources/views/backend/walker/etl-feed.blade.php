@extends('backend.layouts.app')

@section('crumb', $feed['name'])

@section('content')
    @include('backend.partials.page-head', ['title' => $feed['name'], 'sub' => ($feed['direction'] === 'In' ? 'Inbound from ' : 'Outbound to ').e($feed['party']).' · '.e($feed['schedule'])])

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Feed</h2></div><div class="panel-body"><dl class="kv">
            <dt>Direction</dt><dd>{{ $feed['direction'] === 'In' ? 'Inbound' : 'Outbound' }}</dd>
            <dt>{{ $feed['direction'] === 'In' ? 'From' : 'To' }}</dt><dd>{{ $feed['party'] }}</dd>
            <dt>Schedule</dt><dd>{{ $feed['schedule'] }} <span class="mono help">{{ $feed['cron'] }}</span></dd>
            <dt>Format</dt><dd>{{ $feed['format'] }}</dd>
            <dt>File Name</dt><dd class="mono">{{ $feed['file'] }}</dd>
            <dt>{{ $feed['direction'] === 'In' ? 'Loads Into' : 'Built From' }}</dt><dd>{{ $feed['target'] }}</dd>
            <dt>Command</dt><dd class="mono">etl:{{ $key }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Health</h2></div><div class="panel-body"><dl class="kv">
            <dt>Last Good Run</dt><dd>{{ $lastOk?->started_at->format('n/j/Y g:i A') ?? 'Never' }}@if ($lastOk) <span class="help">{{ $lastOk->started_at->diffForHumans() }}</span>@endif</dd>
            <dt>Runs</dt><dd>{{ number_format($counts->sum()) }}</dd>
            <dt>Failed</dt><dd>{{ number_format($counts['Failed'] ?? 0) }}</dd>
            <dt>Warnings</dt><dd>{{ number_format($counts['Warning'] ?? 0) }}</dd>
        </dl></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Runs</h2>
        <div class="chips small">
            <a class="chip-link {{ empty($f['status']) ? 'on' : '' }}" href="{{ route('walker.etl.show', $key) }}">All <span class="help">{{ $counts->sum() }}</span></a>
            @foreach (['OK', 'Warning', 'Failed'] as $s)<a class="chip-link {{ ($f['status'] ?? null) === $s ? 'on' : '' }}" href="{{ route('walker.etl.show', [$key, 'status' => $s]) }}">{{ $s }} <span class="help">{{ $counts[$s] ?? 0 }}</span></a>@endforeach
        </div></div>
        <div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Started</th><th>Status</th><th>Result</th><th class="num">Duration</th></tr></thead>
            <tbody>@forelse ($runs as $r)
                @php $u = \App\Support\Records::url('job_run', $r->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $r->id }}</b></a></td><td class="nowrap">{{ $r->started_at->format('n/j/Y g:i A') }}</td>
                    <td>@include('backend.partials.pill', ['text' => $r->status, 'tone' => ['OK' => 'ok', 'Warning' => 'warn', 'Failed' => 'bad'][$r->status] ?? ''])</td>
                    <td>{{ $r->message }}</td><td class="num nowrap">{{ $r->duration_ms !== null ? number_format($r->duration_ms / 1000, 1).'s' : '—' }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No runs{{ ! empty($f['status']) ? ' with status '.$f['status'] : '' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $runs])
    </div>
@endsection
