@extends('frontend.layouts.main')
@section('title', 'Refer a Friend')
@section('heading', 'My Account')
@section('no-banner', '1')
@section('main')
<x-frontend.myaccount :c="$c" title="Refer a Friend">
  <div class="co-card">
    <p>Share your link. When a friend signs up with it, you both get a bill credit.</p>
    <div class="field"><label for="ref">Your referral link</label><input id="ref" readonly value="{{ url('checkout?ref='.$c->referral_code) }}" onfocus="this.select()"></div>
    <p class="muted-text">Code: <b>{{ $c->referral_code ?? '—' }}</b></p>
  </div>
  <div class="co-card" style="margin-top:20px"><h3>Your Referrals</h3><table class="price-table"><tbody>
    @forelse ($referrals as $r)<tr><td>{{ $r->first_name }}</td><td>{{ $r->created_at->format('M j, Y') }}</td><td>{{ $r->status }}</td></tr>
    @empty <tr><td>No referrals yet.</td></tr> @endforelse</tbody></table></div>
</x-frontend.myaccount>
@endsection
