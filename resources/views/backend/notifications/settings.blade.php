@extends('backend.layouts.app')

@section('crumb', 'Settings')

{{-- Your settings: which notifications you get, and (testing only) switching to another sample user. --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Settings', 'sub' => $user->name.' · '.$user->email])

    <form method="post" action="{{ route('admin.settings.save') }}" class="panel">@csrf @method('put')
        <div class="panel-head"><h2>Notifications</h2><span class="help">they add to the number on your avatar</span></div>
        <div class="table-wrap"><table>
            <thead><tr><th style="width:60px">On</th><th>Notification</th><th>When you get it</th></tr></thead>
            <tbody>@foreach (\App\Models\AdminNotification::KINDS as $k => [$label, $when, $icon])
                <tr><td><input type="checkbox" id="nk-{{ $k }}" name="kinds[]" value="{{ $k }}" @checked($user->wantsNotification($k))></td>
                    <td><label for="nk-{{ $k }}"><b>{{ $icon }} {{ $label }}</b></label></td><td class="wrap help">{{ $when }}</td></tr>
            @endforeach</tbody>
        </table></div>
        <div class="panel-body"><button class="btn cyan">Save Settings</button></div>
    </form>

    <form method="post" action="{{ route('admin.account.start-page.save') }}" class="panel" id="start-page">@csrf @method('put')
        <div class="panel-head"><h2>Start Page</h2><span class="help">where you land after signing in</span></div>
        <div class="panel-body form-stack">
            <div class="field"><label for="start_page">Open</label><select id="start_page" name="start_page">
                <option value="">Admin home (all apps)</option>
                <option value="account" @selected($user->start_page === 'account')>Account → My Profile</option>
                @foreach ($user->apps() as $key => $a)<option value="{{ $key }}" @selected($user->start_page === $key)>{{ $a['name'] }}</option>@endforeach
            </select></div>
            <button class="btn cyan">Save Start Page</button>
        </div>
    </form>

    <form method="post" action="{{ route('admin.account.signature.save') }}" class="panel" id="signature">@csrf @method('put')
        <div class="panel-head"><h2>Email Signature</h2><span class="help">added to emails you send customers</span></div>
        <div class="panel-body form-stack">
            <div class="field"><label for="sig">Signature</label><textarea id="sig" name="signature" maxlength="1000" placeholder="e.g. {{ $user->name }}&#10;{{ $user->title ?? 'Customer Care' }} · {{ config('brand.name') }}">{{ old('signature', $user->signature) }}</textarea></div>
            <p class="help">Goes under Ranger replies sent by email and Corral's Contact - Send Email.</p>
            <button class="btn cyan">Save Signature</button>
        </div>
    </form>

    @if ($testUsers->isNotEmpty())
        <div class="panel"><div class="panel-head"><h2>Switch User</h2><span class="help">testing only: never shown in production</span></div>
            <div class="panel-body"><input type="search" class="search-box" placeholder="Find a user" data-live-filter aria-label="Find a user to switch to"></div>
            <form method="post" class="table-wrap">@csrf<table>
                <thead><tr><th>Name</th><th>Role</th><th><span class="sr-only">Switch</span></th></tr></thead>
                <tbody>@foreach ($testUsers as $u)
                    <tr data-filter-row="{{ strtolower($u->name.' '.$u->role?->name.' '.$u->email) }}"><td><b>{{ $u->name }}</b><div class="help">{{ $u->email }}</div></td><td>{{ $u->role?->name ?? '—' }}</td>
                        <td class="actions"><button class="btn sm" formaction="{{ route('admin.login.test', $u) }}">Switch to {{ \Illuminate\Support\Str::before($u->name, ' ') }}</button></td></tr>
                @endforeach</tbody>
            </table></form>
        </div>
    @endif
@endsection
