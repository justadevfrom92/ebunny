@extends('backend.layouts.app')

@section('crumb', $u->name)

{{-- One employee (any admin user — administrator, CSR or ticket team): every ticket they owned, replied to or wrote a note on. --}}
@section('content')
    @php $ap = $stats['approval']; @endphp
    @include('backend.partials.page-head', ['title' => $u->name, 'sub' => e(collect([$u->title, $u->role?->name, $agent ? \App\Models\RangerAgent::TEAMS[$agent->team].' ticket team · '.\App\Models\RangerAgent::LEVELS[$agent->level] : 'Not on the ticket team'])->filter()->implode(' · ')),
        'actions' => '<a class="btn ghost btn-back" href="'.route('ranger.people').'">Back to Employees</a>'
            .($agent ? '<a class="btn ghost" href="'.route('ranger.team.show', $agent).'">Ticket Team Profile</a>' : '')
            .'<a class="btn ghost" href="'.route('ranger.tickets', ['view' => 'all', 'handled_by' => $u->id]).'">Search Their Tickets</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Tickets Handled</span><strong>{{ number_format($stats['handled']) }}</strong><small>owned, replied or noted</small></div>
        <a class="stat" href="{{ route('ranger.tickets', ['view' => 'active', 'owner' => $u->id]) }}"><span>Open Now</span><strong>{{ number_format($stats['open']) }}</strong><small>of {{ number_format($stats['owned']) }} owned</small></a>
        <div class="stat"><span>Replies Sent</span><strong>{{ number_format($stats['replies']) }}</strong><small>to customers</small></div>
        <div class="stat"><span>Approval</span><strong>{{ $ap['pct'] === null ? '—' : round($ap['pct']).'%' }}</strong><small>{{ $stats['solved'] }} solved · {{ $ap['rated'] }} rated, last 90 days</small></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Tickets Handled</h2><span class="muted">most recent activity first</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Opened</th><th>Subject</th><th>Customer</th><th>Status</th><th>Their Part</th><th>Last Activity</th></tr></thead>
            <tbody>@forelse ($tickets as $t)
                @php [$sLabel, $sTone] = \App\Models\SupportTicket::STATUSES[$t->status]; $link = route('ranger.tickets.show', $t);
                    $part = collect([$t->assigned_to === $u->id ? 'Owner' : null, ($replies[$t->id] ?? 0) ? $replies[$t->id].' '.Str::plural('reply', $replies[$t->id]) : null, ($notes[$t->id] ?? 0) ? $notes[$t->id].' '.Str::plural('note', $notes[$t->id]) : null])->filter()->implode(' · '); @endphp
                <tr class="click" data-href="{{ $link }}"><td class="nowrap"><a class="mono" href="{{ $link }}"><b>{{ $t->number() }}</b></a></td>
                    <td class="nowrap">{{ $t->created_at->format('n/j/Y') }}</td>
                    <td class="wrap"><b>{{ $t->subject }}</b><div class="help">{{ \App\Models\SupportTicket::TOPICS[$t->topic] ?? $t->topic }}</div></td>
                    <td>{{ $t->name }}@if ($t->customer)<div class="help">@include('backend.partials.account-link', ['c' => $t->customer])</div>@endif</td>
                    <td>@include('backend.partials.pill', ['text' => $sLabel, 'tone' => $sTone])</td>
                    <td>{{ $part ?: '—' }}@if ($t->assignee && $t->assigned_to !== $u->id)<div class="help">owner: {{ $t->assignee->name }}</div>@endif</td>
                    <td class="nowrap">{{ ($t->last_activity_at ?? $t->created_at)->format('n/j/Y g:i A') }}</td></tr>
            @empty <tr><td colspan="7" class="empty">No tickets yet.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tickets])
    </div>
@endsection
