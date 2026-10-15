@extends('frontend.layouts.main')
@section('title', 'Reset Password')
@section('heading', 'Choose a new password')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{ route('myaccount.reset.store') }}">@csrf
    <input type="hidden" name="token" value="{{ $token }}"><input type="hidden" name="account" value="{{ $account }}">
    <div class="form-2">
      <div class="field"><label for="password">New Password</label><input id="password" name="password" type="password" required minlength="8" autocomplete="new-password"></div>
      <div class="field"><label for="password_confirmation">Confirm</label><input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"></div>
    </div>
    <button class="btn" style="margin-top:16px">Save Password</button>
  </form>
</div></div></section>
@endsection
