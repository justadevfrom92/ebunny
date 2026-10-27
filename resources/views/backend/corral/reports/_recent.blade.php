{{-- Your runs of this report: name, when, settings, format and status; download the file or preview its first rows --}}
<div class="panel" id="report-runs">
    <div class="panel-head"><h2>{{ $def['title'] ?? 'Report' }} — Your Runs</h2><a class="btn sm ghost" href="{{ route('admin.files') }}">All My Files</a></div>
    <div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>Requested</th><th>Settings</th><th>Format</th><th class="num">Rows</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse ($recent as $r)
            <tr class="click" data-href="{{ route('admin.files.show', $r) }}">
                <td style="white-space:nowrap"><a href="{{ route('admin.files.show', $r) }}"><b>#{{ $r->id }}</b></a><div class="help">{{ $r->summary ? 'Summary' : 'Every row' }}</div></td>
                <td class="nowrap">{{ $r->created_at->format('n/j/Y g:i A') }}</td>
                <td class="help wrap" style="min-width:160px">@foreach ($r->params ?? [] as $k => $v){{ \Illuminate\Support\Str::headline($k) }}: {{ is_array($v) ? implode(', ', $v) : $v }}@if (! $loop->last) · @endif @endforeach</td>
                <td class="nowrap">{{ $r->formatLabel() }}</td>
                <td class="num">{{ $r->status === 'done' ? number_format($r->rows) : '…' }}</td>
                <td style="white-space:nowrap">@include('backend.partials.pill', ['text' => \App\Models\ReportRun::stateLabel($r->state())[0], 'tone' => \App\Models\ReportRun::stateLabel($r->state())[1]])</td>
                <td class="actions">@include('backend.partials.report-actions', ['size' => 'row', 'downloadLabel' => 'Download '.$r->formatLabel(),
                    'download' => $r->file ? route('corral.reports.download', $r) : null, 'why' => in_array($r->status, ['queued', 'running']) ? 'still running' : 'no file',
                    'rerun' => route('admin.files.rerun', $r)])</td></tr>
        @empty <tr><td colspan="7" class="empty">You haven't run this report yet. Pick the settings on the left and Run Report.</td></tr> @endforelse</tbody>
    </table></div>
</div>
