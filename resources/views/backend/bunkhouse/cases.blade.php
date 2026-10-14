@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'HR Cases', 'sub' => 'Confidential: concerns, coaching, accommodations, policy questions and performance plans. Only people with Bunkhouse see these.',
        'actions' => '<a class="btn cyan" href="'.route('bunkhouse.cases.create').'">Open a Case</a>'])

    <div class="panel"><div class="panel-head"><h2>Open</h2><span class="muted">{{ $open->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>Case</th><th>About</th><th>Type</th><th>Priority</th><th>Assigned</th><th>Opened</th><th>Status</th></tr></thead>
        <tbody>@forelse ($open as $c)<tr class="click" data-href="{{ route('bunkhouse.cases.show', $c) }}"><td><a href="{{ route('bunkhouse.cases.show', $c) }}"><b>{{ $c->subject }}</b></a><div class="help">#{{ $c->id }} · {{ $c->updates_count }} {{ Str::plural('update', $c->updates_count) }}</div></td>
            <td>{{ $c->user->name }}</td><td>{{ $c->type }}</td><td>@include('backend.partials.pill', ['text' => $c->priority, 'tone' => ['High' => 'bad', 'Medium' => 'warn'][$c->priority] ?? ''])</td><td>{{ $c->assignee?->name ?? 'Unassigned' }}</td>
            <td class="nowrap">{{ $c->created_at->format('n/j/Y') }}<div class="help">{{ $c->created_at->diffForHumans() }}</div></td><td>@include('backend.partials.pill', ['text' => $c->statusLabel(), 'tone' => $c->statusTone()])</td></tr>
        @empty <tr><td colspan="7" class="empty">No open cases.</td></tr> @endforelse</tbody>
    </table></div></div>

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>By Type</h2><span class="muted">last 12 months</span></div><div class="table-wrap"><table>
            <tbody>@forelse ($byType as $t => $n)<tr><td>{{ $t }}</td><td class="num">{{ $n }}</td></tr>@empty <tr><td class="empty">None.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Recently Closed</h2></div><div class="table-wrap"><table>
            <tbody>@forelse ($closed as $c)<tr class="click" data-href="{{ route('bunkhouse.cases.show', $c) }}"><td><a href="{{ route('bunkhouse.cases.show', $c) }}">{{ $c->subject }}</a><div class="help">{{ $c->user->name }} · {{ $c->type }}</div></td><td class="nowrap">{{ $c->closed_at?->format('n/j/Y') }}</td></tr>
            @empty <tr><td class="empty">None yet.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
