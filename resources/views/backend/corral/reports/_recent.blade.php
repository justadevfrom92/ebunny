{{-- Your last runs of this report; click one to run it again with the same settings --}}
<div class="panel"><div class="panel-head"><h2>Recent Results</h2></div><div class="table-wrap"><table>
    <thead><tr><th>Date</th><th>Report</th><th>Parameters</th><th class="num">Rows</th><th></th></tr></thead>
    <tbody>@forelse ($recent as $r)
        <tr @class(['saved-report' => $r->file])><td>{{ $r->created_at->format('n/j/Y @ g:i A') }}</td><td>{{ \Illuminate\Support\Str::headline($r->report) }}</td>
            <td class="help">@foreach ($r->params as $k => $v){{ \Illuminate\Support\Str::headline($k) }}: {{ is_array($v) ? implode(', ', $v) : $v }}@if (! $loop->last) · @endif @endforeach</td>
            <td class="num">{{ in_array($r->status, ['queued', 'running']) ? '…' : number_format($r->rows) }}</td>
            <td class="actions">
                @if ($r->file)<a class="btn sm" href="{{ route('corral.reports.download', $r) }}">Download .xlsx</a>
                @elseif ($r->state() !== 'done')@include('backend.partials.pill', ['text' => \App\Models\ReportRun::stateLabel($r->state())[0], 'tone' => \App\Models\ReportRun::stateLabel($r->state())[1]])@endif
                <a class="btn sm ghost" href="{{ route($route, array_merge($r->params, ['run' => 1, 'output' => in_array($r->params['output'] ?? '', ['screen', 'summary']) ? $r->params['output'] : 'screen'])) }}">Run again</a></td></tr>
    @empty <tr><td colspan="5" class="empty">No reports run yet.</td></tr> @endforelse</tbody>
</table></div></div>
