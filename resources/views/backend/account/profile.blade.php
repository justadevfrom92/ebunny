@extends('backend.layouts.app')

@section('crumb', 'My Profile')

{{-- The Account dashboard: your details (you can change your name and phone ext; the rest is set in Sheriff → Users),
     what you can open, where you're signed in and everything recorded under your name. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'My Profile', 'sub' => e($u->title ?? 'Employee').' · '.e($u->company ? $u->company.' (3rd party)' : ($u->department ?? 'No department'))
        .' · Role: '.e($u->role?->name ?? 'none').' · Last sign-in: '.($u->last_login_at?->format('n/j/Y g:i A') ?? 'never')])
    @if ($u->access_ends_on)
        <div class="banner-note">Your access ends on {{ $u->access_ends_on->format('n/j/Y') }}@if ($u->access_ends_on->isFuture()) ({{ $u->access_ends_on->diffForHumans() }})@endif.</div>
    @endif

    <div class="grid-2">
        <form method="post" action="{{ route('admin.account.profile.save') }}" class="panel">@csrf @method('put')
            <div class="panel-head"><h2>Your Details</h2><span class="help">you can change these</span></div>
            <div class="panel-body">
                <div class="field"><label for="name">Name</label><input id="name" name="name" value="{{ old('name', $u->name) }}" required maxlength="120" autocomplete="name">
                    @error('name')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="phone_ext">Phone Ext.</label><input id="phone_ext" name="phone_ext" value="{{ old('phone_ext', $u->phone_ext) }}" maxlength="20" inputmode="numeric">
                    @error('phone_ext')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <button class="btn cyan" style="margin-top:8px">Save Profile</button>
            </div>
        </form>

        <div class="panel"><div class="panel-head"><h2>Work Details</h2><span class="help">set by an administrator in Sheriff</span></div><div class="panel-body"><dl class="kv">
            <dt>Email</dt><dd>{{ $u->email }}</dd>
            <dt>Role</dt><dd>{{ $u->role?->name ?? '—' }}</dd>
            <dt>Title</dt><dd>{{ $u->title ?? '—' }}</dd>
            @if ($u->company)
                <dt>Company</dt><dd>{{ $u->company }} <span class="pill info">3rd party</span></dd>
            @else
                <dt>Department</dt><dd>{{ $u->department ?? '—' }}</dd>
                <dt>Employee #</dt><dd class="mono">{{ $u->employee_id ?? '—' }}</dd>
            @endif
            <dt>{{ $u->company ? 'Our Contact' : 'Manager' }}</dt><dd>{{ $u->manager?->name ?? '—' }}</dd>
            <dt>Started</dt><dd>{{ $u->hired_on?->format('n/j/Y') ?? '—' }}@if ($u->tenure()) <span class="muted">({{ $u->tenure() }})</span>@endif</dd>
            <dt>Location</dt><dd>{{ $u->location ?? '—' }}</dd>
        </dl></div></div>
    </div>

    {{-- My Activity: everything recorded under your name across the apps --}}
    <div class="panel" id="activity"><div class="panel-head"><h2>My Activity</h2><span class="muted">{{ $items->total() }}</span></div>
        <form method="get" action="{{ route('admin.account.profile') }}#activity" class="panel-body">
            <div class="field"><label for="act-q">Search</label><input id="act-q" type="search" name="q" value="{{ $q }}" placeholder="What you did, e.g. note, refund, ticket"></div>
            <div class="field"><label for="act-group">Type</label><select id="act-group" name="group" onchange="this.form.submit()">
                <option value="">-- All Types --</option>
                @foreach ($groups as $g)<option value="{{ $g }}" @selected($group === $g)>{{ $g }}</option>@endforeach
            </select></div>
            <button class="btn" style="margin-top:8px">Search</button>@if ($q !== '' || $group !== '') <a class="btn ghost" style="margin-top:8px" href="{{ route('admin.account.profile') }}#activity">Clear</a>@endif
        </form>
        <div class="table-wrap"><table>
            <thead><tr><th>ID#</th><th>Date</th><th>Type</th><th>What You Did</th><th>Account</th></tr></thead>
            <tbody>@forelse ($items as $h)
                <tr class="click" data-href="{{ route('admin.account.activity.show', $h) }}"><td class="nowrap"><a href="{{ route('admin.account.activity.show', $h) }}"><b>#{{ $h->id }}</b></a></td>
                    <td class="nowrap">{{ $h->created_at->format('n/j/Y g:i A') }}</td><td class="nowrap">{{ $h->group }}</td><td class="wrap">{{ $h->summary }}</td>
                    <td class="nowrap">{{ $h->customer?->account ?? '—' }}</td></tr>
            @empty <tr><td colspan="5" class="empty">No activity recorded{{ $q !== '' || $group !== '' ? ' for that search' : '' }}.</td></tr> @endforelse</tbody>
        </table></div>
        @include('backend.partials.pager', ['p' => $items])
    </div>

    {{-- My Access: where you're signed in (each opens its own page to sign it out, block or report it) and what you can open --}}
    <div class="panel" id="signed-in"><div class="panel-head"><h2>Signed In On</h2>
            @if ($devices->where('current', false)->isNotEmpty() && ! session()->has('impersonator_id'))
                <form method="post" action="{{ route('admin.account.sign-out-others') }}" class="inline" data-confirm="Sign out your other devices?|Every other browser signed in as you will have to sign in again.|Sign out">@csrf<button class="btn sm ghost">Sign Out Other Devices</button></form>
            @endif
        </div>
        <div class="table-wrap"><table data-page="0">
            <thead><tr><th>Browser</th><th>IP Address</th><th>Signed In</th><th>Last Active</th></tr></thead>
            <tbody>@forelse ($devices as $d)
                <tr class="click" data-href="{{ route('admin.account.device', $d->key) }}"><td><a href="{{ route('admin.account.device', $d->key) }}"><b>{{ $d->browser }}</b></a>
                        @if ($d->current) <span class="pill ok">This browser</span>@endif
                        @if ($d->logged_in_as_by) <span class="pill warn">{{ $d->logged_in_as_by->name }} using Log in as</span>@endif</td>
                    <td class="mono">{{ $d->ip ?? '—' }}</td><td class="nowrap">{{ $d->since?->format('n/j/Y g:i A') ?? '—' }}</td>
                    <td class="nowrap">{{ $d->last->format('n/j/Y g:i A') }} <span class="muted">({{ $d->last->diffForHumans() }})</span></td></tr>
            @empty <tr><td colspan="4" class="empty">No sign-ins recorded.</td></tr> @endforelse</tbody>
        </table></div>
        @if ($devices->count() > 1)<div class="panel-body help">More than one sign-in? Open any you don't recognize to sign it out, block it or report it.</div>@endif
    </div>

    @if ($blocks->isNotEmpty())
        <div class="panel" id="blocked"><div class="panel-head"><h2>Blocked Devices</h2><span class="help">can't sign in to your account</span></div><div class="table-wrap"><table data-page="0">
            <thead><tr><th>ID#</th><th>Date</th><th>Device</th><th>Reason</th><th><span class="sr-only">Unblock</span></th></tr></thead>
            <tbody>@foreach ($blocks as $b)
                <tr><td class="nowrap"><b>#{{ $b->id }}</b></td><td class="nowrap">{{ $b->created_at->format('n/j/Y g:i A') }}</td>
                    <td><b>{{ \App\Http\Controllers\Backend\AccountController::browser((string) $b->user_agent) }}</b> <span class="mono muted">{{ $b->ip }}</span></td>
                    <td class="wrap">{{ $b->reason ?? 'Blocked by you' }}@if ($b->reported_at) <span class="pill info">Reported</span>@endif</td>
                    <td class="actions">@unless (session()->has('impersonator_id'))<form method="post" action="{{ route('admin.account.unblock', $b) }}" data-confirm="Unblock this device?|It will be able to sign in to your account again.|Unblock">@csrf @method('delete')<button class="btn sm ghost">Unblock</button></form>@endunless</td></tr>
            @endforeach</tbody>
        </table></div></div>
    @endif

    <div class="panel" id="access"><div class="panel-head"><h2>What You Can Open</h2><span class="help">ask an administrator to change it</span></div><div class="table-wrap"><table data-page="0">
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

    <div class="panel" id="saved-reports"><div class="panel-head"><h2>My Saved Reports</h2><span class="muted">{{ $saved->count() }}</span></div><div class="table-wrap"><table>
        <thead><tr><th>ID#</th><th>Date</th><th>Name</th><th>Shared</th></tr></thead>
        <tbody>@forelse ($saved as $s)
            <tr><td class="nowrap"><b>#{{ $s->id }}</b></td><td class="nowrap">{{ $s->created_at->format('n/j/Y') }}</td><td class="wrap"><b>{{ $s->name }}</b><div class="help">{{ $s->settingsLine() }}</div></td><td>{{ $s->shared ? 'Yes' : 'No' }}</td></tr>
        @empty <tr><td colspan="4" class="empty">No saved reports. Save one from a report in Walker.</td></tr> @endforelse</tbody>
    </table></div></div>
@endsection
