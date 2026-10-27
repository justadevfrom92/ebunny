@extends('backend.layouts.app')

@section('crumb', $call->api.' Call #'.$call->id)

{{-- One API call: what was sent and what came back (secrets are blanked before saving), then the API's other calls. --}}
@section('content')
    @php
        $app = \App\Support\AdminMenu::currentApp();
        $json = fn ($v) => $v === null || $v === [] ? null : (is_string($v) ? $v : json_encode($v, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $req = $call->request ?? []; $res = $call->response ?? [];
        $tone = $call->ok() ? 'ok' : (in_array($call->status, ['skipped', 'not built'], true) ? 'warn' : 'bad');
    @endphp
    @include('backend.partials.page-head', ['title' => $call->api.' · '.$call->action, 'sub' => 'Call #'.$call->id.' · '.$call->created_at->format('n/j/Y g:i:s A'),
        'actions' => ($app === 'sheriff' && $integration ? '<a class="btn ghost btn-back" href="'.route('sheriff.integrations.show', $integration).'">Back to '.e($call->api).'</a>' : '')
            .($app === 'corral' && $call->customer ? '<a class="btn ghost btn-back" href="'.route('corral.customers.api-log', $call->customer).'">Back to API Log</a>' : '')])

    <div style="display:flex;flex-direction:column;gap:20px">
        <div class="grid-4 stat-row">
            <div @class(['stat', 'alert' => $tone === 'bad'])><span>Status</span><strong style="font-size:1.25rem">{{ $call->status }}</strong><small>{{ $call->ok() ? 'worked' : 'did not work' }}</small></div>
            <div class="stat"><span>Response Time</span><strong style="font-size:1.25rem">{{ $call->response_ms !== null ? number_format($call->response_ms).' ms' : '—' }}</strong><small>round trip</small></div>
            <div class="stat"><span>Method</span><strong style="font-size:1.25rem">{{ $call->method ?? '—' }}</strong><small>{{ $call->action }}</small></div>
            <div class="stat"><span>Account</span><strong style="font-size:1.25rem">@if ($call->customer)@include('backend.partials.account-link', ['c' => $call->customer])@else — @endif</strong><small>{{ $call->customer?->name ?? 'not for an account' }}</small></div>
        </div>

        <div class="panel"><div class="panel-head"><h2>What Was Sent</h2>@include('backend.partials.pill', ['text' => $call->method ?? 'Request', 'tone' => 'info'])</div>
            <div class="panel-body api-x">
                <div class="api-line"><b class="mono">{{ $call->method ?? '—' }}</b> <span class="mono">{{ $call->endpoint ?? 'No address recorded' }}</span></div>
                @if ($h = $json($req['headers'] ?? null))<h3>Headers</h3><pre class="code">{{ $h }}</pre>@endif
                <h3>Body</h3>@if ($b = $json($req['body'] ?? null))<pre class="code">{{ $b }}</pre>@else<p class="help">No body (nothing sent beyond the address).</p>@endif
            </div>
        </div>

        <div class="panel"><div class="panel-head"><h2>What Came Back</h2>@include('backend.partials.pill', ['text' => (string) ($res['status'] ?? $call->status), 'tone' => $tone])</div>
            <div class="panel-body api-x">
                @if ($h = $json($res['headers'] ?? null))<h3>Headers</h3><pre class="code">{{ $h }}</pre>@endif
                <h3>Body</h3>@if ($b = $json($res['body'] ?? null))<pre class="code">{{ $b }}</pre>@else<p class="help">Nothing came back.</p>@endif
            </div>
        </div>
        <p class="help">Keys, tokens, passwords and card numbers are blanked (•••• hidden) before a call is saved.</p>

        <div class="panel"><div class="panel-head"><h2>Other {{ $call->api }} Calls{{ $call->customer ? ' for This Account' : '' }}</h2><span class="muted">newest first</span></div><div class="table-wrap"><table>
            <thead><tr><th>ID</th><th>Time</th><th>Action</th><th>Status</th><th class="num">Response</th></tr></thead>
            <tbody>@forelse ($others as $o)
                @php $u = route($app.'.api-calls.show', $o); @endphp
                <tr class="click" data-href="{{ $u }}"><td class="nowrap"><a href="{{ $u }}"><b>#{{ $o->id }}</b></a></td><td class="nowrap">{{ $o->created_at->format('n/j/Y g:i:s A') }}</td><td class="mono">{{ $o->action }}</td>
                    <td>@include('backend.partials.pill', ['text' => $o->status, 'tone' => $o->ok() ? 'ok' : 'bad'])</td><td class="num">{{ $o->response_ms !== null ? number_format($o->response_ms).' ms' : '—' }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No other calls.</td></tr> @endforelse</tbody>
        </table></div>@include('backend.partials.pager', ['p' => $others])</div>
    </div>
@endsection
