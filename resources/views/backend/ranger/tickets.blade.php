@extends('backend.layouts.app')

@section('content')
    @php
        $views = ['active' => 'Open', 'mine' => 'Mine', 'unassigned' => 'Unassigned'] + ($manager ? ['manager' => 'Manager Requests'] : []) + ['watching' => 'Joined', 'done' => 'Solved & Closed', 'all' => 'All'];
        $url = fn (array $set) => route('ranger.tickets', array_filter(array_merge($f, ['view' => $view], $set), fn ($v) => $v !== null && $v !== ''));
    @endphp
    @include('backend.partials.page-head', ['title' => $customer ? $customer->name.' — Ticket History' : ($owner ? $owner->name.'\'s Tickets' : ($view === 'unassigned' ? 'Unassigned Tickets' : 'Tickets')),
        'sub' => $customer ? 'Every ticket account '.$customer->account.' has opened, newest first.' : ($view === 'unassigned' ? 'Nobody has these yet. Urgent and high first, then the longest waiting. Click one to pick it up.' : 'Urgent and high first, then the longest waiting. Click a ticket to open it.'),
        'actions' => ($owner || $customer) ? '<a class="btn ghost" href="'.route('ranger.tickets', ['view' => $view]).'">Everyone\'s Tickets</a>' : null])

    <div class="chips small" style="margin-bottom:12px">@foreach ($views as $k => $l)<a class="chip-link {{ $view === $k ? 'on' : '' }}" href="{{ route('ranger.tickets', ['view' => $k]) }}">{{ $l }} · {{ $counts[$k] }}</a>@endforeach</div>


    <div class="panel"><div class="panel-head"><h2>{{ number_format($tickets->total()) }} {{ Str::plural('ticket', $tickets->total()) }}</h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th><th>Owner</th><th>Rating</th></tr></thead>
            <tbody>@forelse ($tickets as $t)@include('backend.ranger._row')@empty<tr><td colspan="6" class="empty">No tickets here.</td></tr>@endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tickets])
    </div>
@endsection
