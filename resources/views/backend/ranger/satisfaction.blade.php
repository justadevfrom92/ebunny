@extends('backend.layouts.app')

@section('content')
    @php $days = (int) ($f['days'] ?? 90); $period = $days === 365 ? 'last year' : 'last '.$days.' days'; @endphp
    @include('backend.partials.page-head', ['title' => 'Approval Ratings', 'sub' => 'How customers rated the help they got, '.$period.'.'])

    <div class="chips small" style="margin-bottom:12px">@foreach ([30, 90, 365] as $d)<a class="chip-link {{ $days === $d ? 'on' : '' }}" href="{{ route('ranger.satisfaction', ['days' => $d]) }}">{{ $d === 365 ? '1 year' : $d.' days' }}</a>@endforeach</div>

    <div class="panel"><div class="panel-head"><h2>How Approval Works</h2></div><div class="panel-body ap-explain">
        <div><b>1. Ticket is solved</b>The customer sees “Did we help?” on the ticket in My Account.</div>
        <div><b>2. They rate it</b>👍 Helped or 👎 Didn't help, with an optional comment.</div>
        <div><b>3. Approval %</b>👍 ÷ (👍 + 👎). Tickets nobody rated don't count either way.</div>
        <div><b>Colors</b><span class="approval ok">90%+ good</span> · <span class="approval warn">75–89% watch</span> · <span class="approval bad">under 75% needs work</span></div>
    </div></div>

    <div class="grid-3">
        @include('backend.ranger._approval-card', ['title' => 'Everyone', 'a' => $overall])
        @foreach ($teams as $team => $a)@include('backend.ranger._approval-card', ['title' => $a['label'], 'a' => $a])@endforeach
    </div>

    <div class="grid-2" style="align-items:start">
        @foreach ($teams as $team => $a)
            <div class="panel"><div class="panel-head"><h2>{{ $a['label'] }} — Week by Week</h2><span class="help">approval %, last 12 weeks</span></div><div class="panel-body">
                <x-bar-chart :bars="$weeks->filter(fn ($w) => $w[$team]['rated'])->map(fn ($w) => ['label' => $w['week']->format('n/j'), 'value' => $w[$team]['pct'] ?? 0, 'tip' => 'Week of '.$w['week']->format('M j').($w[$team]['rated'] ? ' ('.$w[$team]['good'].' 👍, '.($w[$team]['rated'] - $w[$team]['good']).' 👎)' : ' (no ratings)')])" unit="%" height="150" />
            </div></div>
        @endforeach
    </div>

    <div class="panel"><div class="panel-head"><h2>By Team Member</h2><span class="help">highest approval first</span></div><div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Team</th><th class="num">👍 Helped</th><th class="num">👎 Didn't</th><th class="num">Rated</th><th>Approval</th></tr></thead>
        <tbody>@forelse ($people as $p)
            <tr class="click" data-href="{{ route('ranger.team.show', $p['agent']) }}"><td><a href="{{ route('ranger.team.show', $p['agent']) }}"><b>{{ $p['agent']->user->name }}</b></a>@if ($p['agent']->user->company)<div class="help">{{ $p['agent']->user->company }}</div>@endif</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\RangerAgent::TEAMS[$p['agent']->team], 'tone' => $p['agent']->team === 'office' ? 'info' : 'warn'])</td>
                <td class="num">{{ $p['good'] }}</td><td class="num">{{ $p['rated'] - $p['good'] }}</td><td class="num">{{ $p['rated'] }}</td>
                <td class="nowrap"><b class="approval {{ $p['pct'] >= 90 ? 'ok' : ($p['pct'] >= 75 ? 'warn' : 'bad') }}">{{ rtrim(rtrim(number_format($p['pct'], 1), '0'), '.') }}%</b> <span class="ap-split"><span style="width:{{ $p['pct'] }}%"></span></span></td></tr>
        @empty <tr><td colspan="6" class="empty">No ratings in this period.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>What Customers Said</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Rated</th><th>Ticket</th><th>Rating</th><th>Comment</th><th>Handled By</th></tr></thead>
        <tbody>@forelse ($comments as $t)
            <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td class="nowrap">{{ $t->rated_at->format('n/j/Y') }}</td><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td>
                <td class="nowrap">{{ $t->rating === 'good' ? '👍 Helped' : '👎 Didn\'t help' }}</td><td class="wrap">{{ $t->rating_comment }}</td><td>{{ $t->assignee?->name ?? '—' }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No comments in this period.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $comments])</div>
@endsection
