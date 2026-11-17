@extends('backend.layouts.app')

@section('crumb', 'My Access & Sign-ins')

{{-- What you can open and do, where you're signed in, and your sign-in history --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Access & Sign-ins', 'sub' => 'Role: '.e($u->role?->name ?? 'none').' · Last sign-in: '.($u->last_login_at?->format('n/j/Y g:i A') ?? 'never')])
    @if ($u->access_ends_on)
        <div class="banner-note">Your access ends on {{ $u->access_ends_on->format('n/j/Y') }}@if ($u->access_ends_on->isFuture()) ({{ $u->access_ends_on->diffForHumans() }})@endif.</div>
    @endif

    <div class="panel"><div class="panel-head"><h2>Signed In On</h2>
            @if ($devices->where('current', false)->isNotEmpty() && ! session()->has('impersonator_id'))
                <form method="post" action="{{ route('admin.account.sign-out-others') }}" class="inline" data-confirm="Sign out your other devices?|Every other browser signed in as you will have to sign in again.|Sign out">@csrf<button class="btn sm ghost">Sign Out Other Devices</button></form>
            @endif
        </div>
        <div class="table-wrap"><table>
            <thead><tr><th>Browser</th><th>IP Address</th><th>Last Active</th></tr></thead>
            <tbody>@forelse ($devices as $d)
                <tr><td><b>{{ $d->browser }}</b>@if ($d->current) <span class="pill ok">This browser</span>@endif</td><td class="mono">{{ $d->ip ?? '—' }}</td><td>{{ $d->last->format('n/j/Y g:i A') }} <span class="muted">({{ $d->last->diffForHumans() }})</span></td></tr>
            @empty <tr><td colspan="3" class="empty">No sign-ins recorded.</td></tr> @endforelse</tbody>
        </table></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>What You Can Open</h2><span class="help">ask an administrator to change it</span></div><div class="table-wrap"><table>
        <thead><tr><th>App or Right</th><th>Access</th><th>Why</th></tr></thead>
        <tbody>
            @foreach ($apps as $key => $a)
                @php $has = $u->hasPerm($key); $ch = $changes[$key] ?? null; @endphp
                <tr><td><b>{{ $a['name'] }}</b></td>
                    <td>@include('backend.partials.pill', $has ? ['text' => 'Can open', 'tone' => 'ok'] : ['text' => 'No access', 'tone' => ''])</td>
                    <td class="help">{{ $ch === 'added' ? 'Added just for you' : ($ch === 'removed' ? 'Taken away just for you (your role has it)' : ($has ? 'From your role' : '—')) }}</td></tr>
            @endforeach
            @foreach ($rights as $key => $label)
                @php $has = $u->hasPerm($key); $ch = $changes[$key] ?? null; @endphp
                <tr><td><b>{{ $label }}</b></td>
                    <td>@include('backend.partials.pill', $has ? ['text' => 'Allowed', 'tone' => 'ok'] : ['text' => 'Not allowed', 'tone' => ''])</td>
                    <td class="help">{{ $ch === 'added' ? 'Added just for you' : ($ch === 'removed' ? 'Taken away just for you (your role has it)' : ($has ? 'From your role' : '—')) }}</td></tr>
            @endforeach
        </tbody>
    </table></div></div>

    <div class="panel"><div class="panel-head"><h2>Sign-in History</h2><span class="muted">{{ $logins->total() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>ID#</th><th>Date</th><th>What Happened</th></tr></thead>
        <tbody>@forelse ($logins as $l)
            <tr class="click" data-href="{{ route('admin.account.activity.show', $l) }}"><td class="nowrap"><a href="{{ route('admin.account.activity.show', $l) }}"><b>#{{ $l->id }}</b></a></td><td class="nowrap">{{ $l->created_at->format('n/j/Y g:i A') }}</td><td class="wrap">{{ $l->summary }}@if ($l->ip) <span class="muted">· {{ $l->ip }}</span>@endif</td></tr>
        @empty <tr><td colspan="3" class="empty">No sign-ins recorded.</td></tr> @endforelse</tbody>
    </table></div>
    @if ($logins->hasPages())<div class="panel-body">{{ $logins->links() }}</div>@endif
    </div>
@endsection
