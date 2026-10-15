@extends('frontend.layouts.main')
@section('title', 'Cancel Your Order')
@section('heading', 'Cancel your order')
@section('show-title', '1')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  @if (session('cancelled'))
    <p class="site-alert ok">Order {{ session('cancelled') }} is cancelled. You won't be charged and we'll email a confirmation.</p>
  @else
    <p>You can cancel within 3 days of signing up at no cost (your right of rescission).</p>
    @if ($errors->any())<div class="site-alert error">{{ $errors->first() }}</div>@endif
    <form method="post" action="{{ route('checkout.cancel') }}">@csrf
      <div class="form-3">
        <div class="field"><label for="account">Account #</label><input id="account" name="account" required value="{{ old('account') }}"></div>
        <div class="field"><label for="zip">Service Zip</label><input id="zip" name="zip" required maxlength="5" value="{{ old('zip') }}"></div>
        <div class="field"><label for="email">Email on the order</label><input id="email" name="email" type="email" required value="{{ old('email') }}"></div>
      </div>
      <button class="btn" style="margin-top:20px">Cancel My Order</button>
    </form>
  @endif
</div></div></section>
@endsection
