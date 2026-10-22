@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Cookie Consents', 'sub' => 'Every choice made in the website\'s cookie banner, from signed-in customers, employees and anonymous visitors. The latest choice for a person or browser is the one in force (policy version '.\App\Models\CookieConsent::POLICY_VERSION.').'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Choices</span><strong>{{ number_format($all) }}</strong><small>last 30 days</small></div>
        <div class="stat"><span>Accepted All</span><strong>{{ $all ? round(100 * ($totals['all'] ?? 0) / $all) : 0 }}%</strong><small>{{ number_format($totals['all'] ?? 0) }}</small></div>
        <div class="stat"><span>Necessary Only</span><strong>{{ $all ? round(100 * ($totals['necessary'] ?? 0) / $all) : 0 }}%</strong><small>{{ number_format($totals['necessary'] ?? 0) }}</small></div>
        <div class="stat"><span>Allow Analytics / Marketing</span><strong>{{ number_format((int) $optIn->analytics) }} / {{ number_format((int) $optIn->marketing) }}</strong><small>{{ number_format($totals['custom'] ?? 0) }} chose their own</small></div>
    </div>

    {{-- Each cookie the website sets, and how many of the latest choices allow it --}}
    <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>Cookies</h2><span class="muted">allowed in the last 30 days · click one for its page</span></div><div class="table-wrap"><table class="table">
        <thead><tr><th>Cookie</th><th>Category</th><th>What it's for</th><th>Lasts</th><th class="num">Said Yes</th></tr></thead>
        <tbody>@foreach (\App\Models\CookieConsent::COOKIES as $name => [$cat, $purpose, $lasts])
            @php $yes = $cat === 'necessary' ? $all : (int) $optIn->$cat; @endphp
            <tr class="click" data-href="{{ route('lando.consents.cookie', $name) }}"><td class="mono"><a href="{{ route('lando.consents.cookie', $name) }}"><b>{{ $name }}</b></a></td><td>@include('backend.partials.pill', ['text' => ucfirst($cat), 'tone' => ['necessary' => '', 'analytics' => 'info', 'marketing' => 'warn'][$cat]])</td>
                <td class="wrap">{{ $purpose }}</td><td>{{ $lasts }}</td><td class="num">{{ number_format($yes) }} <span class="help">{{ $all ? round(100 * $yes / $all) : 0 }}%</span></td></tr>
        @endforeach</tbody>
    </table></div></div>

    <div class="panel">
        <form method="get" action="{{ route('lando.consents') }}">
            <div class="panel-head" style="flex-wrap:wrap;gap:10px" id="consent-chips">
                <h2>Choices</h2>
                <nav class="chips small" aria-label="Who">
                    @foreach (['' => 'Everyone', 'customer' => 'Customers', 'employee' => 'Employees', 'anonymous' => 'Anonymous'] as $v => $l)
                        <a class="chip-link {{ ($f['who'] ?? '') === $v ? 'on' : '' }}" href="{{ route('lando.consents', array_filter(['who' => $v, 'choice' => $f['choice'] ?? null, 'q' => $f['q'] ?? null])) }}">{{ $l }}</a>
                    @endforeach
                </nav>
                <nav class="chips small" aria-label="Choice">
                    @foreach (['' => 'Any choice', 'all' => 'Accepted all', 'necessary' => 'Necessary only', 'custom' => 'Chose'] as $v => $l)
                        <a class="chip-link {{ ($f['choice'] ?? '') === $v ? 'on' : '' }}" href="{{ route('lando.consents', array_filter(['who' => $f['who'] ?? null, 'choice' => $v, 'q' => $f['q'] ?? null])) }}">{{ $l }}</a>
                    @endforeach
                </nav>
                @foreach (['who', 'choice'] as $k)@if (! empty($f[$k]))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif @endforeach
            </div>
            <div class="panel-body search-row">
                <label class="search-field"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><circle cx="11" cy="11" r="7" fill="none" stroke="currentColor" stroke-width="2"/><path d="M20 20l-3.5-3.5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                    <input type="search" name="q" value="{{ $f['q'] ?? '' }}" placeholder="Search by account, name, IP or visitor id" aria-label="Search choices" autocomplete="off" data-auto-search="#consent-results,#consent-chips"></label>
            </div>
        </form>
        <div id="consent-results">
            <div class="table-wrap"><table class="table">
                <thead><tr><th>When</th><th>Who</th><th>Choice</th><th>Cookies Allowed</th><th>Page</th><th>IP</th><th>Visitor</th></tr></thead>
                <tbody>@forelse ($consents as $k)
                    <tr class="click" data-href="{{ route('lando.consents.show', $k) }}"><td class="nowrap"><a href="{{ route('lando.consents.show', $k) }}">{{ $k->created_at->format('n/j/Y g:i A') }}</a></td>
                        <td>@if ($k->customer)@include('backend.partials.account-link', ['c' => $k->customer])<br><span class="help">{{ $k->customer->name }}</span>@elseif ($k->user){{ $k->user->name }}<br><span class="help">Employee</span>@else<span class="muted">Anonymous</span>@endif</td>
                        <td>@include('backend.partials.pill', ['text' => $k->label(), 'tone' => ['all' => 'ok', 'necessary' => 'warn', 'custom' => 'info'][$k->choice] ?? ''])</td>
                        <td class="wrap" style="min-width:220px"><span class="cookie-list">@foreach ($k->cookies() as $ck)<span class="mono cookie-{{ \App\Models\CookieConsent::COOKIES[$ck][0] }}">{{ $ck }}</span>@endforeach</span></td>
                        <td class="mono">{{ $k->page ?? '—' }}</td><td class="mono">{{ $k->ip }}</td><td class="mono help">{{ \Illuminate\Support\Str::limit($k->visitor, 10, '…') }}</td></tr>
                @empty <tr><td colspan="7" class="empty">No choices match.</td></tr> @endforelse</tbody>
            </table></div>
            @include('backend.partials.pager', ['p' => $consents])
        </div>
    </div>
@endsection
