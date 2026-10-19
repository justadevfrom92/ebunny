@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Orders Report', 'sub' => 'Pull orders by date range and status. Runs in the background and saves a file you can download.'])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel"><div class="panel-head"><h2>Search Orders Report</h2></div><div class="panel-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="form-row">
            <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] }}"></div>
            <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] }}"></div>
            @include('backend.corral.reports._output')
        </div>
        <div class="actions">
            @foreach ($statuses as $s)
                <label class="check" style="margin-right:10px"><input type="checkbox" name="statuses[]" value="{{ $s }}" @checked(in_array($s, $f['statuses']))> {{ $s }}</label>
            @endforeach
        </div>
    </div></form>
    </aside>
    <div class="search-main">
    @include('backend.corral.reports._recent', ['route' => 'corral.reports.orders'])
    </div>
    </div>
@endsection
