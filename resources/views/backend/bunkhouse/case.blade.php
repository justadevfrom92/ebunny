@extends('backend.layouts.app')

@section('crumb', 'Case #'.$c->id)

@section('content')
    @include('backend.partials.page-head', ['title' => $c->subject, 'sub' => 'Case #'.$c->id.' · '.e($c->type).' · '.e($c->priority).' priority · '.view('backend.partials.pill', ['text' => $c->statusLabel(), 'tone' => $c->statusTone()])->render(),
        'actions' => '<a class="btn ghost" href="'.route('bunkhouse.cases').'">All Cases</a>'])

    <div class="grid-3">
        <div class="panel"><div class="panel-head"><h2>About</h2></div><div class="panel-body"><dl class="kv">
            <dt>Person</dt><dd>@include('backend.bunkhouse._who', ['p' => $c->user])</dd>
            <dt>{{ $c->user->company ? 'Contact' : 'Manager' }}</dt><dd>{{ $c->user->manager?->name ?? '—' }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Case</h2></div><div class="panel-body"><dl class="kv">
            <dt>Opened</dt><dd>{{ $c->created_at->format('n/j/Y g:i A') }} by {{ $c->opener?->name ?? '—' }}</dd>
            <dt>Assigned To</dt><dd>{{ $c->assignee?->name ?? 'Unassigned' }}</dd>
            @if ($c->closed_at)<dt>Closed</dt><dd>{{ $c->closed_at->format('n/j/Y') }}</dd><dt>Resolution</dt><dd>{{ $c->resolution }}</dd>@endif
        </dl></div></div>
        @if ($others->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Other Cases</h2><span class="muted">same person</span></div><div class="table-wrap"><table>
            <tbody>@foreach ($others as $o)<tr class="click" data-href="{{ route('bunkhouse.cases.show', $o) }}"><td><a href="{{ route('bunkhouse.cases.show', $o) }}">{{ $o->subject }}</a></td><td>{{ $o->statusLabel() }}</td></tr>@endforeach</tbody>
        </table></div></div>
        @else
        <div class="panel"><div class="panel-head"><h2>Other Cases</h2></div><div class="panel-body muted">No other cases for {{ explode(' ', $c->user->name)[0] }}.</div></div>
        @endif
    </div>

    <div class="panel"><div class="panel-head"><h2>Updates</h2><span class="muted">{{ $c->updates->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>By</th><th>Update</th></tr></thead>
        <tbody>@foreach ($c->updates as $u)<tr><td class="nowrap">{{ $u->created_at->format('n/j/Y g:i A') }}</td><td>{{ $u->user?->name }}</td><td class="wrap" style="white-space:pre-line">{{ $u->body }}</td></tr>@endforeach</tbody>
    </table></div></div>

    <form method="post" action="{{ route('bunkhouse.cases.update', $c) }}" class="panel">@csrf @method('put')
        <div class="panel-head"><h2>Add an Update</h2></div>
        <div class="panel-body stack">
            <div class="field"><label>Status</label><div class="chips small">@foreach (\App\Models\ErmCase::STATUSES as $s => [$l])<label><input type="radio" name="status" value="{{ $s }}" @checked(old('status', $c->status) === $s)><span>{{ $l }}</span></label>@endforeach</div></div>
            <div class="field"><label for="body">Update</label><textarea id="body" name="body" rows="3">{{ old('body') }}</textarea><div class="help">Closing a case needs a note on how it was resolved.</div></div>
            <div class="field"><label>Assigned to</label>@include('backend.bunkhouse._pick', ['name' => 'assigned_to', 'people' => $handlers, 'selected' => [$c->assigned_to]])</div>
            <div><button class="btn cyan">Save</button></div>
        </div>
    </form>
@endsection
