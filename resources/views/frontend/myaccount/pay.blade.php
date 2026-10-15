@extends('frontend.layouts.main')
@section('title', 'Pay Bill')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Pay Bill">
  @if ($properties->count() > 1)
    {{-- A business with several locations: pick which property to pay --}}
    <div class="co-card" style="margin-bottom:20px"><h3>Which property?</h3>
      <div class="ma-table"><table class="price-table">
        <thead><tr><th>Property</th><th>Account</th><th class="num">Balance</th><th>Due</th><th></th></tr></thead>
        <tbody>@foreach ($properties as $p)
          <tr @class(['on' => $p->is($c)])><td>{{ $p->propertyLabel() }}</td><td>{{ $p->account }}</td><td class="num">${{ number_format($p->balance, 2) }}</td><td>{{ $p->due_date?->format('M j') ?? '—' }}</td>
            <td>@if ($p->is($c))<b>Paying this one</b>@else<form method="post" action="{{ route('myaccount.property', $p->account) }}">@csrf<input type="hidden" name="to" value="pay"><button class="link">Pay this one</button></form>@endif</td></tr>
        @endforeach</tbody>
      </table></div>
    </div>
  @endif

  <div class="co-card">
    @if ($methods->isEmpty())
      <p class="site-alert">You don't have a saved payment method. Adding a new card online isn't available yet; please call {{ config('brand.phone') }} to pay.</p>
    @else
      <form method="post" action="{{ route('myaccount.pay.store') }}" class="pay-form">@csrf
        <fieldset class="field"><legend class="pay-legend">How much?</legend>
          <div class="pay-choices">
            @foreach ($amounts as $k => [$label, $amt, $note])
              <label @class(['pay-choice', 'other' => $k === 'other'])>
                <input type="radio" name="choice" value="{{ $k }}" @checked(old('choice', array_key_first($amounts)) === $k)>
                <span class="pc-label">{{ $label }}</span>
                @if ($amt !== null)<strong>${{ number_format($amt, 2) }}</strong>@else
                  <span class="pc-other">$ <input name="amount" type="number" step="0.01" min="1" max="10000" inputmode="decimal" aria-label="Other amount" value="{{ old('amount') }}" placeholder="0.00"></span>@endif
                @if ($note)<small>{{ $note }}</small>@endif
              </label>
            @endforeach
          </div>
        </fieldset>

        <fieldset class="field"><legend class="pay-legend">Pay with</legend>
          <div class="ma-table"><table class="price-table"><tbody>@foreach ($methods as $m)
            <tr><td style="width:36px"><input type="radio" id="m{{ $m->id }}" name="method" value="{{ $m->id }}" @checked((int) old('method', $methods->first()->id) === $m->id)></td><td><label for="m{{ $m->id }}">{{ $m->label() }}</label></td><td>{{ $m->autopay ? 'Default' : '' }}</td></tr>
          @endforeach</tbody></table></div>
        </fieldset>

        <fieldset class="field"><legend class="pay-legend">When?</legend>
          <label class="radio"><input type="radio" name="when" value="now" @checked(old('when', 'now') === 'now')> Pay now</label>
          <label class="radio"><input type="radio" name="when" value="later" @checked(old('when') === 'later')> Pay at a date and time I pick</label>
          <div class="form-2 pay-later" @if (old('when') !== 'later') hidden @endif>
            <div class="field"><label for="pay_date">Date</label><input id="pay_date" name="pay_date" type="date" min="{{ today()->toDateString() }}" max="{{ today()->addDays(60)->toDateString() }}" value="{{ old('pay_date', ($c->due_date && $c->due_date->isFuture() ? $c->due_date : today()->addDay())->toDateString()) }}"></div>
            <div class="field"><label for="pay_time">Time</label><input id="pay_time" name="pay_time" type="time" value="{{ old('pay_time', '09:00') }}"></div>
          </div>
        </fieldset>
        <button class="btn" style="margin-top:20px" id="pay-btn">Pay Now</button>
      </form>
    @endif
  </div>

  @if ($scheduled->isNotEmpty() || $recent->isNotEmpty())
    <div class="co-card" style="margin-top:20px"><h3>Scheduled Payments</h3>
      <div class="ma-table"><table class="price-table">
        <thead><tr><th>Pays On</th><th class="num">Amount</th><th>With</th><th>Status</th><th></th></tr></thead>
        <tbody>@foreach ($scheduled->concat($recent) as $s)
          <tr><td>{{ $s->pay_at->format('D, M j, Y g:i A') }}</td><td class="num">${{ number_format($s->amount, 2) }}</td><td>{{ $s->method?->label() }}</td>
            <td><span @class(['pill-on' => $s->status === 'paid', 'pill-off' => $s->status !== 'paid'])>{{ ucfirst($s->status) }}</span></td>
            <td>@if ($s->status === 'scheduled')<form method="post" action="{{ route('myaccount.scheduled.cancel', $s) }}">@csrf @method('delete')<button class="link">Cancel</button></form>@endif</td></tr>
        @endforeach</tbody>
      </table></div>
    </div>
  @endif
</x-frontend.myaccount>
<script>
(function () {
  var f = document.querySelector('.pay-form'); if (!f) return;
  var later = f.querySelector('.pay-later'), btn = document.getElementById('pay-btn'), other = f.querySelector('input[name=amount]');
  function sync() {
    var isLater = f.querySelector('input[name=when]:checked').value === 'later';
    later.hidden = !isLater; btn.textContent = isLater ? 'Schedule Payment' : 'Pay Now';
    var isOther = f.querySelector('input[name=choice]:checked').value === 'other';
    if (other) other.required = isOther;
  }
  f.addEventListener('change', sync);
  if (other) other.addEventListener('focus', function () { f.querySelector('input[name=choice][value=other]').checked = true; sync(); });
  sync();
})();
</script>
@endsection
