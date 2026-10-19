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
    @include('backend.corral.reports._recent', ['route' => 'corral.reports.phonecalls'])
    </div>
    </div>
@endsection
