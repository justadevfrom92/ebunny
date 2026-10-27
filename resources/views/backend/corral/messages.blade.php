@extends('backend.layouts.app')

@section('content')
    @include('backend.partials.page-head', ['title' => 'Web Messages', 'sub' => 'Sent from the website\'s Contact Us form. Open a message to see the account it matches and reply.'])
    <div class="actions" style="margin-bottom:14px"><span class="muted">Topic:</span>
        <a class="btn sm {{ $topic ? 'ghost' : '' }}" href="{{ route('corral.messages.index') }}">All <span class="count-pill">{{ $counts->sum() }}</span></a>
        @foreach (\App\Models\ContactMessage::TOPICS as $k => $label)<a class="btn sm {{ $topic === $k ? '' : 'ghost' }}" href="{{ route('corral.messages.index', ['topic' => $k]) }}">{{ $label }}@if ($counts[$k] ?? 0) <span class="count-pill">{{ $counts[$k] }}</span>@endif</a>@endforeach
    </div>
    @foreach (['Open' => $open, 'Recently Handled' => $handled] as $heading => $messages)
        <div class="panel" style="margin-bottom:20px"><div class="panel-head"><h2>{{ $heading }} <span class="count-pill">{{ $messages->count() }}</span></h2>@if ($heading === 'Open')<span class="muted">oldest first</span>@endif</div>
            <div class="table-wrap"><table class="table">
                <thead><tr><th>ID</th><th>Received</th><th>From</th><th>Topic</th><th>Message</th><th>{{ $heading === 'Open' ? 'Waiting' : 'Handled' }}</th><th>Resolution</th></tr></thead>
                <tbody>@forelse ($messages as $m)
                    <tr class="click" data-href="{{ route('corral.messages.show', $m) }}"><td class="nowrap"><a href="{{ route('corral.messages.show', $m) }}"><b>#{{ $m->id }}</b></a></td><td class="nowrap">{{ $m->created_at->format('n/j g:i A') }}</td>
                        <td><a href="{{ route('corral.messages.show', $m) }}"><b>{{ $m->first_name }} {{ $m->last_name }}</b></a><br><span class="help">{{ $m->email }}</span></td>
                        <td>{{ $m->topicLabel() }}</td>
                        <td class="wrap">{{ \Illuminate\Support\Str::limit($m->message, 110) }}@if ($m->replied_at)<br><span class="help">Replied {{ $m->replied_at->diffForHumans() }}</span>@endif</td>
                        <td class="nowrap">@if ($heading === 'Open'){{ $m->created_at->diffForHumans(null, true) }}@else{{ $m->handled_at->format('n/j') }}<br><span class="help">{{ $m->handler?->name }}</span>@endif</td>
                        <td>@include('backend.partials.resolution-pill', ['value' => $m->resolution])</td></tr>
                @empty <tr><td colspan="7" class="empty">{{ $heading === 'Open' ? 'No new messages.' : 'Nothing handled yet.' }}</td></tr> @endforelse</tbody>
            </table></div>
        </div>
    @endforeach
@endsection
