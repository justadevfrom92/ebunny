@extends('backend.layouts.app')

@section('crumb', 'Phone Call')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Phone Call'.($call->customer ? ': '.$call->customer->name : ''),
        'sub' => $call->started_at->format('l, F j, Y g:i A').' · '.ucfirst($call->direction).' · '.intdiv($call->duration_sec, 60).' min '.($call->duration_sec % 60).' sec',
        'actions' => '<a class="btn ghost" href="'.route('corral.calls').'">Back to Phone Calls</a>'.($call->customer ? '<a class="btn ghost" href="'.route('corral.customers.show', $call->customer).'">Back to account</a>' : '')])
    <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="panel"><div class="panel-head"><h2>Call</h2></div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>{{ $call->customer?->account ?? '—' }}<br><span class="help">{{ $call->customer?->name }}</span></dd>
            <dt>Phone</dt><dd class="mono">{{ $call->phone }}</dd>
            <dt>Issue</dt><dd>{{ $call->disposition ?? '—' }}</dd>
            <dt>Answered by</dt><dd>{{ $call->user?->name ?? '—' }} <span class="mono muted">{{ $call->agent_id }}</span></dd>
            <dt>Duration</dt><dd>{{ intdiv($call->duration_sec, 60) }}:{{ str_pad((string) ($call->duration_sec % 60), 2, '0', STR_PAD_LEFT) }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Transcript</h2></div><div class="panel-body transcript">
            @forelse (preg_split("/\r?\n/", (string) $call->transcript, -1, PREG_SPLIT_NO_EMPTY) as $line)
                @php [$who, $said] = str_contains($line, ':') ? array_map('trim', explode(':', $line, 2)) : ['', $line]; @endphp
                <p @class(['agent' => strtolower($who) === 'agent'])><b>{{ $who }}</b> {{ $said }}</p>
            @empty <p class="muted">No transcript for this call.</p> @endforelse
        </div></div>
    </div>
@endsection
