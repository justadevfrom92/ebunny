@extends('backend.layouts.app')

@section('crumb', 'Message from '.$m->first_name)

@section('content')
    @include('backend.partials.page-head', ['title' => $m->topicLabel().': '.$m->first_name.' '.$m->last_name,
        'sub' => 'Received '.$m->created_at->format('l, F j, Y g:i A').' ('.$m->created_at->diffForHumans().')',
        'actions' => '<a class="btn ghost" href="'.route('corral.messages.index').'">All Messages</a><form method="post" action="'.route('corral.messages.handled', $m).'" class="inline">'.csrf_field().'<button class="btn '.($m->handled_at ? 'ghost' : 'cyan').'">'.($m->handled_at ? 'Reopen' : 'Mark Handled').'</button></form>'])
    <div class="grid-2" style="align-items:start;grid-template-columns:minmax(0,2fr) minmax(0,1fr)">
        <div style="display:grid;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>Message</h2>@include('backend.partials.pill', $m->handled_at ? ['text' => 'Handled', 'tone' => 'ok'] : ['text' => 'Open', 'tone' => 'warn'])</div>
                <div class="panel-body"><p style="white-space:pre-line;margin:0;line-height:1.6">{{ $m->message }}</p></div></div>
            @if ($m->reply)
                <div class="panel"><div class="panel-head"><h2>Reply sent</h2><span class="muted">{{ $m->replier?->name }} · {{ $m->replied_at->format('n/j/Y g:i A') }}</span></div>
                    <div class="panel-body"><p style="white-space:pre-line;margin:0;line-height:1.6">{{ $m->reply }}</p></div></div>
            @endif
            <form method="post" action="{{ route('corral.messages.reply', $m) }}" class="panel">@csrf
                <div class="panel-head"><h2>{{ $m->reply ? 'Send another reply' : 'Reply' }}</h2><span class="muted">emails {{ $m->email }}</span></div>
                <div class="panel-body" style="display:grid;gap:10px">
                    <label for="reply" class="sr-only">Reply</label>
                    <textarea id="reply" name="reply" rows="6" required maxlength="5000" style="width:100%;font:inherit;padding:10px;border:1px solid var(--line);border-radius:6px">{{ old('reply', 'Hi '.$m->first_name.",\n\nThanks for reaching out. \n\n".auth()->user()->name."\n".config('brand.name').' Customer Care') }}</textarea>
                    <label class="check"><input type="checkbox" name="handled" value="1" @checked(! $m->handled_at)> Mark handled when sent</label>
                    <div><button class="btn cyan">Send Reply</button></div>
                </div>
            </form>
        </div>
        <div style="display:grid;gap:20px;min-width:0">
            <div class="panel"><div class="panel-head"><h2>From</h2></div><div class="panel-body"><dl class="kv">
                <dt>Name</dt><dd>{{ $m->first_name }} {{ $m->last_name }}</dd>
                <dt>Email</dt><dd><a href="mailto:{{ $m->email }}">{{ $m->email }}</a></dd>
                <dt>Phone</dt><dd>{{ $m->phone ?: '—' }}</dd>
                <dt>Topic</dt><dd>{{ $m->topicLabel() }}</dd>
                <dt>IP</dt><dd class="mono">{{ $m->ip ?: '—' }}</dd>
                @if ($m->handled_at)<dt>Handled</dt><dd>{{ $m->handled_at->format('n/j/Y g:i A') }}<br><span class="help">{{ $m->handler?->name }}</span></dd>@endif
            </dl></div></div>
            <div class="panel"><div class="panel-head"><h2>Account</h2></div><div class="panel-body">
                @if ($c)
                    <dl class="kv"><dt>Account</dt><dd><a href="{{ route('corral.customers.show', $c) }}" class="mono">{{ $c->account }}</a></dd><dt>Name</dt><dd>{{ $c->name }}</dd>
                        <dt>Status</dt><dd>@include('backend.partials.pill', ['text' => $c->status, 'tone' => $c->statusTone()])</dd><dt>Balance</dt><dd>${{ number_format($c->balance, 2) }}</dd><dt>Plan</dt><dd>{{ $c->plan?->name ?? '—' }}</dd></dl>
                    <p class="help" style="margin:8px 0 0">Matched by {{ strtolower($c->email) === strtolower($m->email) ? 'email' : 'phone number' }}. Replies are logged on this account.</p>
                @else
                    <p class="muted" style="margin:0">No account matches this email or phone. Likely a prospect: <a href="{{ route('corral.orders.create') }}">start an order</a> if they want service.</p>
                @endif
            </div></div>
            @if ($others->isNotEmpty())
                <div class="panel"><div class="panel-head"><h2>Other messages from them</h2></div><div class="table-wrap"><table><tbody>@foreach ($others as $o)
                    <tr class="click" data-href="{{ route('corral.messages.show', $o) }}"><td class="nowrap"><a href="{{ route('corral.messages.show', $o) }}">{{ $o->created_at->format('n/j/Y') }}</a></td><td>{{ $o->topicLabel() }}</td><td>{{ $o->handled_at ? 'Handled' : 'Open' }}</td></tr>
                @endforeach</tbody></table></div></div>
            @endif
        </div>
    </div>
@endsection
