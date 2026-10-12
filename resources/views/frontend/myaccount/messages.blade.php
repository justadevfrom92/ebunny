@extends('frontend.layouts.main')
@section('title', 'Message Center')
@section('crumb', 'My Account')
@section('heading', 'My Account')
@section('main')
<x-frontend.myaccount :c="$c" title="Message Center">
  <div class="co-card"><table class="price-table"><thead><tr><th>Date</th><th>Type</th><th>Message</th></tr></thead><tbody>
    @forelse ($messages as $m)<tr><td>{{ $m->created_at->format('M j, Y') }}</td><td>{{ $m->channel === 'SMS' ? 'Text' : 'Email' }}</td><td><b>{{ $m->template }}</b>@if ($m->body)<br><small>{{ \Illuminate\Support\Str::limit($m->body, 140) }}</small>@endif</td></tr>
    @empty <tr><td colspan="3">No messages yet.</td></tr> @endforelse</tbody></table></div>
</x-frontend.myaccount>
@endsection
