@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Ranger', 'sub' => 'Support tickets customers open from My Account.',
        'actions' => '<a class="btn cyan" href="'.route('ranger.tickets', ['view' => 'unassigned']).'">Pick Up a Ticket</a>'])

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('ranger.tickets', ['status' => 'new']) }}"><span>New</span><strong>{{ $counts['new'] }}</strong><small>not answered yet</small></a>
        <a class="stat {{ $counts['unassigned'] ? 'alert' : '' }}" href="{{ route('ranger.tickets', ['view' => 'unassigned']) }}"><span>Unassigned</span><strong>{{ $counts['unassigned'] }}</strong><small>nobody has it</small></a>
        <a class="stat" href="{{ route('ranger.tickets') }}"><span>Open</span><strong>{{ $counts['open'] }}</strong><small>new, open or waiting</small></a>
        <a class="stat" href="{{ route('ranger.tickets', ['view' => 'done']) }}"><span>Solved Today</span><strong>{{ $counts['solvedToday'] }}</strong></a>
    </div>

    @if ($manager)
        <div class="grid-3">
            @include('backend.ranger._approval-card', ['title' => 'Approval — Everyone', 'a' => $overall, 'note' => 'last 30 days'])
            @foreach ($teams as $team => $a)@include('backend.ranger._approval-card', ['title' => 'Approval — '.$a['label'], 'a' => $a, 'note' => 'last 30 days'])@endforeach
        </div>

        <div class="panel"><div class="panel-head"><h2>Manager Requests</h2><span class="muted">agents asking for a manager's help</span></div><div class="table-wrap"><table data-page="10">
            <thead><tr><th>Ticket</th><th>Subject</th><th>Asked By</th><th>Why</th><th>Asked</th></tr></thead>
            <tbody>@forelse ($managerRequests as $t)
                <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td><td class="wrap">{{ $t->subject }}</td>
                    <td>{{ $t->managerRequester?->name ?? '—' }}</td><td class="wrap">{{ $t->manager_request_note ?? '—' }}</td><td class="nowrap">{{ $t->manager_requested_at->diffForHumans() }}</td></tr>
            @empty <tr><td colspan="5" class="empty">Nobody needs a manager right now.</td></tr> @endforelse</tbody>
        </table></div></div>
    @endif

    {{-- Every open ticket, most urgent first (urgent and high, then the longest waiting) --}}
    <div class="panel"><div class="panel-head"><h2>Open Tickets</h2><a class="btn sm ghost" href="{{ route('ranger.tickets') }}">All Tickets</a></div><div class="table-wrap"><table data-page="10">
        <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th><th>Owner</th><th>Rating</th></tr></thead>
        <tbody>@forelse ($open as $t)@include('backend.ranger._row')@empty<tr><td colspan="6" class="empty">No open tickets. Nice.</td></tr>@endforelse</tbody>
    </table></div></div>
@endsection
