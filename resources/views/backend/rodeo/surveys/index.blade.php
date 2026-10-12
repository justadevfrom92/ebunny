@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Surveys',
        'sub' => 'Customer surveys answered on the website. Send one by email: put <span class="mono">&#123;&#123;survey:slug&#125;&#125;</span> in an email template and each customer gets their own link.',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.surveys.responses').'">All Responses</a><a class="btn cyan" href="'.route('rodeo.surveys.create').'">New Survey</a>'])

    @php $byState = $all->groupBy(fn ($s) => $s->state()); @endphp
    <div class="stat-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:20px">
        <div class="stat"><span>Open Surveys</span><strong>{{ $byState->get('open', collect())->count() }}</strong><small>{{ $byState->get('scheduled', collect())->count() }} scheduled · {{ $byState->get('closed', collect())->count() + $byState->get('ended', collect())->count() }} closed</small></div>
        <div class="stat"><span>Responses, last 30 days</span><strong>{{ number_format($recent) }}</strong><small>{{ number_format($all->sum('responses_count')) }} in all</small></div>
        <div class="stat"><span>NPS, last 30 days</span><strong>{{ $nps ?? '—' }}</strong><small>promoters minus detractors</small></div>
        <div class="stat"><span>Questions in the Bank</span><strong>{{ \App\Models\SurveyBankQuestion::count() }}</strong><small><a href="{{ route('rodeo.surveys.bank') }}">Question Bank</a> · <a href="{{ route('rodeo.surveys.answers') }}">Answer Sets</a></small></div>
    </div>

    <div class="panel">
        <div class="panel-head"><h2>All Surveys</h2>
            <div class="actions">@foreach (['' => 'All', 'open' => 'Open', 'scheduled' => 'Scheduled', 'ended' => 'Ended', 'closed' => 'Closed'] as $v => $l)<a class="btn sm {{ (string) $state === $v ? '' : 'ghost' }}" href="{{ route('rodeo.surveys', ['state' => $v ?: null]) }}">{{ $l }}</a>@endforeach</div></div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>Survey</th><th>Status</th><th>Questions by Category</th><th class="num">Responses</th><th class="num">NPS</th><th>Last Response</th><th></th></tr></thead>
            <tbody>@forelse ($surveys as $s)
                @php $st = $s->state(); @endphp
                <tr><td class="wrap" style="min-width:160px"><a href="{{ route('rodeo.surveys.edit', $s) }}"><b>{{ $s->title }}</b></a><div class="mono help">/survey/{{ $s->slug }}</div></td>
                    <td>@include('backend.partials.pill', ['text' => $s->stateLabel(), 'tone' => ['open' => 'ok', 'scheduled' => 'info', 'ended' => '', 'closed' => ''][$st]])
                        @if ($s->closes_on && $st === 'open')<div class="help">until {{ $s->closes_on->format('M j, Y') }}</div>@endif</td>
                    <td class="wrap"><div class="sv-cats">@foreach ($s->questions->groupBy(fn ($q) => $q->category?->name ?? 'Uncategorized') as $cat => $qs)<span class="sv-cat"><span class="sv-dot" style="--dot:{{ $qs->first()->category?->color ?? '#96999d' }}"></span>{{ $cat }} · {{ $qs->count() }}</span>@endforeach</div></td>
                    <td class="num">{{ number_format($s->responses_count) }}</td>
                    <td class="num">{{ $npsBySurvey[$s->id] ?? '—' }}</td>
                    <td class="nowrap">{{ $s->responses_max_created_at ? \Illuminate\Support\Carbon::parse($s->responses_max_created_at)->diffForHumans() : '—' }}</td>
                    <td class="actions"><a class="btn sm" href="{{ route('rodeo.surveys.results', $s) }}">Results</a><a class="btn sm ghost" href="{{ route('rodeo.surveys.responses', ['survey' => $s->id]) }}">Responses</a><a class="btn sm ghost" href="{{ route('rodeo.surveys.edit', $s) }}">Edit</a><a class="btn sm ghost" href="{{ url('survey/'.$s->slug) }}" target="_blank" rel="noopener">Open</a></td></tr>
            @empty <tr><td colspan="7" class="empty">No surveys {{ $state ? 'with that status' : 'yet' }}.</td></tr> @endforelse</tbody>
        </table></div>
    </div>
@endsection
