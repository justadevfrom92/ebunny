{{-- Top right: your avatar (with the number of unread notifications on its corner) and name. Clicking your name opens your
     latest 5 notifications, My Files, Settings and Logout. --}}
@php
    $unread = $user->adminNotifications()->whereNull('read_at')->count();
    $latest = $user->adminNotifications()->limit(5)->get();
    $myFiles = \App\Models\ReportRun::where('user_id', $user->id)->count();
@endphp
<div class="user-menu" data-user-menu data-notify="{{ route('admin.notifications') }}">
    {{-- Settings gear: opens your Account area's Settings (Notifications, My Files and Settings in its menu) --}}
    <a class="settings-gear @if (\App\Support\AdminMenu::isAccountPage()) on @endif" href="{{ route('admin.settings') }}" title="Settings" aria-label="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 1 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.68a1.65 1.65 0 0 0 1-1.51V3a2 2 0 1 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 1 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg></a>
    <button type="button" class="user-chip" aria-haspopup="true" aria-expanded="false" data-user-toggle>
        <span class="avatar-wrap"><span class="avatar">{{ $user->initials() }}</span><span class="files-badge" data-files-badge @if (! $unread) hidden @endif aria-label="{{ $unread }} unread {{ Str::plural('notification', $unread) }}">{{ $unread }}</span></span><span><span>{{ $user->name }}</span><small>{{ $user->role?->name }}</small></span>
        <svg class="caret" viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
    </button>
    <div class="user-pop" data-user-pop hidden>
        <div class="up-head"><b>{{ $user->name }}</b><span>{{ $user->email }}</span></div>
        <div class="up-label up-label-first"><span>Notifications</span>@if ($unread)<span class="up-new">{{ $unread }} new</span>
            {{-- Clear: marks them all read (they stay in All Notifications) --}}
            <form method="post" action="{{ route('admin.notifications.read-all') }}" class="up-clear">@csrf<button type="submit">Clear</button></form>@endif</div>
        <div class="up-notes">
            @forelse ($latest as $n)
                <a class="up-note {{ $n->read_at ? '' : 'unread' }}" href="{{ route('admin.notifications.open', $n) }}"><span class="up-ico" aria-hidden="true">{{ $n->icon() }}</span>
                    <span><b>{{ $n->title }}</b>@if ($n->body)<small>{{ \Illuminate\Support\Str::limit($n->body, 70) }}</small>@endif<small>{{ $n->created_at->diffForHumans() }}</small></span></a>
            @empty <p class="up-empty">Nothing yet.</p> @endforelse
        </div>
        <a class="up-item" href="{{ route('admin.notifications.index') }}"><span>All Notifications</span></a>
        <a class="up-item" href="{{ route('admin.files') }}"><span>My Files</span><span class="up-count" data-files-total>{{ $myFiles }}</span></a>
        <a class="up-item" href="{{ route('admin.settings') }}"><span>Settings</span></a>
        <form method="post" action="{{ route('admin.logout') }}">@csrf<button class="up-item up-logout">Logout</button></form>
    </div>
</div>
