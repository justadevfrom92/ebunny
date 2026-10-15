@extends('frontend.layouts.main')
@section('title', 'You\'re Signed Up')
@section('heading', 'You\'re signed up!')
@section('show-title', '1')
@section('banner')<p>Thanks, {{ $c->first_name }}. Your confirmation number is <b>{{ $c->account }}</b>.</p>@endsection
@section('main')
<section class="section"><div class="wrap narrow">
  @if ($intro)<div class="cms-content co-intro">{!! $intro !!}</div>@endif
  <div class="co-card">
    <h2>What happens next</h2>
    <ol class="next-steps">
      <li>We run a soft credit check. If a deposit is needed we'll email you; you can also pay or choose a deposit alternative on the <a href="{{ route('checkout.deposit') }}">deposit page</a>.</li>
      <li>We send your order to {{ $c->market?->description ?? 'your utility' }}. {{ $c->enrollment_type === 'Move-In' && $c->requested_start ? 'Service is scheduled to start '.$c->requested_start->format('l, M j').'.' : 'Switches usually complete in 2–7 business days.' }}</li>
      <li>You'll get a welcome email with your Electricity Facts Label, Terms of Service and Your Rights as a Customer.</li>
    </ol>
    <dl class="kv-site">
      <dt>Account / confirmation #</dt><dd>{{ $c->account }}</dd>
      <dt>Plan</dt><dd>{{ $c->plan?->name }}</dd>
      <dt>Service address</dt><dd>{{ $c->address }}{{ $c->unit ? ' '.$c->unit : '' }}, {{ $c->city }} {{ $c->zip }}</dd>
      <dt>Your referral code</dt><dd>{{ $c->referral_code }} — share it and you both get a bill credit</dd>
    </dl>
    <div class="co-actions">
      @if ($c->username)<a class="btn" href="{{ route('myaccount.login') }}">Sign in to My Account</a>@else<a class="btn" href="{{ route('myaccount.register') }}">Create your My Account login</a>@endif
    </div>
    <p class="muted-text">Changed your mind? You can <a href="{{ route('checkout.page', 'cancel') }}">cancel within 3 days</a> at no cost.</p>
  </div>
</div></section>
@endsection
