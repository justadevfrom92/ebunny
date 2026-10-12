@extends('frontend.layouts.main')
@section('title', 'Payment Methods')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Payment Methods">
  <div class="co-card">
    @forelse ($methods as $m)
      <div class="ma-line"><span><b>{{ $m->label() }}</b>@if ($m->expires) <small>exp {{ $m->expires }}</small>@endif @if ($m->autopay) <span class="pill-on">AutoPay</span>@endif</span>
        <span class="ma-actions">
          @unless ($m->autopay)<form method="post" action="{{ route('myaccount.methods.default', $m) }}">@csrf<button class="link">Use for AutoPay</button></form>@endunless
          <form method="post" action="{{ route('myaccount.methods.remove', $m) }}">@csrf @method('delete')<button class="link">Remove</button></form>
        </span></div>
    @empty <p>No saved payment methods.</p> @endforelse
    <p class="site-alert" style="margin-top:16px">{{ $cards ? 'Adding cards online is being connected to our card processor.' : 'Adding a new card or bank account online isn\'t available yet.' }} Call {{ config('brand.phone') }} to add one.</p>
  </div>
</x-frontend.myaccount>
@endsection
