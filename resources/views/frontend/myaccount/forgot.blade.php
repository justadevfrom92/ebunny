@extends('frontend.layouts.main')
@section('title', 'Forgot '.ucfirst($what))
@section('heading', 'Forgot your '.$what.'?')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if (session('status'))<div class="site-alert ok" role="status">{{ session('status') }}</div>@endif
  @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="post" action="{{ route('myaccount.forgot.send', $what) }}">@csrf
    <div class="field"><label for="login">{{ $what === 'username' ? 'Email on the account' : 'Username or Email' }}</label><input id="login" name="login" required value="{{ old('login') }}"></div>
    <button class="btn" style="margin-top:16px">{{ $what === 'username' ? 'Email My Username' : 'Email a Reset Link' }}</button>
  </form>
  <p class="muted-text"><a href="{{ route('myaccount.login') }}">Back to sign in</a></p>
</div></div></section>
@endsection
