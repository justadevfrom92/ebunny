@extends('backend.layouts.app')

@section('crumb', $agent->user->name)

{{-- One ticket team member: profile, numbers, approval, open tickets and what customers said. --}}
@section('content')
    @php
        $u = $agent->user;
        $fmtMin = fn ($m) => $m === null ? '—' : ($m < 60 ? round($m).' min' : ($m < 1440 ? round($m / 60, 1).' hrs' : round($m / 1440, 1).' days'));
    @endphp
    @include('backend.partials.page-head', ['title' => $u->name, 'sub' => view('backend.partials.pill', ['text' => \App\Models\RangerAgent::TEAMS[$agent->team], 'tone' => $agent->team === 'office' ? 'info' : 'warn'])->render()
        .' · '.e(\App\Models\RangerAgent::LEVELS[$agent->level]).($agent->active ? '' : ' · <b>Off tickets</b>'),
        'actions' => '<a class="btn ghost" href="'.route('ranger.team').'">Ticket Team</a>'])

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('ranger.tickets', ['view' => 'active', 'owner' => $u->id]) }}"><span>Open Tickets</span><strong>{{ $open->count() }}</strong><small>new, open or waiting</small></a>
        <div class="stat"><span>Solved</span><strong>{{ $solved }}</strong><small>last 90 days</small></div>
        <div class="stat"><span>Avg First Reply</span><strong>{{ $fmtMin($firstReply) }}</strong><small>last 90 days</small></div>
        <div class="stat"><span>Rated</span><strong>{{ $approval[90]['rated'] }}</strong><small>tickets, last 90 days</small></div>
    </div>

    <div class="grid-3">
        @foreach ($approval as $d => $a)@include('backend.ranger._approval-card', ['title' => 'Approval — '.($d === 365 ? '1 year' : $d.' days'), 'a' => $a])@endforeach
    </div>

    <div class="grid-2" style="grid-template-columns:minmax(0,2fr) minmax(0,1fr);align-items:start">
        <div class="panel"><div class="panel-head"><h2>Approval by Week</h2><span class="help">% of each week's ratings that were 👍 (weeks with no ratings left out)</span></div><div class="panel-body">
            <x-bar-chart :bars="$weeks->filter(fn ($w) => $w['rated'])->map(fn ($w) => ['label' => $w['week']->format('n/j'), 'value' => $w['pct'] ?? 0, 'tip' => 'Week of '.$w['week']->format('M j').($w['rated'] ? ' ('.$w['good'].' 👍, '.($w['rated'] - $w['good']).' 👎)' : ' (no ratings)')])" unit="%" />
        </div></div>
        <div class="panel"><div class="panel-head"><h2>Profile</h2></div><div class="panel-body"><dl class="kv">
            <dt>Email</dt><dd>{{ $u->email }}</dd>
            <dt>Title</dt><dd>{{ $u->title ?? '—' }}</dd>
            <dt>{{ $agent->team === 'office' ? 'Department' : 'Company' }}</dt><dd>{{ ($agent->team === 'office' ? $u->department : $u->company) ?? '—' }}</dd>
            <dt>Role</dt><dd>{{ $u->role?->name ?? '—' }}</dd>
            <dt>On the team since</dt><dd>{{ $agent->created_at->format('n/j/Y') }}@if ($agent->adder)<div class="help">added by {{ $agent->adder->name }}</div>@endif</dd>
        </dl></div>
            @if ($manager)
                <form method="post" action="{{ route('ranger.team.update', $agent) }}" class="panel-body" style="border-top:1px solid var(--line);display:flex;flex-direction:column;gap:12px">@csrf @method('patch')
                    @unless ($company)<div class="field"><label>Team</label><div class="chips small">@foreach (\App\Models\RangerAgent::TEAMS as $k => $l)<label><input type="radio" name="team" value="{{ $k }}" @checked($agent->team === $k)><span>{{ $l }}</span></label>@endforeach</div></div>@endunless
                    <div class="field"><label>Level</label><div class="chips small">@foreach (\App\Models\RangerAgent::LEVELS as $k => $l)@if ($k !== 'company_admin' || (! $company && $agent->team === 'third_party'))<label><input type="radio" name="level" value="{{ $k }}" @checked($agent->level === $k)><span>{{ $l }}</span></label>@endif @endforeach</div></div>
                    <div class="field"><label>Working tickets</label><div class="chips small"><label><input type="radio" name="active" value="1" @checked($agent->active)><span>Yes</span></label><label><input type="radio" name="active" value="0" @checked(! $agent->active)><span>Off tickets</span></label></div>
                        <span class="help">Taking someone off tickets sends their open tickets back to Unassigned.</span></div>
                    <div><button class="btn">Save</button></div>
                </form>
            @endif
        </div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Open Tickets</h2></div><div class="table-wrap"><table data-page="10">
        <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th><th>Opened</th></tr></thead>
        <tbody>@forelse ($open as $t)
            <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td><td class="wrap">{{ $t->subject }}</td><td>{{ $t->name }}</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\SupportTicket::STATUSES[$t->status][0], 'tone' => \App\Models\SupportTicket::STATUSES[$t->status][1]])</td><td class="nowrap">{{ $t->created_at->format('n/j/Y') }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No open tickets.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Latest Ratings</h2></div><div class="table-wrap"><table data-page="10">
        <thead><tr><th>Rated</th><th>Ticket</th><th>Rating</th><th>What They Said</th></tr></thead>
        <tbody>@forelse ($ratings as $t)
            <tr class="click" data-href="{{ route('ranger.tickets.show', $t) }}"><td class="nowrap">{{ $t->rated_at->format('n/j/Y') }}</td><td><a class="mono" href="{{ route('ranger.tickets.show', $t) }}">{{ $t->number() }}</a></td>
                <td class="nowrap">{{ $t->rating === 'good' ? '👍 Helped' : '👎 Didn\'t help' }}</td><td class="wrap">{{ $t->rating_comment ?? '—' }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No ratings yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
