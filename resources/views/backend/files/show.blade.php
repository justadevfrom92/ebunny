@extends('backend.layouts.app')

@section('title', $run->label())
@section('crumb', 'My Files › '.$run->label())

{{-- One file: the report, its settings, the file (download / run again) and its first rows, kept with the run so nothing runs again --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $run->label(), 'sub' => 'Run #'.$run->id.' · '.view('backend.partials.pill', ['text' => \App\Models\ReportRun::stateLabel($run->state())[0], 'tone' => \App\Models\ReportRun::stateLabel($run->state())[1]])->render(),
        'actions' => '<a class="btn ghost" href="'.route('admin.files').'">All My Files</a>'.view('backend.partials.report-actions', ['size' => 'page', 'downloadLabel' => 'Download '.$run->formatLabel(),
            'download' => $run->file ? route('admin.files.download', $run) : null, 'why' => in_array($run->status, ['queued', 'running'], true) ? 'still running' : 'no file saved',
            'rerun' => route('admin.files.rerun', $run)])->render()])
    @if (in_array($run->state(), ['queued', 'running']))<div class="alert info"><b>{{ $run->state() === 'queued' ? 'Waiting to start' : 'Running' }}</b> You'll get a notice at the top right when the file is ready; this page refreshes itself.</div><meta http-equiv="refresh" content="6">@endif
    @if ($run->state() === 'failed')<div class="alert bad"><b>Error</b> {{ $run->error ?? 'The report stopped with an error.' }}</div>@endif

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>File</h2></div><div class="panel-body"><dl class="kv">
            <dt>Name</dt><dd class="mono">{{ $run->file ? basename($run->file) : '—' }}</dd>
            <dt>Format</dt><dd>{{ $run->formatLabel() }}{{ $run->summary ? ' · summary' : '' }}</dd>
            <dt>Rows</dt><dd>{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</dd>
            <dt>Size</dt><dd>{{ $run->sizeLabel() ?? '—' }}</dd>
            <dt>Requested</dt><dd>{{ $run->created_at->format('n/j/Y g:i A') }} from {{ ucfirst($run->app) }}</dd>
            <dt>Finished</dt><dd>{{ $run->finished_at?->format('n/j/Y g:i A') ?? '—' }}@if ($run->duration_ms !== null) <span class="muted">({{ number_format($run->duration_ms / 1000, 1) }}s)</span>@endif</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Settings</h2></div><div class="panel-body"><dl class="kv">
            @forelse ($run->params ?? [] as $k => $v)<dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{{ is_array($v) ? implode(', ', $v) : $v }}</dd>
            @empty <dt>Settings</dt><dd>Defaults</dd> @endforelse
            @if ($def)<dt>Reads</dt><dd class="mono">{{ $def['model'] }}</dd>@endif
        </dl></div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>First Rows</h2><span class="muted">@if ($run->preview){{ min(count($run->preview['rows']), $run->rows) }} of {{ number_format($run->rows) }} · download for all @endif</span></div>
        @if ($run->preview)
            <div class="table-wrap"><table><thead><tr>@foreach ($run->preview['header'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>@forelse ($run->preview['rows'] as $row)<tr>@foreach ($row as $v)<td>{{ is_float($v) ? number_format($v, 2) : $v }}</td>@endforeach</tr>@empty<tr><td colspan="{{ count($run->preview['header']) }}" class="empty">The report found no rows for these settings.</td></tr>@endforelse</tbody></table></div>
        @else
            <div class="panel-body muted">{{ in_array($run->state(), ['queued', 'running']) ? 'The first rows show here when it finishes.' : 'This run kept no preview. Download the file, or run it again.' }}</div>
        @endif
    </div>
@endsection
