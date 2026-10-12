{{-- A page's price grid (Lando → Edit Page → Price Grid Settings) --}}
<div class="price-grid">
  <h2>{{ $grid['header'] ?: 'Plans'.($label ? ' for '.$label : ($grid['market'] ? ' in '.$grid['market']->description : '')) }}</h2>
  @if (! $grid['rows'])
    <p>No plans are priced here yet.</p>
  @elseif ($grid['type'] === 'table')
    <div style="overflow-x:auto"><table class="price-table">
      <thead><tr><th>Plan</th><th>Term</th><th>500 kWh</th><th>1,000 kWh</th><th>2,000 kWh</th><th></th></tr></thead>
      <tbody>@foreach ($grid['rows'] as $r)
        <tr><td><b>{{ $r['plan']->name }}</b></td><td>{{ $r['plan']->term === 1 ? 'Month-to-month' : $r['plan']->term.' months' }}</td>
          @foreach ([500, 1000, 2000] as $kwh)<td>{{ number_format($r['avg'][$kwh], 1) }}¢</td>@endforeach
          <td><a class="btn" href="/checkout?plan={{ urlencode($r['plan']->internal) }}">Sign Up</a></td></tr>
      @endforeach</tbody>
    </table></div>
  @else
    <div class="plan-grid">@foreach ($grid['rows'] as $r)
      <div class="plan">
        <div class="plan-top">
          <span class="plan-term">{{ $r['plan']->term === 1 ? 'Month-to-month' : $r['plan']->term.' months' }}</span>
          <h3>{{ $r['plan']->name }}</h3>
          <div class="plan-rate"><strong>{{ number_format($r['price'], 1) }}¢</strong><span>per kWh</span></div>
          <p class="plan-rate-note">{{ $grid['formula'] }}</p>
        </div>
        <div class="plan-body"><a class="btn btn-block" href="/checkout?plan={{ urlencode($r['plan']->internal) }}">Sign Up</a></div>
      </div>
    @endforeach</div>
  @endif
  <p class="price-note">{{ $grid['formula'] }}{{ $grid['market'] ? ' · '.$grid['market']->description : '' }}. Includes TDSP delivery charges. See each plan's Electricity Facts Label.</p>
</div>
