@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Automations', 'sub' => 'Agents that don\'t talk to customers. They run on a schedule (cron) to fix issues in the background, and leave what they can\'t fix for a person.',
        'actions' => '<a class="btn cyan" href="'.route('deputy.agents.create').'">New Agent</a>'])

    <div class="panel" style="margin-bottom:20px">
        <div class="panel-head"><h2>Scheduled Agents</h2><span class="muted">last 7 days</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Agent</th><th>Schedule</th><th>Model</th><th>Last Run</th><th>Next Run</th><th class="num">Runs</th><th class="num">Checked</th><th class="num">Fixed</th><th class="num">For a Person</th><th class="num">Failed</th></tr></thead>
            <tbody>@forelse ($agents as $a)
                @php $w = $week[$a->id] ?? null; $l = $last[$a->id] ?? null; @endphp
                <tr @class(['muted' => ! $a->active, 'on' => $agent === $a->id])>
                    <td><a href="{{ route('deputy.automations', ['agent' => $a->id]) }}"><b>{{ $a->name }}</b></a> @unless ($a->active)@include('backend.partials.pill', ['text' => 'Paused', 'tone' => ''])@endunless<div class="help">{{ $a->description ?: $a->activityLabel() }} · <a href="{{ route('deputy.agents.edit', $a) }}">Edit</a></div></td>
                    <td><span class="mono">{{ $a->schedule ?? '—' }}</span>@if ($a->schedule)<div class="help">{{ \App\Support\Cron::describe($a->schedule) }}</div>@endif</td>
                    <td>@include('backend.deputy._model', ['m' => $a->model])</td>
                    <td class="nowrap">@if ($l){{ $l->started_at->format('n/j g:i A') }}<br>@include('backend.partials.pill', $l->status === 'ok' ? ['text' => 'OK', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])@else — @endif</td>
                    <td class="nowrap">{{ $a->active && $a->schedule ? \App\Support\Cron::next($a->schedule)?->format('n/j g:i A') : '—' }}</td>
                    <td class="num">{{ number_format($w->n ?? 0) }}</td>
                    <td class="num">{{ number_format($w->checked ?? 0) }}</td>
                    <td class="num">{{ number_format($w->fixed ?? 0) }}</td>
                    <td class="num">{{ number_format($w->flagged ?? 0) }}</td>
                    <td class="num">{{ ($w->failed ?? 0) ? number_format($w->failed) : '—' }}</td></tr>
            @empty <tr><td colspan="10" class="empty">No automations yet. Add an agent with a scheduled activity.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>Runs{{ $agent ? ': '.$agents->firstWhere('id', $agent)?->name : '' }}</h2>@if ($agent)<a class="btn sm ghost" href="{{ route('deputy.automations') }}">All agents</a>@endif</div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Started</th><th>Agent</th><th>Model</th><th>What It Did</th><th class="num">Checked</th><th class="num">Fixed</th><th class="num">For a Person</th><th class="num">Length</th><th>Status</th></tr></thead>
            <tbody>@forelse ($runs as $r)
                <tr><td class="nowrap">{{ $r->started_at->format('n/j/Y g:i A') }}</td>
                    <td>{{ $r->agent?->name }}</td>
                    <td>@include('backend.deputy._model', ['m' => $r->model])</td>
                    <td style="white-space:normal">{{ $r->summary }}</td>
                    <td class="num">{{ number_format($r->checked) }}</td><td class="num">{{ number_format($r->fixed) }}</td><td class="num">{{ number_format($r->flagged) }}</td>
                    <td class="num">{{ $r->duration_sec }}s</td>
                    <td>@include('backend.partials.pill', $r->status === 'ok' ? ['text' => 'OK', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])</td></tr>
            @empty <tr><td colspan="9" class="empty">No runs yet.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $runs])
    </div>
@endsection
