@extends('frontend.layouts.main')
@section('title', 'Deposit')
@section('heading', 'Pay your deposit')
@section('show-title', '1')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  @if ($errors->any())<div class="site-alert error">{{ $errors->first() }}</div>@endif
  <form method="get" class="form-3">
    <div class="field"><label for="account">Account #</label><input id="account" name="account" inputmode="numeric" required value="{{ request('account') }}"></div>
    <div class="field"><label for="zip">Service Zip</label><input id="zip" name="zip" inputmode="numeric" maxlength="5" required value="{{ request('zip') }}"></div>
    <div class="field" style="align-self:end"><button class="btn btn-block">Look Up</button></div>
  </form>
  @if ($looked && ! $c)<p class="site-alert error">We couldn't find that account. Check the number on your confirmation email.</p>@endif
  @if ($c)
    @if ($c->deposit_due > 0)
      <p style="margin-top:20px">A deposit of <b>${{ number_format($c->deposit_due, 2) }}</b> is due for account {{ $c->account }}. It's returned with interest after 12 on-time payments.</p>
      @if ($payments)
        <p class="site-alert">Online deposit payments aren't connected to the card processor yet. Please call {{ config('brand.phone') }} to pay by card.</p>
      @else
        <p class="site-alert">Online card payments aren't available right now. Please call {{ config('brand.phone') }} to pay your deposit.</p>
      @endif
      <p>Can't pay a deposit? See <a href="{{ route('checkout.page', 'alternatives') }}">deposit alternatives</a>.</p>
    @else
      <p class="site-alert ok" style="margin-top:20px">No deposit is due on account {{ $c->account }}.</p>
    @endif
  @endif
</div></div></section>
@endsection
