@extends('frontend.layouts.main')
@section('title', 'Credit Freeze')
@section('heading', 'Your credit file is frozen')
@section('show-title', '1')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  <p>We can't run the soft credit check while your file is frozen. To finish signing up:</p>
  <ol class="next-steps">
    <li>Temporarily lift the freeze with Experian (experian.com/freeze or 888-397-3742). You can set it to re-freeze automatically.</li>
    <li>Come back and finish. Your details are kept for this visit; use “Save and finish later” to get a link by email.</li>
    <li>Or skip the credit check: choose a <a href="{{ route('checkout.page', 'alternatives') }}">deposit alternative</a> or pay a deposit.</li>
  </ol>
  <div class="co-actions"><a class="btn btn-outline btn-back" href="{{ route('checkout.about') }}">Back</a><a class="btn" href="{{ route('checkout.review') }}">I lifted the freeze — continue</a></div>
</div></div></section>
@endsection
