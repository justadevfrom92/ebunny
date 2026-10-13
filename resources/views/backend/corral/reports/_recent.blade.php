{{-- Your last runs of this report: save icon downloads a saved run, reload icon runs it again with the same settings --}}
<div class="panel"><div class="panel-head"><h2>Recent Results</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Report</th><th>Parameters</th><th class="num">Rows</th><th></th></tr></thead>
    <tbody>@forelse ($recent as $r)
        <tr @class(['saved-report' => $r->file])><td>{{ $r->created_at->format('n/j/Y @ g:i A') }}</td><td>{{ \Illuminate\Support\Str::headline($r->report) }}</td>
            <td class="help">@foreach ($r->params as $k => $v){{ \Illuminate\Support\Str::headline($k) }}: {{ is_array($v) ? implode(', ', $v) : $v }}@if (! $loop->last) · @endif @endforeach</td>
            <td class="num">{{ in_array($r->status, ['queued', 'running']) ? '…' : number_format($r->rows) }}</td>
            <td class="actions">
                @if ($r->state() !== 'done' && ! $r->file)@include('backend.partials.pill', ['text' => \App\Models\ReportRun::stateLabel($r->state())[0], 'tone' => \App\Models\ReportRun::stateLabel($r->state())[1]])@endif
                @include('backend.partials.report-actions', ['size' => 'row', 'downloadLabel' => 'Download .xlsx', 'download' => $r->file ? route('corral.reports.download', $r) : null,
                    'why' => 'shown on screen; choose Output - Backend to save a file', 'rerunMethod' => 'get',
                    'rerun' => route($route, array_merge($r->params, ['run' => 1, 'output' => in_array($r->params['output'] ?? '', ['screen', 'summary']) ? $r->params['output'] : 'screen']))])</td></tr>
    @empty <tr><td colspan="5" class="empty">No reports run yet.</td></tr> @endforelse</tbody>
</table></div></div>
