@extends('frontend.layouts.main')
@section('title', 'Review & Sign Up')
@section('heading', 'Review and sign up')
@section('show-title', '1')
@section('main')
<x-frontend.checkout :d="$d" :step="$step" :intro="$intro">
  <form method="post" action="{{ route('checkout.submit') }}">@csrf
    <h2>{{ $plan->name }}</h2>
    <p>{{ $plan->term === 1 ? 'Month-to-month variable rate.' : $plan->term.'-month fixed rate.' }} Early termination fee: {{ $plan->etf }}. {{ $plan->green }}% renewable.</p>
    @if ($prices->isNotEmpty())
      <table class="price-table"><thead><tr><th>Monthly use</th><th>500 kWh</th><th>1,000 kWh</th><th>2,000 kWh</th></tr></thead>
        <tbody><tr><td>Average price</td>@foreach ($prices as $p)<td>{{ number_format($p, 1) }}¢</td>@endforeach</tr></tbody></table>
      <p class="price-note">For {{ $market->description }}. Includes delivery charges.</p>
    @endif
    <h3 style="margin-top:24px">Account</h3>
    <p>{{ ! empty($d['biz']) ? $d['business_name'].' — ' : '' }}{{ $d['first_name'] }} {{ $d['last_name'] }} · {{ $d['email'] }} · {{ $d['phone'] }}</p>

    <h3 style="margin-top:24px">Add-ons</h3>
    <label class="check"><input type="hidden" name="autopay" value="0"><input type="checkbox" name="autopay" value="1" @checked(old('autopay', true))> AutoPay — pay automatically each month</label>
    <label class="check"><input type="hidden" name="paperless" value="0"><input type="checkbox" name="paperless" value="1" @checked(old('paperless', true))> Paperless billing — get your bill by email</label>
    <label class="check"><input type="hidden" name="peak_perks" value="0"><input type="checkbox" name="peak_perks" value="1" @checked(old('peak_perks'))> Peak Perks — earn credits for saving energy at peak times</label>

    <h3 style="margin-top:24px">Agreements</h3>
    <label class="check"><input type="checkbox" name="agree_efl" value="1" required> I have read the <a href="{{ url('historical-efls') }}" target="_blank">Electricity Facts Label</a> for this plan</label>
    <label class="check"><input type="checkbox" name="agree_tos" value="1" required> I agree to the <a href="{{ url('terms-of-use') }}" target="_blank">Terms of Service</a></label>
    <label class="check"><input type="checkbox" name="agree_yrac" value="1" required> I have read <a href="{{ url('your-rights-as-a-customer') }}" target="_blank">Your Rights as a Customer</a>, and I authorize the switch of my electric service</label>
    <div class="co-actions"><a class="btn btn-outline" href="{{ route('checkout.about') }}">Back</a><button class="btn">Sign Up</button></div>
  </form>
</x-frontend.checkout>
@endsection
