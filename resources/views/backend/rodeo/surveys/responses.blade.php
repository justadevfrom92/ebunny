@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Survey Responses', 'sub' => 'Every answer customers have sent, newest first. Open one to see all its answers by category.'])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel" style="margin-bottom:16px"><div class="panel-head"><h2>Search Responses</h2></div><div class="panel-body form-row">
        <div class="field grow"><label for="f-survey">Survey</label><select id="f-survey" name="survey"><option value="">All surveys</option>@foreach ($surveys as $s)<option value="{{ $s->id }}" @selected(($f['survey'] ?? null) == $s->id)>{{ $s->title }}</option>@endforeach</select></div>
        <div class="field"><label for="f-band">Recommend Score</label><select id="f-band" name="band"><option value="">Any</option>@foreach (['promoter' => 'Promoters (9–10)', 'passive' => 'Passives (7–8)', 'detractor' => 'Detractors (0–6)'] as $v => $l)<option value="{{ $v }}" @selected(($f['band'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="f-source">Came From</label><select id="f-source" name="source"><option value="">Anywhere</option>@foreach (\App\Models\SurveyResponse::SOURCES as $v => $l)<option value="{{ $v }}" @selected(($f['source'] ?? null) === $v)>{{ $l }}</option>@endforeach</select></div>
        <div class="field"><label for="f-account">Account</label><input id="f-account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric" style="width:14ch"></div>
        <div class="field"><label for="f-from">From</label><input id="f-from" type="date" name="from" value="{{ $f['from'] ?? '' }}"></div>
        <div class="field"><label for="f-to">To</label><input id="f-to" type="date" name="to" value="{{ $f['to'] ?? '' }}"></div>
        <label class="check" style="margin-bottom:8px"><input type="checkbox" name="comments" value="1" @checked($f['comments'] ?? false)> With a written answer</label>
        <button class="btn">Filter</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('rodeo.surveys.responses') }}">Clear</a>@endif
    </div></form>
    </aside>
    <div class="search-main">


    <div class="panel">
        <div class="panel-head"><h2>{{ number_format($total) }} {{ Str::plural('Response', $total) }}</h2>@if ($f['survey'] ?? null)<a class="btn sm ghost" href="{{ route('rodeo.surveys.results', $f['survey']) }}">Results for this survey</a>@endif</div>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>ID</th><th>Date</th><th>Survey</th><th>Customer</th><th class="num">Score</th><th>Written Answer</th><th>Came From</th></tr></thead>
            <tbody>@forelse ($rows as $r)
                @php $n = $r->nps(); $c = $comment($r); @endphp
                <tr class="click" data-href="{{ route('rodeo.surveys.responses.show', $r) }}">
                    <td class="nowrap"><a href="{{ route('rodeo.surveys.responses.show', $r) }}"><b>#{{ $r->id }}</b></a></td>
                    <td class="nowrap">{{ $r->created_at->format('n/j/Y g:i A') }}</td>
                    <td>{{ $r->survey->title }}</td>
                    <td>@if ($r->customer){{ $r->customer->name }}<div class="help">@include('backend.partials.account-link', ['c' => $r->customer])</div>@else<span class="muted">Anonymous</span>@endif</td>
                    <td class="num">@if ($n !== null)<span class="sv-band {{ $n >= 9 ? 'pro' : ($n >= 7 ? 'pas' : 'det') }}">{{ $n }}</span>@else — @endif</td>
                    <td class="wrap">{{ $c ? Str::limit($c, 90) : '' }}</td>
                    <td>{{ \App\Models\SurveyResponse::SOURCES[$r->source] ?? '—' }}</td>
                    </tr>
            @empty <tr><td colspan="7" class="empty">No responses match.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $rows])
    </div>
    </div>
    </div>
@endsection
