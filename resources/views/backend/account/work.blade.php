@extends('backend.layouts.app')

@section('crumb', 'My Work')

{{-- Everything waiting on you across the apps: your open tickets, approvals, follow-ups and reports still running --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Work', 'sub' => 'What\'s waiting on you across the apps.'])

    @if ($tickets)
        <div class="panel" id="tickets"><div class="panel-head"><h2>My Open Tickets</h2><span class="muted">{{ $tickets->total() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Ticket #</th><th>Last Activity</th><th>Subject</th><th>Customer</th><th>Status</th><th>Priority</th></tr></thead>
            <tbody>@forelse ($tickets as $t)
                <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td class="nowrap"><a href="{{ route('ranger.tickets.show', $t) }}"><b>{{ $t->number() }}</b></a></td>
                    <td class="nowrap">{{ ($t->last_activity_at ?? $t->created_at)->format('n/j/Y g:i A') }}</td><td class="wrap">{{ $t->subject }}</td>
                    <td class="nowrap">{{ $t->customer?->name ?? $t->name }}</td>
                    <td>@include('backend.partials.pill', ['text' => \App\Models\SupportTicket::STATUSES[$t->status][0] ?? $t->status, 'tone' => \App\Models\SupportTicket::STATUSES[$t->status][1] ?? ''])</td>
                    <td class="nowrap">{{ ucfirst($t->priority) }}</td></tr>
            @empty <tr><td colspan="6" class="empty">No open tickets assigned to you.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tickets])
        </div>
    @endif

    @if ($toApprove)
        <div class="panel" id="approvals"><div class="panel-head"><h2>Waiting for Your Approval</h2><span class="muted">{{ $toApprove->total() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Lead Approval #</th><th>Date</th><th>Account</th><th>Change</th><th>Asked By</th></tr></thead>
            <tbody>@forelse ($toApprove as $a)
                <tr class="click" data-href="{{ route('corral.approvals.show', $a) }}"><td class="nowrap"><a href="{{ route('corral.approvals.show', $a) }}"><b>#{{ $a->id }}</b></a></td>
                    <td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td><td class="nowrap mono">{{ $a->customer?->account }}</td>
                    <td class="wrap"><b>{{ $a->label() }}</b>@if ($a->details())<div class="help">{{ $a->details() }}</div>@endif</td><td class="nowrap">{{ $a->requester?->name ?? '—' }}</td></tr>
            @empty <tr><td colspan="5" class="empty">Nothing waiting for you.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $toApprove])
        </div>
    @endif

    @if ($myRequests)
        <div class="panel" id="requests"><div class="panel-head"><h2>My Lead Approval Requests</h2><span class="muted">{{ $myRequests->total() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Lead Approval #</th><th>Date</th><th>Account</th><th>Change</th><th>Status</th></tr></thead>
            <tbody>@forelse ($myRequests as $a)
                <tr class="click" data-href="{{ route('corral.approvals.show', $a) }}"><td class="nowrap"><a href="{{ route('corral.approvals.show', $a) }}"><b>#{{ $a->id }}</b></a></td>
                    <td class="nowrap">{{ $a->created_at->format('n/j/Y g:i A') }}</td><td class="nowrap mono">{{ $a->customer?->account }}</td><td class="wrap">{{ $a->label() }}</td>
                    <td>@include('backend.partials.pill', ['text' => ['pending' => 'Waiting', 'approved' => 'Approved', 'denied' => 'Denied'][$a->status], 'tone' => ['pending' => 'warn', 'approved' => 'ok', 'denied' => 'bad'][$a->status]])</td></tr>
            @empty <tr><td colspan="5" class="empty">You haven't asked a Lead for anything.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $myRequests])
        </div>
    @endif

    @if ($followUps)
        <div class="panel" id="follow-ups"><div class="panel-head"><h2>My Follow-ups</h2><span class="muted">{{ $followUps->total() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Follow-up #</th><th>Due</th><th>For</th><th>What</th></tr></thead>
            <tbody>@forelse ($followUps as $f)
                @php $url = $f->lead ? route('corral.crm.leads.show', $f->lead) : ($f->partner ? route('corral.crm.partners.show', $f->partner) : route('corral.crm.tasks')); @endphp
                <tr class="click" data-href="{{ $url }}"><td class="nowrap"><a href="{{ $url }}"><b>#{{ $f->id }}</b></a></td>
                    <td class="nowrap">{{ $f->due_on->format('n/j/Y') }}@if ($f->due_on->lt(today())) <span class="pill bad">Overdue</span>@elseif ($f->due_on->isToday()) <span class="pill warn">Today</span>@endif</td>
                    <td class="nowrap">{{ $f->lead?->displayName() ?? $f->partner?->name ?? '—' }}</td><td class="wrap">{{ $f->subject }}</td></tr>
            @empty <tr><td colspan="4" class="empty">No follow-ups due.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $followUps])
        </div>
    @endif

    @if ($running)
        <div class="banner-note">{{ $running }} {{ Str::plural('report', $running) }} still running. They'll show in <a href="{{ route('admin.files') }}">My Files</a> when ready.</div>
    @endif
@endsection
