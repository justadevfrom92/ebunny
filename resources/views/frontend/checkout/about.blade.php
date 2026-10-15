@extends('frontend.layouts.main')
@section('title', 'About You')
@section('heading', 'Tell us about you')
@section('banner')<p>We use this to set up your account and run a soft credit check, which doesn't affect your credit score.</p>@endsection
@section('main')
<x-frontend.checkout :d="$d" :step="$step" :intro="$intro">
  <form method="post" action="{{ route('checkout.about.store') }}" novalidate>@csrf
    <h2>Account holder</h2>
    @if (! empty($d['biz']))<div class="field"><label for="business_name">Business Name *</label><input id="business_name" name="business_name" required value="{{ old('business_name', $d['business_name'] ?? '') }}"></div>@endif
    <div class="form-2" style="margin-top:16px">
      <div class="field"><label for="first_name">First Name *</label><input id="first_name" name="first_name" required autocomplete="given-name" value="{{ old('first_name', $d['first_name'] ?? '') }}"></div>
      <div class="field"><label for="last_name">Last Name *</label><input id="last_name" name="last_name" required autocomplete="family-name" value="{{ old('last_name', $d['last_name'] ?? '') }}"></div>
    </div>
    <div class="form-2" style="margin-top:16px">
      <div class="field"><label for="email">Email *</label><input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email', $d['email'] ?? '') }}"></div>
      <div class="field"><label for="phone">Phone *</label><input id="phone" name="phone" type="tel" required autocomplete="tel" value="{{ old('phone', $d['phone'] ?? '') }}"></div>
    </div>
    <div class="form-3" style="margin-top:16px">
      <div class="field"><label for="phone_type">Phone Type *</label><select id="phone_type" name="phone_type">@foreach (['mobile' => 'Mobile', 'landline' => 'Landline', 'voip' => 'Internet / VoIP'] as $v => $l)<option value="{{ $v }}" @selected(old('phone_type', $d['phone_type'] ?? 'mobile') === $v)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="language">Language *</label><select id="language" name="language">@foreach (['English', 'Spanish'] as $l)<option @selected(old('language', $d['language'] ?? 'English') === $l)>{{ $l }}</option>@endforeach</select></div>
      <div class="field"><label for="ssn_last4">Last 4 of SSN *</label><input id="ssn_last4" name="ssn_last4" required inputmode="numeric" maxlength="4" autocomplete="off"></div>
    </div>
    <label class="check" style="margin-top:16px"><input type="hidden" name="credit_frozen" value="0"><input type="checkbox" name="credit_frozen" value="1"> My credit file is frozen</label>

    <h2 style="margin-top:28px">Create your My Account login <small>(optional)</small></h2>
    <div class="form-3">
      <div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" value="{{ old('username', $d['username'] ?? '') }}"></div>
      <div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="new-password" minlength="8"></div>
      <div class="field"><label for="password_confirmation">Confirm Password</label><input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password"></div>
    </div>
    <label class="check" style="margin-top:16px"><input type="hidden" name="marketing_opt_in" value="0"><input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in', $d['marketing_opt_in'] ?? true))> Email me offers and energy-saving tips</label>
    <div class="co-actions"><a class="btn btn-outline" href="{{ route('checkout.plan') }}">Back</a><button class="btn">Continue</button></div>
  </form>
</x-frontend.checkout>
@endsection
