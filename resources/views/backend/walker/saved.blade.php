@extends('backend.layouts.app')

{{-- Walker → Saved Reports: reports bookmarked with their settings. Each row opens the saved report (Run Now is on its page). --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Saved Reports', 'sub' => 'Reports saved with their settings, yours and the ones others shared. Open one to run it again in one click. Save one from any report under Run a Report (or from a past run).',
        'actions' => '<a class="btn cyan" href="'.route('walker.reports.index').'">Run a Report</a>'])

    <div class="panel"><div class="table-wrap"><table data-page="10">
        <thead><tr><th>ID</th><th>Name</th><th>Report</th><th>Settings</th><th>Saved By</th><th class="num">Runs</th><th>Last Run</th></tr></thead>
        <tbody>@forelse ($saved as $s)
            @php $u = route('walker.saved.show', $s); $def = $s->definition(); @endphp
            <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $s->id }}</b></a></td>
                <td><b>{{ $s->name }}</b>@if ($s->shared) @include('backend.partials.pill', ['text' => 'Shared', 'tone' => 'info'])@endif</td>
                <td>{{ $def['title'] ?? $s->report }}<div class="help">{{ \App\Support\Export::FORMATS[$s->format][0] ?? $s->format }}{{ $s->summary ? ' · summary' : '' }}</div></td>
                <td class="wrap help">{{ $s->settingsLine() }}</td>
                <td>{{ $s->user_id === auth()->id() ? 'You' : $s->user?->name }}</td>
                <td class="num">{{ number_format($s->runs_count) }}</td>
                <td class="nowrap">{{ $s->last_run_at?->format('n/j/Y g:i A') ?? 'Not yet' }}</td></tr>
        @empty <tr><td colspan="7" class="empty">No saved reports yet. Open a report under Run a Report and use Save Report.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
