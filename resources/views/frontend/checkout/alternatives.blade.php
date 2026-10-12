@extends('frontend.layouts.main')
@section('title', 'Deposit Alternatives')
@section('crumb', 'Sign Up')
@section('heading', 'Deposit alternatives')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  <p>If a deposit is required, you may be able to skip it:</p>
  <ul class="next-steps">
    <li><b>Letter of credit</b> from your previous electric provider showing 12 months of on-time payments.</li>
    <li><b>Age 65 or older</b> with no outstanding balance to any electric provider.</li>
    <li><b>Domestic violence survivor</b> certification from a family violence center.</li>
    <li><b>Military and first responders</b> — see <a href="{{ url('military-and-first-responders') }}">our program</a>.</li>
    <li><b>Prepaid plan or a guarantor</b> who is a current customer in good standing.</li>
  </ul>
  <p>Email your documents through the <a href="/contact-us.html">contact page</a> or call {{ config('brand.phone') }} with your account number.</p>
</div></div></section>
@endsection
