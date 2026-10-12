@extends('backend.layouts.app')

@section('crumb', 'Account '.$c->account.' › Contact Log')

@section('content')
    @include('backend.partials.page-head', [
        'title' => 'Contact Log',
        'sub' => e($c->name).' · Account <span class="mono">'.e($c->account).'</span> · '.e($c->email).' · '.e($c->phone),
        'actions' => '<a class="btn ghost" href="'.route('corral.customers.show', $c).'#general">Back to account</a>',
    ])

    <div class="panel"><div class="panel-head"><h2>Marketing Emails</h2>
        <form method="post" action="{{ route('corral.customers.marketing-toggle', $c) }}" class="inline">@csrf
            <button class="btn sm {{ $c->marketing_opt_in ? 'ghost' : '' }}">{{ $c->marketing_opt_in ? 'Opt Out' : 'Opt In' }}</button></form></div>
        <div class="panel-body">Customer is opted <b>{{ $c->marketing_opt_in ? 'IN' : 'OUT' }}</b> of marketing emails. Billing and service messages are always sent.</div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Emails &amp; Texts ({{ $logs->count() }})</h2>
        <span class="actions"><a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'send-email']) }}">Send Email</a><a class="btn sm ghost" href="{{ route('corral.customers.action', [$c, 'send-text']) }}">Send Text</a></span></div>
        <div class="table-wrap"><table>
            <thead><tr><th>Created</th><th>Type</th><th>Template</th><th>Status</th><th>Sent</th><th>Opened</th><th>Clicked</th><th>Dropped</th></tr></thead>
            <tbody>@forelse ($logs as $l)
                <tr class="click" data-href="{{ $l->corralUrl() }}"><td><a href="{{ $l->corralUrl() }}">{{ $l->created_at->format('n/j/Y g:i A') }}</a></td><td>{{ $l->channel }}@if ($l->channel === 'SMS')<br><span class="help">{{ $l->direction === 'in' ? 'From customer' : 'To customer' }}</span>@endif</td><td>{{ $l->template }}@if ($l->body)<br><span class="help">{{ \Illuminate\Support\Str::limit($l->body, 120) }}</span>@endif</td>
                    <td>@include('backend.partials.pill', ['text' => $l->status, 'tone' => ['sent' => 'ok', 'queued' => 'info'][$l->status] ?? 'bad'])</td>
                    @foreach (['sent_at', 'opened_at', 'clicked_at', 'dropped_at'] as $f)<td>{{ $l->$f?->format('n/j/Y g:i A') ?? '—' }}</td>@endforeach</tr>
            @empty <tr><td colspan="8" class="empty">No emails or texts sent.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Phone Calls ({{ $calls->count() }})</h2></div><div class="table-wrap"><table>
        <thead><tr><th>Started</th><th>Direction</th><th>Phone</th><th>Agent</th><th class="num">Duration</th><th>Disposition</th></tr></thead>
        <tbody>@forelse ($calls as $p)
            <tr class="click" data-href="{{ route('corral.calls.transcript', $p) }}"><td><a href="{{ route('corral.calls.transcript', $p) }}">{{ $p->started_at->format('n/j/Y g:i A') }}</a></td><td>{{ ucfirst($p->direction) }}</td><td class="mono">{{ $p->phone }}</td>
                <td>{{ $p->user?->name ?? $p->agent_id ?? '—' }}</td><td class="num">{{ gmdate('i:s', $p->duration_sec) }}</td><td>{{ $p->disposition ?? '—' }}</td></tr>
        @empty <tr><td colspan="6" class="empty">No phone calls.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
