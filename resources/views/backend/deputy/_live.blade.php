{{-- The live part of a conversation page: who is answering, the action buttons, and the transcript. Swapped in every few seconds while the conversation is open. --}}
@php
    $mine = $c->status === 'agent' && $c->handed_to_id === auth()->id();
    $tone = ['bot' => 'info', 'waiting' => 'warn', 'agent' => 'ok', 'closed' => ''][$c->status] ?? '';
@endphp
<div id="live" data-status="{{ $c->status }}" data-mine="{{ $mine ? 1 : 0 }}" data-lines="{{ count($c->lines()) }}">
    <div class="panel-head" style="flex-wrap:wrap;gap:10px">
        <h2>Transcript</h2>
        @include('backend.partials.pill', ['text' => config('deputy.statuses.'.$c->status).($c->status === 'agent' && $c->handedTo ? ': '.($mine ? 'you' : $c->handedTo->name) : ''), 'tone' => $tone])
        @if ($c->isLive())
            <div class="actions" style="margin-left:auto;display:flex;gap:8px;flex-wrap:wrap">
                @unless ($mine)
                    <form method="post" action="{{ route('deputy.conversations.take', $c) }}">@csrf<button class="btn sm cyan">Take Over</button></form>
                @else
                    <form method="post" action="{{ route('deputy.conversations.handback', $c) }}">@csrf<button class="btn sm ghost">Hand Back to Bot</button></form>
                @endunless
                @if ($c->customer?->phone)
                    <form method="post" action="{{ route('deputy.conversations.call', $c) }}">@csrf<button class="btn sm">Call {{ $c->customer->phone }}</button></form>
                @endif
                <form method="post" action="{{ route('deputy.conversations.close', $c) }}">@csrf<button class="btn sm ghost">Close</button></form>
            </div>
        @endif
    </div>
    <div class="panel-body transcript" id="transcript">
        @forelse ($c->lines() as [$who, $said])
            <p @class(['agent' => $who === 'Bot' || strtolower($who) === 'agent', 'person' => str_ends_with($who, '(agent)'), 'system' => $who === 'System'])><b>{{ $who }}</b> {{ $said }}</p>
        @empty <p class="muted">No transcript.</p> @endforelse
    </div>
</div>
