@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Web Messages', 'sub' => 'Sent from the website\'s Contact Us form.'])
    @foreach (['Open' => $open, 'Recently Handled' => $handled] as $heading => $messages)
        <div class="panel"><div class="panel-head"><h2>{{ $heading }}</h2><span class="muted">{{ $messages->count() }}</span></div>
            <div class="table-wrap"><table>
                <thead><tr><th>Received</th><th>From</th><th>Topic</th><th>Message</th><th></th></tr></thead>
                <tbody>@forelse ($messages as $m)
                    <tr><td>{{ $m->created_at->format('Y-m-d H:i') }}</td>
                        <td><b>{{ $m->first_name }} {{ $m->last_name }}</b><br><span class="muted">{{ $m->email }}@if ($m->phone) · {{ $m->phone }}@endif</span></td>
                        <td>{{ $m->topic }}</td><td class="wrap">{{ $m->message }}</td>
                        <td>@if (! $m->handled_at)<form method="post" action="{{ route('corral.messages.handled', $m) }}" class="inline">@csrf<button class="btn sm cyan">Mark Handled</button></form>
                            @else<span class="muted">{{ $m->handled_at->format('Y-m-d') }}</span>@endif</td></tr>
                @empty <tr><td colspan="5" class="empty">{{ $heading === 'Open' ? 'No new messages.' : 'Nothing handled yet.' }}</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    @endforeach
@endsection
