@extends('admin.layouts.app')

@section('crumb', $survey->title.' · Results')

@section('content')
    @include('admin.partials.page-head', ['title' => $survey->title,
        'sub' => 'Results · '.$survey->stateLabel().' · <span class="mono">/survey/'.e($survey->slug).'</span>',
        'actions' => '<a class="btn ghost" href="'.route('rodeo.surveys.responses', ['survey' => $survey->id]).'">Responses</a><a class="btn ghost" href="'.route('rodeo.surveys.export', [$survey] + array_filter($f)).'">Export CSV</a><a class="btn ghost" href="'.route('rodeo.surveys.edit', $survey).'">Edit Survey</a>'])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel" style="margin-bottom:16px"><div class="panel-head"><h2>Filter Results</h2></div><div class="panel-body form-row">
        <div class="field"><label for="f-from">From</label><input id="f-from" type="date" name="from" value="{{ $f['from'] }}"></div>
        <div class="field"><label for="f-to">To</label><input id="f-to" type="date" name="to" value="{{ $f['to'] }}"></div>
        <div class="field"><label for="f-source">Came From</label><select id="f-source" name="source"><option value="">Anywhere</option>@foreach (\App\Models\SurveyResponse::SOURCES as $v => $l)<option value="{{ $v }}" @selected($f['source'] === $v)>{{ $l }}</option>@endforeach</select></div>
        <button class="btn">Apply</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('rodeo.surveys.results', $survey) }}">Clear</a>@endif
    </div></form>
    </aside>
    <div class="search-main">


    <div class="stat-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;margin-bottom:20px">
        <div class="stat"><span>Responses</span><strong>{{ number_format($responses->count()) }}</strong><small>{{ $responses->whereNotNull('customer_id')->count() }} from known customers</small></div>
        <div class="stat"><span>Net Promoter Score</span><strong>{{ $nps ?? '—' }}</strong><small>{{ $nps === null ? 'no 0–10 question' : ($nps >= 50 ? 'excellent' : ($nps >= 20 ? 'good' : ($nps >= 0 ? 'fair' : 'needs work'))) }}</small></div>
        <div class="stat"><span>{{ $scale ? Str::limit($scale['q']->question, 32) : 'Satisfaction' }}</span><strong>{{ $scale && $scale['avg'] ? $scale['avg'].' / '.$scale['of'] : '—' }}</strong><small>{{ $scale && $scale['top'] !== null ? $scale['top'].'% picked the top two' : 'no scale question' }}</small></div>
        <div class="stat"><span>Answered Everything</span><strong>{{ $complete === null ? '—' : $complete.'%' }}</strong><small>{{ $survey->questions->count() }} questions · {{ $required }} required</small></div>
    </div>

    <div class="panel" style="margin-bottom:8px">
        <div class="panel-head"><h2>Responses by Week</h2><span class="muted">last 12 weeks, all sources</span></div>
        <div class="panel-body"><x-bar-chart :bars="collect($weeks)->map(fn ($w) => ['label' => $w['label'], 'value' => $w['count'], 'tip' => 'Week of '.$w['label']])" unit="responses" /></div>
    </div>

    @forelse ($groups as $g)
        @php $cat = $g['category']; $dot = $cat?->color ?? '#96999d'; @endphp
        <div class="sv-cat-head" style="--dot:{{ $dot }}"><span class="sv-dot" style="width:14px;height:14px"></span><h2>{{ $cat?->name ?? 'Uncategorized' }}</h2><span class="muted">{{ count($g['questions']) }} {{ Str::plural('question', count($g['questions'])) }}@if ($cat?->description) · {{ $cat->description }}@endif</span></div>
        <div class="sv-grid">
            @foreach ($g['questions'] as $s)
                @php $q = $s['q']; @endphp
                <div class="panel sv-q" style="--dot:{{ $dot }}">
                    <div class="panel-head"><h3>{{ $q->question }}</h3><span class="sv-type">{{ \App\Models\SurveyQuestion::TYPES[$q->type] ?? $q->type }}</span></div>
                    <div class="panel-body" style="display:grid;gap:12px">
                        <div class="muted" style="font-size:.8rem">{{ $s['count'] }} answered · {{ $s['skipped'] }} skipped</div>
                        @if ($q->type === 'rating')
                            @php $b = array_values($s['bands']); $tot = max(1, array_sum($b)); @endphp
                            <div style="display:flex;gap:18px;align-items:center"><div><div class="sv-big">{{ $s['nps'] ?? '—' }}</div><div class="help">NPS</div></div><div><div class="sv-big" style="font-size:1.4rem">{{ $s['avg'] ?? '—' }}</div><div class="help">average</div></div></div>
                            <div class="sv-nps" title="Detractors, passives, promoters"><span class="det" style="width:{{ $b[0] / $tot * 100 }}%"></span><span class="pas" style="width:{{ $b[1] / $tot * 100 }}%"></span><span class="pro" style="width:{{ $b[2] / $tot * 100 }}%"></span></div>
                            <div class="sv-legend">@foreach ($s['bands'] as $label => $n)<span><i style="background:var(--{{ ['red', 'gold', 'ok'][$loop->index] }})"></i>{{ $label }}: {{ $n }}</span>@endforeach</div>
                            @php $mx = max(1, collect($s['rows'])->max('count')); @endphp
                            <div class="sv-cols">@foreach ($s['rows'] as $r)<div tabindex="0" data-tip="Score {{ $r['label'] }}: {{ $r['count'] }} {{ Str::plural('response', $r['count']) }}"><span class="v">{{ $r['count'] }}</span><b style="height:{{ round($r['count'] / $mx * 80) }}%;background:var(--{{ (int) $r['label'] <= 6 ? 'red' : ((int) $r['label'] <= 8 ? 'gold' : 'ok') }})"></b>{{ $r['label'] }}</div>@endforeach</div>
                        @elseif ($q->type === 'text')
                            @forelse ($s['texts']->take(4) as $r)
                                <div class="sv-quote">“{{ $r->answer($q) }}”<small>{{ $r->customer?->name ?? 'Anonymous' }} · {{ $r->created_at->format('M j, Y') }} · <a href="{{ route('rodeo.surveys.responses.show', $r) }}">View response</a></small></div>
                            @empty <p class="muted" style="margin:0">No written answers yet.</p> @endforelse
                            @if ($s['texts']->count() > 4)<a href="{{ route('rodeo.surveys.responses', ['survey' => $survey->id, 'comments' => 1]) }}">All {{ $s['texts']->count() }} written answers</a>@endif
                        @else
                            @if ($q->type === 'scale' && $s['avg'])<div style="display:flex;gap:18px;align-items:center"><div><div class="sv-big" style="font-size:1.5rem">{{ $s['avg'] }} <span class="muted" style="font-size:1rem">/ {{ $s['of'] }}</span></div><div class="help">average score</div></div><div><div class="sv-big" style="font-size:1.5rem">{{ $s['top'] }}%</div><div class="help">top two answers</div></div></div>@endif
                            <div class="sv-bars">@foreach ($s['rows'] as $r)<div class="sv-bar" tabindex="0" data-tip="{{ $r['label'] }}: {{ $r['count'] ?? '' }} ({{ $r['pct'] }}%)"><span>{{ $r['label'] }}</span><span class="track"><span class="fill" style="width:{{ $r['pct'] }}%"></span></span><span class="n">{{ $r['count'] }} · {{ $r['pct'] }}%</span></div>@endforeach</div>
                            @if ($q->type === 'multi')<p class="help" style="margin:0">Percent of people who answered; they could pick more than one.</p>@endif
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @empty
        <div class="panel"><div class="panel-body empty">This survey has no questions yet.</div></div>
    @endforelse
    </div>
    </div>
@endsection
