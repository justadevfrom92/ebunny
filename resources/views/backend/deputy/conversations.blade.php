@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Conversations', 'sub' => 'Texts and My Account chats. The bot answers first; when it hands off, or you take over, you reply here or call the customer.'])

    @foreach (['Needs a Person' => [$waiting, 'Nobody is waiting on a person right now.'], 'Live Now' => [$answering, 'No other conversations are open.']] as $heading => [$list, $none])
        <div class="panel" style="margin-bottom:20px" id="{{ $loop->first ? 'needs-person' : 'live-now' }}">
            <div class="panel-head"><h2>{{ $heading }} <span class="count-pill">{{ $list->count() }}</span></h2>@if ($loop->first)<span class="muted">oldest first</span>@endif</div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>Last Message</th><th>Channel</th><th>Account</th><th>Issue</th><th>Who's Answering</th><th></th></tr></thead>
                <tbody>@forelse ($list as $c)
                    <tr class="click" data-href="{{ route('deputy.conversations.show', $c) }}">
                        <td class="nowrap">{{ $c->last_message_at?->diffForHumans() }}</td>
                        <td>{{ config('deputy.channels.'.$c->channel) }}</td>
                        <td>@if ($c->customer)@include('backend.partials.account-link', ['c' => $c->customer])<br><span class="help">{{ $c->customer->name }}</span>@endif</td>
                        <td>{{ $c->topic }}</td>
                        <td>@include('backend.partials.pill', ['text' => config('deputy.statuses.'.$c->status), 'tone' => ['bot' => 'info', 'waiting' => 'warn', 'agent' => 'ok'][$c->status] ?? ''])@if ($c->status === 'agent' && $c->handedTo)<br><span class="help">{{ $c->handedTo->name }}</span>@endif</td>
                        <td class="actions">@if ($c->status !== 'agent' || $c->handed_to_id !== auth()->id())<form method="post" action="{{ route('deputy.conversations.take', $c) }}">@csrf<button class="btn sm cyan">Take Over</button></form>@else<a class="btn sm" href="{{ route('deputy.conversations.show', $c) }}">Open</a>@endif</td></tr>
                @empty <tr><td colspan="6" class="empty">{{ $none }}</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    @endforeach

    <h2 style="margin:4px 0 12px">All Conversations <span class="count-pill">{{ number_format($conversations->total()) }}</span></h2>

    <div class="search-layout">
        <aside class="search-side">
            <form method="get" class="panel"><div class="panel-head"><h2>Search Conversations</h2></div><div class="panel-body">
                <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
                <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
                <div class="field"><label for="account">Account Number</label><input id="account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric"></div>
                <div class="field"><label for="agent">Agent</label><select id="agent" name="agent"><option value="">Any</option>@foreach ($agents as $a)<option value="{{ $a->id }}" @selected(($f['agent'] ?? '') == $a->id)>{{ $a->name }}</option>@endforeach</select></div>
                <div class="field"><label for="model">Model</label><select id="model" name="model"><option value="">Any</option>@foreach ($models as $m)<option value="{{ $m->id }}" @selected(($f['model'] ?? '') == $m->id)>{{ $m->name }}</option>@endforeach</select></div>
                <div class="field"><label for="channel">Channel</label><select id="channel" name="channel"><option value="">Any</option>@foreach (config('deputy.channels') as $v => $l)<option value="{{ $v }}" @selected(($f['channel'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
                <div class="field"><label for="topic">Issue</label><select id="topic" name="topic"><option value="">Any</option>@foreach ($topics as $t)<option @selected(($f['topic'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
                <div class="field"><label for="outcome">Outcome</label><select id="outcome" name="outcome"><option value="">Any</option>@foreach (config('deputy.outcomes') as $v => $l)<option value="{{ $v }}" @selected(($f['outcome'] ?? '') === $v)>{{ $l }}</option>@endforeach</select></div>
                <button class="btn">Search</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('deputy.conversations') }}">Clear</a>@endif
            </div></form>
        </aside>
        <div class="search-main">
            <div class="panel"><div class="table-wrap"><table class="table">
                <thead><tr><th>Started</th><th>Account</th><th>Issue</th><th class="num">Length</th><th>Agent &amp; Model</th><th>Outcome</th><th>Resolution</th></tr></thead>
                <tbody>@forelse ($conversations as $c)
                    <tr class="click" data-href="{{ route('deputy.conversations.show', $c) }}">
                        <td class="nowrap"><a href="{{ route('deputy.conversations.show', $c) }}">{{ $c->started_at->format('n/j/Y g:i A') }}</a><br><span class="help">{{ config('deputy.channels.'.$c->channel) }}</span></td>
                        <td>@if ($c->customer)@include('backend.partials.account-link', ['c' => $c->customer])<br><span class="help">{{ $c->customer->name }}</span>@elseif ($c->user)<span class="help">Test by {{ $c->user->name }}</span>@else<span class="muted">—</span>@endif</td>
                        <td>{{ $c->topic }}</td>
                        <td class="num">{{ intdiv($c->duration_sec, 60) }}:{{ str_pad((string) ($c->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</td>
                        <td>{{ $c->agent?->name }}<br><span class="help">{{ $c->model?->name ?? 'No model answered' }}</span></td>
                        <td>@if ($c->isLive())@include('backend.partials.pill', ['text' => config('deputy.statuses.'.$c->status), 'tone' => 'warn'])@else @include('backend.deputy._outcome', ['c' => $c])@endif @if ($c->handedTo)<br><span class="help">to {{ $c->handedTo->name }}</span>@endif</td>
                        <td>@include('backend.partials.resolution-pill', ['value' => $c->resolution])</td></tr>
                @empty <tr><td colspan="7" class="empty">No conversations match.</td></tr> @endforelse</tbody>
            </table></div>
            @include('backend.partials.pager', ['p' => $conversations])</div>
        </div>
    </div>
    <script>
    // Keep Needs a Person and Live Now current without reloading the page
    setInterval(function () {
        if (document.hidden) return;
        fetch(location.href, { credentials: 'same-origin' }).then(function (r) { return r.ok ? r.text() : Promise.reject(); }).then(function (html) {
            var doc = new DOMParser().parseFromString(html, 'text/html');
            ['needs-person', 'live-now'].forEach(function (id) { var n = doc.getElementById(id), o = document.getElementById(id); if (n && o) o.replaceWith(n); });
        }).catch(function () {});
    }, 10000);
    </script>
@endsection
