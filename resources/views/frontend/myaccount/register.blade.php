@extends('frontend.layouts.main')
@section('title', 'Create Account')
@section('crumb', 'My Account')
@section('heading', 'Create your My Account login')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{ route('myaccount.register.store') }}">@csrf
    <p>Enter the details from your bill or welcome email so we know it's your account.</p>
    <div class="form-3">
      <div class="field"><label for="account">Account #</label><input id="account" name="account" required inputmode="numeric" value="{{ old('account') }}"></div>
      <div class="field"><label for="zip">Service Zip</label><input id="zip" name="zip" required maxlength="5" inputmode="numeric" value="{{ old('zip') }}"></div>
      <div class="field"><label for="email">Email on the account</label><input id="email" name="email" type="email" required value="{{ old('email') }}"></div>
    </div>
    <div class="form-3" style="margin-top:16px">
      <div class="field"><label for="username">Choose a Username</label><input id="username" name="username" required autocomplete="username" value="{{ old('username') }}"></div>
      <div class="field"><label for="password">Password (8+ characters)</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="password_confirmation">Confirm Password</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    </div>
    <button class="btn" style="margin-top:20px">Create Login</button>
  </form>
  <p class="muted-text">Already have a login? <a href="{{ route('myaccount.login') }}">Sign in</a>.</p>
</div></div></section>
@endsection
