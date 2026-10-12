@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'ERCOT', 'sub' => 'Search market transactions across every account: '
        .collect(['linked', 'unlinked', 'cancelled'])->map(fn ($s) => number_format($counts[$s] ?? 0).' '.$s)->implode(' · ')])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel"><div class="panel-head"><h2>Search Transactions</h2></div><div class="panel-body">
        <div class="form-row">
            <div class="field grow"><label for="esiid">ESIID</label><input id="esiid" name="esiid" value="{{ $f['esiid'] ?? '' }}" inputmode="numeric"></div>
            <div class="field"><label for="account">Account</label><input id="account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric" style="width:14ch"></div>
            <div class="field"><label for="tracking">Tracking #</label><input id="tracking" name="tracking" value="{{ $f['tracking'] ?? '' }}" style="width:22ch"></div>
            <div class="field"><label for="type">Trans Type</label><select id="type" name="type"><option value="">-- Any --</option>@foreach ($types as $t)<option @selected(($f['type'] ?? '') === $t)>{{ $t }}</option>@endforeach</select></div>
            <div class="field"><label for="purpose">Purpose</label><select id="purpose" name="purpose"><option value="">-- Any --</option>@foreach (['request', 'response'] as $p)<option @selected(($f['purpose'] ?? '') === $p)>{{ $p }}</option>@endforeach</select></div>
            <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">-- Any --</option>@foreach (['linked', 'unlinked', 'cancelled'] as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
            <div class="field"><label for="start">From</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
            <div class="field"><label for="end">To</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
            <button class="btn">Search</button>
            @if (array_filter($f))<a class="btn ghost" href="{{ route('corral.ercot') }}">Clear</a>@endif
        </div>
    </div></form>
    </aside>
    <div class="search-main">


    <div class="panel"><div class="panel-head"><h2>Transactions</h2><span class="muted">{{ number_format($rows->total()) }} found</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Trans Date</th><th>Account</th><th>ESIID</th><th>Purpose</th><th>Scheduled</th><th>Trans Type</th><th>Tracking</th><th>Status</th></tr></thead>
            <tbody>@forelse ($rows as $t)
                <tr><td>{{ $t->trans_date->format('n/j/Y') }}</td>
                    <td>@if ($t->customer)<a href="{{ route('corral.customers.show', $t->customer) }}#ercot">{{ $t->customer->account }}</a><br><span class="help">{{ $t->customer->name }}</span>@endif</td>
                    <td class="mono">{{ $t->esiid ?? '—' }}</td><td>{{ $t->purpose }}</td><td>{{ $t->scheduled_on?->format('n/j/Y') ?? '—' }}</td>
                    <td><span class="mono">{{ $t->trans_type }}</span> {{ $t->label }}</td><td class="mono">{{ $t->tracking ?? '—' }}</td>
                    <td>@include('backend.partials.pill', ['text' => $t->status, 'tone' => ['linked' => 'ok', 'unlinked' => 'warn'][$t->status] ?? 'bad'])</td></tr>
            @empty <tr><td colspan="8" class="empty">No transactions match.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $rows])
    </div>
    </div>
    </div>
@endsection
