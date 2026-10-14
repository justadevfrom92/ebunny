@extends('backend.layouts.app')

{{-- Bunkhouse home: who's here, who's out, what's due, and recent kudos --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'People', 'sub' => 'Employees and 3rd-party company users: who\'s out, reviews and training that are due, open HR cases and recent kudos.',
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.kudos').'">Give Kudos</a><a class="btn cyan" href="'.route('bunkhouse.people').'">Directory</a>'])

    <div class="grid-4 stat-row">
        <a class="stat" href="{{ route('bunkhouse.people') }}"><span>People</span><strong>{{ $stats['employees'] + $stats['external'] }}</strong><small>{{ $stats['employees'] }} employees · {{ $stats['external'] }} from {{ $stats['companies'] }} outside {{ Str::plural('company', $stats['companies']) }}</small></a>
        <a class="stat" href="{{ route('bunkhouse.time-off') }}"><span>Out Today</span><strong>{{ $stats['outToday'] }}</strong><small>{{ $stats['pendingTimeOff'] }} {{ Str::plural('request', $stats['pendingTimeOff']) }} waiting</small></a>
        <a class="stat" href="{{ route('bunkhouse.reviews') }}"><span>Reviews Due (30 days)</span><strong>{{ $stats['reviewsDue'] }}</strong><small>{{ $stats['reviewsOverdue'] }} overdue</small></a>
        <a class="stat" href="{{ route('bunkhouse.cases') }}"><span>Open HR Cases</span><strong>{{ $stats['openCases'] }}</strong><small>{{ $stats['trainingOverdue'] }} training {{ Str::plural('course', $stats['trainingOverdue']) }} overdue</small></a>
    </div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>People by Department</h2></div><div class="panel-body"><div class="sv-bars">@php $max = max(1, $byDept->max('value')); @endphp @foreach ($byDept as $d)<div class="sv-bar" tabindex="0" data-tip="{{ $d['label'] }}: {{ $d['value'] }} {{ Str::plural('person', $d['value']) }}"><span>{{ $d['label'] }}</span><span class="track"><span class="fill" style="width:{{ round(100 * $d['value'] / $max) }}%"></span></span><span class="n">{{ $d['value'] }}</span></div>@endforeach</div></div></div>
        <div class="panel"><div class="panel-head"><h2>Out This Week</h2><a class="btn sm ghost" href="{{ route('bunkhouse.time-off') }}">Time Off</a></div><div class="table-wrap"><table>
            <thead><tr><th>Person</th><th>Dates</th><th>Type</th></tr></thead>
            <tbody>@forelse ($out as $t)<tr class="click" data-href="{{ route('bunkhouse.people.show', $t->user) }}"><td>@include('backend.bunkhouse._who', ['p' => $t->user, 'short' => true])</td><td class="nowrap">{{ $t->dates() }}</td><td>{{ $t->type }}</td></tr>
            @empty <tr><td colspan="3" class="empty">Everyone's in this week.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Reviews Coming Up</h2><a class="btn sm ghost" href="{{ route('bunkhouse.reviews') }}">All Reviews</a></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Review</th><th>Reviewer</th><th>Due</th><th>Status</th></tr></thead>
        <tbody>@forelse ($reviews as $r)<tr class="click" data-href="{{ route('bunkhouse.reviews.show', $r) }}"><td><a href="{{ route('bunkhouse.reviews.show', $r) }}"><b>{{ $r->user->name }}</b></a></td><td>{{ $r->kind }} · {{ $r->period }}</td><td>{{ $r->reviewer?->name ?? '—' }}</td><td class="nowrap">{{ $r->due_on->format('n/j/Y') }}</td><td>@include('backend.partials.pill', ['text' => $r->overdue() ? 'Overdue' : $r->statusLabel(), 'tone' => $r->statusTone()])</td></tr>
        @empty <tr><td colspan="5" class="empty">No reviews due.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Recent Kudos</h2><a class="btn sm ghost" href="{{ route('bunkhouse.kudos') }}">All Kudos</a></div><div class="table-wrap"><table>
            <tbody>@forelse ($kudos as $k)<tr><td><b>{{ $k->to?->name }}</b><div class="help">from {{ $k->from?->name ?? 'someone' }} · {{ $k->value }} · {{ $k->created_at->diffForHumans() }}</div><div class="wrap">“{{ $k->message }}”</div></td></tr>
            @empty <tr><td class="empty">No kudos yet.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="stack">
            <div class="panel"><div class="panel-head"><h2>Work Anniversaries</h2><span class="muted">next 45 days</span></div><div class="table-wrap"><table>
                <tbody>@forelse ($anniversaries as $u)<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}"><td>@include('backend.bunkhouse._who', ['p' => $u])</td><td class="nowrap">{{ $u->hired_on->copy()->year(now()->year)->format('D n/j') }}</td><td class="num">{{ now()->year - $u->hired_on->year }} {{ Str::plural('year', now()->year - $u->hired_on->year) }}</td></tr>
                @empty <tr><td class="empty">None coming up.</td></tr> @endforelse</tbody>
            </table></div></div>
            <div class="panel"><div class="panel-head"><h2>3rd-party Access Ending</h2><span class="muted">next 90 days</span></div><div class="table-wrap"><table>
                <tbody>@forelse ($endings as $u)<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}"><td>@include('backend.bunkhouse._who', ['p' => $u])</td><td class="nowrap">{{ $u->access_ends_on->format('n/j/Y') }}</td></tr>
                @empty <tr><td class="empty">No contracts ending soon.</td></tr> @endforelse</tbody>
            </table></div></div>
        </div>
    </div>
@endsection
