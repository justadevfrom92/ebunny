@extends('backend.layouts.app')

@section('crumb', 'Notifications')

{{-- Everything you've been notified about, newest first; open one to go to it (it's marked read). --}}
@section('content')
    @include('backend.partials.page-head', ['title' => 'Notifications', 'sub' => $unread ? $unread.' unread. Open one to go to it.' : 'All caught up.',
        'actions' => ($unread ? '<form method="post" action="'.route('admin.notifications.read-all').'" class="inline">'.csrf_field().'<button class="btn ghost">Mark All Read</button></form>' : '')
            .'<a class="btn ghost" href="'.route('admin.settings').'">Settings</a>'])

    <div class="chips small" style="margin-bottom:12px"><a class="chip-link {{ empty($f['kind']) ? 'on' : '' }}" href="{{ route('admin.notifications.index') }}">All</a>@foreach (\App\Models\AdminNotification::KINDS as $k => [$l])<a class="chip-link {{ ($f['kind'] ?? '') === $k ? 'on' : '' }}" href="{{ route('admin.notifications.index', ['kind' => $k]) }}">{{ $l }}</a>@endforeach</div>

    <div class="panel"><div class="table-wrap"><table>
        <thead><tr><th>When</th><th>What</th><th>Kind</th></tr></thead>
        <tbody>@forelse ($items as $n)
            <tr class="click {{ $n->read_at ? '' : 'row-unread' }}" data-href="{{ route('admin.notifications.open', $n) }}"><td class="nowrap"><a href="{{ route('admin.notifications.open', $n) }}">{{ $n->created_at->format('n/j/Y g:i A') }}</a><div class="help">{{ $n->created_at->diffForHumans() }}</div></td>
                <td class="wrap"><b>{{ $n->icon() }} {{ $n->title }}</b>@if ($n->body)<div class="help">{{ $n->body }}</div>@endif</td>
                <td>@include('backend.partials.pill', ['text' => \App\Models\AdminNotification::KINDS[$n->kind][0] ?? $n->kind, 'tone' => $n->read_at ? '' : 'info'])</td></tr>
        @empty <tr><td colspan="3" class="empty">No notifications.</td></tr> @endforelse</tbody>
    </table></div>@include('backend.partials.pager', ['p' => $items])</div>
@endsection
