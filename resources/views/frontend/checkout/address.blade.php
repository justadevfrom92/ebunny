@extends('frontend.layouts.main')
@section('title', 'Sign Up')
@section('crumb', 'Sign Up')
@section('heading', 'Sign up for electricity')
@section('banner')<p>It takes about five minutes. You'll need your service address and, if you have it, your ESIID.</p>@endsection
@section('main')
<x-frontend.checkout :d="$d" :step="$step" :intro="$intro">
  <form method="post" action="{{ route('checkout.address') }}" novalidate>@csrf
    <h2>Where do you need service?</h2>
    <div class="toggle-group" role="radiogroup" aria-label="Service type">
      <label @class(['on' => empty(old('biz', $d['biz'] ?? false))])><input type="radio" name="biz" value="0" @checked(empty(old('biz', $d['biz'] ?? false)))> Home</label>
      <label @class(['on' => ! empty(old('biz', $d['biz'] ?? false))])><input type="radio" name="biz" value="1" @checked(! empty(old('biz', $d['biz'] ?? false)))> Business</label>
    </div>
    <div class="field"><label for="address">Street Address *</label><input id="address" name="address" required autocomplete="address-line1" value="{{ old('address', $d['address'] ?? '') }}"></div>
    <div class="form-3" style="margin-top:16px">
      <div class="field"><label for="unit">Apt / Unit</label><input id="unit" name="unit" autocomplete="address-line2" value="{{ old('unit', $d['unit'] ?? '') }}"></div>
      <div class="field"><label for="city">City *</label><input id="city" name="city" required autocomplete="address-level2" value="{{ old('city', $d['city'] ?? '') }}"></div>
      <div class="field"><label for="zip">Zip *</label><input id="zip" name="zip" required inputmode="numeric" maxlength="5" autocomplete="postal-code" value="{{ old('zip', $d['zip'] ?? '') }}"></div>
    </div>
    <div class="field" style="margin-top:16px"><label for="esiid">ESIID (optional)</label><input id="esiid" name="esiid" inputmode="numeric" value="{{ old('esiid', $d['esiid'] ?? '') }}" placeholder="17–22 digits, on a past electric bill"></div>
    <fieldset class="field" style="margin-top:16px"><legend class="legend">Are you moving or switching? *</legend>
      <label class="radio"><input type="radio" name="enrollment_type" value="Switch" @checked(old('enrollment_type', $d['enrollment_type'] ?? 'Switch') === 'Switch')> Switching providers at my current home</label>
      <label class="radio"><input type="radio" name="enrollment_type" value="Move-In" @checked(old('enrollment_type', $d['enrollment_type'] ?? '') === 'Move-In')> Moving into a new home</label>
    </fieldset>
    <div class="field" style="margin-top:16px"><label for="start_date">Start date (required if moving)</label><input id="start_date" name="start_date" type="date" min="{{ today()->addDay()->toDateString() }}" max="{{ today()->addDays(89)->toDateString() }}" value="{{ old('start_date', $d['start_date'] ?? '') }}"></div>
    <button class="btn" style="margin-top:24px">Continue</button>
  </form>
</x-frontend.checkout>
@endsection
