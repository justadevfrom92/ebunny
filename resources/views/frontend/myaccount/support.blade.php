@extends('frontend.layouts.main')
@section('title', 'Support Tickets')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Support Tickets">
  <div class="co-card">
    <div class="sp-head"><p>Questions about your bill, your plan or your service? Open a ticket and our team will reply here.</p><a class="btn" href="{{ route('myaccount.support.create') }}">New Ticket</a></div>
    <div class="ma-table"><table class="price-table msg-list">
      <thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Last Update</th><th><span class="sr-only">Open</span></th></tr></thead>
      <tbody>@forelse ($tickets as $t)
        <tr><td class="nowrap"><a href="{{ route('myaccount.support.show', $t->id) }}">{{ $t->number() }}</a></td>
          <td><a class="msg-link" href="{{ route('myaccount.support.show', $t->id) }}"><b>{{ $t->subject }}</b></a><br><small>{{ \App\Models\SupportTicket::TOPICS[$t->topic] ?? '' }}</small></td>
          <td><span class="sp-status sp-{{ $t->status }}">{{ $t->status === 'waiting' ? 'Waiting on you' : ($t->status === 'new' ? 'Received' : ucfirst($t->status)) }}</span>@if ($t->isDone() && ! $t->rating)<br><small>Tell us how we did</small>@endif</td>
          <td class="nowrap"><small>{{ ($t->last_activity_at ?? $t->created_at)->format('M j, Y') }}</small></td>
          <td class="num"><a href="{{ route('myaccount.support.show', $t->id) }}" aria-label="Open {{ $t->number() }}">Open ›</a></td></tr>
      @empty <tr><td colspan="5">No tickets yet.</td></tr> @endforelse</tbody>
    </table></div>
  </div>
</x-frontend.myaccount>
@endsection
