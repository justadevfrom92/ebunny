@extends('frontend.layouts.main')
@section('title', 'Saved')
@section('crumb', 'Sign Up')
@section('heading', 'Your sign-up is saved')
@section('main')
<section class="section"><div class="wrap narrow"><div class="co-card">
  @if ($intro)<div class="cms-content">{!! $intro !!}</div>@endif
  <p>We emailed you a link to pick up where you left off. It works for 30 days. For your security we don't save your SSN or password; you'll enter those again.</p>
  <a class="btn" href="{{ route('checkout') }}">Keep going now</a>
</div></div></section>
@endsection
