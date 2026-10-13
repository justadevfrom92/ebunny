{{-- Top right of a Corral report: save icon downloads these results (same filters) as .xlsx; reload icon runs the report again --}}
@php
    $params = collect($f)->except('output')->filter(fn ($v) => $v !== null && $v !== '' && $v !== [])->all();
    $summaryView = ($f['output'] ?? 'screen') === 'summary';
@endphp
@include('backend.partials.report-actions', ['size' => 'page', 'downloadLabel' => 'Download .xlsx',
    'download' => url()->current().'?'.http_build_query($params + ['run' => 1, 'output' => $summaryView ? 'xls-summary' : 'xls']),
    'rerun' => url()->current().'?'.http_build_query($params + ['run' => 1, 'output' => $summaryView ? 'summary' : 'screen']), 'rerunMethod' => 'get'])
