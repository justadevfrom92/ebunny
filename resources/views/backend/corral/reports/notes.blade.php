@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Notes Report', 'sub' => 'Account notes by agent, date, text and priority. "System" notes are the ones written automatically.'])

    <div class="search-layout">
    <aside class="search-side">
    <form method="get" class="panel"><div class="panel-head"><h2>Search Notes Report</h2></div><div class="panel-body" style="display:flex;flex-direction:column;gap:16px">
        <div class="form-row">
            <div class="field"><label for="user">Username</label><select id="user" name="user"><option value="">-- Anyone --</option>
                @foreach ($users as $u)<option value="{{ $u->id }}" @selected($f['user'] == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
            <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] }}"></div>
            <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] }}"></div>
            <div class="field grow"><label for="contains">Note Contains</label><input id="contains" name="contains" value="{{ $f['contains'] }}" maxlength="100"></div>
        </div>
        <div class="form-row">
            <div class="field"><label>Priority</label><div class="actions">
                @foreach ($priorities as $p)<label class="check" style="margin-right:10px"><input type="checkbox" name="priorities[]" value="{{ $p }}" @checked(in_array($p, $f['priorities']))> {{ $p }}</label>@endforeach
            </div></div>
            @include('backend.corral.reports._output')
        </div>
    </div></form>
    </aside>
    <div class="search-main">
    @include('backend.corral.reports._recent', ['route' => 'corral.reports.notes'])
    </div>
    </div>
@endsection
