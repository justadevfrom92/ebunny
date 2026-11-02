@extends('backend.layouts.app')

@section('crumb', $s->name)

{{-- One saved report: its settings, Run Now (top right), Open in the report form to change them, and the runs it made. --}}
@section('content')
    @php $mine = $s->user_id === auth()->id() || auth()->user()->can('configure'); @endphp
    @include('backend.partials.page-head', ['title' => $s->name, 'sub' => e($def['title'] ?? $s->report).' · saved by '.e($s->user_id === auth()->id() ? 'you' : ($s->user?->name ?? '—')).' '.$s->created_at->format('n/j/Y').($s->shared ? ' · shared with everyone' : ''),
        'actions' => '<a class="btn ghost btn-back" href="'.route('walker.saved').'">Back to Saved Reports</a>'
            .($def ? '<a class="btn ghost" href="'.route('walker.reports.show', [$s->report, 'saved' => $s->id]).'">Change Settings</a>'
                .'<form method="post" action="'.route('walker.saved.run', $s).'" class="inline">'.csrf_field().'<button class="btn cyan">Run Now</button></form>' : '')])

    @unless ($def)<div class="alert bad"><b>Report removed</b> This report is no longer in the library, so it can't run.</div>@endunless

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Settings</h2></div><div class="panel-body"><dl class="kv">
            <dt>Report</dt><dd>@if ($def)<a href="{{ route('walker.reports.show', $s->report) }}">{{ $def['title'] }}</a>@else{{ $s->report }}@endif</dd>
            @foreach ($s->params ?? [] as $k => $v)<dt>{{ \Illuminate\Support\Str::headline($k) }}</dt><dd>{{ is_array($v) ? implode(', ', $v) : ($v === '' || $v === null ? '—' : $v) }}</dd>@endforeach
            <dt>Export As</dt><dd>{{ \App\Support\Export::FORMATS[$s->format][0] ?? $s->format }}</dd>
            <dt>Summary Only</dt><dd>{{ $s->summary ? 'Yes' : 'No' }}</dd>
            <dt>Last Run</dt><dd>{{ $s->last_run_at?->format('n/j/Y g:i A') ?? 'Not yet' }}</dd>
        </dl></div>
            @if ($mine)
            <form method="post" action="{{ route('walker.saved.destroy', $s) }}" class="panel-body" data-confirm="Remove {{ $s->name }}?|It comes off Saved Reports. Its past runs and files are kept.|Remove">@csrf @method('delete')
                <button class="btn sm ghost">Remove Saved Report</button></form>
            @endif
        </div>
        <div class="panel"><div class="panel-head"><h2>Runs</h2></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Requested</th><th>By</th><th class="num">Rows</th><th>Status</th></tr></thead>
            <tbody>@forelse ($runs as $run)
                <tr class="click" data-href="{{ route('walker.runs.show', $run) }}"><td class="nowrap"><a href="{{ route('walker.runs.show', $run) }}"><b>#{{ $run->id }}</b></a></td><td class="nowrap">{{ $run->created_at->format('n/j/Y g:i A') }}</td>
                    <td>{{ $run->user?->name ?? 'System' }}</td><td class="num">{{ $run->status === 'done' ? number_format($run->rows) : '—' }}</td><td>@include('backend.walker._state')</td></tr>
            @empty <tr><td colspan="5" class="empty">Not run yet. Use Run Now at the top right.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $runs])</div>
    </div>
@endsection
