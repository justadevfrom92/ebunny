@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'SMS', 'sub' => 'Recent text messages to and from customers. '.($waiting->count() ? '<b>'.$waiting->count().'</b> '.Str::plural('customer', $waiting->count()).' waiting on a reply.' : 'Nobody is waiting on a reply.'),
        'actions' => '<a class="btn ghost" href="'.route('corral.contacts.create', array_filter(['account' => $f['account'] ?? null, 'channel' => 'sms'])).'">New Contact</a><a class="btn cyan" href="'.route('corral.sms.create').'">Send a Text</a>'])

    @unless ($ready)
        <div class="alert info" style="margin-bottom:16px"><b>Texts are logged, not sent.</b> Add the Twilio settings to .env (see Sheriff → APIs) to send texts.</div>
    @endunless

    <div class="search-layout">
        <aside class="search-side">
            <form method="get" class="panel"><div class="panel-head"><h2>Search Messages</h2></div><div class="panel-body">
                <div class="field"><label for="q">Name, phone or text</label><input id="q" name="q" value="{{ $f['q'] ?? '' }}"></div>
                <div class="field"><label for="account">Account Number</label><input id="account" name="account" value="{{ $f['account'] ?? '' }}" inputmode="numeric"></div>
                <div class="field"><label for="direction">Direction</label><select id="direction" name="direction"><option value="">Both</option><option value="in" @selected(($f['direction'] ?? '') === 'in')>From customer</option><option value="out" @selected(($f['direction'] ?? '') === 'out')>To customer</option></select></div>
                <div class="field"><label for="status">Status</label><select id="status" name="status"><option value="">Any</option>@foreach ($statuses as $s)<option @selected(($f['status'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
                <div class="field"><label for="start">Start</label><input id="start" name="start" type="date" value="{{ $f['start'] ?? '' }}"></div>
                <div class="field"><label for="end">End</label><input id="end" name="end" type="date" value="{{ $f['end'] ?? '' }}"></div>
                <label class="check"><input type="checkbox" name="waiting" value="1" @checked($f['waiting'] ?? false)> Waiting on a reply</label>
                <button class="btn">Search</button>@if (array_filter($f))<a class="btn ghost" href="{{ route('corral.sms') }}">Clear</a>@endif
            </div></form>
        </aside>
        <div class="search-main">
            <div class="panel"><div class="panel-head"><h2>Recent Messages</h2><span class="muted">{{ number_format($messages->total()) }}</span></div>
                <div class="table-wrap"><table>
                    <thead><tr><th>ID</th><th>Sent</th><th>Account</th><th>Message</th><th>Status</th><th>Resolution</th></tr></thead>
                    <tbody>@forelse ($messages as $m)
                        <tr @class(['click', 'row-alert' => $m->direction === 'in' && $waiting->contains($m->customer_id)]) data-href="{{ route('corral.sms.show', $m) }}">
                            <td class="nowrap"><a href="{{ route('corral.sms.show', $m) }}"><b>#{{ $m->id }}</b></a></td><td class="nowrap">{{ $m->created_at->format('n/j/Y g:i A') }}<br><span class="help">{{ $m->direction === 'in' ? 'From customer' : 'To customer' }}</span></td>
                            <td>@if ($m->customer)<a href="{{ route('corral.customers.show', $m->customer) }}">{{ $m->customer->account }}</a><br><span class="help">{{ $m->customer->name }}</span><br>@endif<span class="help mono">{{ $m->phone ?? $m->customer?->phone }}</span></td>
                            <td class="wrap">{{ $m->body }}</td>
                            <td>{{ $m->status }}@if ($m->direction !== 'in')<br><span class="help">by {{ $m->user?->name ?? 'System' }}</span>@endif</td>
                            <td>@include('backend.partials.resolution-pill', ['value' => $m->resolution])</td>
                            </tr>
                    @empty <tr><td colspan="6" class="empty">No messages match.</td></tr> @endforelse</tbody>
                </table></div>
                @include('backend.partials.pager', ['p' => $messages])
            </div>
        </div>
    </div>
@endsection
