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
