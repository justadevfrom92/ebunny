@extends('frontend.layouts.main')
@section('title', 'QuickPay')
@section('crumb', 'Pay My Bill')
@section('heading', 'QuickPay')
@section('banner')<p>Check your balance and pay without signing in.</p>@endsection
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
  <form method="get" class="form-3">
    <div class="field"><label for="account">Account #</label><input id="account" name="account" required inputmode="numeric" value="{{ request('account') }}"></div>
    <div class="field"><label for="zip">Service Zip</label><input id="zip" name="zip" required maxlength="5" inputmode="numeric" value="{{ request('zip') }}"></div>
    <div class="field" style="align-self:end"><button class="btn btn-block">Look Up</button></div>
  </form>
  @if ($looked && ! $c)<p class="site-alert error" style="margin-top:16px">We couldn't find that account.</p>@endif
  @if ($c)
    <dl class="kv-site">
      <dt>Account</dt><dd>{{ $c->account }} · {{ $c->first_name }}</dd>
      <dt>Balance</dt><dd><b>${{ number_format($c->balance, 2) }}</b></dd>
      <dt>Due</dt><dd>{{ $c->due_date?->format('M j, Y') ?? '—' }}</dd>
    </dl>
    <p class="site-alert">Paying with a new card online isn't available yet. <a href="{{ route('myaccount.login') }}">Sign in</a> to pay with a saved payment method, or call {{ config('brand.phone') }}.</p>
  @endif
</div></div></section>
@endsection
