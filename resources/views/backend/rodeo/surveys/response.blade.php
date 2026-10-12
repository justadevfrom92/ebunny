@extends('backend.layouts.app')

@section('crumb', 'Response #'.$r->id)

@section('content')
    @php $n = $r->nps(); @endphp
    @include('backend.partials.page-head', ['title' => $r->survey->title.' · Response #'.$r->id,
        'sub' => $r->created_at->format('l, F j, Y g:i A').' · came from '.(\App\Models\SurveyResponse::SOURCES[$r->source] ?? 'unknown'),
        'actions' => ($prev ? '<a class="btn ghost" href="'.route('rodeo.surveys.responses.show', $prev).'">‹ Older</a>' : '').($next ? '<a class="btn ghost" href="'.route('rodeo.surveys.responses.show', $next).'">Newer ›</a>' : '')
            .'<a class="btn ghost" href="'.route('rodeo.surveys.results', $r->survey).'">Survey Results</a>'])

    <div class="stat-row" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;margin-bottom:20px">
        <div class="stat"><span>Customer</span><strong style="font-size:1.1rem">{{ $r->customer?->name ?? 'Anonymous' }}</strong><small>@if ($r->customer)<span class="mono">{{ $r->customer->account }}</span> · {{ $r->customer->status }}@else answered without a customer link @endif</small></div>
        <div class="stat"><span>Plan</span><strong style="font-size:1.1rem">{{ $r->customer?->plan?->name ?? '—' }}</strong><small>{{ $r->customer?->type }}</small></div>
        <div class="stat"><span>Recommend Score</span><strong>@if ($n !== null)<span class="sv-band {{ $n >= 9 ? 'pro' : ($n >= 7 ? 'pas' : 'det') }}">{{ $n }}</span>@else — @endif</strong><small>{{ $n === null ? 'not answered' : ($n >= 9 ? 'Promoter' : ($n >= 7 ? 'Passive' : 'Detractor')) }}</small></div>
        <div class="stat"><span>Answered</span><strong>{{ $r->survey->questions->filter(fn ($q) => $r->answer($q) !== null)->count() }} / {{ $r->survey->questions->count() }}</strong><small>questions</small></div>
    </div>

    @foreach ($groups as $qs)
        @php $cat = $qs->first()->category; $dot = $cat?->color ?? '#96999d'; @endphp
        <div class="sv-cat-head" style="--dot:{{ $dot }}"><span class="sv-dot" style="width:14px;height:14px"></span><h2>{{ $cat?->name ?? 'Uncategorized' }}</h2></div>
        <div class="panel sv-q" style="--dot:{{ $dot }}"><div class="table-wrap"><table class="table">
            <tbody>@foreach ($qs as $q)
                @php $a = $r->answer($q); @endphp
                <tr><td style="width:46%"><b>{{ $q->question }}</b><div class="sv-type">{{ \App\Models\SurveyQuestion::TYPES[$q->type] ?? $q->type }}{{ $q->required ? ' · required' : '' }}</div></td>
                    <td>@if ($a === null)<span class="muted">Skipped</span>
                        @elseif (is_array($a))<div class="sv-opts">@foreach ($a as $x)<span class="sv-opt">{{ $x }}</span>@endforeach</div>
                        @elseif ($q->type === 'rating')<span class="sv-band {{ $a >= 9 ? 'pro' : ($a >= 7 ? 'pas' : 'det') }}" style="font-size:1.2rem">{{ $a }}</span> <span class="muted">/ 10</span>
                        @elseif ($q->type === 'scale'){{ $a }}@if (($pos = array_search($a, $q->choices(), true)) !== false) <span class="muted">· {{ $pos + 1 }} of {{ count($q->choices()) }}</span>@endif
                        @elseif ($q->type === 'text')<div class="sv-quote">{{ $a }}</div>
                        @else{{ $a }}@endif</td></tr>
            @endforeach</tbody>
        </table></div></div>
    @endforeach
@endsection
