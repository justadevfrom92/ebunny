@extends('backend.layouts.app')

@section('crumb', 'My Activity')

{{-- Everything recorded under your name across the apps, plus your Saved Reports and report runs --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Activity', 'sub' => 'Everything recorded under your name across the admin apps.'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>All Activity</span><strong>{{ number_format($total) }}</strong><small>recorded under your name</small></div>
        <div class="stat"><span>Last 7 Days</span><strong>{{ number_format($week) }}</strong><small>since {{ now()->subDays(7)->format('n/j') }}</small></div>
        <div class="stat"><span>Saved Reports</span><strong>{{ $saved->count() }}</strong><small>listed below</small></div>
        <a class="stat" href="{{ route('admin.files') }}"><span>Report Runs</span><strong>{{ number_format($runs) }}</strong><small>in My Files</small></a>
    </div>

    <div class="panel"><div class="panel-head"><h2>Activity</h2><span class="muted">{{ $items->total() }}</span></div>
        <form method="get" class="panel-body">
            <div class="field"><label for="act-q">Search</label><input id="act-q" type="search" name="q" value="{{ $q }}" placeholder="What you did, e.g. note, refund, ticket"></div>
            <div class="field"><label for="act-group">Type</label><select id="act-group" name="group" onchange="this.form.submit()">
                <option value="">-- All Types --</option>
                @foreach ($groups as $g)<option value="{{ $g }}" @selected($group === $g)>{{ $g }}</option>@endforeach
            </select></div>
            <button class="btn" style="margin-top:8px">Search</button>@if ($q !== '' || $group !== '') <a class="btn ghost" href="{{ route('admin.account.activity') }}">Clear</a>@endif
        </form>
        <div class="table-wrap"><table>
            <thead><tr><th>ID#</th><th>Date</th><th>Type</th><th>What You Did</th><th>Account</th></tr></thead>
            <tbody>@forelse ($items as $h)
                <tr class="click" data-href="{{ route('admin.account.activity.show', $h) }}"><td class="nowrap"><a href="{{ route('admin.account.activity.show', $h) }}"><b>#{{ $h->id }}</b></a></td>
                    <td class="nowrap">{{ $h->created_at->format('n/j/Y g:i A') }}</td><td class="nowrap">{{ $h->group }}</td><td class="wrap">{{ $h->summary }}</td>
                    <td class="nowrap">{{ $h->customer?->account ?? '—' }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No activity recorded{{ $q !== '' || $group !== '' ? ' for that search' : '' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @if ($items->hasPages())<div class="panel-body">{{ $items->links() }}</div>@endif
    </div>

    <div class="panel"><div class="panel-head"><h2>My Saved Reports</h2><span class="muted">{{ $saved->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>ID#</th><th>Date</th><th>Name</th><th>Shared</th></tr></thead>
        <tbody>@forelse ($saved as $s)
            <tr><td class="nowrap"><b>#{{ $s->id }}</b></td><td class="nowrap">{{ $s->created_at->format('n/j/Y') }}</td><td class="wrap"><b>{{ $s->name }}</b><div class="help">{{ $s->settingsLine() }}</div></td><td>{{ $s->shared ? 'Yes' : 'No' }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No saved reports. Save one from a report in Walker.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
