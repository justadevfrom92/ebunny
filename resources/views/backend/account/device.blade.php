@extends('backend.layouts.app')

@section('crumb', $d->browser)

{{-- One place you're signed in: what it is, and buttons to end it, block it or report it to the administrators --}}
@section('content')
    @include('backend.partials.page-head', ['title' => $d->browser, 'sub' => e($d->ip ?? 'Unknown IP').' · last active '.$d->last->diffForHumans(),
        'actions' => '<a class="btn ghost" href="'.route('admin.account.profile').'#signed-in">Back to My Profile</a>'])

    @if ($d->logged_in_as_by)
        <div class="banner-note"><b>{{ $d->logged_in_as_by->name }}</b> is using Log in as to see the admin as you. Everything they do is recorded under both names. End it below if it shouldn't be happening.</div>
    @elseif ($d->current)
        <div class="banner-note">This is the browser you're using now.</div>
    @endif
    @if ($blocked)
        <div class="alert bad" role="alert">This device is blocked{{ $blocked->reported_at ? ' and was reported' : '' }}: it can't sign in to your account.</div>
    @endif

    <div class="grid-2">
        <div class="panel"><div class="panel-head"><h2>Sign-in Details</h2></div><div class="panel-body"><dl class="kv">
            <dt>Account</dt><dd>{{ $u->name }} · {{ $u->email }}</dd>
            <dt>Who</dt><dd>@if ($d->logged_in_as_by){{ $d->logged_in_as_by->name }} <span class="pill warn">using Log in as</span>@else You @endif</dd>
            <dt>Browser</dt><dd>{{ $d->browser }}</dd>
            <dt>IP Address</dt><dd class="mono">{{ $d->ip ?? '—' }}</dd>
            <dt>Signed In</dt><dd>{{ $d->since?->format('n/j/Y g:i A') ?? 'Not recorded' }}@if ($d->since) <span class="muted">({{ $d->since->diffForHumans() }})</span>@endif</dd>
            <dt>Last Active</dt><dd>{{ $d->last->format('n/j/Y g:i A') }} <span class="muted">({{ $d->last->diffForHumans() }})</span></dd>
            <dt>Browser Details</dt><dd class="mono wrap" style="font-size:.8rem">{{ $d->ua ?: '—' }}</dd>
        </dl></div></div>

        <div class="panel"><div class="panel-head"><h2>Secure Your Account</h2></div><div class="panel-body">
            @if ($loggedInAs)
                <p class="muted">You're using Log in as, so you can't change this person's sign-ins. Go back to yourself first.</p>
            @elseif ($d->current)
                <p class="help">Sign out of this browser. To end every other sign-in, use Sign Out Other Devices on My Profile.</p>
                <form method="post" action="{{ route('admin.account.device.sign-out', $d->key) }}">@csrf<button class="btn">Sign Out This Browser</button></form>
            @else
                <p class="help"><b>{{ $d->logged_in_as_by ? 'End Log in as' : 'Sign out' }}</b>: ends this sign-in now{{ $d->logged_in_as_by ? '; '.$d->logged_in_as_by->name.' goes back to their own account' : '' }}. It can sign in again.</p>
                <form method="post" action="{{ route('admin.account.device.sign-out', $d->key) }}" data-confirm="{{ $d->logged_in_as_by ? 'End '.$d->logged_in_as_by->name.'\'s Log in as?' : 'Sign out '.$d->browser.'?' }}|This sign-in ends now.|{{ $d->logged_in_as_by ? 'End it' : 'Sign out' }}">@csrf
                    <button class="btn">{{ $d->logged_in_as_by ? 'End Log in as' : 'Sign Out This Device' }}</button></form>

                @if ($sameDevice)
                    <p class="help" style="margin-top:16px">This sign-in is from the same IP address and browser you're using, so it can't be blocked (that would block you too). Sign it out instead.</p>
                @else
                    <p class="help" style="margin-top:16px"><b>Block</b>: signs it out and keeps this device (this IP address with this browser) from signing in to your account again. You can unblock it on My Profile.</p>
                    <form method="post" action="{{ route('admin.account.device.block', $d->key) }}" data-confirm="Block this device?|It's signed out now and can't sign in to your account until you unblock it.|Block">@csrf<button class="btn">Block This Device</button></form>

                    <p class="help" style="margin-top:16px"><b>Report</b>: you don't recognize it. Blocks it, signs it out and tells the administrators so they can look into it. Change your password too.</p>
                    <form method="post" action="{{ route('admin.account.device.report', $d->key) }}" data-confirm="Report this sign-in?|It's signed out and blocked, and the administrators are told.|Report">@csrf
                        <div class="field"><label for="reason">What happened (optional)</label><input id="reason" name="reason" maxlength="500" placeholder="e.g. I wasn't signed in on this browser"></div>
                        <button class="btn danger" style="margin-top:8px">Report to Administrators</button></form>
                @endif
            @endif
        </div></div>
    </div>

    <div class="panel"><div class="panel-head"><h2>Sign-ins From This IP Address</h2><span class="muted">latest 5</span></div><div class="table-wrap"><table data-page="0">
        <thead><tr><th>ID#</th><th>Date</th><th>What Happened</th></tr></thead>
        <tbody>@forelse ($logins as $l)
            <tr class="click" data-href="{{ route('admin.account.activity.show', $l) }}"><td class="nowrap"><a href="{{ route('admin.account.activity.show', $l) }}"><b>#{{ $l->id }}</b></a></td><td class="nowrap">{{ $l->created_at->format('n/j/Y g:i A') }}</td><td class="wrap">{{ $l->summary }}</td></tr>
        @empty <tr><td colspan="3" class="empty">No sign-ins recorded from this IP address.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
