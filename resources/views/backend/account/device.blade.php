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

    <div class="grid-2">
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

        <div class="panel"><div class="panel-head"><h2>Secure Your Account</h2></div><div class="panel-body">
            @if ($loggedInAs)
                <p class="muted">You're using Log in as, so you can't change this person's sign-ins. Go back to yourself first.</p>
            @else
                @if ($d?->current)
                    <p class="help">Sign out of this browser. To end every other sign-in, use Sign Out Other Devices on My Profile.</p>
                    <form method="post" action="{{ route('admin.account.device.sign-out', $s) }}">@csrf<button class="btn">Sign Out This Browser</button></form>
                @elseif ($d)
                    <p class="help"><b>{{ $s->impersonator ? 'End Log in as' : 'Sign out' }}</b>: ends this sign-in now{{ $s->impersonator ? '; '.$s->impersonator->name.' goes back to their own account' : '' }}. It can sign in again.</p>
                    <form method="post" action="{{ route('admin.account.device.sign-out', $s) }}" data-confirm="{{ $s->impersonator ? 'End '.$s->impersonator->name.'\'s Log in as?' : 'Sign out '.$s->browser().'?' }}|This sign-in ends now.|{{ $s->impersonator ? 'End it' : 'Sign out' }}">@csrf
                        <button class="btn">{{ $s->impersonator ? 'End Log in as' : 'Sign Out This Device' }}</button></form>
                @else
                    <p class="help">This sign-in has ended, so there's nothing to sign out.</p>
                @endif

                @if ($blocked)
                    <p class="help" style="margin-top:16px">This device is already blocked. Unblock it on My Profile.</p>
                @elseif ($sameDevice || $d?->current)
                    <p class="help" style="margin-top:16px">This is from the same IP address and browser you're using, so it can't be blocked (that would block you too).</p>
                @else
                    <p class="help" style="margin-top:16px"><b>Block</b>: {{ $d ? 'signs it out and keeps' : 'keeps' }} this device (this IP address with this browser) from signing in to your account again. You can unblock it on My Profile.</p>
                    <form method="post" action="{{ route('admin.account.device.block', $s) }}" data-confirm="Block this device?|It can't sign in to your account until you unblock it.|Block">@csrf<button class="btn">Block This Device</button></form>

                    <p class="help" style="margin-top:16px"><b>Report</b>: you don't recognize it. Blocks it{{ $d ? ', signs it out' : '' }} and tells the administrators so they can look into it. Change your password too.</p>
                    <form method="post" action="{{ route('admin.account.device.report', $s) }}" data-confirm="Report this sign-in?|The device is blocked and the administrators are told.|Report">@csrf
                        <div class="field"><label for="reason">What happened (optional)</label><input id="reason" name="reason" maxlength="500" placeholder="e.g. I wasn't signed in on this browser"></div>
                        <button class="btn danger" style="margin-top:8px">Report to Administrators</button></form>
                @endif
            @endif
        </div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Other Sign-ins From This IP Address</h2><span class="muted">latest 5</span></div><div class="table-wrap"><table data-page="0">
        <thead><tr><th>ID#</th><th>Signed In</th><th>Browser</th><th>Status</th></tr></thead>
        <tbody>@forelse ($fromHere as $x)
            <tr class="click" data-href="{{ route('admin.account.device', $x) }}"><td class="nowrap"><a href="{{ route('admin.account.device', $x) }}"><b>#{{ $x->id }}</b></a></td><td class="nowrap">{{ $x->signed_in_at->format('n/j/Y g:i A') }}</td><td>{{ $x->browser() }}</td><td class="muted">{{ $x->ended_reason ?? ($x->ended_at ? 'Ended' : 'Signed in or expired') }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No other sign-ins from this IP address.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
