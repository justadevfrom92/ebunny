@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Phone Calls', 'sub' => 'Recent calls with customers: the issue, how long it took, who answered, how it ended and the transcript.',
        'actions' => '<a class="btn cyan" href="'.route('corral.contacts.create', array_filter(['account' => $f['account'] ?? null, 'channel' => 'call'])).'">New Contact</a>'])

    <div class="search-layout">
        <aside class="search-side">
            <form method="get" class="panel"><div class="panel-head"><h2>Search Calls</h2></div><div class="panel-body">
                <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
                <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
                <div class="field"><label for="account">Account Number</label><input id="account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric"></div>
                <div class="field"><label for="phone">Phone Number</label><input id="phone" name="phone" value="{{ $f['phone'] ?? '' }}" inputmode="tel"></div>
                <div class="field"><label for="issue">Issue</label><select id="issue" name="issue"><option value="">Any</option>@foreach ($issues as $i)<option @selected(($f['issue'] ?? '') === $i)>{{ $i }}</option>@endforeach</select></div>
                <div class="field"><label for="user">Answered By</label><select id="user" name="user"><option value="">Anyone</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(($f['user'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div class="field"><label for="agent">Agent Id</label><input id="agent" name="agent" value="{{ $f['agent'] ?? '' }}"></div>
                <div class="field"><label for="direction">Direction</label><select id="direction" name="direction"><option value="">Both</option><option value="inbound" @selected(($f['direction'] ?? '') === 'inbound')>Inbound</option><option value="outbound" @selected(($f['direction'] ?? '') === 'outbound')>Outbound</option></select></div>
                <button class="btn">Search</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('corral.calls') }}">Clear</a>@endif
            </div></form>
        </aside>
        <div class="search-main">
            <div class="panel"><div class="panel-head"><h2>Recent Calls</h2><span class="muted">{{ number_format($calls->total()) }}</span></div>
                <div class="table-wrap"><table>
                    <thead><tr><th>Called</th><th>Account</th><th>Issue</th><th class="num">Duration</th><th>Answered By</th><th>Resolution</th></tr></thead>
                    <tbody>@forelse ($calls as $p)
                        <tr @if ($p->transcript) class="click" data-href="{{ route('corral.calls.transcript', $p) }}" @endif><td class="nowrap">@if ($p->transcript)<a href="{{ route('corral.calls.transcript', $p) }}" title="View transcript">{{ $p->started_at->format('n/j/Y g:i A') }}</a>@else{{ $p->started_at->format('n/j/Y g:i A') }}@endif<br><span class="help">{{ ucfirst($p->direction) }}</span></td>
                            <td>@if ($p->customer)<a href="{{ route('corral.customers.show', $p->customer) }}">{{ $p->customer->account }}</a><br><span class="help">{{ $p->customer->name }}</span><br>@endif<span class="help mono">{{ $p->phone }}</span></td>
                            <td>{{ $p->disposition ?? '—' }}</td>
                            <td class="num">{{ intdiv($p->duration_sec, 60) }}:{{ str_pad((string) ($p->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</td>
                            <td>{{ $p->user?->name ?? '—' }}@if ($p->agent_id)<br><span class="help mono">{{ $p->agent_id }}</span>@endif</td>
                            <td>@include('backend.partials.resolution-pill', ['value' => $p->resolution])</td></tr>
                    @empty <tr><td colspan="6" class="empty">No calls match.</td></tr> @endforelse</tbody>
                </table></div>
                @include('backend.partials.pager', ['p' => $calls])
            </div>
        </div>
    </div>
@endsection
