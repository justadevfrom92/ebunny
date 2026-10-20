@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Ranger', 'sub' => 'Support tickets customers open from My Account. Approval is the share of rated tickets customers gave a thumbs up (last 30 days).',
        'actions' => '<a class="btn cyan" href="'.route('ranger.tickets', ['view' => 'unassigned']).'">Pick Up a Ticket</a>'])

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('ranger.tickets', ['status' => 'new']) }}"><span>New</span><strong>{{ $counts['new'] }}</strong><small>not answered yet</small></a>
        <a class="stat {{ $counts['unassigned'] ? 'alert' : '' }}" href="{{ route('ranger.tickets', ['view' => 'unassigned']) }}"><span>Unassigned</span><strong>{{ $counts['unassigned'] }}</strong><small>nobody has it</small></a>
        <a class="stat" href="{{ route('ranger.tickets') }}"><span>Open</span><strong>{{ $counts['open'] }}</strong><small>new, open or waiting</small></a>
        <a class="stat" href="{{ route('ranger.tickets', ['view' => 'done']) }}"><span>Solved Today</span><strong>{{ $counts['solvedToday'] }}</strong></a>
    </div>

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Approval — Everyone</h2></div><div class="panel-body approval-big">@include('backend.ranger._approval', ['a' => $overall])</div></div>
        @foreach ($teams as $team => $a)
            <a class="panel panel-link" href="{{ route('ranger.team', ['team' => $team]) }}"><div class="panel-head"><h2>Approval — {{ $a['label'] }}</h2></div><div class="panel-body approval-big">@include('backend.ranger._approval', ['a' => $a])</div></a>
        @endforeach
    </div>

    <div class="grid-2" style="align-items:start">
        <div class="panel"><div class="panel-head"><h2>My Tickets</h2><a class="btn sm ghost" href="{{ route('ranger.tickets', ['view' => 'mine']) }}">All Mine</a></div><div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th></tr></thead>
            <tbody>@forelse ($mine as $t)@include('backend.ranger._row', ['compact' => true])@empty<tr><td colspan="4" class="empty">Nothing assigned to you. Pick one up from Unassigned.</td></tr>@endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Waiting Longest — Unassigned</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th></tr></thead>
            <tbody>@forelse ($waitingLongest as $t)@include('backend.ranger._row', ['compact' => true])@empty<tr><td colspan="4" class="empty">Every ticket has someone. Nice.</td></tr>@endforelse</tbody>
        </table></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Latest Ratings</h2><a class="btn sm ghost" href="{{ route('ranger.satisfaction') }}">Approval Ratings</a></div><div class="table-wrap"><table>
        <thead><tr><th>Rated</th><th>Ticket</th><th>Rating</th><th>What They Said</th><th>Handled By</th></tr></thead>
        <tbody>@forelse ($recentRatings as $t)
            <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td class="nowrap">{{ $t->rated_at->format('n/j g:i A') }}</td><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td>
                <td>{{ $t->rating === 'good' ? '👍 Good' : '👎 Bad' }}</td><td class="wrap">{{ $t->rating_comment ?? '—' }}</td><td>{{ $t->assignee?->name ?? '—' }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No ratings yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
