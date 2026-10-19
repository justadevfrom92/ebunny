@extends('backend.layouts.app')

@section('content')
    @php $current = $month->isSameMonth(now()); @endphp
    @include('backend.partials.page-head', ['title' => 'Monthly Drawing', 'sub' => 'Customers enter by redeeming the free "Monthly Drawing" offer. The winner is saved to Bounty → Drawing Winners.',
        'actions' => $winner && $current ? null : '<a class="btn cyan" href="'.route('bounty.drawing.draw').'">Draw a Winner</a>'])

    <div class="actions" style="margin-bottom:14px">@foreach ($months as $m)<a class="btn sm {{ $m->isSameMonth($month) ? '' : 'ghost' }}" href="{{ route('bounty.drawing', $m->isSameMonth(now()) ? [] : ['month' => $m->format('Y-m')]) }}">{{ $m->format('F Y') }}</a>@endforeach</div>

    <div class="grid-3 stat-row">
        <div class="stat"><span>Entries in {{ $month->format('F') }}</span><strong>{{ number_format($entries->count()) }}</strong><small>from {{ $people }} {{ Str::plural('customer', $people) }}</small></div>
        <div class="stat"><span>Winner</span><strong style="font-size:1.1rem">@if ($winner)Account @include('backend.partials.account-link', ['c' => $winners[$winner->cells[2]] ?? null])@else{{ $current ? 'Not drawn yet' : 'None drawn' }}@endif</strong>@if ($winner)<small>{{ $winner->cells[1] }} · drawn {{ $winner->cells[3] ?? '' }}</small>@endif</div>
        <div class="stat"><span>Entries Close</span><strong style="font-size:1.1rem">{{ $month->copy()->endOfMonth()->format('F j, Y') }}</strong></div>
    </div>

    <div class="stack">
        <div class="panel"><div class="panel-head"><h2>Entries for {{ $month->format('F Y') }}</h2><span class="muted">{{ $entries->count() }}</span></div><div class="table-wrap"><table>
            <thead><tr><th>Entered</th><th>Account</th><th>Name</th></tr></thead>
            <tbody>@forelse ($entries as $e)<tr><td class="nowrap">{{ $e->created_at->format('n/j/Y g:i A') }}</td><td>@include('backend.partials.account-link', ['c' => $e->customer])</td><td>{{ $e->customer?->name }}</td></tr>
            @empty <tr><td colspan="3" class="empty">No entries in {{ $month->format('F Y') }}.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Past Winners</h2></div><div class="table-wrap"><table>
            <thead><tr><th>Month</th><th>Prize</th><th>Winner Account</th><th>Drawn On</th></tr></thead>
            <tbody>@forelse ($past as $r)<tr><td>{{ $r->cells[0] ?? '' }}</td><td>{{ $r->cells[1] ?? '' }}</td><td>@include('backend.partials.account-link', ['c' => $winners[$r->cells[2] ?? ''] ?? null])</td><td>{{ ($r->cells[3] ?? '') ?: '—' }}</td></tr>
            @empty <tr><td colspan="4" class="empty">None.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
