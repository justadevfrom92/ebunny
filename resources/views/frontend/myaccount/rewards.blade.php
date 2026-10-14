@extends('frontend.layouts.main')
@section('title', 'My Rewards')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="My Rewards">
  <div class="co-card"><p>You have <b>{{ number_format($c->stars) }} stars</b>. Earn stars for on-time payments, paperless billing and referrals.</p></div>
  <div class="offer-grid">
    @foreach ($offers as $o)
                @php [$i, $cost, $name, $desc] = [$o->id, $o->stars, $o->name, $o->description]; @endphp
      <form method="post" action="{{ route('myaccount.rewards.redeem') }}" @class(['offer-card', 'locked' => $cost > $c->stars])>@csrf
        <input type="hidden" name="offer" value="{{ $i }}">
        <strong>{{ $cost ? number_format($cost).' ★' : 'Free' }}</strong><b>{{ $name }}</b><small>{{ $desc }}</small>
        <button class="btn btn-block" @disabled($cost > $c->stars)>{{ $cost > $c->stars ? 'Need '.number_format($cost - $c->stars).' more' : 'Redeem' }}</button>
      </form>
    @endforeach
  </div>
  <div class="co-card" style="margin-top:20px"><h3>Star History</h3><table class="price-table"><tbody>
    @forelse ($history as $s)<tr><td>{{ $s->created_at->format('M j, Y') }}</td><td>{{ $s->reason }}</td><td>{{ $s->stars >= 0 ? '+' : '' }}{{ $s->stars }}</td></tr>
    @empty <tr><td>No stars yet.</td></tr> @endforelse</tbody></table></div>
</x-frontend.myaccount>
@endsection
