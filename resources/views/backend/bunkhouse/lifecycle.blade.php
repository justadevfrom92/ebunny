@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Onboarding & Offboarding', 'sub' => 'Checklists for people joining and leaving, including 3rd-party users whose contracts end. Open someone to tick off their steps.'])

    @if ($endingSoon->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>3rd-party Access Ending Without an Offboarding</h2><span class="muted">next 60 days</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($endingSoon as $u)<tr><td>@include('backend.bunkhouse._who', ['p' => $u])</td><td class="nowrap">{{ $u->access_ends_on->format('n/j/Y') }}</td>
                <td class="actions"><form method="post" action="{{ route('bunkhouse.lifecycle.start') }}" class="inline">@csrf<input type="hidden" name="user_id" value="{{ $u->id }}"><input type="hidden" name="kind" value="offboarding"><input type="hidden" name="date" value="{{ $u->access_ends_on->toDateString() }}"><button class="btn sm">Start Offboarding</button></form></td></tr>@endforeach</tbody>
        </table></div></div>
    @endif

    @foreach (['onboarding' => $onboarding, 'offboarding' => $offboarding] as $kind => $rows)
        <div class="panel"><div class="panel-head"><h2>{{ ucfirst($kind) }}</h2><span class="muted">{{ $rows->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Person</th><th>{{ $kind === 'onboarding' ? 'Starts' : 'Last Day' }}</th><th>Progress</th><th>Next Step</th></tr></thead>
            <tbody>@forelse ($rows as $r)@php $pct = round(100 * $r['done'] / max(1, $r['items']->count())); $day = $r['items']->firstWhere('team', $kind === 'onboarding' ? 'Manager' : 'Finance')?->due_on; @endphp
                <tr class="click" data-href="{{ route('bunkhouse.people.show', $r['user']) }}"><td>@include('backend.bunkhouse._who', ['p' => $r['user']])</td><td class="nowrap">{{ $day?->format('n/j/Y') ?? '—' }}</td>
                    <td style="min-width:140px"><div class="sv-bar" style="grid-template-columns:1fr 64px"><span class="track"><span class="fill" style="width:{{ $pct }}%"></span></span><span class="n">{{ $r['done'] }}/{{ $r['items']->count() }}</span></div></td>
                    <td class="wrap">@if ($r['next'])<span @class(['bad-text' => $r['next']->due_on?->lt(today())])>{{ $r['next']->task }}</span><div class="help">{{ $r['next']->team }} · due {{ $r['next']->due_on?->format('n/j') }}</div>@else<span class="muted">All done</span>@endif</td></tr>
            @empty <tr><td colspan="4" class="empty">No {{ $kind }} in progress.</td></tr> @endforelse</tbody>
        </table></div></div>
    @endforeach

    <form method="post" action="{{ route('bunkhouse.lifecycle.start') }}" class="panel">@csrf
        <div class="panel-head"><h2>Start a Checklist</h2></div>
        <div class="panel-body stack">
            <div class="field"><label>Kind</label><div class="chips small"><label><input type="radio" name="kind" value="onboarding" checked><span>Onboarding (someone joining)</span></label><label><input type="radio" name="kind" value="offboarding"><span>Offboarding (someone leaving)</span></label></div></div>
            <div class="field"><label for="date">First or last day</label><input id="date" name="date" type="date" required style="max-width:20ch"></div>
            <div class="field"><label>Person</label>@include('backend.bunkhouse._pick', ['name' => 'user_id', 'people' => $people])<div class="help">Add new people in Sheriff first so they can sign in.</div></div>
            <div><button class="btn cyan">Start</button></div>
        </div>
    </form>
@endsection
