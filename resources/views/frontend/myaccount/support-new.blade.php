@extends('frontend.layouts.main')
@section('title', 'New Support Ticket')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="New Support Ticket">
  <form method="post" action="{{ route('myaccount.support.store') }}" class="co-card">@csrf
    <div class="field"><label>What's it about?</label>
      <div class="sp-topics">@foreach (\App\Models\SupportTicket::TOPICS as $k => $l)<label><input type="radio" name="topic" value="{{ $k }}" required @checked(old('topic') === $k)><span>{{ $l }}</span></label>@endforeach</div>
      @error('topic')<small class="sp-error">Pick what your ticket is about.</small>@enderror</div>
    <div class="field"><label for="sp-subject">Subject</label><input id="sp-subject" name="subject" required maxlength="200" value="{{ old('subject') }}" placeholder="e.g. Question about my last bill"></div>
    <div class="field"><label for="sp-body">Tell us more</label><textarea id="sp-body" name="body" required maxlength="5000" placeholder="The more detail, the faster we can help.">{{ old('body') }}</textarea></div>
    <p><small>For a power outage, call your utility first. We'll still follow up here.</small></p>
    <div class="sp-actions"><a class="btn btn-outline btn-back" href="{{ route('myaccount.support') }}">Back</a><button class="btn">Open Ticket</button></div>
  </form>
</x-frontend.myaccount>
@endsection
