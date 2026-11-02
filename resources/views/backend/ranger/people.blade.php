@extends('backend.layouts.app')

{{-- Everyone who has handled a ticket: the ticket team, administrators, CSRs. Each opens a page with the tickets they handled. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Employees', 'sub' => 'Everyone who has owned, replied to or written a note on a ticket. Open someone to see every ticket they handled.'])

    <div class="panel"><div class="panel-head"><h2>{{ $people->count() }} {{ Str::plural('employee', $people->count()) }}</h2></div>
        <div class="table-wrap"><table data-page="10">
            <thead><tr><th>ID</th><th>Name</th><th>Role</th><th>Ticket Team</th><th class="num">Tickets Handled</th><th class="num">Open Now</th><th>Last Ticket Activity</th></tr></thead>
            <tbody>@forelse ($people as $p)
                @php $u = route('ranger.people.show', $p); $a = $agents[$p->id] ?? null; $s = $stats[$p->id]; @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $p->id }}</b></a></td>
                    <td><b>{{ $p->name }}</b><div class="help">{{ $p->title ?? $p->email }}</div></td>
                    <td>{{ $p->role?->name ?? '—' }}</td>
                    <td>@if ($a){{ \App\Models\RangerAgent::TEAMS[$a->team] }} · {{ \App\Models\RangerAgent::LEVELS[$a->level] }}@else<span class="muted">Not on the team</span>@endif</td>
                    <td class="num">{{ number_format($s['handled']) }}</td><td class="num">{{ number_format($s['open']) }}</td>
                    <td class="nowrap">{{ $s['last'] ? \Illuminate\Support\Carbon::parse($s['last'])->format('n/j/Y g:i A') : '—' }}</td></tr>
            @empty <tr><td colspan="7" class="empty">Nobody has handled a ticket yet.</td></tr> @endforelse</tbody>
        </table></div>
    </div>
@endsection
