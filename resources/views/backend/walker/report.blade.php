@extends('backend.layouts.app')

@section('crumb', $def['title'])

@section('content')
    @php $latest = $runs->first(); $latestFile = $runs->firstWhere('file', '!=', null); @endphp
    {{-- Top right: download the latest saved file (save icon) and rerun the latest run's settings, or run with the defaults (reload icon) --}}
    @include('backend.partials.page-head', ['title' => $def['title'], 'sub' => e($def['description']).' · Model <span class="mono">'.e($def['model']).'</span>'
        .($latest ? ' · Last run #'.$latest->id.' '.$latest->created_at->diffForHumans() : ''),
        'actions' => view('backend.partials.report-actions', ['size' => 'page', 'downloadLabel' => 'Download latest '.($latestFile?->formatLabel() ?? 'file'),
            'download' => $latestFile ? route('walker.runs.download', $latestFile) : null, 'why' => $latest ? 'no saved file yet' : 'not run yet',
            'rerun' => $latest ? route('walker.runs.rerun', $latest) : route('walker.reports.run', $def['key'])])->render()])

    <div class="search-layout">
        <aside class="search-side">
            <form method="post" action="{{ route('walker.reports.run', $def['key']) }}" class="panel">@csrf
                <div class="panel-head"><h2>Parameters</h2></div>
                <div class="panel-body">
                    @foreach ($filters as $name => $filter)
                        @php [$label, $type] = $filter; $options = $filter[2] ?? []; $value = old($name, $defaults[$name] ?? null); @endphp
                        @if ($type === 'multi')
                            <div class="field"><label>{{ $label }}</label><div class="check-stack">
                                @foreach ($options as $v => $l)<label class="check"><input type="checkbox" name="{{ $name }}[]" value="{{ $v }}" @checked(in_array($v, (array) $value))> {{ $l }}</label>@endforeach
                            </div></div>
                        @elseif ($type === 'select')
                            <div class="field"><label>{{ $label }}</label><div class="chips small"><label><input type="radio" name="{{ $name }}" value="" @checked((string) $value === '')><span>Any</span></label>
                                @foreach ($options as $v => $l)<label><input type="radio" name="{{ $name }}" value="{{ $v }}" @checked((string) $value === (string) $v)><span>{{ $l }}</span></label>@endforeach</div></div>
                        @else
                            <div class="field"><label for="f-{{ $name }}">{{ $label }}</label><input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type === 'date' ? 'date' : 'text' }}" value="{{ $value }}"></div>
                        @endif
                    @endforeach
                    <div class="field"><label>Export as</label><div class="chips small">
                        @foreach (\App\Support\Export::FORMATS as $v => [$l])<label><input type="radio" name="format" value="{{ $v }}" @checked(old('format', 'xlsx') === $v)><span>{{ $l }}</span></label>@endforeach
                    </div></div>
                    <label class="check"><input type="hidden" name="summary" value="0"><input type="checkbox" name="summary" value="1" @checked(old('summary'))> Summary only (counts instead of every row)</label>
                    <button class="btn cyan">Run Report</button>
                    <p class="help">Runs in the background and saves the file. You'll get a notice at the top right when it's ready.</p>
                </div>
            </form>
        </aside>
        <div class="search-main">
            @include('backend.walker._export-columns')
            <div class="panel"><div class="panel-head"><h2>Recent Runs</h2></div><div class="table-wrap"><table>
                <thead><tr><th>Run</th><th>Requested</th><th>By</th><th class="num">Rows</th><th>Status</th><th><span class="sr-only">Download or rerun</span></th></tr></thead>
                <tbody>@forelse ($runs as $run)
                    <tr class="click" data-href="{{ route('walker.runs.show', $run) }}"><td><a href="{{ route('walker.runs.show', $run) }}">#{{ $run->id }}</a></td><td>{{ $run->created_at->format('n/j/Y g:i A') }}</td>
                        <td>{{ $run->user?->name ?? 'System' }}</td><td class="num">{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</td><td>@include('backend.walker._state')</td><td class="actions">@include('backend.walker._row-actions')</td></tr>
                @empty <tr><td colspan="6" class="empty">Not run yet.</td></tr> @endforelse</tbody>
            </table></div></div>
        </div>
    </div>
@endsection
