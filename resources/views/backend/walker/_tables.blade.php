{{-- Report Status tables: Running, Errors & Did Not Finish, Completed. Swapped in place every few seconds by home.blade.php. --}}
@foreach ($tables as $k => $t)
    <div class="panel" id="runs-{{ $k }}" data-count="{{ $t['runs']->total() }}">
        <div class="panel-head"><h2>{{ $t['label'] }} <span class="count-pill">{{ number_format($t['runs']->total()) }}</span></h2>
            @if ($k === 'running' && $t['runs']->total())<span class="muted">updating live</span>@endif</div>
        <div class="table-wrap"><table style="table-layout:fixed;min-width:1100px">
            <colgroup><col style="width:60px"><col style="width:20%"><col><col style="width:120px"><col style="width:72px"><col style="width:150px"><col style="width:80px"><col style="width:62px"><col style="width:112px"><col style="width:92px"></colgroup>
            <thead><tr><th>ID</th><th>Report</th><th>Model</th><th>Requested By</th><th>From</th><th>{{ $k === 'done' ? 'Finished' : 'Started' }}</th><th class="num">Duration</th><th class="num">Rows</th><th>Status</th><th><span class="sr-only">Download or rerun</span></th></tr></thead>
            <tbody>@forelse ($t['runs'] as $run)
                <tr class="click" data-href="{{ route('walker.runs.show', $run) }}">
                    <td class="nowrap"><a href="{{ route('walker.runs.show', $run) }}"><b>#{{ $run->id }}</b></a></td>
                    <td style="white-space:normal;overflow-wrap:anywhere"><b>{{ $run->title ?? \Illuminate\Support\Str::headline($run->report) }}</b>@if ($k === 'problem' && $run->error)<div class="help">{{ \Illuminate\Support\Str::limit($run->error, 90) }}</div>@endif</td>
                    <td class="mono" style="white-space:normal;overflow-wrap:anywhere">{{ $run->model ?? '—' }}</td>
                    <td>{{ $run->user?->name ?? 'System' }}</td>
                    <td>{{ ucfirst($run->app) }}</td>
                    <td>{{ ($k === 'done' ? ($run->finished_at ?? $run->started_at ?? $run->created_at) : ($run->started_at ?? $run->created_at))->format('n/j/Y g:i A') }}</td>
                    <td class="num">{{ $run->duration_ms !== null ? number_format($run->duration_ms / 1000, 1).'s' : '—' }}</td>
                    <td class="num">{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</td>
                    <td>@include('backend.walker._state')</td><td class="actions">@include('backend.walker._row-actions')</td></tr>
            @empty <tr><td colspan="10" class="empty">{{ ['running' => 'Nothing is running right now.', 'problem' => 'No errors.', 'done' => 'No completed runs yet.'][$k] }}</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $t['runs']])
    </div>
@endforeach
