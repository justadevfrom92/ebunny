@extends('backend.layouts.app')

@section('crumb', $agent->name)

{{-- One cron automation: what it does, its schedule and model, how it has done, and every run --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $agent->name, 'sub' => e($agent->description ?: $agent->activityLabel()).' · '.view('backend.partials.pill', $agent->active ? ['text' => 'On', 'tone' => 'ok'] : ['text' => 'Paused', 'tone' => ''])->render(),
        'actions' => '<form method="post" action="'.route('sheriff.automations.toggle', $agent).'" class="inline">'.csrf_field().'<button class="btn ghost">'.($agent->active ? 'Pause' : 'Resume').'</button></form><a class="btn cyan" href="'.route('sheriff.automations.edit', $agent).'">Edit</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Runs</span><strong>{{ number_format($week->n ?? 0) }}</strong><small>last 7 days · {{ number_format($month->n ?? 0) }} in 30</small></div>
        <div class="stat"><span>Fixed</span><strong>{{ number_format($week->fixed ?? 0) }}</strong><small>of {{ number_format($week->checked ?? 0) }} checked</small></div>
        <div class="stat"><span>For a Person</span><strong>{{ number_format($week->flagged ?? 0) }}</strong><small>left for the team</small></div>
        <div class="stat"><span>Failed Runs</span><strong>{{ number_format($week->failed ?? 0) }}</strong><small>{{ $week && $week->avg_sec ? 'avg '.round($week->avg_sec).'s a run' : 'no runs' }}</small></div>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Schedule</h2></div><div class="panel-body"><dl class="kv">
            <dt>Cron</dt><dd class="mono">{{ $agent->schedule }}</dd>
            <dt>Runs</dt><dd>{{ \App\Support\Cron::describe($agent->schedule) }}</dd>
            <dt>Next Run</dt><dd>{{ $agent->active ? \App\Support\Cron::next($agent->schedule)?->format('l n/j g:i A') : 'Paused' }}</dd>
            <dt>Does</dt><dd>{{ $agent->activityLabel() }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Model</h2></div><div class="panel-body"><dl class="kv">
            <dt>Model</dt><dd>@include('backend.deputy._model', ['m' => $agent->model])</dd>
            <dt>Fallback</dt><dd>@include('backend.deputy._model', ['m' => $agent->fallback])</dd>
            <dt>Temperature</dt><dd>{{ rtrim(rtrim(number_format($agent->temperature, 2), '0'), '.') }}</dd>
            <dt>Tokens</dt><dd>{{ number_format($month->tokens ?? 0) }} in 30 days</dd>
        </dl></div></div>
    </div>

    <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Instructions</h2></div><div class="panel-body"><div style="white-space:pre-wrap">{{ $agent->system_prompt }}</div></div></div>

    <div class="panel">
        <div class="panel-head"><h2>Runs</h2><span class="muted">{{ number_format($runs->total()) }}</span></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>ID</th><th>Started</th><th>What It Did</th><th class="num">Checked</th><th class="num">Fixed</th><th class="num">For a Person</th><th class="num">Length</th><th>Status</th></tr></thead>
            <tbody>@forelse ($runs as $r)
                @php $u = \App\Support\Records::url('automation_run', $r->id); @endphp<tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $r->id }}</b></a></td><td class="nowrap">{{ $r->started_at->format('n/j/Y g:i A') }}@if ($r->model && $r->ai_model_id !== $agent->ai_model_id)<div class="help">on {{ $r->model->name }}</div>@endif</td>
                    <td style="white-space:normal">{{ $r->summary }}</td>
                    <td class="num">{{ number_format($r->checked) }}</td><td class="num">{{ number_format($r->fixed) }}</td><td class="num">{{ number_format($r->flagged) }}</td>
                    <td class="num">{{ $r->duration_sec }}s</td>
                    <td>@include('backend.partials.pill', $r->status === 'ok' ? ['text' => 'OK', 'tone' => 'ok'] : ['text' => 'Failed', 'tone' => 'bad'])</td></tr>
            @empty <tr><td colspan="8" class="empty">Hasn't run yet. It runs on its schedule once it's on.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $runs])
    </div>
@endsection
