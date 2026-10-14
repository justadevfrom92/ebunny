@extends('frontend.layouts.main')
@section('title', 'Profile & Preferences')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="Profile & Preferences">
  <form method="post" action="{{ route('myaccount.profile.update') }}" class="co-card">@csrf @method('put')
    <h3>Contact</h3>
    <div class="form-2">
      <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" required value="{{ old('email', $c->email) }}"></div>
      <div class="field"><label for="phone">Phone</label><input id="phone" name="phone" required value="{{ old('phone', $c->phone) }}"></div>
    </div>
    <div class="form-2" style="margin-top:16px">
      <div class="field"><label for="phone_type">Phone Type</label><select id="phone_type" name="phone_type">@foreach (['mobile' => 'Mobile', 'landline' => 'Landline', 'voip' => 'Internet / VoIP'] as $v => $l)<option value="{{ $v }}" @selected(old('phone_type', $c->phone_type) === $v)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="language">Language</label><select id="language" name="language">@foreach (['English', 'Spanish'] as $l)<option @selected(old('language', $c->language) === $l)>{{ $l }}</option>@endforeach</select></div>
    </div>
    <h3 style="margin-top:20px">Billing Address</h3>
    <div class="field"><label for="billing_street">Street</label><input id="billing_street" name="billing_street" required value="{{ old('billing_street', $c->billing_street ?? $c->address) }}"></div>
    <div class="form-3" style="margin-top:16px">
      <div class="field"><label for="billing_city">City</label><input id="billing_city" name="billing_city" required value="{{ old('billing_city', $c->billing_city ?? $c->city) }}"></div>
      <div class="field"><label for="billing_state">State</label><input id="billing_state" name="billing_state" required maxlength="2" value="{{ old('billing_state', $c->billing_state ?? 'TX') }}"></div>
      <div class="field"><label for="billing_zip">Zip</label><input id="billing_zip" name="billing_zip" required maxlength="5" value="{{ old('billing_zip', $c->billing_zip ?? $c->zip) }}"></div>
    </div>
    <label class="check" style="margin-top:16px"><input type="checkbox" name="marketing_opt_in" value="1" @checked($c->marketing_opt_in)> Email me offers and energy-saving tips</label>
    <button class="btn" style="margin-top:16px">Save Profile</button>
  </form>

  <form method="post" action="{{ route('myaccount.password') }}" class="co-card" style="margin-top:20px">@csrf @method('put')
    <h3>Change Password</h3>
    <div class="form-3">
      <div class="field"><label for="current_password">Current</label><input id="current_password" name="current_password" type="password" required autocomplete="current-password"></div>
      <div class="field"><label for="new_password">New</label><input id="new_password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="password_confirmation">Confirm</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    </div>
    <button class="btn" style="margin-top:16px">Change Password</button>
  </form>

  <div class="co-card" style="margin-top:20px"><h3>Authorized Users</h3>
    <p class="muted-text">People who may call about this account.</p>
    @forelse ($c->authorized_users ?? [] as $i => $u)
      <div class="ma-line"><span>{{ $u['name'] }} · {{ $u['phone'] }}</span><form method="post" action="{{ route('myaccount.authorized.remove', $i) }}">@csrf @method('delete')<button class="link">Remove</button></form></div>
    @empty <p>None.</p> @endforelse
    <form method="post" action="{{ route('myaccount.authorized.store') }}" class="form-3" style="margin-top:12px">@csrf
      <div class="field"><label for="au-name">Name</label><input id="au-name" name="name" required></div>
      <div class="field"><label for="au-phone">Phone</label><input id="au-phone" name="phone" required></div>
      <div class="field" style="align-self:end"><button class="btn btn-outline btn-block">Add</button></div>
    </form>
  </div>

  <div class="co-card" style="margin-top:20px"><h3>Linked Accounts</h3>
    <p>@forelse ($c->linked_accounts ?? [] as $a){{ $a }}@if (! $loop->last), @endif @empty None. @endforelse</p>
    <form method="post" action="{{ route('myaccount.link') }}" class="form-3">@csrf
      <div class="field"><label for="la-account">Account #</label><input id="la-account" name="account" required></div>
      <div class="field"><label for="la-zip">Service Zip</label><input id="la-zip" name="zip" required maxlength="5"></div>
      <div class="field" style="align-self:end"><button class="btn btn-outline btn-block">Link Account</button></div>
    </form>
  </div>
</x-frontend.myaccount>
@endsection
