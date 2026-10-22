@extends('backend.layouts.app')

@section('content')
    @php $days = (int) ($f['days'] ?? 90); $url = fn (array $set) => route('ranger.team', array_filter(array_merge($f, $set))); @endphp
    @include('backend.partials.page-head', ['title' => $company ? $company.' — Ticket Team' : 'Ticket Team', 'sub' => ($company ? 'Your company\'s people who work Ranger tickets.' : 'Everyone who works Ranger tickets, in the office and at 3rd-party companies.').' Click someone for their page. Approval = 👍 ÷ all ratings, last '.$days.' days.',
        'actions' => '<a class="btn cyan" href="'.route('ranger.team.create').'">Add a Team Member</a>'])

    <div class="actions" style="margin-bottom:12px;justify-content:space-between">
        @if ($company)<span class="help">Only your company's people.</span>@else<div class="chips small"><a class="chip-link {{ empty($f['team']) ? 'on' : '' }}" href="{{ $url(['team' => null]) }}">Everyone</a>@foreach (\App\Models\RangerAgent::TEAMS as $k => $l)<a class="chip-link {{ ($f['team'] ?? '') === $k ? 'on' : '' }}" href="{{ $url(['team' => $k]) }}">{{ $l }}</a>@endforeach</div>@endif
        <div class="chips small">@foreach ([30, 90, 365] as $d)<a class="chip-link {{ $days === $d ? 'on' : '' }}" href="{{ $url(['days' => $d]) }}">{{ $d === 365 ? '1 year' : $d.' days' }}</a>@endforeach</div>
    </div>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Name</th><th>Team</th><th>Level</th><th class="num">Open</th><th class="num">Solved</th><th>Approval</th><th>Added</th></tr></thead>
        <tbody>@forelse ($agents as $a)
            @php $s = $stats[$a->user_id] ?? null; $rated = (int) ($s->rated ?? 0); @endphp
            <tr @class(['click', 'muted' => ! $a->active]) data-href="{{ route('ranger.team.show', $a) }}">
                <td class="wrap" style="min-width:180px"><a href="{{ route('ranger.team.show', $a) }}"><b>{{ $a->user->name }}</b></a><div class="help">{{ $a->user->title }}{{ $a->user->company ? ' · '.$a->user->company : '' }}</div></td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\RangerAgent::TEAMS[$a->team], 'tone' => $a->team === 'office' ? 'info' : 'warn'])</td>
                <td>{{ \App\Models\RangerAgent::LEVELS[$a->level] }}@unless ($a->active)<div class="help">Off tickets</div>@endunless</td>
                <td class="num">{{ (int) ($s->open ?? 0) }}</td>
                <td class="num">{{ (int) ($s->solved ?? 0) }}</td>
                <td>@include('backend.ranger._approval', ['a' => ['rated' => $rated, 'good' => (int) ($s->good ?? 0), 'pct' => $rated ? round(100 * $s->good / $rated, 1) : null]])</td>
                <td>{{ $a->created_at->format('n/j/y') }}@if ($a->adder)<div class="help">by {{ $a->adder->name }}</div>@endif</td>
            </tr>
        @empty <tr><td colspan="7" class="empty">Nobody yet. Add a team member.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
