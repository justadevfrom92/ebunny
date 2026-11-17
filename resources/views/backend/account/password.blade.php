@extends('backend.layouts.app')

@section('crumb', 'Change Password')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Change Password', 'sub' => 'At least 8 characters, with letters and numbers.'])

    @if ($loggedInAs)
        <div class="banner-note">You're using Log in as, so you can't change this person's password. Go back to yourself first.</div>
    @else
        <form method="post" action="{{ route('admin.account.password.save') }}" class="panel">@csrf @method('put')
            <div class="panel-body">
                <div class="field"><label for="current_password">Current Password</label><input id="current_password" name="current_password" type="password" autocomplete="current-password" required>
                    @error('current_password')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="password">New Password</label><input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
                    @error('password')<div class="help" style="color:var(--red)">{{ $message }}</div>@enderror</div>
                <div class="field"><label for="password_confirmation">New Password Again</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required minlength="8"></div>
                <button class="btn cyan" style="margin-top:8px">Change Password</button>
            </div>
        </form>
    @endif
@endsection
