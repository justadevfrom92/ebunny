@extends('backend.layouts.app')

@section('crumb', 'Text Message')

@section('content')
    @php $c = $m->customer; @endphp
    @include('backend.partials.page-head', ['title' => 'Text Message'.($c ? ': '.$c->name : ''),
        'sub' => $m->created_at->format('l, F j, Y g:i A').' · '.($m->direction === 'in' ? 'From the customer' : 'To the customer').' · <span class="mono">'.e($m->phone ?? $c?->phone).'</span>',
        'actions' => '<a class="btn ghost" href="'.route('corral.sms').'">Back to SMS</a>'.($c ? '<a class="btn ghost" href="'.route('corral.customers.contact-log', $c).'">Contact Log</a><a class="btn ghost" href="'.route('corral.customers.show', $c).'">Back to account</a><a class="btn ghost" href="'.route('corral.contacts.create', ['account' => $c->account, 'channel' => 'sms']).'">New Contact</a><a class="btn cyan" href="'.route('corral.sms.create', ['account' => $c->account]).'">Reply</a>' : '')])

    <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,1fr) minmax(0,2fr)">
        <div class="panel"><div class="panel-head"><h2>Message</h2>@include('backend.partials.pill', ['text' => $m->status, 'tone' => ['sent' => 'ok', 'queued' => 'info', 'received' => 'ok', 'logged' => 'info'][$m->status] ?? 'bad'])</div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>@include('backend.partials.account-link', ['c' => $c])<br><span class="help">{{ $c?->name }}</span></dd>
            <dt>Phone</dt><dd class="mono">@include('backend.partials.phone-actions', ['phone' => $m->phone ?? $c?->phone, 'c' => $c])</dd>
            <dt>Direction</dt><dd>{{ $m->direction === 'in' ? 'From the customer' : 'To the customer' }}</dd>
            <dt>Template</dt><dd>{{ $m->template }}</dd>
            <dt>Sent by</dt><dd>{{ $m->direction === 'in' ? '—' : ($m->user?->name ?? 'System') }}</dd>
            <dt>Created</dt><dd>{{ $m->created_at->format('n/j/Y g:i:s A') }}</dd>
            <dt>Delivered</dt><dd>{{ $m->sent_at?->format('n/j/Y g:i:s A') ?? '—' }}</dd>
            <dt>Text</dt><dd>{{ $m->body }}</dd>
            <dt>Resolution</dt><dd>@include('backend.partials.resolution-pill', ['value' => $m->resolution])</dd>
        </dl></div>
            <div class="panel-body" style="border-top:1px solid var(--line)"><h3>How did it end?</h3>@include('backend.corral._resolution', ['type' => 'contact', 'id' => $m->id, 'value' => $m->resolution, 'by' => $m->resolvedBy?->name, 'at' => $m->resolved_at])</div>
        </div>
        <div class="panel"><div class="panel-head"><h2>Conversation</h2><span class="muted">{{ $thread->count() }} {{ Str::plural('message', $thread->count()) }}</span></div>
            <div class="panel-body transcript">
                @foreach ($thread as $t)
                    <p @class(['agent' => $t->direction !== 'in', 'current' => $t->is($m)])><b>{{ $t->direction === 'in' ? ($c?->first_name ?? 'Customer') : ($t->user?->name ?? 'Us') }}</b> @if (! $t->is($m))<a href="{{ route('corral.sms.show', $t) }}" class="muted" style="font-size:.75rem">{{ $t->created_at->format('n/j g:i A') }}</a>@else<span class="muted" style="font-size:.75rem">{{ $t->created_at->format('n/j g:i A') }} · this message</span>@endif<br>{{ $t->body }}</p>
                @endforeach
            </div>
        </div>
    </div>
@endsection
