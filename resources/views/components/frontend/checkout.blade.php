@props(['d', 'step', 'intro' => ''])
{{-- Shared frame for the sign-up steps: progress bar, the step's form, and the order summary --}}
@php
    $steps = \App\Http\Controllers\Frontend\SignupController::STEPS;
    $keys = array_keys($steps);
    $plan = isset($d['plan_id']) ? \App\Models\Plan::find($d['plan_id']) : null;
@endphp
<section class="section">
  <div class="wrap">
    <ol class="steps" aria-label="Sign-up steps">
      @foreach ($steps as $key => $label)
        <li @class(['on' => $key === $step, 'done' => array_search($key, $keys) < array_search($step, $keys)])>{{ $label }}</li>
      @endforeach
    </ol>
    @if ($intro)<div class="cms-content co-intro">{!! $intro !!}</div>@endif
    @if ($errors->any())<div class="site-alert error" role="alert">{{ $errors->first() }}</div>@endif
    <div class="co-grid">
      <div class="co-card">{{ $slot }}</div>
      <aside class="co-card co-summary">
        <h3>Your Order</h3>
        <dl>
          @if (! empty($d['address']))<dt>Service address</dt><dd>{{ $d['address'] }} {{ $d['unit'] ?? '' }}<br>{{ $d['city'] }}, TX {{ $d['zip'] }}</dd>@endif
          @if (! empty($d['enrollment_type']))<dt>Type</dt><dd>{{ $d['enrollment_type'] === 'Move-In' ? 'Moving in on '.\Illuminate\Support\Carbon::parse($d['start_date'])->format('M j, Y') : 'Switching providers' }}</dd>@endif
          @if ($plan)<dt>Plan</dt><dd>{{ $plan->name }}</dd>@endif
          @if (! empty($d['promo_code']))<dt>Promo code</dt><dd>{{ $d['promo_code'] }}</dd>@endif
        </dl>
        @if (empty($d['address']))<p class="muted-text">Your order details show here as you go.</p>@endif
        @if ($step !== 'review')
          <details class="co-save"><summary>Save and finish later</summary>
            <form method="post" action="{{ route('checkout.save') }}">@csrf<input type="hidden" name="step" value="{{ $step }}">
              <div class="field"><label for="save-email">Email</label><input id="save-email" name="email" type="email" required value="{{ $d['email'] ?? '' }}"></div>
              <button class="btn btn-outline btn-block" style="margin-top:12px">Email me a link</button>
            </form>
          </details>
        @endif
        <p class="muted-text">Questions? Call <a href="tel:{{ preg_replace('/\D/', '', config('brand.phone')) }}">{{ config('brand.phone') }}</a> or <a href="{{ route('checkout.page', 'start-call') }}">finish by phone</a>.</p>
      </aside>
    </div>
  </div>
</section>
