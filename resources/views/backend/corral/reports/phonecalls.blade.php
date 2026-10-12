@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Phonecalls Report', 'sub' => 'Search calls by date, account, agent id or phone number.'])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel"><div class="panel-head"><h2>Search Phonecalls Report</h2></div><div class="panel-body">
        <div class="form-row">
            <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] }}"></div>
            <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] }}"></div>
            <div class="field"><label for="account">Account Number</label><input id="account" name="account" value="{{ $f['account'] }}" inputmode="numeric" style="width:14ch"></div>
            <div class="field"><label for="agent">Agent Id</label><input id="agent" name="agent" value="{{ $f['agent'] }}" style="width:10ch"></div>
            <div class="field"><label for="phone">Phone Number</label><input id="phone" name="phone" value="{{ $f['phone'] }}" inputmode="tel" style="width:16ch"></div>
            @include('backend.corral.reports._output')
        </div>
    </div></form>
    </aside>
    <div class="search-main">


    @if ($records !== null)
        <div class="panel">
            @if ($f['output'] === 'summary')
                <div class="panel-head"><h2>Summary</h2><span class="muted">{{ $records->count() }} calls</span></div>
                @include('backend.corral.reports._summary')
            @else
                <div class="panel-head"><h2>Phonecalls</h2><span class="muted">{{ $records->count() }} calls</span></div>
                <div class="table-wrap"><table><thead><tr><th>Started</th><th>Direction</th><th>Phone</th><th>Account</th><th>Agent</th><th class="num">Duration</th><th>Disposition</th></tr></thead><tbody>
                    @forelse ($records as $p)
                        <tr><td>{{ $p->started_at->format('n/j/Y g:i A') }}</td><td>{{ ucfirst($p->direction) }}</td><td class="mono">{{ $p->phone }}</td>
                            <td>@if ($p->customer)<a href="{{ route('corral.customers.contact-log', $p->customer) }}">{{ $p->customer->account }}</a><br><span class="help">{{ $p->customer->name }}</span>@else — @endif</td>
                            <td><span class="mono">{{ $p->agent_id ?? '—' }}</span>@if ($p->user)<br><span class="help">{{ $p->user->name }}</span>@endif</td>
                            <td class="num">{{ gmdate('i:s', $p->duration_sec) }}</td><td>{{ $p->disposition ?? '—' }}</td></tr>
                    @empty <tr><td colspan="7" class="empty">No calls match.</td></tr> @endforelse
                </tbody></table></div>
            @endif
        </div>
    @endif

    @include('backend.corral.reports._recent', ['route' => 'corral.reports.phonecalls'])
    </div>
    </div>
@endsection
