@extends('backend.layouts.app')

@section('content')
    @php $days = (int) ($f['days'] ?? 90); $url = fn (array $set) => route('ranger.team', array_filter(array_merge($f, $set))); @endphp
    @include('backend.partials.page-head', ['title' => 'Ticket Team', 'sub' => 'Everyone who works Ranger tickets, in the office and at 3rd-party companies. Approval = thumbs up out of rated tickets, last '.$days.' days.',
        'actions' => '<a class="btn cyan" href="'.route('ranger.team.create').'">Add a Team Member</a>'])

    <div class="actions" style="margin-bottom:12px;justify-content:space-between">
        <div class="chips small"><a class="chip-link {{ empty($f['team']) ? 'on' : '' }}" href="{{ $url(['team' => null]) }}">Everyone</a>@foreach (\App\Models\RangerAgent::TEAMS as $k => $l)<a class="chip-link {{ ($f['team'] ?? '') === $k ? 'on' : '' }}" href="{{ $url(['team' => $k]) }}">{{ $l }}</a>@endforeach</div>
        <div class="chips small">@foreach ([30, 90, 365] as $d)<a class="chip-link {{ $days === $d ? 'on' : '' }}" href="{{ $url(['days' => $d]) }}">{{ $d === 365 ? '1 year' : $d.' days' }}</a>@endforeach</div>
    </div>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Team</th><th>Level</th><th class="num">Open</th><th class="num">Solved</th><th>Approval</th><th>Added</th>@if ($manager)<th><span class="sr-only">Change</span></th>@endif</tr></thead>
        <tbody>@forelse ($agents as $a)
            @php $s = $stats[$a->user_id] ?? null; $rated = (int) ($s->rated ?? 0); @endphp
            <tr @class(['muted' => ! $a->active])>
                <td class="wrap" style="min-width:180px"><b>{{ $a->user->name }}</b><div class="help">{{ $a->user->title }}{{ $a->user->company ? ' · '.$a->user->company : '' }}</div></td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\RangerAgent::TEAMS[$a->team], 'tone' => $a->team === 'office' ? 'info' : 'warn'])</td>
                <td>{{ \App\Models\RangerAgent::LEVELS[$a->level] }}@unless ($a->active)<div class="help">Off tickets</div>@endunless</td>
                <td class="num"><a href="{{ route('ranger.tickets', ['view' => 'active', 'owner' => $a->user_id]) }}">{{ (int) ($s->open ?? 0) }}</a></td>
                <td class="num">{{ (int) ($s->solved ?? 0) }}</td>
                <td>@include('backend.ranger._approval', ['a' => ['rated' => $rated, 'good' => (int) ($s->good ?? 0), 'pct' => $rated ? round(100 * $s->good / $rated, 1) : null]])</td>
                <td>{{ $a->created_at->format('n/j/y') }}@if ($a->adder)<div class="help">by {{ $a->adder->name }}</div>@endif</td>
                @if ($manager)
                    <td class="actions nowrap">
                        <form method="post" action="{{ route('ranger.team.update', $a) }}" class="inline">@csrf @method('patch')<input type="hidden" name="level" value="{{ $a->level === 'manager' ? 'agent' : 'manager' }}"><button class="btn sm ghost">{{ $a->level === 'manager' ? '→ Agent' : '→ Manager' }}</button></form>
                        <form method="post" action="{{ route('ranger.team.update', $a) }}" class="inline" @if ($a->active) data-confirm="Take {{ $a->user->name }} off tickets?|Their open tickets go back to Unassigned.|Take Off" @endif>@csrf @method('patch')<input type="hidden" name="active" value="{{ $a->active ? 0 : 1 }}"><button class="btn sm ghost">{{ $a->active ? 'Take Off' : 'Put Back' }}</button></form>
                    </td>
                @endif
            </tr>
        @empty <tr><td colspan="8" class="empty">Nobody yet. Add a team member.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
