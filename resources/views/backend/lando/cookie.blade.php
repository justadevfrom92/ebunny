@extends('backend.layouts.app')

@section('crumb', $name)

{{-- One of the website's cookies: what it does, how many choices allow it, and every browser it was seen in. --}}
@section('content')
    @php
        [$cat, $purpose, $lasts] = $cookie;
        $tone = ['necessary' => '', 'analytics' => 'info', 'marketing' => 'warn'][$cat];
        $row = function ($k) use ($name) {
            return '<tr class="click" data-href="'.route('lando.consents.show', $k).'"><td class="nowrap"><a href="'.route('lando.consents.show', $k).'">'.($k->cookies_seen_at ?? $k->created_at)->format('n/j/Y g:i A').'</a></td>'
                .'<td>'.($k->customer ? e($k->customer->name).'<div class="help mono">'.e($k->customer->account).'</div>' : ($k->user ? e($k->user->name).'<div class="help">Employee</div>' : '<span class="muted">Anonymous</span>')).'</td>'
                .'<td>'.view('backend.partials.pill', ['text' => $k->label(), 'tone' => ['all' => 'ok', 'necessary' => 'warn', 'custom' => 'info'][$k->choice] ?? ''])->render().'</td>'
                .'<td class="mono" style="overflow-wrap:anywhere">'.e(($k->cookie_jar[$name] ?? '') !== '' ? $k->cookie_jar[$name] : '(empty)').'</td>'
                .'<td class="mono">'.e($k->ip).'</td></tr>';
        };
    @endphp
    @include('backend.partials.page-head', ['title' => $name, 'sub' => view('backend.partials.pill', ['text' => ucfirst($cat), 'tone' => $tone])->render().' · '.e($purpose).' · lasts '.e($lasts),
        'actions' => '<a class="btn ghost" href="'.route('lando.consents').'">Cookie Consents</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Choices Allowing It</span><strong>{{ $choices ? round(100 * $allowing / $choices) : 0 }}%</strong><small>{{ number_format($allowing) }} of {{ number_format($choices) }}, last 30 days</small></div>
        <div class="stat"><span>Seen in Browsers</span><strong>{{ number_format($seen->count()) }}</strong><small>in the cookie caches</small></div>
        <div @class(['stat', 'alert' => $notAllowed->isNotEmpty()])><span>Present but Not Allowed</span><strong>{{ $notAllowed->count() }}</strong><small>{{ $cat === 'necessary' ? 'necessary: always allowed' : 'their choice blocks it' }}</small></div>
        <div class="stat"><span>Lasts</span><strong style="font-size:1.3rem">{{ $lasts }}</strong><small>{{ $cat === 'necessary' ? 'always on' : 'only with consent' }}</small></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>About This Cookie</h2></div><div class="panel-body"><dl class="kv">
        <dt>Name</dt><dd class="mono">{{ $name }}</dd>
        <dt>Category</dt><dd>@include('backend.partials.pill', ['text' => ucfirst($cat), 'tone' => $tone]) <span class="help">{{ ['necessary' => 'The site needs it; it\'s set for everyone.', 'analytics' => 'Only set when the visitor allows analytics.', 'marketing' => 'Only set when the visitor allows marketing.'][$cat] }}</span></dd>
        <dt>What it's for</dt><dd>{{ $purpose }}</dd>
        <dt>Lasts</dt><dd>{{ $lasts }}</dd>
    </dl></div></div>

    @if ($notAllowed->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Present but Not Allowed</h2><span class="muted">set before they chose, or a script ignoring the choice</span></div><div class="table-wrap"><table data-page="10">
            <thead><tr><th>Seen</th><th>Who</th><th>Choice</th><th>Value</th><th>IP</th></tr></thead>
            <tbody>@foreach ($notAllowed as $k){!! $row($k) !!}@endforeach</tbody>
        </table></div></div>
    @endif

    <div class="panel"><div class="panel-head"><h2>Browsers With This Cookie</h2><span class="muted">latest {{ $seen->count() }}</span></div><div class="table-wrap"><table data-page="10">
        <thead><tr><th>Seen</th><th>Who</th><th>Choice</th><th>Value</th><th>IP</th></tr></thead>
        <tbody>@forelse ($seen as $k){!! $row($k) !!}@empty<tr><td colspan="5" class="empty">Not seen in any browser yet.</td></tr>@endforelse</tbody>
    </table></div></div>
@endsection
