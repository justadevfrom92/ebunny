@extends('frontend.layouts.main')
@section('title', 'My Account')
@section('heading', 'Sign in to My Account')
@section('banner')<p>Pay your bill, check usage, renew your plan and redeem rewards.</p>@endsection
@section('main')
<section class="section"><div class="wrap narrow ma-split">
  <div class="co-card">
    @if (session('status'))<div class="site-alert ok" role="status">{{ session('status') }}</div>@endif
    @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('myaccount.login.attempt') }}">@csrf
      <div class="field"><label for="login">Username or Email</label><input id="login" name="login" required autocomplete="username" value="{{ old('login') }}"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" required autocomplete="current-password"></div>
      <label class="check"><input type="checkbox" name="remember" value="1"> Keep me signed in</label>
      <button class="btn btn-block" style="margin-top:16px">Sign In</button>
    </form>
    <p class="muted-text">Forgot your <a href="{{ route('myaccount.forgot', 'username') }}">username</a> or <a href="{{ route('myaccount.forgot', 'password') }}">password</a>?</p>
    @if ($testCustomers->isNotEmpty())
      <div class="test-logins-site">
        <h3>Test Sign-Ins</h3>
        <p class="muted-text">For testing only (never shown on the live site): open My Account as a sample customer.</p>
        @foreach ($testCustomers as $tc)
          <form method="post" action="{{ route('myaccount.login.test', $tc) }}">@csrf
            <button class="test-customer"><b>{{ $tc->name }}</b><small>{{ $tc->account }} · {{ $tc->type }} · {{ $tc->status }} · {{ $tc->balance < 0 ? '$'.number_format(abs($tc->balance), 2).' credit' : '$'.number_format($tc->balance, 2).' due' }}</small></button>
          </form>
        @endforeach
      </div>
    @endif
  </div>
  <div class="co-card">
    <h3>New to My Account?</h3>
    <p>Already a customer? <a href="{{ route('myaccount.register') }}">Create your login</a> with your account number.</p>
    <h3 style="margin-top:20px">Just paying a bill?</h3>
    <p><a href="{{ route('myaccount.quickpay') }}">QuickPay</a> — no sign-in needed.</p>
    <h3 style="margin-top:20px">Not a customer yet?</h3>
    <p><a class="btn btn-outline" href="{{ route('checkout') }}">Sign up for service</a></p>
  </div>
</div></section>
@endsection
