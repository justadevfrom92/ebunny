@extends('backend.layouts.app')

{{-- Sheriff → Cron Automations: AI agents that run on a schedule in the background. Click one for its page and runs. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Cron Automations', 'sub' => 'AI agents that don\'t talk to customers. Each runs on its cron schedule to fix issues in the background, and leaves what it can\'t fix for a person.',
        'actions' => '<a class="btn cyan" href="'.route('sheriff.automations.create').'">New Automation</a>'])

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>Automations</h2><span class="muted">last 7 days</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Automation</th><th>Schedule</th><th>Model</th><th>Last Run</th><th>Next Run</th><th class="num">Runs</th><th class="num">Fixed</th><th class="num">For a Person</th><th class="num">Failed</th></tr></thead>
            <tbody>@forelse ($agents as $a)
                @php $w = $week[$a->id] ?? null; $l = $last[$a->id] ?? null; @endphp
                <tr class="click" data-href="{{ route('sheriff.automations.show', $a) }}">
                    <td><a href="{{ route('sheriff.automations.show', $a) }}"><b>{{ $a->name }}</b></a> @include('backend.partials.pill', $a->active ? ['text' => 'On', 'tone' => 'ok'] : ['text' => 'Paused', 'tone' => ''])<div class="help">{{ $a->description ?: $a->activityLabel() }}</div></td>
                    <td><span class="mono">{{ $a->schedule ?? '—' }}</span>@if ($a->schedule)<div class="help">{{ \App\Support\Cron::describe($a->schedule) }}</div>@endif</td>
                    <td>@include('backend.deputy._model', ['m' => $a->model])</td>
                    <td class="nowrap">@if ($l){{ $l->started_at->format('n/j g:i A') }}<br>@include('backend.partials.pill', $l->status === 'ok' ? ['text' => 'OK', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])@else — @endif</td>
                    <td class="nowrap">{{ $a->active && $a->schedule ? \App\Support\Cron::next($a->schedule)?->format('n/j g:i A') : '—' }}</td>
                    <td class="num">{{ number_format($w->n ?? 0) }}</td>
                    <td class="num">{{ number_format($w->fixed ?? 0) }}</td>
                    <td class="num">{{ number_format($w->flagged ?? 0) }}</td>
                    <td class="num">{{ ($w->failed ?? 0) ? number_format($w->failed) : '—' }}</td></tr>
            @empty <tr><td colspan="9" class="empty">No automations yet.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Latest Runs</h2><span class="muted">all automations</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>ID</th><th>Started</th><th>Automation</th><th>What It Did</th><th class="num">Checked</th><th class="num">Fixed</th><th class="num">For a Person</th><th>Status</th></tr></thead>
            <tbody>@forelse ($runs as $r)
                <tr class="click" data-href="{{ route('sheriff.automations.show', $r->ai_agent_id) }}"><td class="nowrap"><a href="{{ route('sheriff.automations.show', $r->ai_agent_id) }}"><b>#{{ $r->id }}</b></a></td><td class="nowrap">{{ $r->started_at->format('n/j/Y g:i A') }}</td>
                    <td>{{ $r->agent?->name }}</td><td style="white-space:normal">{{ $r->summary }}</td>
                    <td class="num">{{ number_format($r->checked) }}</td><td class="num">{{ number_format($r->fixed) }}</td><td class="num">{{ number_format($r->flagged) }}</td>
                    <td>@include('backend.partials.pill', $r->status === 'ok' ? ['text' => 'OK', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])</td></tr>
            @empty <tr><td colspan="8" class="empty">No runs yet.</td></tr> @endforelse</tbody>
        </table></div>
    </div>
@endsection
