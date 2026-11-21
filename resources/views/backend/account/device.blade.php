@extends('backend.layouts.app')

@section('crumb', 'Sign-in #'.$s->id)

{{-- One sign-in from Signed In On: what it is, and while it's signed in, buttons to end it, block it or report it --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Sign-in #'.$s->id.' · '.$s->browser(), 'sub' => e($s->ip ?? 'Unknown IP').' · signed in '.$s->signed_in_at->format('n/j/Y g:i A')
        .' · '.($d ? ($d->current ? 'this browser' : 'signed in now') : e($s->ended_reason ?? 'expired')),
        'actions' => '<a class="btn ghost" href="'.route('admin.account.profile').'#signed-in">Back to My Profile</a>'])

    @if ($s->impersonator && $d)
        <div class="banner-note"><b>{{ $s->impersonator->name }}</b> is using Log in as to see the admin as you. Everything they do is recorded under both names. End it below if it shouldn't be happening.</div>
    @elseif ($d?->current)
        <div class="banner-note">This is the browser you're using now.</div>
    @endif
    @if ($blocked)
        <div class="alert bad" role="alert">This device is blocked{{ $blocked->reported_at ? ' and was reported' : '' }}: it can't sign in to your account.</div>
    @endif

    {{-- Secure Your Account: first, full width and hard to miss --}}
    <section class="panel secure-panel" id="secure" aria-labelledby="secure-title">
        <div class="secure-head">
            <span class="secure-ico" aria-hidden="true"><svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l8 3v6c0 5-3.4 9.3-8 11-4.6-1.7-8-6-8-11V5z"/><path d="M12 8v4M12 16h.01"/></svg></span>
            <h2 id="secure-title">Secure Your Account</h2>
        </div>
        <div class="secure-actions">
            @if ($loggedInAs)
                <p class="secure-opt">You're using Log in as, so you can't change this person's sign-ins.</p>
            @else
                <div class="secure-opt">
                    <h3>{{ $s->impersonator && $d ? 'End Log in as' : 'Sign Out' }}</h3>
                    @if ($d?->current)
                        <p>This is the browser you're using</p>
                        <form method="post" action="{{ route('admin.account.device.sign-out', $s) }}">@csrf<button class="btn">Sign Out</button></form>
                    @elseif ($d)
                        <p>{{ $s->impersonator ? $s->impersonator->name.' is using Log in as' : 'I don\'t recognize this device' }}</p>
                        <form method="post" action="{{ route('admin.account.device.sign-out', $s) }}" data-confirm="{{ $s->impersonator ? 'End '.$s->impersonator->name.'\'s Log in as?' : 'Sign out '.$s->browser().'?' }}|This sign-in ends now.|{{ $s->impersonator ? 'End it' : 'Sign out' }}">@csrf
                            <button class="btn">{{ $s->impersonator ? 'End Log in as' : 'Sign Out' }}</button></form>
                    @else
                        <p>This sign-in has already ended</p>
                    @endif
                </div>
                <div class="secure-opt">
                    <h3>Block</h3>
                    <p>I don't recognize this IP Address</p>
                    <div class="btn disabled" aria-disabled="true">Block</div>
                </div>
            @endif
        </div>
    </section>

    <div class="panel"><div class="panel-head"><h2>Sign-in Details</h2><span class="mono muted">#{{ $s->id }}</span></div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>{{ $u->name }} · {{ $u->email }}</dd>
            <dt>Who</dt><dd>@if ($s->impersonator){{ $s->impersonator->name }} <span class="pill warn">using Log in as</span>@else You @endif</dd>
            <dt>How</dt><dd>{{ \App\Models\UserSignIn::HOW[$s->how] ?? $s->how }}</dd>
            <dt>Browser</dt><dd>{{ $s->browser() }}</dd>
            <dt>IP Address</dt><dd class="mono">{{ $s->ip ?? '—' }}</dd>
            <dt>Signed In</dt><dd>{{ $s->signed_in_at->format('n/j/Y g:i A') }} <span class="muted">({{ $s->signed_in_at->diffForHumans() }})</span></dd>
            @if ($d)
                <dt>Status</dt><dd><span class="pill {{ $d->current ? 'ok' : 'info' }}">{{ $d->current ? 'This browser' : 'Signed in now' }}</span></dd>
                <dt>Last Active</dt><dd>{{ $d->last->format('n/j/Y g:i A') }} <span class="muted">({{ $d->last->diffForHumans() }})</span></dd>
            @else
                <dt>Status</dt><dd>{{ $s->ended_reason ?? 'Expired (no longer signed in)' }}</dd>
                @if ($s->ended_at)<dt>Ended</dt><dd>{{ $s->ended_at->format('n/j/Y g:i A') }} <span class="muted">({{ $s->ended_at->diffForHumans() }})</span></dd>@endif
            @endif
            <dt>Browser Details</dt><dd class="mono wrap" style="font-size:.8rem">{{ $s->user_agent ?: '—' }}</dd>
        </dl></div></div>

    <div class="panel"><div class="panel-head"><h2>Other Sign-ins From This IP Address</h2><span class="muted">latest 5</span></div><div class="table-wrap"><table data-page="0">
        <thead><tr><th>ID#</th><th>Signed In</th><th>Browser</th><th>Status</th></tr></thead>
        <tbody>@forelse ($fromHere as $x)
            <tr class="click" data-href="{{ route('admin.account.device', $x) }}"><td class="nowrap"><a href="{{ route('admin.account.device', $x) }}"><b>#{{ $x->id }}</b></a></td><td class="nowrap">{{ $x->signed_in_at->format('n/j/Y g:i A') }}</td><td>{{ $x->browser() }}</td><td class="muted">{{ $x->ended_reason ?? ($x->ended_at ? 'Ended' : 'Signed in or expired') }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No other sign-ins from this IP address.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
