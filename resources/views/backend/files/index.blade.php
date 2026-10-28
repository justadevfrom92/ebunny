@extends('backend.layouts.app')

@section('title', 'My Files')
@section('crumb', 'My Files')

{{-- Every report you ran, from any app: name, when, format, rows, size, status; open one for its preview and download --}}
@section('content')
    @php $chip = fn ($s, $label, $n) => '<a href="'.route('admin.files', $s ? ['status' => $s] : []).'" class="chip-link'.(($f['status'] ?? null) === $s ? ' on' : '').'">'.e($label).' · '.$n.'</a>'; @endphp
    @include('backend.partials.page-head', ['title' => 'My Files', 'sub' => 'Reports you ran, from every app. They run in the background; the number by your name (top right) counts finished ones you haven\'t opened yet.'])
    <div class="chips small">{!! $chip(null, 'All', $counts['all']) !!}{!! $chip('running', 'Running', $counts['running']) !!}{!! $chip('done', 'Ready', $counts['done']) !!}{!! $chip('failed', 'Errors', $counts['failed']) !!}</div>
    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>Report</th><th>Requested</th><th>From</th><th>Format</th><th class="num">Rows</th><th class="num">Size</th><th>Status</th><th></th></tr></thead>
        <tbody>@forelse ($runs as $r)
            <tr class="click" data-href="{{ route('admin.files.show', $r) }}">
                <td><a href="{{ route('admin.files.show', $r) }}"><b>{{ $r->label() }}</b></a>@if (in_array($r->id, $unseen)) <span class="pill info">New</span>@endif<div class="help">#{{ $r->id }}</div></td>
                <td class="nowrap">{{ $r->created_at->format('n/j/Y g:i A') }}</td><td>{{ ucfirst($r->app) }}</td><td class="nowrap">{{ $r->formatLabel() }}</td>
                <td class="num">{{ $r->status === 'done' ? number_format($r->rows) : '…' }}</td><td class="num">{{ $r->sizeLabel() ?? '—' }}</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\ReportRun::stateLabel($r->state())[0], 'tone' => \App\Models\ReportRun::stateLabel($r->state())[1]])</td>
                <td class="actions">@include('backend.partials.report-actions', ['size' => 'row', 'downloadLabel' => 'Download '.$r->formatLabel(), 'download' => $r->file ? route('admin.files.download', $r) : null,
                    'why' => in_array($r->status, ['queued', 'running']) ? 'still running' : 'no file', 'rerun' => route('admin.files.rerun', $r)])</td></tr>
        @empty <tr><td colspan="8" class="empty">No files yet. Run a report from Walker → Reports.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $runs])</div>
@endsection
