@extends('frontend.layouts.main')
@section('title', 'Message Center')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Message Center">
  <div class="co-card"><div class="ma-table"><table class="price-table msg-list">
    <thead><tr><th>Date</th><th>Type</th><th>Message</th><th>Status</th><th><span class="sr-only">Open</span></th></tr></thead>
    <tbody>@forelse ($messages as $m)
      @php $info = config('myaccount.messages.'.$m->template) ?? config('myaccount.messages._default'); @endphp
      <tr><td class="nowrap">{{ ($m->sent_at ?? $m->created_at)->format('M j, Y') }}</td>
        <td><span class="msg-kind">{{ $m->channel === 'SMS' ? 'Text' : 'Email' }}</span><br><small>{{ $info['label'] }}</small></td>
        <td><a class="msg-link" href="{{ route('myaccount.message', $m) }}"><b>{{ $m->template }}</b></a>@if ($m->body)<br><small>{{ \Illuminate\Support\Str::limit($m->body, 110) }}</small>@else<br><small>{{ \Illuminate\Support\Str::limit($info['why'], 110) }}</small>@endif</td>
        <td><small>{{ $m->direction === 'in' ? 'You sent' : ($m->opened_at ? 'Opened' : ucfirst($m->status ?? 'sent')) }}</small></td>
        <td class="num"><a href="{{ route('myaccount.message', $m) }}" aria-label="Open {{ $m->template }}">Open ›</a></td></tr>
    @empty <tr><td colspan="5">No messages yet.</td></tr> @endforelse</tbody></table></div></div>
</x-frontend.myaccount>
@endsection
