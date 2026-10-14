@extends('frontend.layouts.main')
@section('title', 'Transfer Service')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="Transfer Service">
  <form method="post" action="{{ route('myaccount.transfer.store') }}" class="co-card">@csrf
    <p>Moving? We'll stop service at {{ $c->address }} the day before and start it at your new home on your move-in date.</p>
    <div class="field"><label for="street">New Street Address</label><input id="street" name="street" required value="{{ old('street') }}"></div>
    <div class="form-3" style="margin-top:16px">
      <div class="field"><label for="city">City</label><input id="city" name="city" required value="{{ old('city') }}"></div>
      <div class="field"><label for="zip">Zip</label><input id="zip" name="zip" required maxlength="5" value="{{ old('zip') }}"></div>
      <div class="field"><label for="date">Move-in Date</label><input id="date" name="date" type="date" required min="{{ today()->addDay()->toDateString() }}" value="{{ old('date') }}"></div>
    </div>
    <div class="field" style="margin-top:16px"><label for="esiid">ESIID at the new home (optional)</label><input id="esiid" name="esiid" inputmode="numeric" value="{{ old('esiid') }}"></div>
    <button class="btn" style="margin-top:20px">Transfer My Service</button>
  </form>
</x-frontend.myaccount>
@endsection
