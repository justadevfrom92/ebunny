@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Test Sends', 'sub' => 'Every test of every template. Open a template’s Send Test page to send one or send one again.'])

    <div class="panel"><div class="table-wrap"><table class="table">
        <thead><tr><th>When</th><th>Template</th><th>By</th><th>Sent To</th><th class="num">People</th><th>Result</th><th></th></tr></thead>
        <tbody>@forelse ($tests as $t)
            <tr><td class="nowrap">{{ $t->created_at->format('n/j/Y g:i A') }}</td>
                <td>@if ($t->template)<a href="{{ route('rodeo.templates.edit', $t->template) }}">{{ $t->template->name }}</a>@endif<div class="muted">{{ $t->subject }}</div></td>
                <td>{{ $t->user?->name }}</td>
                <td class="wrap">{{ \App\Http\Controllers\Backend\Rodeo\TemplateTestController::MODES[$t->mode] ?? $t->mode }}@if ($t->target)<div class="muted">{{ Str::limit($t->target, 60) }}</div>@endif</td>
                <td class="num">{{ count($t->recipients) }}</td>
                <td>@include('backend.partials.pill', ['text' => ['sent' => 'Sent', 'logged' => 'Logged only', 'failed' => 'Failed', 'partial' => 'Partly sent', 'suppressed' => 'Suppressed'][$t->status] ?? $t->status, 'tone' => ['sent' => 'ok', 'failed' => 'bad', 'partial' => 'warn', 'suppressed' => 'bad'][$t->status] ?? ''])</td>
                <td class="actions">@if ($t->template)<a class="btn sm ghost" href="{{ route('rodeo.templates.test', $t->template) }}#history">Open</a>@endif</td></tr>
        @empty <tr><td colspan="7" class="empty">No tests match.</td></tr> @endforelse</tbody>
    </table></div>
    @include('backend.partials.pager', ['p' => $tests])</div>
@endsection
