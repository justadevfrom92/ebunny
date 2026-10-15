@extends('frontend.layouts.main')
@section('title', 'Something Went Wrong')
@section('heading', 'Something went wrong')
@section('show-title', '1')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  <p>We couldn't finish your sign-up. Nothing was charged. Please try again, or call {{ config('brand.phone') }} and we'll finish it with you.</p>
  <a class="btn" href="{{ route('checkout') }}">Try again</a>
</div></div></section>
@endsection
