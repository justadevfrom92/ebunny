@extends('backend.layouts.app')

@section('crumb', 'Notifications')

{{-- Everything you've been notified about, newest first; open one to go to it (it's marked read). --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Notifications', 'sub' => $unread ? $unread.' unread. Open one to go to it.' : 'All caught up.',
        'actions' => ($unread ? '<form method="post" action="'.route('admin.notifications.read-all').'" class="inline">'.csrf_field().'<button class="btn ghost">Mark All Read</button></form>' : '')
            .'<a class="btn ghost" href="'.route('admin.settings').'">Settings</a>'])

    {{-- A real dropdown from the server, so the page never flashes pills first --}}
    <select class="pill-select" aria-label="Kind" style="margin-bottom:18px" onchange="location.href=this.value">
        <option value="{{ route('admin.notifications.index') }}">All notifications</option>
        @foreach (\App\Models\AdminNotification::KINDS as $k => [$l])<option value="{{ route('admin.notifications.index', ['kind' => $k]) }}" @selected(($f['kind'] ?? '') === $k)>{{ $l }}</option>@endforeach
    </select>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>ID</th><th>When</th><th>What</th><th>Kind</th></tr></thead>
        <tbody>@forelse ($items as $n)
            <tr class="click {{ $n->read_at ? '' : 'row-unread' }}" data-href="{{ route('admin.notifications.open', $n) }}"><td class="nowrap"><a href="{{ route('admin.notifications.open', $n) }}"><b>#{{ $n->id }}</b></a></td><td class="nowrap">{{ $n->created_at->format('n/j/Y g:i A') }}<div class="help">{{ $n->created_at->diffForHumans() }}</div></td>
                <td class="wrap"><b>{{ $n->icon() }} {{ $n->title }}</b>@if ($n->body)<div class="help">{{ $n->body }}</div>@endif</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\AdminNotification::KINDS[$n->kind][0] ?? $n->kind, 'tone' => $n->read_at ? '' : 'info'])</td></tr>
        @empty <tr><td colspan="4" class="empty">No notifications.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $items])</div>
@endsection
