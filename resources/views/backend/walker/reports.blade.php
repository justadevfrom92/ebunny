@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Run a Report', 'sub' => 'Reports run in the background. You can leave the page; finished reports show on Report Status with a download.'])
    @foreach ($groups as $category => $reports)
        <h2 style="margin:8px 0 12px">{{ $category }}</h2>
        <div class="grid-3" style="margin-bottom:20px">
            @foreach ($reports as $r)
                <a class="panel report-card" href="{{ route('walker.reports.show', $r['key']) }}"><div class="panel-body">
                    <h3>{{ $r['title'] }}</h3>
                    <p class="mono help">{{ $r['model'] }}</p>
                    <p class="muted">{{ $r['description'] }}</p>
                    <p class="help">{{ isset($last[$r['key']]) ? number_format($last[$r['key']]->runs).' runs · last '.\Illuminate\Support\Carbon::parse($last[$r['key']]->last_at)->diffForHumans() : 'Never run' }}</p>
                </div></a>
            @endforeach
        </div>
    @endforeach
@endsection
