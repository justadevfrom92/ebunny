@extends('backend.layouts.app')

@section('crumb', 'Run #'.$run->id)

{{-- One run of a cron: when, how long, who started it and what it said, then the job's other runs. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $run->jobName().' · Run #'.$run->id, 'sub' => '<span class="mono">'.e($run->command).'</span>'.($job ? ' · runs <span class="mono">'.e($job[1]).'</span>' : ''),
        'actions' => '<a class="btn ghost btn-back" href="'.route('sheriff.jobs.index').'">Back to Crons</a>'
            .($job ? '<form method="post" action="'.route('sheriff.jobs.run').'" class="inline">'.csrf_field().'<input type="hidden" name="command" value="'.e($run->command).'"><button class="btn cyan">Run Again</button></form>' : '')])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-4 stat-row">
            <div @class(['stat', 'alert' => $run->status === 'Failed'])><span>Status</span><strong style="font-size:1.25rem">{{ $run->status }}</strong><small>this run</small></div>
            <div class="stat"><span>Duration</span><strong style="font-size:1.25rem">{{ $run->duration_ms !== null ? number_format($run->duration_ms / 1000, 2).'s' : '—' }}</strong><small>{{ $run->finished_at ? 'finished '.$run->finished_at->format('g:i:s A') : 'not finished' }}</small></div>
            <div class="stat"><span>Runs, 7 Days</span><strong>{{ number_format($stats->n ?? 0) }}</strong><small>{{ (int) ($stats->failed ?? 0) }} failed</small></div>
            <div class="stat"><span>Average Time</span><strong style="font-size:1.25rem">{{ $stats->avg_ms ? number_format($stats->avg_ms / 1000, 2).'s' : '—' }}</strong><small>last 7 days</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Run Details</h2>@include('backend.partials.pill', ['text' => $run->status, 'tone' => $run->tone()])</div>
            <div class="panel-body"><dl class="kv">
                <dt>Run ID</dt><dd class="mono">#{{ $run->id }}</dd>
                <dt>Job</dt><dd>{{ $run->jobName() }}</dd>
                <dt>Command</dt><dd class="mono">php artisan {{ $run->command }}</dd>
                <dt>Schedule</dt><dd class="mono">{{ $job[1] ?? '—' }}</dd>
                <dt>Started</dt><dd>{{ $run->started_at->format('n/j/Y g:i:s A') }} <span class="help">({{ $run->started_at->diffForHumans() }})</span></dd>
                <dt>Finished</dt><dd>{{ $run->finished_at?->format('n/j/Y g:i:s A') ?? '—' }}</dd>
                <dt>Started By</dt><dd>{{ $run->triggeredBy?->name ?? 'Scheduler' }}</dd>
                <dt>Message</dt><dd style="white-space:pre-wrap">{{ $run->message ?: '—' }}</dd>
            </dl></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>Other Runs of This Job</h2><span class="muted">newest first</span></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Started</th><th>Status</th><th class="num">Duration</th><th>Started By</th><th>Message</th></tr></thead>
            <tbody>@forelse ($others as $o)
                @php $url = route('sheriff.jobs.show', $o); @endphp
                <tr class="click" data-href="{{ $url }}"><td class="nowrap"><a href="{{ $url }}"><b>#{{ $o->id }}</b></a></td><td class="nowrap">{{ $o->started_at->format('n/j/Y g:i A') }}</td>
                    <td>@include('backend.partials.pill', ['text' => $o->status, 'tone' => $o->tone()])</td>
                    <td class="num">{{ $o->duration_ms !== null ? number_format($o->duration_ms / 1000, 1).'s' : '—' }}</td>
                    <td>{{ $o->triggered_by ? 'Staff' : 'Scheduler' }}</td><td class="wrap">{{ \Illuminate\Support\Str::limit((string) $o->message, 100) }}</td></tr>
            @empty <tr><td colspan="6" class="empty">No other runs yet.</td></tr> @endforelse</tbody>
        </table></div>@include('backend.partials.pager', ['p' => $others])</div>
    </div>
@endsection
