@extends('backend.layouts.app')

@section('crumb', 'Choice #'.$k->id)

{{-- One cookie banner choice: who made it, what it allows, the cookies their browser actually had, and what else they did. --}}
@section('content')
    @php
        $allowed = $k->cookies();
        $tones = ['necessary' => '', 'analytics' => 'info', 'marketing' => 'warn'];
        $who = $k->customer ? $k->customer->name : ($k->user ? $k->user->name.' (employee)' : 'Anonymous visitor');
        $unexpected = $k->unexpected();
    @endphp
    @include('backend.partials.page-head', ['title' => $k->label().' — '.$who, 'sub' => 'Cookie choice #'.$k->id.' · '.$k->created_at->format('n/j/Y g:i A').' · policy '.$k->policy_version
        .($k->policy_version === \App\Models\CookieConsent::POLICY_VERSION ? ' (current)' : ' (old: they\'ll be asked again)'),
        'actions' => '<a class="btn ghost" href="'.route('lando.consents').'">All Choices</a>'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Choice</span><strong style="font-size:1.3rem">{{ $k->label() }}</strong><small>{{ $k->analytics ? 'analytics on' : 'analytics off' }} · {{ $k->marketing ? 'marketing on' : 'marketing off' }}</small></div>
        <div class="stat"><span>Cookies Allowed</span><strong>{{ count($allowed) }}</strong><small>of {{ count(\App\Models\CookieConsent::COOKIES) }} the site uses</small></div>
        <div class="stat"><span>In Their Browser</span><strong>{{ count($jar) }}</strong><small>{{ $k->cookies_seen_at ? 'last seen '.$k->cookies_seen_at->diffForHumans() : 'not seen yet' }}</small></div>
        <div @class(['stat', 'alert' => $unexpected])><span>Not Allowed but Present</span><strong>{{ count($unexpected) }}</strong><small>{{ $unexpected ? implode(', ', $unexpected) : 'none' }}</small></div>
    </div>

    <div class="grid-2" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr);align-items:start">
        <div class="panel"><div class="panel-head"><h2>Who &amp; Where</h2></div><div class="panel-body"><dl class="kv">
            <dt>Who</dt><dd>@if ($k->customer)@include('backend.partials.account-link', ['c' => $k->customer]) {{ $k->customer->name }}@elseif ($k->user){{ $k->user->name }} <span class="help">employee</span>@else<span class="muted">Anonymous</span>@endif</dd>
            <dt>Chose on</dt><dd class="mono">{{ $k->page ?? '—' }}</dd>
            <dt>IP</dt><dd class="mono">{{ $k->ip ?? '—' }}</dd>
            <dt>Browser</dt><dd class="help" style="overflow-wrap:anywhere">{{ $k->user_agent ?? '—' }}</dd>
            <dt>Visitor id</dt><dd class="mono" style="overflow-wrap:anywhere">{{ $k->visitor }}</dd>
            <dt>When</dt><dd>{{ $k->created_at->format('n/j/Y g:i:s A') }}</dd>
        </dl></div></div>
        <div class="panel"><div class="panel-head"><h2>Cookies by Category</h2></div><div class="panel-body"><dl class="kv">
            @foreach (['necessary' => 'Always on: the site needs them', 'analytics' => 'How people use the site', 'marketing' => 'Ads that brought them here'] as $cat => $why)
                @php $on = $cat === 'necessary' || $k->$cat; @endphp
                <dt>{{ ucfirst($cat) }}</dt><dd>@include('backend.partials.pill', ['text' => $on ? 'Allowed' : 'Blocked', 'tone' => $on ? 'ok' : 'bad']) <span class="help">{{ $why }}</span>
                    <div class="help mono">{{ collect(\App\Models\CookieConsent::COOKIES)->filter(fn ($c) => $c[0] === $cat)->keys()->implode(', ') }}</div></dd>
            @endforeach
        </dl></div></div>
    </div>

    {{-- The cookie cache: every cookie the site uses, whether this choice allows it, and what the browser actually had --}}
    <div class="panel"><div class="panel-head"><h2>Cookie Cache</h2><span class="muted">{{ $k->cookies_seen_at ? 'what their browser sent, '.$k->cookies_seen_at->format('n/j/Y g:i A') : 'not seen yet' }}</span></div>
        <div class="table-wrap"><table class="table cookie-jar">
            <thead><tr><th>Cookie</th><th>Category</th><th>This Choice</th><th>In Their Browser</th><th>What It's For</th><th>Lasts</th></tr></thead>
            <tbody>
                @foreach (\App\Models\CookieConsent::COOKIES as $name => [$cat, $purpose, $lasts])
                    @php $ok = in_array($name, $allowed, true); $has = array_key_exists($name, $jar); @endphp
                    <tr @class(['click', 'row-warn' => $has && ! $ok]) data-href="{{ route('lando.consents.cookie', $name) }}"><td class="mono"><a href="{{ route('lando.consents.cookie', $name) }}"><b>{{ $name }}</b></a></td>
                        <td>@include('backend.partials.pill', ['text' => ucfirst($cat), 'tone' => $tones[$cat]])</td>
                        <td>{{ $ok ? '✓ Allowed' : '✗ Blocked' }}</td>
                        <td class="mono">@if ($has){{ $jar[$name] !== '' ? $jar[$name] : '(empty)' }}@if (! $ok)<div class="help" style="color:var(--bad)">present but not allowed</div>@endif @else<span class="muted">—</span>@endif</td>
                        <td class="wrap">{{ $purpose }}</td><td class="nowrap">{{ $lasts }}</td></tr>
                @endforeach
                @foreach ($unknown as $name => $value)
                    <tr class="row-warn"><td class="mono"><b>{{ $name }}</b></td><td>@include('backend.partials.pill', ['text' => 'Not on our list', 'tone' => 'bad'])</td><td>—</td>
                        <td class="mono">{{ $value !== '' ? $value : '(empty)' }}</td><td class="wrap">Not one of the site's cookies. Set by another script, an old version of the site or a browser extension.</td><td>—</td></tr>
                @endforeach
            </tbody>
        </table></div>
        <div class="panel-body help" style="border-top:1px solid var(--line)">Sign-in and form tokens are cut short here: enough to tell them apart, never enough to use. The cache refreshes as the visitor browses (at most every 10 minutes).</div>
    </div>

    <div class="grid-2" style="align-items:start">
        <div class="panel"><div class="panel-head"><h2>Their Other Choices</h2><span class="muted">{{ $others->count() }}</span></div><div class="table-wrap"><table data-page="10">
            <thead><tr><th>When</th><th>Choice</th><th>Policy</th></tr></thead>
            <tbody>@forelse ($others as $o)
                <tr class="click" data-href="{{ route('lando.consents.show', $o) }}"><td class="nowrap"><a href="{{ route('lando.consents.show', $o) }}">{{ $o->created_at->format('n/j/Y g:i A') }}</a></td>
                    <td>@include('backend.partials.pill', ['text' => $o->label(), 'tone' => ['all' => 'ok', 'necessary' => 'warn', 'custom' => 'info'][$o->choice] ?? ''])</td><td class="mono">{{ $o->policy_version }}</td></tr>
            @empty <tr><td colspan="3" class="empty">This is the only choice from this browser{{ $k->customer ? ' or customer' : '' }}.</td></tr> @endforelse</tbody>
        </table></div></div>
        <div class="panel"><div class="panel-head"><h2>Pages Visited</h2><span class="muted">from this browser</span></div><div class="table-wrap"><table data-page="10">
            <thead><tr><th>When</th><th>Page</th><th>IP</th></tr></thead>
            <tbody>@forelse ($visits as $v)
                <tr><td class="nowrap">{{ $v->created_at->format('n/j/Y g:i A') }}</td><td class="mono">/{{ ltrim($v->path === '/' ? '' : $v->path, '/') }}</td><td class="mono">{{ $v->ip }}</td></tr>
            @empty <tr><td colspan="3" class="empty">No visits recorded from this browser.</td></tr> @endforelse</tbody>
        </table></div></div>
    </div>
@endsection
