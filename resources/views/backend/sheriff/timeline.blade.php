@extends('backend.layouts.app')

@section('crumb', $person ? $person->name.' — Timeline' : 'Timeline')

@section('content')
    @php
        $kinds = \App\Http\Controllers\Backend\Sheriff\TimelineController::KINDS;
        $statuses = \App\Http\Controllers\Backend\Sheriff\TimelineController::STATUSES;
        $tone = ['ok' => 'ok', 'warn' => 'warn', 'bad' => 'bad', 'running' => 'info'];
        $kindLabel = ['job' => 'Cron Job', 'etl' => 'ETL Feed', 'automation' => 'Automation', 'report' => 'Report', 'api' => 'API', 'user' => 'Person'];
        $url = fn (array $set) => route('sheriff.timeline', array_filter(array_merge($f, $set), fn ($v) => $v !== null && $v !== '' && $v !== []));
        $max = max(1, $days->max('n') ?? 1);
    @endphp
    @include('backend.partials.page-head', ['title' => $person ? $person->name.' — Timeline' : 'Timeline',
        'sub' => $person ? 'Everything '.e($person->name).' did or ran, newest first.' : 'Everything that ran or happened, newest first: cron jobs, ETL feeds, automations, reports, API calls and what people did. Search the backlog for errors.',
        'actions' => $person ? '<a class="btn ghost" href="'.route('sheriff.users.show', $person).'">Profile</a><a class="btn ghost" href="'.route('sheriff.timeline').'">Everyone</a>' : null])

    <div class="panel"><div class="panel-head"><h2>Last 14 Days</h2><span class="help">Bar height = events; red = errors, amber = warnings. Click a day to see it.</span></div>
        <div class="panel-body tl-days">
            @for ($i = 13; $i >= 0; $i--)
                @php $d = now()->subDays($i)->toDateString(); $row = $days[$d] ?? null; @endphp
                <a class="tl-day" href="{{ $url(['from' => $d, 'to' => $d]) }}" data-tip="{{ \Illuminate\Support\Carbon::parse($d)->format('D n/j') }}: {{ $row->n ?? 0 }} events, {{ $row->bad ?? 0 }} errors, {{ $row->warn ?? 0 }} warnings">
                    <span class="bar" style="height:{{ max(2, round(($row->n ?? 0) / $max * 70)) }}px">@if ($row?->bad)<span class="bad" style="height:{{ max(4, round($row->bad / max(1, $row->n) * 100)) }}%"></span>@endif @if ($row?->warn)<span class="warn" style="height:{{ max(4, round($row->warn / max(1, $row->n) * 100)) }}%"></span>@endif</span>
                    <small>{{ \Illuminate\Support\Carbon::parse($d)->format('n/j') }}</small>@if ($row?->bad)<b>{{ $row->bad }}</b>@endif
                </a>
            @endfor
        </div>
    </div>

    <form method="get" class="panel" action="{{ route('sheriff.timeline') }}">
        @if ($person)<input type="hidden" name="user" value="{{ $person->id }}">@endif
        @if (! empty($f['status']))<input type="hidden" name="status" value="{{ $f['status'] }}">@endif
        <div class="panel-body tl-filters">
            <div class="field"><label for="tl-q">Search</label><input id="tl-q" name="q" type="search" value="{{ $f['q'] ?? '' }}" placeholder="e.g. timeout, Stripe, 867, a name"></div>
            <div class="field"><label for="tl-from">From</label><input id="tl-from" name="from" type="date" value="{{ $f['from'] ?? '' }}"></div>
            <div class="field"><label for="tl-to">To</label><input id="tl-to" name="to" type="date" value="{{ $f['to'] ?? '' }}"></div>
            <div class="field"><label>What</label><div class="chips small">@foreach ($kinds as $k => $l)<label><input type="checkbox" name="kind[]" value="{{ $k }}" @checked(in_array($k, $f['kind'] ?? []))><span>{{ $l }}</span></label>@endforeach</div></div>
            <div class="actions"><button class="btn cyan">Search</button>@if (array_filter(\Illuminate\Support\Arr::except($f, 'user')))<a class="btn ghost" href="{{ route('sheriff.timeline', $person ? ['user' => $person->id] : []) }}">Clear</a>@endif</div>
        </div>
    </form>

    <div class="panel"><div class="panel-head"><h2>{{ number_format($page->total()) }} {{ Str::plural('event', $page->total()) }}</h2>
        <div class="chips small">
            <a class="chip-link {{ empty($f['status']) ? 'on' : '' }}" href="{{ $url(['status' => null]) }}">All · {{ number_format($counts->sum()) }}</a>
            @foreach ($statuses as $s => $l)<a class="chip-link {{ ($f['status'] ?? null) === $s ? 'on' : '' }}" href="{{ $url(['status' => $s]) }}">{{ $l }} · {{ number_format($counts[$s] ?? 0) }}</a>@endforeach
        </div></div>
        <div class="table-wrap"><table class="tl-table">
            <thead><tr><th>ID</th><th>When</th><th>What</th><th>Status</th><th>Event</th><th>Who</th></tr></thead>
            <tbody>@forelse ($page as $e)
                @php
                    $title = match ($e->kind) {
                        'job' => $jobs[$e->title][0] ?? $e->title,
                        'etl' => $feeds[\Illuminate\Support\Str::after($e->title, 'etl:')]['name'] ?? $e->title,
                        'automation' => $agents[$e->title] ?? 'Automation #'.$e->title,
                        default => $e->title,
                    };
                    $idUrl = match ($e->src) {
                        'job_run' => route('sheriff.jobs.show', $e->seq),
                        'report_run' => route('admin.files.show', $e->seq),
                        'history_item' => route('sheriff.history.show', $e->seq),
                        'api_log' => route('sheriff.api-calls.show', $e->seq),
                        default => \App\Support\Records::url($e->src, $e->seq),
                    };
                    $link = match ($e->kind) {
                        'job', 'etl' => $idUrl,
                        'automation' => $e->ref ? route('sheriff.automations.show', $e->ref) : null,
                        'report' => route('admin.files.show', $e->ref),
                        'user' => route('sheriff.history.show', $e->ref),
                        'api' => isset(config('admin.integrations')[$e->ref]) ? route('sheriff.integrations.show', $e->ref) : null,
                        default => null,
                    };
                @endphp
                <tr class="click" data-href="{{ $link ?? $idUrl }}">
                    <td class="nowrap"><a href="{{ $idUrl }}"><b>#{{ $e->seq }}</b></a></td>
                    <td class="nowrap">{{ \Illuminate\Support\Carbon::parse($e->at)->format('n/j/Y g:i:s A') }}</td>
                    <td class="nowrap">{{ $kindLabel[$e->kind] ?? $e->kind }}</td>
                    <td>@include('backend.partials.pill', ['text' => ['ok' => 'Worked', 'warn' => 'Warning', 'bad' => 'Error', 'running' => 'Running'][$e->status] ?? $e->status, 'tone' => $tone[$e->status] ?? ''])</td>
                    <td class="wrap">@if ($link)<a href="{{ $link }}"><b>{{ $title }}</b></a>@else<b>{{ $title }}</b>@endif @if ($e->detail)<div class="help">{{ \Illuminate\Support\Str::limit($e->detail, 160) }}</div>@endif</td>
                    <td class="nowrap">@if ($e->user_id && isset($users[$e->user_id]))<a href="{{ route('sheriff.timeline', ['user' => $e->user_id]) }}">{{ $users[$e->user_id] }}</a>@else<span class="help">System</span>@endif</td>
                </tr>
            @empty <tr><td colspan="6" class="empty">Nothing matches. Try fewer filters.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $page])
    </div>
@endsection
