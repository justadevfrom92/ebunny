@extends('backend.layouts.app')

@section('crumb', 'Conversation '.$c->id)

@section('content')
    @include('backend.partials.page-head', ['title' => $c->topic.($c->customer ? ': '.$c->customer->name : ''),
        'sub' => $c->started_at->format('l, F j, Y g:i A').' · '.config('deputy.channels.'.$c->channel).' · '.intdiv($c->duration_sec, 60).' min '.($c->duration_sec % 60).' sec',
        'actions' => '<a class="btn ghost" href="'.route('deputy.conversations').'">Back to Conversations</a>'.($c->agent ? '<a class="btn ghost" href="'.route('deputy.agents.test', $c->agent).'">Test this Agent</a>' : '')])
    @if (session('dial'))<div class="banner-note" style="margin-bottom:12px">Calling <a href="tel:{{ session('dial') }}" id="dial">{{ session('dial') }}</a>. If your phone app didn't open, click the number.</div>@endif
    <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="panel"><div class="panel-head"><h2>Conversation</h2>@include('backend.deputy._outcome', ['c' => $c])</div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>{{ $c->customer?->account ?? '—' }}@if ($c->customer)<br><span class="help">{{ $c->customer->name }}</span>@endif</dd>
            <dt>Phone</dt><dd>{{ $c->customer?->phone ?? '—' }}</dd>
            <dt>Channel</dt><dd>{{ config('deputy.channels.'.$c->channel) }}</dd>
            <dt>Bot</dt><dd>@if ($c->agent)<a href="{{ route('deputy.agents.edit', $c->agent) }}">{{ $c->agent->name }}</a><br><span class="help">{{ $c->agent->activityLabel() }}</span>@else — @endif</dd>
            <dt>Model</dt><dd>@include('backend.deputy._model', ['m' => $c->model])</dd>
            <dt>Issue</dt><dd>{{ $c->topic }}</dd>
            <dt>Handed to</dt><dd>{{ $c->handedTo?->name ?? '—' }}</dd>
            @if ($c->user)<dt>Tested by</dt><dd>{{ $c->user->name }}</dd>@endif
            <dt>Tokens</dt><dd>{{ number_format($c->input_tokens) }} in · {{ number_format($c->output_tokens) }} out</dd>
            <dt>Last message</dt><dd>{{ $c->last_message_at?->format('n/j/Y g:i A') ?? '—' }}</dd>
        </dl></div>
            <div class="panel-body" style="border-top:1px solid var(--line)"><h3>Resolution</h3>@include('backend.corral._resolution', ['type' => 'chat', 'id' => $c->id, 'value' => $c->resolution, 'by' => $c->resolvedBy?->name, 'at' => $c->resolved_at])</div>
        </div>
        <div class="panel">
            @include('backend.deputy._live')
            @if ($c->isLive())
                <form method="post" action="{{ route('deputy.conversations.reply', $c) }}" class="panel-body reply-box" id="reply">@csrf
                    <label for="message" id="reply-label">{{ $c->channel === 'sms' ? 'Text the customer' : 'Reply in the chat' }}</label>
                    <textarea id="message" name="message" required maxlength="{{ $c->channel === 'sms' ? 480 : 2000 }}" rows="3" placeholder="Type your reply…">{{ old('message') }}</textarea>
                    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                        <button class="btn cyan">Send</button>
                        <span class="help" id="reply-hint"></span>
                    </div>
                </form>
            @endif
        </div>
    </div>

    @if ($c->isLive())
    <script>
    (function () {
        var form = document.getElementById('reply'), hint = document.getElementById('reply-hint');
        function sync() {
            var live = document.getElementById('live'), st = live.dataset.status, mine = live.dataset.mine === '1';
            hint.textContent = mine ? 'The bot is quiet while you have this conversation.'
                : st === 'waiting' ? 'The bot handed this to a person. Sending takes it over.'
                : st === 'agent' ? 'Someone else has this conversation. Sending takes it over from them.'
                : 'The bot is answering. Sending takes it over.';
            if (st === 'closed') form.remove();
            var t = document.getElementById('transcript'); t.scrollTop = t.scrollHeight;
        }
        sync();
        @if (session('dial')) location.href = 'tel:{{ session('dial') }}'; @endif
        // New messages from the customer or the bot show up without a reload
        setInterval(function () {
            if (document.hidden) return;
            fetch(location.pathname + '?part=live', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok ? r.text() : Promise.reject(); })
                .then(function (html) {
                    var box = document.createElement('div'); box.innerHTML = html;
                    var next = box.querySelector('#live'), cur = document.getElementById('live');
                    if (next && (next.dataset.lines !== cur.dataset.lines || next.dataset.status !== cur.dataset.status || next.dataset.mine !== cur.dataset.mine)) { cur.replaceWith(next); sync(); }
                }).catch(function () {});
        }, 4000);
    })();
    </script>
    @endif
@endsection
