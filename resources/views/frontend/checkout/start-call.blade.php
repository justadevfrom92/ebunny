@extends('frontend.layouts.main')
@section('title', 'Finish by Phone')
@section('heading', 'Finish your sign-up by phone')
@section('show-title', '1')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  <p>Call <a href="tel:{{ preg_replace('/\D/', '', config('brand.phone')) }}"><b>{{ config('brand.phone') }}</b></a> ({{ config('brand.hours') }}).</p>
  @if (! empty($d['address']))<p>Tell the agent you started online for <b>{{ $d['address'] }}, {{ $d['zip'] }}</b> so they can pick up from there.</p>@endif
  <a class="btn btn-outline btn-back" href="{{ route('checkout') }}">Back to sign-up</a>
</div></div></section>
@endsection
