@extends('backend.layouts.app')

@section('content')
    @php $chip = fn ($g) => '<a href="'.route('bunkhouse.people', array_filter(['group' => ($f['group'] ?? null) === $g ? null : $g, 'q' => $f['q'] ?? null])).'" class="chip-link'.(($f['group'] ?? null) === $g ? ' on' : '').'">'.e($g).'</a>'; @endphp
    @include('backend.partials.page-head', ['title' => 'Directory', 'sub' => 'Everyone who signs in to the admin: employees by department and 3rd-party users by company. Sign-in access is managed in Sheriff.'])

    <div class="panel"><div class="panel-body stack">
        <form method="get" action="{{ route('bunkhouse.people') }}" class="inline-search">@if ($f['group'] ?? null)<input type="hidden" name="group" value="{{ $f['group'] }}">@endif
            <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search name, title or email" aria-label="Search people"><button class="btn">Search</button></form>
        <div class="chips small">@foreach ($groups as $g){!! $chip($g) !!}@endforeach</div>
    </div></div>

    <div class="panel"><div class="panel-head"><h2>{{ $list->count() }} {{ Str::plural('person', $list->count()) }}</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Person</th><th>Department / Company</th><th>Manager / Contact</th><th class="num">Reports</th><th>Location</th><th>Started</th></tr></thead>
        <tbody>@forelse ($list as $u)<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}">
            <td><a href="{{ route('bunkhouse.people.show', $u) }}"><b>{{ $u->name }}</b></a>@if (in_array($u->id, $out, true)) @include('backend.partials.pill', ['text' => 'Out today', 'tone' => 'warn'])@endif<div class="help">{{ $u->title }}</div></td>
            <td>@if ($u->company)<span class="pill info">3rd party</span> {{ $u->company }}@else{{ $u->department ?? '—' }}@endif</td>
            <td>{{ $u->manager?->name ?? '—' }}</td><td class="num">{{ $u->reports_count ?: '—' }}</td><td>{{ $u->location ?? '—' }}</td>
            <td class="nowrap">{{ $u->hired_on?->format('n/j/Y') ?? '—' }}@if ($u->access_ends_on)<div class="help">access ends {{ $u->access_ends_on->format('n/j/Y') }}</div>@endif</td></tr>
        @empty <tr><td colspan="6" class="empty">Nobody matches.</td></tr> @endforelse</tbody>
    </table></div></div>

    @if ($former->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Former</h2><span class="muted">can't sign in</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($former as $u)<tr class="click" data-href="{{ route('bunkhouse.people.show', $u) }}"><td><a href="{{ route('bunkhouse.people.show', $u) }}"><b>{{ $u->name }}</b></a><div class="help">{{ $u->title }}</div></td><td>{{ $u->company ?? $u->department ?? '—' }}</td><td>{{ $u->access_ends_on ? 'Ended '.$u->access_ends_on->format('n/j/Y') : 'Disabled' }}</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
