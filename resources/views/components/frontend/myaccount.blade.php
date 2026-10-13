@props(['c', 'title'])
{{-- My Account frame: account menu on the left (below the page on phones), the page on the right --}}
@php
    $cartCount = app(\App\Services\Cart::class)->count();
    $menu = [
        'Account' => [['Dashboard', route('myaccount.dashboard')], ['Profile & Preferences', route('myaccount.profile')], ['Products & Protections', route('myaccount.products')], ['Message Center', route('myaccount.messages')]],
        'Bills' => [['View Bills', route('myaccount.bills')], ['Pay Bill', route('myaccount.pay')], ['Payment Methods', route('myaccount.methods')]],
        'Plan & Services' => [['Current Plan & Renew', route('myaccount.plan')], ['Transfer Service', route('myaccount.transfer')], ['Energy Insights', route('myaccount.insights')]],
        'Rewards' => [['My Rewards', route('myaccount.rewards')], ['Refer a Friend', route('myaccount.refer')]],
        'Shop' => [['Cart & Checkout'.($cartCount ? ' ('.$cartCount.')' : ''), route('shop.cart')], ['Orders', route('myaccount.orders')], ['Shop', route('shop')]],
    ];
    $here = url()->current();
    $login = auth('customer')->user();
    $properties = $login ? $login->properties() : collect();
@endphp
<section class="section ma">
  <div class="wrap ma-grid">
    <div class="ma-main">
      <h2 class="ma-title">{{ $title }}</h2>
      @if (session('status'))<div class="site-alert ok" role="status">{{ session('status') }}</div>@endif
      @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
      {{ $slot }}
    </div>
    <nav class="ma-nav" aria-label="My Account">
      <div class="ma-who"><b>{{ $c->name }}</b><span>Account {{ $c->account }}</span>@if ($properties->count() > 1)<span>{{ $c->propertyLabel() }}</span>@endif</div>
      @if ($properties->count() > 1)
        {{-- Business (or linked) accounts: one row per property; pick one to see its bills and pay it --}}
        <div class="ma-props">
          <h4>{{ $login->type === 'Residential' ? 'Accounts' : 'Properties' }} ({{ $properties->count() }})</h4>
          @foreach ($properties as $p)
            <form method="post" action="{{ route('myaccount.property', $p->account) }}">@csrf
              <button @class(['on' => $p->is($c)]) @if ($p->is($c)) aria-current="true" @endif><span>{{ $p->propertyLabel() }}</span><small>{{ $p->account }} · ${{ number_format($p->balance, 2) }}</small></button>
            </form>
          @endforeach
        </div>
      @endif
      @php $testCustomers = \App\Http\Controllers\Frontend\MyAccount\AuthController::testCustomers(); @endphp
      @if ($testCustomers->count() > 1)
        <form method="post" class="ma-test-switch">@csrf
          <label for="ma-switch">Testing: switch customer</label>
          <select id="ma-switch" onchange="if (this.value) { this.form.action = this.value; this.form.requestSubmit(); }">
            <option value="">Choose…</option>
            @foreach ($testCustomers as $tc)@unless ($tc->is($c))<option value="{{ route('myaccount.login.test', $tc) }}">{{ $tc->name }} · {{ $tc->status }}</option>@endunless @endforeach
          </select>
        </form>
      @endif
      @foreach ($menu as $heading => $items)
        <h4>{{ $heading }}</h4>
        <div class="ma-group">
        @foreach ($items as [$label, $url])
          @php $on = $url === $here || (in_array($url, [route('myaccount.messages'), route('myaccount.orders')], true) && str_starts_with($here, $url.'/')) || ($url === route('shop.cart') && $here === route('myaccount.checkout')); @endphp
          <a href="{{ $url }}" @class(['on' => $on]) @if ($on) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
        </div>
      @endforeach
    </nav>
  </div>
</section>
<x-frontend.chat />
