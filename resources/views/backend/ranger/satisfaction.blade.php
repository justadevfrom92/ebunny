@extends('backend.layouts.app')

@section('content')
    @php $days = (int) ($f['days'] ?? 90); @endphp
    @include('backend.partials.page-head', ['title' => 'Approval Ratings', 'sub' => 'When a ticket is solved, the customer is asked “Did we help?” in My Account. Approval = thumbs up out of all ratings, last '.($days === 365 ? 'year' : $days.' days').'.'])

    <div class="chips small" style="margin-bottom:12px">@foreach ([30, 90, 365] as $d)<a class="chip-link {{ $days === $d ? 'on' : '' }}" href="{{ route('ranger.satisfaction', ['days' => $d]) }}">{{ $d === 365 ? '1 year' : $d.' days' }}</a>@endforeach</div>

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Everyone</h2></div><div class="panel-body approval-big">@include('backend.ranger._approval', ['a' => $overall])</div></div>
        @foreach ($teams as $team => $a)
            <div class="panel"><div class="panel-head"><h2>{{ $a['label'] }}</h2></div><div class="panel-body approval-big">@include('backend.ranger._approval', ['a' => $a])</div></div>
        @endforeach
    </div>

    <div class="panel"><div class="panel-head"><h2>Week by Week</h2><span class="help">In-office vs 3rd party, last 12 weeks</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Week of</th>@foreach ($teams as $a)<th>{{ $a['label'] }}</th>@endforeach</tr></thead>
            <tbody>@foreach ($weeks->reverse() as $w)<tr><td class="nowrap">{{ $w['week']->format('M j') }}</td>@foreach (array_keys($teams->all()) as $team)
                <td>@if ($w[$team]['pct'] !== null)<span class="hm-bar ap-bar"><span style="width:{{ $w[$team]['pct'] }}%"></span></span> @endif @include('backend.ranger._approval', ['a' => $w[$team]])</td>@endforeach</tr>@endforeach</tbody>
        </table></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>By Team Member</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Team</th><th>Approval</th><th class="num">👍</th><th class="num">👎</th></tr></thead>
        <tbody>@forelse ($people as $p)
            <tr><td><b>{{ $p['agent']->user->name }}</b>@if ($p['agent']->user->company)<div class="help">{{ $p['agent']->user->company }}</div>@endif</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\RangerAgent::TEAMS[$p['agent']->team], 'tone' => $p['agent']->team === 'office' ? 'info' : 'warn'])</td>
                <td>@include('backend.ranger._approval', ['a' => $p])</td><td class="num">{{ $p['good'] }}</td><td class="num">{{ $p['rated'] - $p['good'] }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No ratings in this period.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>What Customers Said</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Rated</th><th>Ticket</th><th>Rating</th><th>Comment</th><th>Handled By</th></tr></thead>
        <tbody>@forelse ($comments as $t)
            <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td class="nowrap">{{ $t->rated_at->format('n/j/Y') }}</td><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td>
                <td>{{ $t->rating === 'good' ? '👍' : '👎' }}</td><td class="wrap">{{ $t->rating_comment }}</td><td>{{ $t->assignee?->name ?? '—' }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No comments in this period.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $comments])</div>
@endsection
