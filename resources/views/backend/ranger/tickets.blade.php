@extends('backend.layouts.app')

@section('content')
    @php
        $views = ['active' => 'Open', 'mine' => 'Mine', 'unassigned' => 'Unassigned', 'watching' => 'Joined', 'done' => 'Solved & Closed', 'all' => 'All'];
        $url = fn (array $set) => route('ranger.tickets', array_filter(array_merge($f, ['view' => $view], $set), fn ($v) => $v !== null && $v !== ''));
    @endphp
    @include('backend.partials.page-head', ['title' => $owner ? $owner->name.'\'s Tickets' : 'Tickets', 'sub' => 'Urgent and high first, then the longest waiting. Click a ticket to open it.',
        'actions' => $owner ? '<a class="btn ghost" href="'.route('ranger.tickets', ['view' => $view]).'">Everyone\'s Tickets</a>' : null])

    <div class="chips small" style="margin-bottom:12px">@foreach ($views as $k => $l)<a class="chip-link {{ $view === $k ? 'on' : '' }}" href="{{ route('ranger.tickets', ['view' => $k]) }}">{{ $l }} · {{ $counts[$k] }}</a>@endforeach</div>

    <form method="get" class="panel"><input type="hidden" name="view" value="{{ $view }}">@if ($owner)<input type="hidden" name="owner" value="{{ $owner->id }}">@endif
        <div class="panel-body tl-filters" style="grid-template-columns:2fr 1fr">
            <div class="field"><label for="rq">Search</label><input id="rq" name="q" type="search" value="{{ $f['q'] ?? '' }}" placeholder="Ticket #, subject, name, email or account"></div>
            <div class="field"><label>Team</label><div class="chips small"><label><input type="radio" name="team" value="" @checked(empty($f['team']))><span>Both</span></label>@foreach (\App\Models\RangerAgent::TEAMS as $k => $l)<label><input type="radio" name="team" value="{{ $k }}" @checked(($f['team'] ?? '') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
            <div class="field"><label>Status</label><div class="chips small"><label><input type="radio" name="status" value="" @checked(empty($f['status']))><span>Any</span></label>@foreach (\App\Models\SupportTicket::STATUSES as $k => [$l])<label><input type="radio" name="status" value="{{ $k }}" @checked(($f['status'] ?? '') === $k)><span>{{ $l }}</span></label>@endforeach</div></div>
            <div class="actions"><button class="btn cyan">Search</button>@if (array_filter(\Illuminate\Support\Arr::except($f, 'view')))<a class="btn ghost" href="{{ route('ranger.tickets', ['view' => $view]) }}">Clear</a>@endif</div>
        </div>
    </form>

    <div class="panel"><div class="panel-head"><h2>{{ number_format($tickets->total()) }} {{ Str::plural('ticket', $tickets->total()) }}</h2></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Ticket</th><th>Subject</th><th>Customer</th><th>Status</th><th>Owner</th><th>Rating</th></tr></thead>
            <tbody>@forelse ($tickets as $t)@include('backend.ranger._row')@empty<tr><td colspan="6" class="empty">No tickets here.</td></tr>@endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $tickets])
    </div>
@endsection
