@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Cookie Consents', 'sub' => 'Every choice made in the website\'s cookie banner, from signed-in customers, employees and anonymous visitors. The latest choice for a person or browser is the one in force (policy version '.\App\Models\CookieConsent::POLICY_VERSION.').'])

    <div class="grid-4 stat-row">
        <div class="stat"><span>Choices</span><strong>{{ number_format($all) }}</strong><small>last 30 days</small></div>
        <div class="stat"><span>Accepted All</span><strong>{{ $all ? round(100 * ($totals['all'] ?? 0) / $all) : 0 }}%</strong><small>{{ number_format($totals['all'] ?? 0) }}</small></div>
        <div class="stat"><span>Necessary Only</span><strong>{{ $all ? round(100 * ($totals['necessary'] ?? 0) / $all) : 0 }}%</strong><small>{{ number_format($totals['necessary'] ?? 0) }}</small></div>
        <div class="stat"><span>Allow Analytics / Marketing</span><strong>{{ number_format((int) $optIn->analytics) }} / {{ number_format((int) $optIn->marketing) }}</strong><small>{{ number_format($totals['custom'] ?? 0) }} chose their own</small></div>
    </div>

    <div class="panel">
        <form method="get" class="panel-head" style="flex-wrap:wrap;gap:10px">
            <h2>Choices</h2>
            <nav class="actions" aria-label="Who">
                @foreach (['' => 'Everyone', 'customer' => 'Customers', 'employee' => 'Employees', 'anonymous' => 'Anonymous'] as $v => $l)
                    <a href="{{ route('lando.consents', array_filter(['who' => $v, 'choice' => $f['choice'] ?? null, 'q' => $f['q'] ?? null])) }}" @class(['btn', 'sm', 'ghost' => ($f['who'] ?? '') !== $v])>{{ $l }}</a>
                @endforeach
            </nav>
            <nav class="actions" aria-label="Choice">
                @foreach (['' => 'Any choice', 'all' => 'Accepted all', 'necessary' => 'Necessary only', 'custom' => 'Chose'] as $v => $l)
                    <a href="{{ route('lando.consents', array_filter(['who' => $f['who'] ?? null, 'choice' => $v, 'q' => $f['q'] ?? null])) }}" @class(['btn', 'sm', 'ghost' => ($f['choice'] ?? '') !== $v])>{{ $l }}</a>
                @endforeach
            </nav>
            @foreach (['who', 'choice'] as $k)@if (! empty($f[$k]))<input type="hidden" name="{{ $k }}" value="{{ $f[$k] }}">@endif @endforeach
            <input name="q" value="{{ $f['q'] ?? '' }}" placeholder="Account, name, IP or visitor id" aria-label="Search" style="margin-left:auto;min-width:240px"><button class="btn sm">Search</button>
        </form>
        <div class="table-wrap"><table class="table">
            <thead><tr><th>When</th><th>Who</th><th>Choice</th><th>Analytics</th><th>Marketing</th><th>Page</th><th>IP</th><th>Visitor</th></tr></thead>
            <tbody>@forelse ($consents as $k)
                <tr><td class="nowrap">{{ $k->created_at->format('n/j/Y g:i A') }}</td>
                    <td>@if ($k->customer)@include('backend.partials.account-link', ['c' => $k->customer])<br><span class="help">{{ $k->customer->name }}</span>@elseif ($k->user){{ $k->user->name }}<br><span class="help">Employee</span>@else<span class="muted">Anonymous</span>@endif</td>
                    <td>@include('backend.partials.pill', ['text' => $k->label(), 'tone' => ['all' => 'ok', 'necessary' => 'warn', 'custom' => 'info'][$k->choice] ?? ''])</td>
                    <td>{{ $k->analytics ? 'Yes' : 'No' }}</td><td>{{ $k->marketing ? 'Yes' : 'No' }}</td>
                    <td class="mono">{{ $k->page ?? '—' }}</td><td class="mono">{{ $k->ip }}</td><td class="mono help">{{ \Illuminate\Support\Str::limit($k->visitor, 10, '…') }}</td></tr>
            @empty <tr><td colspan="8" class="empty">No choices yet.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $consents])
    </div>
@endsection
