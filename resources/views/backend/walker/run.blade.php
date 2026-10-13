@extends('backend.layouts.app')

@section('crumb', 'Report #'.$run->id)

@section('head')
    @if (in_array($run->state(), ['queued', 'running']))<meta http-equiv="refresh" content="5">@endif
@endsection

{{-- One report run: what it read (its model), the parameters, the result, and Download (save icon) and Rerun (reload icon) at the top right. --}}
@section('content')
    @include('backend.partials.page-head', [
        'title' => ($run->title ?? \Illuminate\Support\Str::headline($run->report)).' · #'.$run->id,
        'sub' => 'Model <span class="mono">'.e($run->model ?? '—').'</span> · '.view('backend.walker._state', ['run' => $run])->render(),
        'actions' => view('backend.partials.report-actions', ['size' => 'page', 'downloadLabel' => 'Download .xlsx',
            'download' => $run->file ? route('walker.runs.download', $run) : null, 'rerun' => $def ? route('walker.runs.rerun', $run) : null,
            'why' => in_array($run->state(), ['queued', 'running'], true) ? 'still running' : 'this run did not save a file'])->render(),
    ])

    @if ($run->state() === 'failed')<div class="alert bad"><b>Error</b> {{ $run->error ?? 'The report stopped with an error.' }}</div>@endif
    @if ($run->state() === 'stalled')<div class="alert bad"><b>Did not finish</b> This run was still {{ $run->status }} after {{ config('walker.timeout_minutes') }} minutes. Check that the queue worker is running (<span class="mono">php artisan queue:work</span>), then Rerun.</div>@endif
    @if (in_array($run->state(), ['queued', 'running']))<div class="alert info"><b>{{ $run->state() === 'queued' ? 'Waiting for the queue worker' : 'Running' }}</b> This page refreshes every few seconds.</div>@endif

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>Run</h2></div><div class="panel-body"><dl class="kv">
            <dt>Report</dt><dd>@if ($def)<a href="{{ route('walker.reports.show', $run->report) }}">{{ $def['title'] }}</a>@else{{ $run->report }}@endif</dd>
            <dt>Requested By</dt><dd>{{ $run->user?->name ?? 'System' }}</dd>
            <dt>From</dt><dd>{{ ucfirst($run->app) }}</dd>
            <dt>Queued</dt><dd>{{ $run->created_at->format('n/j/Y g:i:s A') }}</dd>
            <dt>Started</dt><dd>{{ $run->started_at?->format('n/j/Y g:i:s A') ?? '—' }}</dd>
            <dt>Finished</dt><dd>{{ $run->finished_at?->format('n/j/Y g:i:s A') ?? '—' }}</dd>
            <dt>Duration</dt><dd>{{ $run->duration_ms !== null ? number_format($run->duration_ms / 1000, 2).' seconds' : '—' }}</dd>
            <dt>Rows</dt><dd>{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</dd>
            @if ($run->rerun_of)<dt>Rerun Of</dt><dd><a href="{{ route('walker.runs.show', $run->rerun_of) }}">#{{ $run->rerun_of }}</a></dd>@endif
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Parameters</h2></div><div class="panel-body"><dl class="kv">
            @forelse ($run->params ?? [] as $k => $v)<dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{{ is_array($v) ? implode(', ', $v) : $v }}</dd>
            @empty <dt>None</dt><dd>—</dd> @endforelse
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Model Reference</h2></div><div class="panel-body">
            <p><span class="mono"><b>{{ $run->model ?? '—' }}</b></span></p>
            @if ($def)<p class="muted" style="margin:8px 0 12px">{{ $def['description'] }}</p>@endif
            @if ($columns)<h3>Columns</h3><p class="help">{{ implode(' · ', $columns) }}</p>@endif
        </div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Results</h2><span class="muted">@if ($run->preview){{ min(50, $run->rows) }} of {{ number_format($run->rows) }} rows shown @if ($run->file)· download for all @endif @endif</span></div>
        @if ($run->preview)
            <div class="table-wrap"><table>
                <thead><tr>@foreach ($run->preview['header'] as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>@forelse ($run->preview['rows'] as $row)<tr>@foreach ($row as $v)<td>{{ is_float($v) ? number_format($v, 2) : $v }}</td>@endforeach</tr>
                @empty <tr><td colspan="{{ count($run->preview['header']) }}" class="empty">No rows.</td></tr> @endforelse</tbody>
            </table></div>
        @elseif ($run->status === 'done')
            <div class="panel-body muted">This run was viewed on screen or downloaded from Corral; its rows weren't saved. Rerun it to keep a copy here.</div>
        @else
            <div class="panel-body muted">Results show here when the run completes.</div>
        @endif
    </div>

    @if ($history->count())
        <div class="panel"><div class="panel-head"><h2>Other Runs of This Report</h2></div><div class="table-wrap"><table>
            <tbody>@foreach ($history as $h)<tr class="click" data-href="{{ route('walker.runs.show', $h) }}"><td><a href="{{ route('walker.runs.show', $h) }}">#{{ $h->id }}</a></td><td>{{ $h->created_at->format('n/j/Y g:i A') }}</td><td>{{ $h->user?->name ?? 'System' }}</td><td>@include('backend.walker._state', ['run' => $h])</td><td class="actions">@include('backend.walker._row-actions', ['run' => $h])</td></tr>@endforeach</tbody>
        </table></div></div>
    @endif
@endsection
