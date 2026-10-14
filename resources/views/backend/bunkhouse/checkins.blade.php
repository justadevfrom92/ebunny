@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Check-ins', 'sub' => 'One-on-ones between managers and their team: how people are feeling, what came up and what happens next.',
        'actions' => '<a class="btn cyan" href="'.route('bunkhouse.checkins.create').'">Log a Check-in</a>'])

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Due for a Check-in</h2><span class="muted">none in 30 days · {{ $due->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Person</th><th>Manager</th><th>Last</th><th></th></tr></thead>
            <tbody>@forelse ($due as $u)<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}"><td>@include('backend.bunkhouse._who', ['p' => $u])</td><td>{{ $u->manager?->name }}</td><td class="nowrap">{{ isset($last[$u->id]) ? \Illuminate\Support\Carbon::parse($last[$u->id])->format('n/j/Y') : 'Never' }}</td>
                <td class="actions"><a class="btn sm" href="{{ route('bunkhouse.checkins.create', ['user' => $u->id]) }}">Log</a></td></tr>
            @empty <tr><td colspan="4" class="empty">Everyone's had one in the last 30 days.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>How People Are Feeling</h2><span class="muted">last 60 days</span></div><div class="panel-body">
            <x-bar-chart :bars="collect(\App\Models\ErmCheckin::MOODS)->map(fn ($l, $n) => ['label' => $l, 'value' => $moods[$n] ?? 0])->values()" unit="check-ins" /></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Recent Check-ins</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Person</th><th>With</th><th>Feeling</th><th>Topics</th></tr></thead>
        <tbody>@forelse ($recent as $c)<tr class="click" data-href="{{ route('bunkhouse.checkins.show', $c) }}"><td class="nowrap"><a href="{{ route('bunkhouse.checkins.show', $c) }}">{{ $c->held_on->format('n/j/Y') }}</a></td><td>{{ $c->user->name }}</td><td>{{ $c->manager?->name }}</td>
            <td>@include('backend.partials.pill', ['text' => $c->moodLabel(), 'tone' => $c->mood >= 4 ? 'ok' : ($c->mood <= 2 ? 'bad' : '')])</td><td class="wrap">{{ Str::limit($c->topics, 90) }}</td></tr>
        @empty <tr><td colspan="5" class="empty">No check-ins yet.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
