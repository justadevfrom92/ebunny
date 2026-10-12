@extends('frontend.layouts.main')
@section('title', 'Products')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Products">
  <p style="margin-bottom:20px">Everything you can add to your account. Turn the free ones on and off here; for the rest, we'll set it up with you.</p>
  <div class="prod-grid">
    @foreach ($products as $slug => [$name, $desc, $price, $how])
      @php $enrolled = in_array($name, $on, true); @endphp
      <section class="co-card prod-card" id="{{ $slug }}" aria-labelledby="{{ $slug }}-h">
        <h3 id="{{ $slug }}-h">{{ $name }} <span @class(['pill-on' => $enrolled, 'pill-off' => ! $enrolled])>{{ $enrolled ? 'On' : 'Off' }}</span></h3>
        <p>{{ $desc }}</p>
        <p class="price">{{ $price }}</p>
        @if ($how === 'self')
          @if ($slug === 'autopay' && ! $hasMethod && ! $enrolled)<p class="site-alert" style="margin:0">Add a payment method first (<a href="{{ route('myaccount.methods') }}">Payment Methods</a>).</p>@endif
          <form method="post" action="{{ route('myaccount.product.toggle', $slug) }}">@csrf
            <input type="hidden" name="enroll" value="{{ $enrolled ? 0 : 1 }}">
            <button class="btn {{ $enrolled ? 'btn-outline' : '' }}">{{ $enrolled ? 'Turn Off' : 'Turn On' }}</button>
          </form>
        @elseif ($how === 'call')
          <p class="how">{{ $enrolled ? 'To change it, ' : 'To add it, ' }}call <a href="tel:{{ preg_replace('/[^\d+]/', '', config('brand.phone')) }}">{{ config('brand.phone') }}</a> or ask in the chat.</p>
        @else
          <a class="btn btn-outline" href="{{ route($how) }}">Open {{ $name }}</a>
        @endif
      </section>
    @endforeach
  </div>
</x-frontend.myaccount>
@endsection
